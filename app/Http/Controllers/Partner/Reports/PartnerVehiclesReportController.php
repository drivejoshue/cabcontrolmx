<?php

namespace App\Http\Controllers\Partner\Reports;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class PartnerVehiclesReportController extends Controller
{
    private function tenantId(): int
    {
        return (int) auth()->user()->tenant_id;
    }

    private function partnerId(): int
    {
        return (int) (session('partner_id') ?: auth()->user()->default_partner_id);
    }

    private function baseVehicleQuery(Request $request)
    {
        $q = Vehicle::query()
            ->where('tenant_id', $this->tenantId())
            ->where('partner_id', $this->partnerId());

        if (($active = $request->get('active')) !== null && $active !== '') {
            $q->where('active', (int)$active);
        }

        if ($ver = $request->get('verification_status')) {
            $q->where('verification_status', $ver);
        }

        if ($search = trim((string)$request->get('q'))) {
            $like = '%' . $search . '%';
            $q->where(function ($w) use ($like) {
                $w->where('economico','like',$like)
                  ->orWhere('plate','like',$like)
                  ->orWhere('brand','like',$like)
                  ->orWhere('model','like',$like)
                  ->orWhere('color','like',$like);
            });
        }

        return $q;
    }

    private function ridesAggSubquery(Request $request)
    {
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        $dateField = $request->get('date_field', 'requested_at');
        if (!in_array($dateField, ['requested_at','created_at','accepted_at','finished_at','canceled_at'], true)) {
            $dateField = 'requested_at';
        }

        $from = $request->get('from');
        $to   = $request->get('to');

        $r = Ride::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('vehicle_id', Vehicle::query()
                ->where('tenant_id', $tenantId)
                ->where('partner_id', $partnerId)
                ->select('id')
            );

        if ($from) $r->where($dateField, '>=', $from . ' 00:00:00');
        if ($to)   $r->where($dateField, '<=', $to . ' 23:59:59');

        return $r->select([
            'vehicle_id',
            DB::raw('COUNT(*) as rides_total'),
            DB::raw("SUM(status='finished') as rides_finished"),
            DB::raw("SUM(status='canceled') as rides_canceled"),
            DB::raw("SUM(status IN ('requested','offered','accepted','en_route','arrived','on_board','queued','scheduled')) as rides_active"),
            DB::raw('SUM(COALESCE(agreed_amount,total_amount,quoted_amount,0)) as amount_sum'),
            DB::raw('AVG(distance_m) as avg_distance_m'),
            DB::raw('AVG(duration_s) as avg_duration_s'),
        ])->groupBy('vehicle_id');
    }

    public function index(Request $request)
    {
        $request->validate([
            'from' => ['nullable','date'],
            'to'   => ['nullable','date'],
            'date_field' => ['nullable','string'],
            'active' => ['nullable'],
            'verification_status' => ['nullable','string'],
            'q' => ['nullable','string','max:120'],
        ]);

        $vehiclesQ = $this->baseVehicleQuery($request);
        $agg = $this->ridesAggSubquery($request);

        $vehicles = $vehiclesQ
            ->leftJoinSub($agg, 'ra', function ($join) {
                $join->on('vehicles.id', '=', 'ra.vehicle_id');
            })
            ->select([
                'vehicles.*',
                DB::raw('COALESCE(ra.rides_total,0) as rides_total'),
                DB::raw('COALESCE(ra.rides_finished,0) as rides_finished'),
                DB::raw('COALESCE(ra.rides_canceled,0) as rides_canceled'),
                DB::raw('COALESCE(ra.rides_active,0) as rides_active'),
                DB::raw('COALESCE(ra.amount_sum,0) as amount_sum'),
                DB::raw('ra.avg_distance_m as avg_distance_m'),
                DB::raw('ra.avg_duration_s as avg_duration_s'),
            ])
            ->orderByDesc('rides_total')
            ->paginate(25)
            ->withQueryString();

        $kpi = [
            'vehicles_total' => Vehicle::query()
                ->where('tenant_id',$this->tenantId())
                ->where('partner_id',$this->partnerId())
                ->count(),
        ];

        return view('partner.reports.vehicles.index', compact('vehicles','kpi'));
    }

public function show(Request $request, int $vehicle)
{
    $tenantId  = $this->tenantId();
    $partnerId = $this->partnerId();

    $vehicleRow = Vehicle::query()
        ->where('tenant_id', $tenantId)
        ->where('partner_id', $partnerId)
        ->where('id', $vehicle)
        ->firstOrFail();

    // =========================
    // Defaults: últimos 3 meses
    // =========================
    $defaultTo   = Carbon::today()->toDateString();
    $defaultFrom = Carbon::today()->subMonths(3)->toDateString();

    $status = $request->input('status', 'finished'); // finished | canceled | '' (ambos)
    if (!in_array($status, ['finished','canceled',''], true)) {
        $status = 'finished';
    }

    $from = $request->input('from') ?: $defaultFrom;
    $to   = $request->input('to')   ?: $defaultTo;

    // Campo fecha según status
    $dateExpr = $status === 'canceled'
        ? 'r.canceled_at'
        : ($status === 'finished' ? 'r.finished_at' : 'COALESCE(r.finished_at, r.canceled_at)');

    // Base query (SIEMPRE con alias r)
    $base = DB::table('rides as r')
        ->where('r.tenant_id', $tenantId)
        ->where('r.vehicle_id', $vehicleRow->id);

    // Estado del reporte
    if ($status === '') {
        $base->whereIn('r.status', ['finished','canceled']);
    } else {
        $base->where('r.status', $status);
    }

    // Rango por fecha correcta (finished_at / canceled_at / coalesce)
    $base->whereRaw("$dateExpr IS NOT NULL")
         ->whereRaw("$dateExpr >= ? AND $dateExpr <= ?", [
             $from.' 00:00:00',
             $to.' 23:59:59',
         ]);

    // =========================
    // MÉTRICAS (vehículo)
    // =========================
    $metrics = (clone $base)->selectRaw("
        COUNT(*) as rides_total,
        SUM(r.status='finished') as rides_finished,
        SUM(r.status='canceled') as rides_canceled,

        SUM(CASE WHEN r.status='finished' THEN COALESCE(r.distance_m,0) ELSE 0 END) as distance_m_sum,
        SUM(CASE WHEN r.status='finished' THEN COALESCE(r.duration_s,0) ELSE 0 END) as duration_s_sum,

        AVG(CASE
            WHEN r.accepted_at IS NOT NULL AND r.arrived_at IS NOT NULL
            THEN TIMESTAMPDIFF(SECOND, r.accepted_at, r.arrived_at)
        END) as avg_pickup_eta_s,

        AVG(CASE
            WHEN r.onboard_at IS NOT NULL AND r.finished_at IS NOT NULL
            THEN TIMESTAMPDIFF(SECOND, r.onboard_at, r.finished_at)
        END) as avg_trip_s,

        SUM(CASE
            WHEN r.accepted_at IS NOT NULL AND r.finished_at IS NOT NULL
            THEN TIMESTAMPDIFF(SECOND, r.accepted_at, r.finished_at)
            ELSE 0
        END) as busy_s_sum,

        SUM(CASE WHEN r.status='finished' THEN COALESCE(r.agreed_amount,r.total_amount,r.quoted_amount,0) ELSE 0 END) as amount_sum,

        MIN($dateExpr) as first_activity_at,
        MAX($dateExpr) as last_activity_at
    ")->first();

    // =========================
    // Rendimiento por chofer (del vehículo)
    // =========================
    $byDriver = (clone $base)
        ->whereNotNull('r.driver_id')
        ->leftJoin('drivers as d', function($j) use ($tenantId) {
            $j->on('d.id','=','r.driver_id')->where('d.tenant_id','=',$tenantId);
        })
        ->groupBy('r.driver_id','d.name')
        ->selectRaw("
            r.driver_id,
            COALESCE(d.name, CONCAT('Chofer #', r.driver_id)) as driver_name,

            COUNT(*) as rides_total,
            SUM(r.status='finished') as rides_finished,
            SUM(r.status='canceled') as rides_canceled,

            SUM(CASE WHEN r.status='finished' THEN COALESCE(r.distance_m,0) ELSE 0 END) as distance_m_sum,
            SUM(CASE WHEN r.status='finished' THEN COALESCE(r.agreed_amount,r.total_amount,r.quoted_amount,0) ELSE 0 END) as amount_sum,

            AVG(CASE
                WHEN r.accepted_at IS NOT NULL AND r.arrived_at IS NOT NULL
                THEN TIMESTAMPDIFF(SECOND, r.accepted_at, r.arrived_at)
            END) as avg_pickup_eta_s,

            AVG(CASE
                WHEN r.onboard_at IS NOT NULL AND r.finished_at IS NOT NULL
                THEN TIMESTAMPDIFF(SECOND, r.onboard_at, r.finished_at)
            END) as avg_trip_s
        ")
        ->orderByDesc('rides_total')
        ->limit(50)
        ->get();

    // =========================
    // Serie diaria para chart (rellena ceros)
    // =========================
    $dailyRows = (clone $base)
        ->selectRaw("DATE($dateExpr) as dia")
        ->selectRaw("SUM(r.status='finished') as finalizados")
        ->selectRaw("SUM(r.status='canceled') as cancelados")
        ->selectRaw("SUM(CASE WHEN r.status='finished' THEN COALESCE(r.distance_m,0) ELSE 0 END)/1000 as km")
        ->groupBy('dia')
        ->orderBy('dia')
        ->get();

    $start = Carbon::parse($from);
    $end   = Carbon::parse($to);

    // guardrail: max 120 días
    if ($start->diffInDays($end) > 120) {
        $start = $end->copy()->subDays(120);
        $from = $start->toDateString();
    }

    $map = $dailyRows->keyBy(fn($r) => (string)$r->dia);

    $labels = [];
    $fin = [];
    $can = [];
    $km  = [];

    foreach (CarbonPeriod::create($start, $end) as $d) {
        $key = $d->toDateString();
        $row = $map->get($key);

        $labels[] = $d->translatedFormat('d M'); // ej: 21 ene
        $fin[]    = (int)($row->finalizados ?? 0);
        $can[]    = (int)($row->cancelados ?? 0);
        $km[]     = (float)($row->km ?? 0);
    }

    $chart = [
        'labels'   => $labels,
        'finished' => $fin,
        'canceled' => $can,
        'km'       => $km,
    ];

    $filters = [
        'from'   => $from,
        'to'     => $to,
        'status' => $status,
    ];

    return view('partner.reports.vehicles.show', [
        'vehicle'  => $vehicleRow,
        'metrics'  => $metrics,
        'byDriver' => $byDriver,
        'chart'    => $chart,
        'filters'  => $filters,
    ]);
}

    public function exportCsv(Request $request): StreamedResponse
    {
        $request->validate([
            'from' => ['nullable','date'],
            'to'   => ['nullable','date'],
            'date_field' => ['nullable','string'],
            'active' => ['nullable'],
            'verification_status' => ['nullable','string'],
            'q' => ['nullable','string','max:120'],
        ]);

        $vehiclesQ = $this->baseVehicleQuery($request);
        $agg = $this->ridesAggSubquery($request);

        $q = $vehiclesQ
            ->leftJoinSub($agg, 'ra', function ($join) {
                $join->on('vehicles.id', '=', 'ra.vehicle_id');
            })
            ->select([
                'vehicles.id','vehicles.economico','vehicles.plate','vehicles.brand','vehicles.model','vehicles.type',
                'vehicles.color','vehicles.year','vehicles.active','vehicles.verification_status',
                DB::raw('COALESCE(ra.rides_total,0) as rides_total'),
                DB::raw('COALESCE(ra.rides_finished,0) as rides_finished'),
                DB::raw('COALESCE(ra.rides_canceled,0) as rides_canceled'),
                DB::raw('COALESCE(ra.rides_active,0) as rides_active'),
                DB::raw('COALESCE(ra.amount_sum,0) as amount_sum'),
                DB::raw('ra.avg_distance_m as avg_distance_m'),
                DB::raw('ra.avg_duration_s as avg_duration_s'),
            ])
            ->orderByDesc('rides_total');

        $fileName = 'partner_vehicles_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'vehicle_id','economico','plate','brand','model','type','color','year','active','verification_status',
                'rides_total','rides_finished','rides_canceled','rides_active',
                'amount_sum','avg_distance_m','avg_duration_s',
            ]);

            $q->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $v) {
                    fputcsv($out, [
                        $v->id,
                        $v->economico,
                        $v->plate,
                        $v->brand,
                        $v->model,
                        $v->type,
                        $v->color,
                        $v->year,
                        (int)$v->active,
                        $v->verification_status,
                        (int)$v->rides_total,
                        (int)$v->rides_finished,
                        (int)$v->rides_canceled,
                        (int)$v->rides_active,
                        (float)$v->amount_sum,
                        $v->avg_distance_m !== null ? (float)$v->avg_distance_m : null,
                        $v->avg_duration_s !== null ? (float)$v->avg_duration_s : null,
                    ]);
                }
            });

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }



private function resolveDateRange(Request $r, int $monthsBack = 3): array
{
    $to   = $r->input('to') ?: Carbon::today()->toDateString();
    $from = $r->input('from') ?: Carbon::today()->subMonths($monthsBack)->toDateString();
    if ($from > $to) [$from, $to] = [$to, $from];
    return [$from, $to];
}

/**
 * dateExpr para rides con alias r (finished/canceled/ambos)
 */
private function rideFinalExpr(string $status, string $alias = 'r'): string
{
    return $status === 'canceled'
        ? "$alias.canceled_at"
        : ($status === 'finished' ? "$alias.finished_at" : "COALESCE($alias.finished_at, $alias.canceled_at)");
}

/**
 * Policy simple para PDF.
 */
private function reportExportPolicy(Request $r): array
{
    $scope = $r->input('export_scope', 'limit');
    $scope = in_array($scope, ['limit','all'], true) ? $scope : 'limit';

    $limit = (int)($r->input('limit_rows') ?: 1200);
    if ($limit < 100) $limit = 100;
    if ($limit > 5000) $limit = 5000;

    $force = (int)($r->input('force') ?: 0) === 1;

    return [
        'scope' => $scope,
        'limitRows' => $limit,
        'force' => $force,
    ];
}


public function exportPdf(Request $request)
{
    $request->validate([
        'from' => ['nullable','date'],
        'to'   => ['nullable','date'],

        // filtros vehicles
        'active' => ['nullable'],
        'verification_status' => ['nullable','string'],
        'q' => ['nullable','string','max:120'],

        // reporte por rides
        'status' => ['nullable','in:finished,canceled,'], // '' permitido (ambos)
        'export_scope' => ['nullable','in:limit,all'],
        'limit_rows'   => ['nullable','integer','min:100','max:5000'],
        'force'        => ['nullable','in:0,1'],
    ]);

    $tenantId  = $this->tenantId();
    $partnerId = $this->partnerId();

    [$from, $to] = $this->resolveDateRange($request, 3);

    $status = $request->input('status', 'finished');
    if (!in_array($status, ['finished','canceled',''], true)) $status = 'finished';

    $policy = $this->reportExportPolicy($request);

    // =========================
    // Partner branding (opcional)
    // =========================
    $partner = DB::table('partners')
        ->where('tenant_id', $tenantId)
        ->where('id', $partnerId)
        ->first();

    $partnerBrand = [
        'name'          => $partner->name ?? ('Partner #'.$partnerId),
        'city'          => $partner->city ?? '',
        'state'         => $partner->state ?? '',
        'contact_phone' => $partner->contact_phone ?? '',
        'contact_email' => $partner->contact_email ?? '',
        'legal_name'    => $partner->legal_name ?? '',
        'rfc'           => $partner->rfc ?? '',
    ];

    // =========================
    // 1) Base vehicles + agg por rides (ventana por fecha final)
    // =========================
    $vehiclesQ = $this->baseVehicleQuery($request);

    $dateExpr = $this->rideFinalExpr($status, 'r');

    // Subquery rides por vehicle (alias r)
    $ridesAgg = DB::table('rides as r')
        ->where('r.tenant_id', $tenantId)
        ->whereIn('r.vehicle_id', Vehicle::query()
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->select('id')
        )
        ->when($status === '', fn($q) => $q->whereIn('r.status', ['finished','canceled']),
              fn($q) => $q->where('r.status', $status))
        ->whereRaw("$dateExpr IS NOT NULL")
        ->whereRaw("$dateExpr BETWEEN ? AND ?", [$from.' 00:00:00', $to.' 23:59:59'])
        ->groupBy('r.vehicle_id')
        ->selectRaw("
            r.vehicle_id,
            COUNT(*) as rides_total,
            SUM(r.status='finished') as rides_finished,
            SUM(r.status='canceled') as rides_canceled,

            SUM(CASE WHEN r.status='finished' THEN COALESCE(r.distance_m,0) ELSE 0 END) as distance_m_sum,
            SUM(CASE WHEN r.status='finished' THEN COALESCE(r.duration_s,0) ELSE 0 END) as duration_s_sum,
            SUM(CASE WHEN r.status='finished' THEN COALESCE(r.agreed_amount,r.total_amount,r.quoted_amount,0) ELSE 0 END) as amount_sum,

            AVG(CASE WHEN r.status='finished' THEN r.distance_m END) as avg_distance_m,
            AVG(CASE WHEN r.status='finished' THEN r.duration_s END) as avg_duration_s
        ");

    $rowsQ = $vehiclesQ
        ->leftJoinSub($ridesAgg, 'ra', fn($j) => $j->on('vehicles.id','=','ra.vehicle_id'))
        ->selectRaw("
            vehicles.id, vehicles.economico, vehicles.plate, vehicles.brand, vehicles.model, vehicles.type,
            vehicles.color, vehicles.year, vehicles.active, vehicles.verification_status,

            COALESCE(ra.rides_total,0) as rides_total,
            COALESCE(ra.rides_finished,0) as rides_finished,
            COALESCE(ra.rides_canceled,0) as rides_canceled,
            COALESCE(ra.amount_sum,0) as amount_sum,
            COALESCE(ra.distance_m_sum,0) as distance_m_sum,
            COALESCE(ra.duration_s_sum,0) as duration_s_sum,
            ra.avg_distance_m as avg_distance_m,
            ra.avg_duration_s as avg_duration_s
        ")
        ->orderByDesc('rides_total')
        ->orderBy('vehicles.economico');

    $totalFiltered = (clone $rowsQ)->count();

    // aplicar policy
    $applyLimit = ($policy['scope'] === 'limit');
    if ($applyLimit) {
        $rowsQ->limit($policy['limitRows']);
    } else {
        if ($totalFiltered > $policy['limitRows'] && !$policy['force']) {
            abort(422, "El reporte tiene {$totalFiltered} registros. Para exportar TODO agrega ?force=1 o usa ?export_scope=limit&limit_rows={$policy['limitRows']}.");
        }
        if ($totalFiltered > 15000) {
            abort(422, "El reporte excede 15000 registros ({$totalFiltered}). Ajusta filtros o exporta por rangos.");
        }
    }

    $rows = $rowsQ->get();

    // =========================
    // 2) KPIs globales (NO dependen de limit)
    // =========================
    $vehiclesTotal = (int) Vehicle::query()
        ->where('tenant_id', $tenantId)
        ->where('partner_id', $partnerId)
        ->count();

    $ridesTotals = DB::table('rides as r')
        ->where('r.tenant_id', $tenantId)
        ->whereIn('r.vehicle_id', Vehicle::query()
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->select('id')
        )
        ->when($status === '', fn($q) => $q->whereIn('r.status', ['finished','canceled']),
              fn($q) => $q->where('r.status', $status))
        ->whereRaw("$dateExpr IS NOT NULL")
        ->whereRaw("$dateExpr BETWEEN ? AND ?", [$from.' 00:00:00', $to.' 23:59:59'])
        ->selectRaw("
            COUNT(*) as rides_total,
            SUM(r.status='finished') as rides_finished,
            SUM(r.status='canceled') as rides_canceled,
            SUM(CASE WHEN r.status='finished' THEN COALESCE(r.agreed_amount,r.total_amount,r.quoted_amount,0) ELSE 0 END) as amount_sum,
            SUM(CASE WHEN r.status='finished' THEN COALESCE(r.distance_m,0) ELSE 0 END) as distance_m_sum,
            SUM(CASE WHEN r.status='finished' THEN COALESCE(r.duration_s,0) ELSE 0 END) as duration_s_sum
        ")
        ->first();

    $kpi = [
        'vehicles_total'     => $vehiclesTotal,
        'vehicles_in_report' => (int)$totalFiltered,

        'rides_total'    => (int)($ridesTotals->rides_total ?? 0),
        'rides_finished' => (int)($ridesTotals->rides_finished ?? 0),
        'rides_canceled' => (int)($ridesTotals->rides_canceled ?? 0),

        'amount_sum'     => (float)($ridesTotals->amount_sum ?? 0),
        'km_sum'         => (float)(($ridesTotals->distance_m_sum ?? 0) / 1000.0),
        'hours_sum'      => (float)(($ridesTotals->duration_s_sum ?? 0) / 3600.0),
    ];

    // =========================
    // 3) Detalle de rides (hard limit)
    // =========================
    $DETAIL_LIMIT = min(900, (int)$policy['limitRows']);

    $ridesRows = DB::table('rides as r')
        ->leftJoin('drivers as d', function($j) use ($tenantId){
            $j->on('d.id','=','r.driver_id')->where('d.tenant_id','=',$tenantId);
        })
        ->leftJoin('vehicles as v', function($j) use ($tenantId){
            $j->on('v.id','=','r.vehicle_id')->where('v.tenant_id','=',$tenantId);
        })
        ->where('r.tenant_id', $tenantId)
        ->whereIn('r.vehicle_id', Vehicle::query()
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->select('id')
        )
        ->when($status === '', fn($q) => $q->whereIn('r.status', ['finished','canceled']),
              fn($q) => $q->where('r.status', $status))
        ->whereRaw("$dateExpr IS NOT NULL")
        ->whereRaw("$dateExpr BETWEEN ? AND ?", [$from.' 00:00:00', $to.' 23:59:59'])
        ->orderByDesc(DB::raw($dateExpr))
        ->limit($DETAIL_LIMIT)
        ->selectRaw("
            r.id,
            r.status,
            $dateExpr as ride_final_at,
            r.driver_id,
            COALESCE(d.name, CONCAT('Chofer #', r.driver_id)) as driver_name,
            r.vehicle_id,
            COALESCE(v.economico, CONCAT('Veh #', r.vehicle_id)) as vehicle_economico,
            COALESCE(r.agreed_amount,r.total_amount,r.quoted_amount,0) as amount,
            r.distance_m,
            r.duration_s
        ")
        ->get();

    // =========================
    // 4) Render PDF
    // =========================
    $filters = [
        'from' => $from,
        'to'   => $to,
        'status' => $status,
        'active' => $request->get('active'),
        'verification_status' => $request->get('verification_status'),
        'q' => trim((string)$request->get('q')),
    ];

    $brand = [
        'name'      => 'Orbana',
        'accent'    => '#00CCFF', // tu acento
        'logo_path' => public_path('images/logo.png'),
    ];

    $generatedAt = now()->format('Y-m-d H:i');

    $pdf = Pdf::loadView('partner.reports.vehicles.pdf', [
        'tenantId'     => $tenantId,
        'partnerId'    => $partnerId,
        'partnerBrand' => $partnerBrand,
        'filters'      => $filters,
        'policy'       => $policy,
        'totalFiltered'=> $totalFiltered,
        'kpi'          => $kpi,
        'rows'         => $rows,
        'ridesRows'    => $ridesRows,
        'detailLimit'  => $DETAIL_LIMIT,
        'brand'        => $brand,
        'generatedAt'  => $generatedAt,
    ])->setPaper('a4','portrait');

    $filename = 'partner_vehicles_'.$partnerId.'_'.date('Ymd_His').'.pdf';
    return $pdf->download($filename);
}



}
