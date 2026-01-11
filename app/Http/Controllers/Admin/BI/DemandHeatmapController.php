<?php

namespace App\Http\Controllers\Admin\BI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DemandHeatmapController extends Controller
{
    private function tenantId(): int
    {
        $tid = Auth::user()->tenant_id ?? null;
        if (!$tid) abort(403, 'Usuario sin tenant asignado');
        return (int)$tid;
    }

    private function tenantTz(int $tenantId): string
    {
        $tz = DB::table('tenants')->where('id', $tenantId)->value('timezone');
        return $tz ?: config('app.timezone', 'America/Mexico_City');
    }

  public function index(Request $r)
{
    $tenantId = $this->tenantId();
    $tz = $this->tenantTz($tenantId);

    $tenant = DB::table('tenants')
        ->select('id','name','latitud','longitud')
        ->where('id', $tenantId)
        ->first();

    $mapCenter = [
        'lat'  => (float)($tenant->latitude ?? 19.1738),
        'lng'  => (float)($tenant->longitude ?? -96.1342),
        'zoom' => 12, // ciudad
    ];

    return view('admin.bi.demand_heatmap', compact('tenantId', 'tz', 'mapCenter'));
}


    /**
     * Heatmap de orígenes agregados por “grid” (round lat/lng)
     * Filtros:
     * - start_date, end_date (Y-m-d)
     * - hour_from, hour_to (0..23)
     * - channel: all|app|dispatch
     * - status: all|finished|canceled|requested|accepted|on_trip (lo que uses)
     * - weight: count|amount|canceled
     */
  public function heatOrigins(Request $r)
{
    $tenantId = $this->tenantId();
    $tz = $this->tenantTz($tenantId);

    $data = $r->validate([
        'start_date' => ['nullable', 'date'],
        'end_date'   => ['nullable', 'date'],
        'hour_from'  => ['nullable', 'integer', 'min:0', 'max:23'],
        'hour_to'    => ['nullable', 'integer', 'min:0', 'max:23'],
        'channel'    => ['nullable', Rule::in(['all','app','dispatch'])],
        'status'     => ['nullable', Rule::in(['all','finished','canceled','released','requested','accepted','on_trip'])],
        'weight'     => ['nullable', Rule::in(['count','amount','canceled'])],
        'grid'       => ['nullable', 'integer', 'min:2', 'max:5'], // decimales
    ]);

    // Defaults: 1 mes (últimos 30 días incluyendo hoy)
    $start = isset($data['start_date'])
        ? \Carbon\Carbon::parse($data['start_date'], $tz)->toDateString()
        : now($tz)->subDays(29)->toDateString(); // 30 días inclusivo

    $end = isset($data['end_date'])
        ? \Carbon\Carbon::parse($data['end_date'], $tz)->toDateString()
        : now($tz)->toDateString();

    // Validación de rango
    if ($start > $end) {
        abort(422, 'start_date no puede ser mayor que end_date');
    }

    // Hora: permitir uno solo (defaults 0..23)
    $hourFrom = $data['hour_from'] ?? null;
    $hourTo   = $data['hour_to'] ?? null;
    $hasHourFilter = ($hourFrom !== null || $hourTo !== null);

    if ($hasHourFilter) {
        $hourFrom = $hourFrom ?? 0;
        $hourTo   = $hourTo   ?? 23;
    }

    $channel = $data['channel'] ?? 'all';
    $status  = $data['status']  ?? 'all';
    $weight  = $data['weight']  ?? 'count';
    $grid    = (int)($data['grid'] ?? 3); // 3 ≈ 110m

    // Tiempo canónico para “demanda de origen”
    $timeCol = 'r.requested_at';

    // Rango datetime (asumiendo timestamps guardados en hora local tenant, como tu decisión previa)
    $from = $start . ' 00:00:00';
    $to   = $end   . ' 23:59:59';

    // Monto real
    $amountExpr = "COALESCE(r.agreed_amount, r.total_amount, r.quoted_amount, 0)";

    // Base query: puntos válidos + rango
    $base = DB::table('rides as r')
        ->where('r.tenant_id', $tenantId)
        ->whereNotNull('r.origin_lat')
        ->whereNotNull('r.origin_lng')
        ->whereRaw("r.origin_lat <> 0 AND r.origin_lng <> 0")
        ->whereBetween($timeCol, [$from, $to]);

    if ($channel !== 'all') {
        $base->where('r.requested_channel', $channel);
    }

    if ($status !== 'all') {
        $base->where('r.status', $status);
    }

    if ($hasHourFilter) {
        if ($hourFrom <= $hourTo) {
            $base->whereRaw("HOUR($timeCol) BETWEEN ? AND ?", [$hourFrom, $hourTo]);
        } else {
            // Cruza medianoche (22 -> 4)
            $base->where(function ($q) use ($timeCol, $hourFrom, $hourTo) {
                $q->whereRaw("HOUR($timeCol) >= ?", [$hourFrom])
                  ->orWhereRaw("HOUR($timeCol) <= ?", [$hourTo]);
            });
        }
    }

    // KPIs
    $kpiRow = (clone $base)
        ->selectRaw("COUNT(*) as rides")
        ->selectRaw("SUM(CASE WHEN r.status = 'canceled' THEN 1 ELSE 0 END) as canceled")
        ->selectRaw("SUM($amountExpr) as income")
        ->selectRaw("AVG(NULLIF($amountExpr,0)) as avg_ticket")
        ->first();

    $kpis = [
        'rides'      => (int)($kpiRow->rides ?? 0),
        'canceled'   => (int)($kpiRow->canceled ?? 0),
        'income'     => (float)($kpiRow->income ?? 0),
        'avg_ticket' => (float)($kpiRow->avg_ticket ?? 0),
    ];

    // Agregación por grid
    $rows = (clone $base)
        ->selectRaw("ROUND(r.origin_lat, ?) as latb", [$grid])
        ->selectRaw("ROUND(r.origin_lng, ?) as lngb", [$grid])
        ->selectRaw("COUNT(*) as cnt")
        ->selectRaw("SUM($amountExpr) as amt")
        ->selectRaw("SUM(CASE WHEN r.status='canceled' THEN 1 ELSE 0 END) as canc")
        ->groupBy('latb', 'lngb')
        ->orderByDesc('cnt')
        ->limit(5000)
        ->get();

    $points = [];
    foreach ($rows as $row) {
        $intensity = match ($weight) {
            'amount'   => (float)$row->amt,
            'canceled' => (float)$row->canc,
            default    => (float)$row->cnt,
        };

        if ($intensity <= 0) continue;

        $points[] = [
            'lat'  => (float)$row->latb,
            'lng'  => (float)$row->lngb,
            'v'    => (float)$intensity,
            'cnt'  => (int)$row->cnt,
            'amt'  => (float)$row->amt,
            'canc' => (int)$row->canc,
        ];
    }

    return response()->json([
        'meta' => [
            'tenant_id' => $tenantId,
            'tz'        => $tz,
            'start'     => $start,
            'end'       => $end,
            'channel'   => $channel,
            'status'    => $status,
            'hour_from' => $hasHourFilter ? $hourFrom : null,
            'hour_to'   => $hasHourFilter ? $hourTo : null,
            'weight'    => $weight,
            'grid'      => $grid,
            'time_col'  => 'requested_at', // útil para depurar (opcional)
        ],
        'kpis'   => $kpis,
        'points' => $points,
    ]);
}

}
