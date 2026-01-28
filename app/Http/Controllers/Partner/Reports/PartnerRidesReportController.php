<?php

namespace App\Http\Controllers\Partner\Reports;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\Geo\GoogleMapsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class PartnerRidesReportController extends Controller
{
    private function ctx(Request $request): object
    {
        $partnerId = (int) $request->attributes->get('partner_id');
        $partner = DB::table('partners')->where('id', $partnerId)->first();
        abort_if(!$partner, 403, 'Partner context inválido.');

        $partner->id = $partnerId;
        $partner->tenant_id = (int) $partner->tenant_id;

        return $partner;
    }

    private function ridesBaseQuery(int $tenantId, int $partnerId)
    {
        return DB::table('rides as r')
            ->where('r.tenant_id', $tenantId)
            ->where(function ($q) use ($partnerId) {
                $q->whereExists(function ($x) use ($partnerId) {
                    $x->selectRaw('1')
                        ->from('drivers as d')
                        ->whereColumn('d.id', 'r.driver_id')
                        ->where('d.partner_id', $partnerId);
                })->orWhereExists(function ($x) use ($partnerId) {
                    $x->selectRaw('1')
                        ->from('vehicles as v')
                        ->whereColumn('v.id', 'r.vehicle_id')
                        ->where('v.partner_id', $partnerId);
                });
            });
    }

    private function normalizeFilters(Request $request): array
    {
        $defaultTo   = Carbon::today()->toDateString();
        $defaultFrom = Carbon::today()->subMonths(3)->toDateString();

        $from = $request->get('from') ?: $defaultFrom;
        $to   = $request->get('to')   ?: $defaultTo;

        $status = (string) $request->get('status', '');
        if (!in_array($status, ['', 'finished', 'canceled'], true)) {
            $status = '';
        }

        $driverId  = $request->get('driver_id');
        $vehicleId = $request->get('vehicle_id');

        $q = trim((string)$request->get('q', ''));

        return compact('from', 'to', 'status', 'driverId', 'vehicleId', 'q');
    }

    private function applyReportFilters($query, array $f)
    {
        // Solo reporte: finalizados/cancelados
        if ($f['status'] === 'finished') {
            $query->where('r.status', 'finished')->whereNotNull('r.finished_at');
            $dateExpr = 'r.finished_at';
        } elseif ($f['status'] === 'canceled') {
            $query->where('r.status', 'canceled')->whereNotNull('r.canceled_at');
            $dateExpr = 'r.canceled_at';
        } else {
            $query->whereIn('r.status', ['finished', 'canceled']);
            $query->whereRaw('COALESCE(r.finished_at, r.canceled_at) IS NOT NULL');
            $dateExpr = 'COALESCE(r.finished_at, r.canceled_at)';
        }

        // Rango por fecha final
        if ($f['from']) $query->whereRaw("$dateExpr >= ?", [$f['from'] . ' 00:00:00']);
        if ($f['to'])   $query->whereRaw("$dateExpr <= ?", [$f['to'] . ' 23:59:59']);

        // Driver / Vehicle
        if (!empty($f['driverId']))  $query->where('r.driver_id', (int)$f['driverId']);
        if (!empty($f['vehicleId'])) $query->where('r.vehicle_id', (int)$f['vehicleId']);

        // Búsqueda simple
        if ($f['q'] !== '') {
            $like = '%' . $f['q'] . '%';
            $query->where(function ($w) use ($like) {
                $w->where('r.passenger_name', 'like', $like)
                    ->orWhere('r.passenger_phone', 'like', $like)
                    ->orWhere('r.origin_label', 'like', $like)
                    ->orWhere('r.dest_label', 'like', $like)
                    ->orWhere('r.notes', 'like', $like);
            });
        }

        return $query;
    }

    public function index(Request $request)
    {
        $partner   = $this->ctx($request);
        $tenantId  = $partner->tenant_id;
        $partnerId = $partner->id;

        $f = $this->normalizeFilters($request);

        // Base (sin joins) para stats/chart
        $baseRaw = $this->ridesBaseQuery($tenantId, $partnerId);
        $this->applyReportFilters($baseRaw, $f);

        // Stats
        $statsQ = (clone $baseRaw);
        $stats = (object)[
            'total'          => (clone $statsQ)->count(),
            'finished'       => (clone $statsQ)->where('r.status', 'finished')->count(),
            'canceled'       => (clone $statsQ)->where('r.status', 'canceled')->count(),
            'amount_sum'     => (clone $statsQ)->sum(DB::raw("CASE WHEN r.status='finished' THEN COALESCE(r.agreed_amount,r.total_amount,r.quoted_amount,0) ELSE 0 END")),
            'distance_m_sum' => (clone $statsQ)->sum(DB::raw("CASE WHEN r.status='finished' THEN COALESCE(r.distance_m,0) ELSE 0 END")),
            'duration_s_sum' => (clone $statsQ)->sum(DB::raw("CASE WHEN r.status='finished' THEN COALESCE(r.duration_s,0) ELSE 0 END")),
        ];

        // Chart diario (fecha final)
        $dateExpr = match ($f['status']) {
            'finished' => 'r.finished_at',
            'canceled' => 'r.canceled_at',
            default    => 'COALESCE(r.finished_at, r.canceled_at)',
        };

        $dailyRows = (clone $baseRaw)
            ->selectRaw("
                DATE($dateExpr) as day,
                SUM(r.status='finished') as rides_finished,
                SUM(r.status='canceled') as rides_canceled,
                SUM(CASE WHEN r.status='finished' THEN COALESCE(r.agreed_amount,r.total_amount,r.quoted_amount,0) ELSE 0 END) as amount_finished
            ")
            ->groupByRaw("DATE($dateExpr)")
            ->orderBy('day')
            ->get();

        $chart = [
            'labels'         => $dailyRows->pluck('day')->map(fn($v) => (string)$v)->all(),
            'rides_finished' => $dailyRows->pluck('rides_finished')->map(fn($v) => (int)$v)->all(),
            'rides_canceled' => $dailyRows->pluck('rides_canceled')->map(fn($v) => (int)$v)->all(),
            'amount_finished'=> $dailyRows->pluck('amount_finished')->map(fn($v) => (float)$v)->all(),
        ];

        // Rides list (con joins para nombre de driver y datos de vehículo)
        $baseList = $this->ridesBaseQuery($tenantId, $partnerId);
        $this->applyReportFilters($baseList, $f);

        $rides = (clone $baseList)
            ->leftJoin('drivers as d', function ($j) use ($tenantId) {
                $j->on('d.id', '=', 'r.driver_id')->where('d.tenant_id', '=', $tenantId);
            })
            ->leftJoin('vehicles as v', function ($j) use ($tenantId) {
                $j->on('v.id', '=', 'r.vehicle_id')->where('v.tenant_id', '=', $tenantId);
            })
            ->selectRaw("
                r.*,
                COALESCE(d.name, CONCAT('Driver #', r.driver_id)) as driver_name,
                v.economico as vehicle_economico,
                v.plate as vehicle_plate,
                v.brand as vehicle_brand,
                v.model as vehicle_model,
                v.type as vehicle_type
            ")
            ->orderByDesc('r.id')
            ->paginate(25)
            ->withQueryString();

        $drivers = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $vehicles = DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->select('id', 'economico', 'plate')
            ->orderBy('economico')
            ->get();

        return view('partner.reports.rides.index', [
            'rides'    => $rides,
            'stats'    => $stats,
            'chart'    => $chart,
            'drivers'  => $drivers,
            'vehicles' => $vehicles,
            'filters'  => $f,
        ]);
    }

    public function show(Request $request, int $ride)
    {
        $partner   = $this->ctx($request);
        $tenantId  = $partner->tenant_id;
        $partnerId = $partner->id;

        $rideRow = $this->ridesBaseQuery($tenantId, $partnerId)
            ->leftJoin('drivers as d', function ($j) use ($tenantId) {
                $j->on('d.id', '=', 'r.driver_id')->where('d.tenant_id', '=', $tenantId);
            })
            ->leftJoin('vehicles as v', function ($j) use ($tenantId) {
                $j->on('v.id', '=', 'r.vehicle_id')->where('v.tenant_id', '=', $tenantId);
            })
            ->where('r.id', $ride)
            ->selectRaw("
                r.*,
                COALESCE(d.name, CONCAT('Driver #', r.driver_id)) as driver_name,
                d.phone as driver_phone,
                v.economico as vehicle_economico,
                v.plate as vehicle_plate,
                v.brand as vehicle_brand,
                v.model as vehicle_model,
                v.type as vehicle_type,
                v.color as vehicle_color,
                v.year as vehicle_year
            ")
            ->first();

        abort_if(!$rideRow, 404, 'Ride no encontrado para este partner.');


         // Fallback (solo si no hay polyline guardada)
    if (empty($routePolyline)
        && !empty($rideRow->dest_lat) && !empty($rideRow->dest_lng)
        && !empty(config('services.google.key'))
    ) {
        try {
            $gm = app(GoogleMapsService::class);
            $rt = $gm->route(
                (float)$rideRow->origin_lat,
                (float)$rideRow->origin_lng,
                (float)$rideRow->dest_lat,
                (float)$rideRow->dest_lng
            );
            $routePolyline = $rt['polyline'] ?? null;
        } catch (\Throwable $e) {
            // silencioso: el reporte NO debe romperse por Google
            $routePolyline = null;
        }
    }


        // Ofertas solo de drivers del partner (enriquecidas con nombre)
        $offers = DB::table('ride_offers as ro')
            ->leftJoin('drivers as d', function($j) use ($tenantId) {
                $j->on('d.id','=','ro.driver_id')->where('d.tenant_id','=',$tenantId);
            })
            ->leftJoin('vehicles as v', function($j) use ($tenantId) {
                $j->on('v.id','=','ro.vehicle_id')->where('v.tenant_id','=',$tenantId);
            })
            ->where('ro.tenant_id', $tenantId)
            ->where('ro.ride_id', $ride)
            ->whereExists(function ($x) use ($partnerId) {
                $x->selectRaw('1')
                    ->from('drivers as d2')
                    ->whereColumn('d2.id', 'ro.driver_id')
                    ->where('d2.partner_id', $partnerId);
            })
            ->selectRaw("
                ro.*,
                COALESCE(d.name, CONCAT('Driver #', ro.driver_id)) as driver_name,
                v.economico as vehicle_economico,
                v.plate as vehicle_plate
            ")
            ->orderBy('ro.sent_at')
            ->get();

        // Bids (si usas bidding)
        $bids = DB::table('ride_bids')
            ->where('ride_id', $ride)
            ->orderBy('created_at')
            ->get();

        // Ratings (si existen)
        $ratings = DB::table('ratings')
            ->where('tenant_id', $tenantId)
            ->where('ride_id', $ride)
            ->get()
            ->groupBy('rater_type');

        return view('partner.reports.rides.show', [
            'ride'    => $rideRow,
            'offers'  => $offers,
            'bids'    => $bids,
            'ratings' => $ratings,
            'routePolyline' => $routePolyline,
        ]);
    }




private function reportExportPolicy(array $f): array
{
    // Límite razonable para PDF con Dompdf (HTML grande = RAM)
    $maxDefault = 3000;

    $scope = (string)request()->get('export_scope', 'all'); // all|limit
    if (!in_array($scope, ['all','limit'], true)) $scope = 'all';

    $limitRows = (int)request()->get('limit_rows', $maxDefault);
    if ($limitRows <= 0) $limitRows = $maxDefault;
    if ($limitRows > 15000) $limitRows = 15000; // hard cap defensivo

    $force = (int)request()->get('force', 0) === 1;

    return compact('scope','limitRows','force');
}

private function buildChartPng(array $chart): ?string
{
    // Render simple (barras) server-side con GD: finished vs canceled por día.
    // Retorna data URI: "data:image/png;base64,...."
    if (!function_exists('imagecreatetruecolor')) return null;
    $labels = $chart['labels'] ?? [];
    $fin = $chart['rides_finished'] ?? [];
    $can = $chart['rides_canceled'] ?? [];

    $n = min(count($labels), count($fin), count($can));
    if ($n <= 0) return null;

    $w = 1100; $h = 340;
    $im = imagecreatetruecolor($w, $h);

    $bg = imagecolorallocate($im, 255,255,255);
    imagefill($im, 0,0, $bg);

    $axis = imagecolorallocate($im, 40,40,40);
    $grid = imagecolorallocate($im, 230,230,230);

    // Colores corporativos suaves (puedes ajustar)
    $cFin = imagecolorallocate($im, 0, 204, 255);   // Orbana #00CCFF
    $cCan = imagecolorallocate($im, 255, 99, 132);  // rosa suave

    // Márgenes
    $ml=55; $mr=20; $mt=20; $mb=55;
    $pw = $w - $ml - $mr;
    $ph = $h - $mt - $mb;

    // Max Y
    $maxY = 0;
    for ($i=0;$i<$n;$i++){
        $maxY = max($maxY, (int)$fin[$i], (int)$can[$i]);
    }
    if ($maxY < 1) $maxY = 1;

    // Grid horizontal (5 líneas)
    $steps = 5;
    for ($s=0;$s<=$steps;$s++){
        $y = (int)($mt + $ph - ($ph*$s/$steps));
        imageline($im, $ml, $y, $w-$mr, $y, $grid);
        $val = (int)round($maxY*$s/$steps);
        imagestring($im, 2, 8, $y-7, (string)$val, $axis);
    }

    // Ejes
    imageline($im, $ml, $mt, $ml, $mt+$ph, $axis);
    imageline($im, $ml, $mt+$ph, $w-$mr, $mt+$ph, $axis);

    // Barras agrupadas
    $groupW = $pw / $n;
    $barW = max(4, (int)floor($groupW * 0.25));
    $gap  = (int)floor(($groupW - 2*$barW) / 3);

    for ($i=0;$i<$n;$i++){
        $x0 = (int)($ml + $i*$groupW);

        $vF = (int)$fin[$i];
        $vC = (int)$can[$i];

        $hf = (int)round($ph * ($vF/$maxY));
        $hc = (int)round($ph * ($vC/$maxY));

        $bxF1 = $x0 + $gap;
        $bxF2 = $bxF1 + $barW;
        $bxC1 = $bxF2 + $gap;
        $bxC2 = $bxC1 + $barW;

        $yBase = $mt + $ph;

        imagefilledrectangle($im, $bxF1, $yBase-$hf, $bxF2, $yBase, $cFin);
        imagefilledrectangle($im, $bxC1, $yBase-$hc, $bxC2, $yBase, $cCan);

        // Etiqueta cada ~4 días para no saturar
        if ($i % 4 === 0) {
            $lab = (string)$labels[$i];
            $lab = Str::of($lab)->replace('-', '/')->toString();
            imagestringup($im, 1, $x0 + (int)($groupW*0.55), $h-5, $lab, $axis);
        }
    }

    // Leyenda
    imagestring($im, 2, $ml+10, $h-20, "Finalizados", $axis);
    imagefilledrectangle($im, $ml+78, $h-20, $ml+90, $h-10, $cFin);

    imagestring($im, 2, $ml+120, $h-20, "Cancelados", $axis);
    imagefilledrectangle($im, $ml+188, $h-20, $ml+200, $h-10, $cCan);

    ob_start();
    imagepng($im);
    $png = ob_get_clean();
    imagedestroy($im);

    return 'data:image/png;base64,' . base64_encode($png);
}

private function fetchRidesForPdf(int $tenantId, int $partnerId, array $f, int $limitRows, bool $applyLimit): array
{
    $base = $this->ridesBaseQuery($tenantId, $partnerId);
    $this->applyReportFilters($base, $f);

    $countTotal = (clone $base)->count();

    $q = (clone $base)
        ->leftJoin('drivers as d', function ($j) use ($tenantId) {
            $j->on('d.id', '=', 'r.driver_id')->where('d.tenant_id', '=', $tenantId);
        })
        ->leftJoin('vehicles as v', function ($j) use ($tenantId) {
            $j->on('v.id', '=', 'r.vehicle_id')->where('v.tenant_id', '=', $tenantId);
        })
        ->selectRaw("
            r.*,
            COALESCE(d.name, CONCAT('Driver #', r.driver_id)) as driver_name,
            v.economico as vehicle_economico,
            v.plate as vehicle_plate,
            v.brand as vehicle_brand,
            v.model as vehicle_model,
            v.type as vehicle_type
        ")
        ->orderByDesc('r.id');

    if ($applyLimit) {
        $q->limit($limitRows);
    }

    $rows = $q->get();

    return [
        'total' => (int)$countTotal,
        'rows'  => $rows,
    ];
}





public function exportPdf(Request $request)
{
    $partner   = $this->ctx($request);
    $tenantId  = $partner->tenant_id;
    $partnerId = $partner->id;

    $f = $this->normalizeFilters($request);

    // Stats + chart igual que index (reusamos tu lógica)
    $baseRaw = $this->ridesBaseQuery($tenantId, $partnerId);
    $this->applyReportFilters($baseRaw, $f);

    $statsQ = (clone $baseRaw);
    $stats = (object)[
        'total'          => (clone $statsQ)->count(),
        'finished'       => (clone $statsQ)->where('r.status', 'finished')->count(),
        'canceled'       => (clone $statsQ)->where('r.status', 'canceled')->count(),
        'amount_sum'     => (clone $statsQ)->sum(DB::raw("CASE WHEN r.status='finished' THEN COALESCE(r.agreed_amount,r.total_amount,r.quoted_amount,0) ELSE 0 END")),
        'distance_m_sum' => (clone $statsQ)->sum(DB::raw("CASE WHEN r.status='finished' THEN COALESCE(r.distance_m,0) ELSE 0 END")),
        'duration_s_sum' => (clone $statsQ)->sum(DB::raw("CASE WHEN r.status='finished' THEN COALESCE(r.duration_s,0) ELSE 0 END")),
    ];

    $dateExpr = match ($f['status']) {
        'finished' => 'r.finished_at',
        'canceled' => 'r.canceled_at',
        default    => 'COALESCE(r.finished_at, r.canceled_at)',
    };

    $dailyRows = (clone $baseRaw)
        ->selectRaw("
            DATE($dateExpr) as day,
            SUM(r.status='finished') as rides_finished,
            SUM(r.status='canceled') as rides_canceled,
            SUM(CASE WHEN r.status='finished' THEN COALESCE(r.agreed_amount,r.total_amount,r.quoted_amount,0) ELSE 0 END) as amount_finished
        ")
        ->groupByRaw("DATE($dateExpr)")
        ->orderBy('day')
        ->get();

    $chart = [
        'labels'         => $dailyRows->pluck('day')->map(fn($v) => (string)$v)->all(),
        'rides_finished' => $dailyRows->pluck('rides_finished')->map(fn($v) => (int)$v)->all(),
        'rides_canceled' => $dailyRows->pluck('rides_canceled')->map(fn($v) => (int)$v)->all(),
        'amount_finished'=> $dailyRows->pluck('amount_finished')->map(fn($v) => (float)$v)->all(),
    ];

    // Policy de export (aviso/limit)
    $policy = $this->reportExportPolicy($f);

    // 1) Conteo y filas (no paginado)
    $applyLimit = ($policy['scope'] === 'limit');
    $dataRows = $this->fetchRidesForPdf($tenantId, $partnerId, $f, $policy['limitRows'], $applyLimit);
    $totalFiltered = $dataRows['total'];

    // 2) Aviso si es enorme y no viene force
    if (!$applyLimit && $totalFiltered > $policy['limitRows'] && !$policy['force']) {
        // No rompemos: devolvemos 422 con mensaje claro para UI
        abort(422, "El reporte tiene {$totalFiltered} registros. Para exportar TODO agrega ?force=1 o usa ?export_scope=limit&limit_rows={$policy['limitRows']}.");
    }

    // Si pidieron ALL con force, igual aplicamos hard cap defensivo
    if (!$applyLimit && $totalFiltered > 15000) {
        abort(422, "El reporte excede 15000 registros ({$totalFiltered}). Ajusta filtros o exporta por rangos.");
    }

    // 3) Gráfica como PNG embebido
    $chartPng = $this->buildChartPng($chart);

    $brand = [
    'platform'   => 'Orbana',
    'accent'     => '#00CCFF',
    'logo_path'  => public_path('images/logo.png'),
    'version'    => config('app.version', env('APP_VERSION', '')),
];

$partnerBrand = [
    'name'         => $partner->name ?? ('Partner #'.$partnerId),
    'legal_name'   => $partner->legal_name ?? null,
    'rfc'          => $partner->rfc ?? null,
    'contact_name' => $partner->contact_name ?? null,
    'contact_phone'=> $partner->contact_phone ?? null,
    'contact_email'=> $partner->contact_email ?? null,
    'city'         => $partner->city ?? null,
    'state'        => $partner->state ?? null,
    'address'      => trim(($partner->address_line1 ?? '').' '.($partner->address_line2 ?? '')) ?: null,
];

    $generatedAt = now()->format('Y-m-d H:i');

   $pdf = Pdf::loadView('partner.reports.rides.pdf', [
    'partner'      => $partner,
    'partnerBrand' => $partnerBrand,
    'tenantId'     => $tenantId,
    'partnerId'    => $partnerId,
    'filters'      => $f,
    'policy'       => $policy,
    'totalFiltered'=> $totalFiltered,
    'stats'        => $stats,
    'chartPng'     => $chartPng,
    'generatedAt'  => $generatedAt,
    'rows'         => $dataRows['rows'],
    'brand'        => $brand,
])->setPaper('a4', 'portrait');

    $filename = 'partner_rides_'.$partnerId.'_'.date('Ymd_His').'.pdf';
    return $pdf->download($filename);
}

}
