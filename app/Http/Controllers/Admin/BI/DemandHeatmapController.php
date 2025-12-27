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

        return view('admin.bi.demand_heatmap', compact('tenantId', 'tz'));
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
            'grid'       => ['nullable', 'integer', 'min:2', 'max:5'], // decimales de redondeo
        ]);

        $start = $data['start_date'] ?? now($tz)->subDays(7)->toDateString();
        $end   = $data['end_date']   ?? now($tz)->toDateString();

        $hourFrom = $data['hour_from'] ?? null;
        $hourTo   = $data['hour_to']   ?? null;

        $channel = $data['channel'] ?? 'all';
        $status  = $data['status']  ?? 'all';
        $weight  = $data['weight']  ?? 'count';

        $grid = (int)($data['grid'] ?? 3); // 3 decimales ≈ 110m

        // Expresión de monto real (según tu esquema)
        $amountExpr = "COALESCE(r.agreed_amount, r.total_amount, r.quoted_amount, 0)";

        // Para filtrar por fecha/hora usando finished_at si existe, si no requested_at, si no created_at
        // (en tu dump existen finished_at y requested_at)
        $timeExpr = "COALESCE(r.finished_at, r.requested_at, r.created_at)";

        // Query base: solo puntos válidos
        $base = DB::table('rides as r')
            ->where('r.tenant_id', $tenantId)
            ->whereNotNull('r.origin_lat')
            ->whereNotNull('r.origin_lng')
            ->whereRaw("r.origin_lat <> 0 AND r.origin_lng <> 0")
            ->whereBetween(DB::raw("DATE(CONVERT_TZ($timeExpr, '+00:00', '+00:00'))"), [$start, $end]); 
        /**
         * Nota:
         * - Si tus timestamps ya están guardados en hora local del tenant (como en Dispatch),
         *   puedes cambiar a: whereBetween(DB::raw("DATE($timeExpr)"), [$start,$end])
         * - Dejo el filtro simple abajo con DATE($timeExpr) para evitar inconsistencias.
         */

        // Mejor: asumir que DB guarda timestamps “tal cual” en local tenant (como tu decisión previa).
        $base = DB::table('rides as r')
            ->where('r.tenant_id', $tenantId)
            ->whereNotNull('r.origin_lat')
            ->whereNotNull('r.origin_lng')
            ->whereRaw("r.origin_lat <> 0 AND r.origin_lng <> 0")
            ->whereBetween(DB::raw("DATE($timeExpr)"), [$start, $end]);

        if ($channel !== 'all') {
            $base->where('r.requested_channel', $channel);
        }

        if ($status !== 'all') {
            $base->where('r.status', $status);
        }

        if ($hourFrom !== null && $hourTo !== null) {
            if ($hourFrom <= $hourTo) {
                $base->whereRaw("HOUR($timeExpr) BETWEEN ? AND ?", [$hourFrom, $hourTo]);
            } else {
                // Rango cruzando medianoche (ej. 22 -> 4)
                $base->where(function ($q) use ($timeExpr, $hourFrom, $hourTo) {
                    $q->whereRaw("HOUR($timeExpr) >= ?", [$hourFrom])
                      ->orWhereRaw("HOUR($timeExpr) <= ?", [$hourTo]);
                });
            }
        }

        // KPIs (sobre el mismo filtro, sin agrupar)
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

        // Agregación por grid (bucket)
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

        // Convertir a puntos para heat layer: [lat, lng, intensity]
        $points = [];
        foreach ($rows as $row) {
            $intensity = match ($weight) {
                'amount'   => (float)$row->amt,
                'canceled' => (float)$row->canc,
                default    => (float)$row->cnt,
            };

            // Evitar intensidades 0
            if ($intensity <= 0) continue;

            $points[] = [
                'lat' => (float)$row->latb,
                'lng' => (float)$row->lngb,
                'v'   => (float)$intensity,
                'cnt' => (int)$row->cnt,
                'amt' => (float)$row->amt,
                'canc'=> (int)$row->canc,
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
                'hour_from' => $hourFrom,
                'hour_to'   => $hourTo,
                'weight'    => $weight,
                'grid'      => $grid,
            ],
            'kpis'   => $kpis,
            'points' => $points,
        ]);
    }
}
