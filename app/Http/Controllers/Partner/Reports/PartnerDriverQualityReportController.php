<?php

namespace App\Http\Controllers\Partner\Reports;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PartnerDriverQualityReportController extends Controller
{
    private function tenantId(): int
    {
        $tid = (int) auth()->user()->tenant_id;
        abort_if($tid <= 0, 403);
        return $tid;
    }

    private function partnerId(): int
    {
        $pid = (int) (session('partner_id') ?: auth()->user()->default_partner_id);
        abort_if($pid <= 0, 403, 'Falta contexto de partner.');
        return $pid;
    }

    private function resolveDateRange(Request $r, int $monthsBack = 3): array
    {
        $to   = $r->input('to') ?: Carbon::today()->toDateString();
        $from = $r->input('from') ?: Carbon::today()->subMonths($monthsBack)->toDateString();
        if ($from > $to) [$from, $to] = [$to, $from];
        return [$from, $to];
    }

    /**
     * Fecha final efectiva del ride: finished_at o canceled_at.
     * Usamos esto para “reporte histórico de rides finalizados/cancelados”.
     */
    private function ridesFinalWindowExpr(): string
    {
        return "COALESCE(r.finished_at, r.canceled_at)";
    }

    private function baseDrivers(Request $r)
    {
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        $q = Driver::query()
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId);

        if ($driverId = $r->input('driver_id')) {
            if (is_numeric($driverId)) {
                $q->where('id', (int) $driverId);
            }
        }

        if ($search = trim((string) $r->input('q'))) {
            $like = '%' . $search . '%';
            $q->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)
                  ->orWhere('phone', 'like', $like)
                  ->orWhere('email', 'like', $like);
            });
        }

        return $q;
    }

    /**
     * Ratings agregados por driver, PERO solo ligados a rides finalizados/cancelados
     * y dentro del rango por fecha final del ride.
     */
    private function ratingsAgg(Request $r)
    {
        [$from, $to] = $this->resolveDateRange($r);

        $minRating = $r->input('min_rating');
        $minRating = is_numeric($minRating) ? (int) $minRating : null;
        if ($minRating !== null && ($minRating < 1 || $minRating > 5)) $minRating = null;

        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        // drivers del partner
        $driverIds = Driver::query()
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->select('id');

        // Nota: usamos rides como fuente de “ventana temporal” (fecha final del ride)
        $finalExpr = $this->ridesFinalWindowExpr();

        $q = DB::table('ratings as ra')
            ->join('rides as r', function ($j) {
                $j->on('ra.ride_id', '=', 'r.id');
            })
            ->where('ra.tenant_id', $tenantId)
            ->where('ra.rated_type', 'driver')
            ->whereIn('ra.rated_id', $driverIds)
            ->where('r.tenant_id', $tenantId)
            ->whereIn('r.status', ['finished','canceled'])
            ->whereRaw("$finalExpr BETWEEN ? AND ?", [
                $from . ' 00:00:00',
                $to   . ' 23:59:59',
            ]);

        if ($minRating !== null) {
            $q->where('ra.rating', '>=', $minRating);
        }

        return $q->selectRaw("
            ra.rated_id as driver_id,
            COUNT(*) as ratings_count,
            AVG(ra.rating) as rating_avg,
            SUM(ra.rating=5) as r5,
            SUM(ra.rating=4) as r4,
            SUM(ra.rating=3) as r3,
            SUM(ra.rating=2) as r2,
            SUM(ra.rating=1) as r1,
            AVG(ra.punctuality) as punctuality_avg,
            AVG(ra.courtesy) as courtesy_avg,
            AVG(ra.vehicle_condition) as vehicle_condition_avg,
            AVG(ra.driving_skills) as driving_skills_avg,
            MAX(ra.created_at) as last_rating_at
        ")->groupBy('ra.rated_id');
    }

    /**
     * Issues agregados por driver, solo rides finalizados/cancelados,
     * y ventana temporal por fecha final del ride.
     */
    private function issuesAgg(Request $r)
    {
        [$from, $to] = $this->resolveDateRange($r);

        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        $driverIds = Driver::query()
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->select('id');

        $finalExpr = $this->ridesFinalWindowExpr();

        $q = DB::table('ride_issues as ri')
            ->join('rides as r', function ($j) {
                $j->on('ri.ride_id', '=', 'r.id');
            })
            ->where('ri.tenant_id', $tenantId)
            ->whereIn('ri.driver_id', $driverIds)
            ->where('r.tenant_id', $tenantId)
            ->whereIn('r.status', ['finished','canceled'])
            ->whereRaw("$finalExpr BETWEEN ? AND ?", [
                $from . ' 00:00:00',
                $to   . ' 23:59:59',
            ]);

        if ($st = $r->input('issue_status')) $q->where('ri.status', $st);
        if ($sev = $r->input('severity')) $q->where('ri.severity', $sev);
        if ($cat = $r->input('category')) $q->where('ri.category', $cat);
        if ($r->filled('forward_to_platform')) {
            $q->where('ri.forward_to_platform', (int) $r->input('forward_to_platform') ? 1 : 0);
        }

        return $q->selectRaw("
            ri.driver_id as driver_id,
            COUNT(*) as issues_count,
            SUM(ri.status IN ('open','in_review')) as issues_openish,
            SUM(ri.status='resolved') as issues_resolved,
            SUM(ri.status='closed') as issues_closed,

            SUM(ri.severity='critical') as sev_critical,
            SUM(ri.severity='high') as sev_high,
            SUM(ri.severity='normal') as sev_normal,
            SUM(ri.severity='low') as sev_low,

            MAX(ri.created_at) as last_issue_at,
            AVG(CASE WHEN ri.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, ri.created_at, ri.resolved_at) END) as avg_resolve_hours
        ")->groupBy('ri.driver_id');
    }

    public function index(Request $r)
    {
        $r->validate([
            'from' => ['nullable','date'],
            'to'   => ['nullable','date'],

            // drivers
            'driver_id' => ['nullable','integer'],
            'q'         => ['nullable','string','max:120'],

            // ratings
            'min_rating' => ['nullable','integer','min:1','max:5'],

            // issues
            'issue_status'        => ['nullable','in:open,in_review,resolved,closed'],
            'severity'            => ['nullable','in:low,normal,high,critical'],
            'category'            => ['nullable','in:safety,overcharge,route,driver_behavior,passenger_behavior,vehicle,lost_item,payment,app_problem,other'],
            'forward_to_platform' => ['nullable','in:0,1'],

            // view helpers
            'only_with' => ['nullable','in:ratings,issues,any'],
        ]);

        $driversQ = $this->baseDrivers($r);

        $ra = $this->ratingsAgg($r);
        $ia = $this->issuesAgg($r);

        $drivers = $driversQ
            ->leftJoinSub($ra, 'ra', fn($j) => $j->on('drivers.id','=','ra.driver_id'))
            ->leftJoinSub($ia, 'ia', fn($j) => $j->on('drivers.id','=','ia.driver_id'))
            ->selectRaw("
                drivers.id, drivers.name, drivers.phone, drivers.email,
                COALESCE(ra.ratings_count,0) as ratings_count,
                COALESCE(ra.rating_avg,0) as rating_avg,
                ra.last_rating_at,

                COALESCE(ia.issues_count,0) as issues_count,
                COALESCE(ia.issues_openish,0) as issues_openish,
                COALESCE(ia.sev_critical,0) as sev_critical,
                COALESCE(ia.sev_high,0) as sev_high,
                ia.last_issue_at,
                ia.avg_resolve_hours
            ");

        // Opcional: mostrar solo drivers con señales
        if ($only = $r->input('only_with')) {
            if ($only === 'ratings') {
                $drivers->whereRaw('COALESCE(ra.ratings_count,0) > 0');
            } elseif ($only === 'issues') {
                $drivers->whereRaw('COALESCE(ia.issues_count,0) > 0');
            } elseif ($only === 'any') {
                $drivers->whereRaw('(COALESCE(ra.ratings_count,0) > 0 OR COALESCE(ia.issues_count,0) > 0)');
            }
        }

        $drivers = $drivers
            ->orderByDesc('issues_openish')
            ->orderByDesc('sev_critical')
            ->orderByDesc('ratings_count')
            ->paginate(25)
            ->withQueryString();

        // dropdown drivers (para filtro rápido)
        $driversList = Driver::query()
            ->where('tenant_id', $this->tenantId())
            ->where('partner_id', $this->partnerId())
            ->orderBy('name')
            ->get(['id','name']);

        $kpi = [
            'drivers_total' => (int) Driver::query()
                ->where('tenant_id', $this->tenantId())
                ->where('partner_id', $this->partnerId())
                ->count(),
        ];

        [$from, $to] = $this->resolveDateRange($r);

        return view('partner.reports.driver_quality.index', compact(
            'drivers','driversList','kpi','from','to'
        ));
    }

public function show(Request $r, int $driverId)
{
    $tenantId  = $this->tenantId();
    $partnerId = $this->partnerId();

    $driver = Driver::query()
        ->where('tenant_id', $tenantId)
        ->where('partner_id', $partnerId)
        ->where('id', $driverId)
        ->firstOrFail(['id','name','phone','email']);

    $r->validate([
        'from' => ['nullable','date'],
        'to'   => ['nullable','date'],

        'min_rating' => ['nullable','integer','min:1','max:5'],

        'issue_status'        => ['nullable','in:open,in_review,resolved,closed'],
        'severity'            => ['nullable','in:low,normal,high,critical'],
        'category'            => ['nullable','in:safety,overcharge,route,driver_behavior,passenger_behavior,vehicle,lost_item,payment,app_problem,other'],
        'forward_to_platform' => ['nullable','in:0,1'],

        'tab' => ['nullable','in:overview,ratings,issues'],
    ]);

    $tab = $r->input('tab', 'overview');

    $minRating = $r->input('min_rating');
    $minRating = is_numeric($minRating) ? (int)$minRating : null;
    if ($minRating !== null && ($minRating < 1 || $minRating > 5)) $minRating = null;

    $finalExpr = $this->ridesFinalWindowExpr();

    // =========================================================
    // 1) Construimos bases SIN rango final todavía (para poder detectar ventana real)
    // =========================================================
    $ratingsBaseNoRange = DB::table('ratings as ra')
        ->join('rides as r', 'ra.ride_id', '=', 'r.id')
        ->where('ra.tenant_id', $tenantId)
        ->where('ra.rated_type', 'driver')
        ->where('ra.rated_id', $driver->id)
        ->where('r.tenant_id', $tenantId)
        ->whereIn('r.status', ['finished','canceled']);

    if ($minRating !== null) $ratingsBaseNoRange->where('ra.rating', '>=', $minRating);

    $issuesBaseNoRange = DB::table('ride_issues as ri')
        ->join('rides as r', 'ri.ride_id', '=', 'r.id')
        ->where('ri.tenant_id', $tenantId)
        ->where('ri.driver_id', $driver->id)
        ->where('r.tenant_id', $tenantId)
        ->whereIn('r.status', ['finished','canceled']);

    if ($st = $r->input('issue_status')) $issuesBaseNoRange->where('ri.status', $st);
    if ($sev = $r->input('severity')) $issuesBaseNoRange->where('ri.severity', $sev);
    if ($cat = $r->input('category')) $issuesBaseNoRange->where('ri.category', $cat);
    if ($r->filled('forward_to_platform')) {
        $issuesBaseNoRange->where('ri.forward_to_platform', (int)$r->input('forward_to_platform') ? 1 : 0);
    }

    // =========================================================
    // 2) Resolver ventana del chart (data-driven) con tope 90 días
    // =========================================================
    $MAX_DAYS = 90;   // 3 meses aprox
    $FALLBACK_DAYS = 30; // si no hay datos, se ve mejor 1 mes

    // Si el usuario manda from/to, lo respetamos, pero capamos a MAX_DAYS
    $hasFrom = $r->filled('from');
    $hasTo   = $r->filled('to');

    if ($hasFrom || $hasTo) {
        // Usa resolveDateRange, pero le metemos el cap a 90 días
        [$from, $to] = $this->resolveDateRange($r);

        $toC = \Carbon\Carbon::parse($to)->endOfDay();
        $fromC = \Carbon\Carbon::parse($from)->startOfDay();

        if ($fromC->diffInDays($toC) + 1 > $MAX_DAYS) {
            $fromC = $toC->copy()->startOfDay()->subDays($MAX_DAYS - 1);
        }

        $from = $fromC->toDateString();
        $to   = $toC->toDateString();
    } else {
        // Ventana basada en datos reales (ratings/issues)
        // Nota: usamos created_at reales para detectar actividad; y filtramos por rides finalExpr igual.
        $rMinMax = (clone $ratingsBaseNoRange)
            ->whereRaw("$finalExpr IS NOT NULL")
            ->selectRaw("MIN(DATE(ra.created_at)) as dmin, MAX(DATE(ra.created_at)) as dmax")
            ->first();

        $iMinMax = (clone $issuesBaseNoRange)
            ->whereRaw("$finalExpr IS NOT NULL")
            ->selectRaw("MIN(DATE(ri.created_at)) as dmin, MAX(DATE(ri.created_at)) as dmax")
            ->first();

        $candidates = [];
        if (!empty($rMinMax->dmin)) $candidates[] = $rMinMax->dmin;
        if (!empty($iMinMax->dmin)) $candidates[] = $iMinMax->dmin;

        $candidatesMax = [];
        if (!empty($rMinMax->dmax)) $candidatesMax[] = $rMinMax->dmax;
        if (!empty($iMinMax->dmax)) $candidatesMax[] = $iMinMax->dmax;

        if (!$candidates || !$candidatesMax) {
            // sin datos -> últimos 30 días
            $toC = now()->endOfDay();
            $fromC = $toC->copy()->startOfDay()->subDays($FALLBACK_DAYS - 1);
        } else {
            $fromC = \Carbon\Carbon::parse(min($candidates))->startOfDay();
            $toC   = \Carbon\Carbon::parse(max($candidatesMax))->endOfDay();

            // cap a MAX_DAYS (recorta por izquierda)
            if ($fromC->diffInDays($toC) + 1 > $MAX_DAYS) {
                $fromC = $toC->copy()->startOfDay()->subDays($MAX_DAYS - 1);
            }
        }

        $from = $fromC->toDateString();
        $to   = $toC->toDateString();
    }

    // =========================================================
    // 3) Ahora sí, aplicamos rango final a las bases
    //    (importante: filtrar por finalExpr dentro de ventana final)
    // =========================================================
    $ratingsBase = (clone $ratingsBaseNoRange)
        ->whereRaw("$finalExpr BETWEEN ? AND ?", [$from.' 00:00:00', $to.' 23:59:59']);

    $issuesBase = (clone $issuesBaseNoRange)
        ->whereRaw("$finalExpr BETWEEN ? AND ?", [$from.' 00:00:00', $to.' 23:59:59']);

    // =========================================================
    // 4) Métricas + listados
    // =========================================================
    $ratingsMetrics = (clone $ratingsBase)->selectRaw("
        COUNT(*) as ratings_count,
        AVG(ra.rating) as rating_avg,
        SUM(ra.rating=5) as r5,
        SUM(ra.rating=4) as r4,
        SUM(ra.rating=3) as r3,
        SUM(ra.rating=2) as r2,
        SUM(ra.rating=1) as r1,
        AVG(ra.punctuality) as punctuality_avg,
        AVG(ra.courtesy) as courtesy_avg,
        AVG(ra.vehicle_condition) as vehicle_condition_avg,
        AVG(ra.driving_skills) as driving_skills_avg
    ")->first();

    $ratingsDailyRaw = (clone $ratingsBase)
        ->selectRaw("
            DATE(ra.created_at) as day,
            COUNT(*) as ratings_count,
            AVG(ra.rating) as rating_avg
        ")
        ->groupByRaw("DATE(ra.created_at)")
        ->orderBy('day')
        ->get();

    $ratings = (clone $ratingsBase)
        ->select([
            'ra.id','ra.ride_id','ra.rating','ra.comment',
            'ra.punctuality','ra.courtesy','ra.vehicle_condition','ra.driving_skills',
            'ra.created_at',
            'r.status as ride_status','r.finished_at','r.canceled_at',
        ])
        ->orderByDesc('ra.id')
        ->paginate(20, ['*'], 'ratings_page')
        ->withQueryString();

    $issuesMetrics = (clone $issuesBase)->selectRaw("
        COUNT(*) as issues_count,
        SUM(ri.status IN ('open','in_review')) as issues_openish,
        SUM(ri.status='resolved') as issues_resolved,
        SUM(ri.status='closed') as issues_closed,
        SUM(ri.severity='critical') as sev_critical,
        SUM(ri.severity='high') as sev_high,
        SUM(ri.severity='normal') as sev_normal,
        SUM(ri.severity='low') as sev_low,
        AVG(CASE WHEN ri.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, ri.created_at, ri.resolved_at) END) as avg_resolve_hours
    ")->first();

    $issuesDailyRaw = (clone $issuesBase)
        ->selectRaw("
            DATE(ri.created_at) as day,
            COUNT(*) as issues_count,
            SUM(ri.status IN ('open','in_review')) as openish_count
        ")
        ->groupByRaw("DATE(ri.created_at)")
        ->orderBy('day')
        ->get();

    $issuesByCategory = (clone $issuesBase)
        ->selectRaw("ri.category, COUNT(*) as cnt")
        ->groupBy('ri.category')
        ->orderByDesc('cnt')
        ->get();

    $issues = (clone $issuesBase)
        ->select([
            'ri.id','ri.ride_id','ri.category','ri.title','ri.description',
            'ri.status','ri.severity','ri.forward_to_platform',
            'ri.created_at','ri.resolved_at','ri.closed_at',
            'r.status as ride_status','r.finished_at','r.canceled_at',
        ])
        ->orderByDesc('ri.id')
        ->paginate(20, ['*'], 'issues_page')
        ->withQueryString();

    // =========================================================
    // 5) Charts: labels SOLO de esta ventana (10 días => 10 días)
    //    avg = null en días sin rating, pero en JS usas spanGaps:true para conectar.
    // =========================================================
    $labels = $this->dateSeries($from, $to);

    $rMap = $ratingsDailyRaw->keyBy(fn($x) => (string)$x->day);
    $ratingsCount = [];
    $ratingsAvg   = [];
    foreach ($labels as $day) {
        $row = $rMap->get($day);
        $c = (int)($row->ratings_count ?? 0);
        $ratingsCount[] = $c;
        $ratingsAvg[]   = $c > 0 ? (float)($row->rating_avg ?? 0) : null;
    }

    $iMap = $issuesDailyRaw->keyBy(fn($x) => (string)$x->day);
    $issuesCount = [];
    $issuesOpen  = [];
    foreach ($labels as $day) {
        $row = $iMap->get($day);
        $issuesCount[] = (int)($row->issues_count ?? 0);
        $issuesOpen[]  = (int)($row->openish_count ?? 0);
    }

  $charts = [
    // ✅ NUEVO: distribución 1..5
    'ratings_dist' => [
        'labels' => ['1','2','3','4','5'],
        'count'  => [
            (int)($ratingsMetrics->r1 ?? 0),
            (int)($ratingsMetrics->r2 ?? 0),
            (int)($ratingsMetrics->r3 ?? 0),
            (int)($ratingsMetrics->r4 ?? 0),
            (int)($ratingsMetrics->r5 ?? 0),
        ],
    ],

    // (opcional) deja esto si sigues usando barras diarias
    'ratings_daily' => [
        'labels' => $labels,
        'count'  => $ratingsCount,
        'avg'    => $ratingsAvg,
    ],

    'issues_daily' => [
        'labels' => $labels,
        'count'  => $issuesCount,
        'openish'=> $issuesOpen,
    ],
    'issues_by_category' => [
        'labels' => $issuesByCategory->pluck('category')->map(fn($v)=>(string)$v)->all(),
        'count'  => $issuesByCategory->pluck('cnt')->map(fn($v)=>(int)$v)->all(),
    ],
];


    return view('partner.reports.driver_quality.show', [
        'driver'         => $driver,
        'tab'            => $tab,
        'from'           => $from,
        'to'             => $to,
        'filters'        => $r->only(['min_rating','issue_status','severity','category','forward_to_platform']),
        'ratingsMetrics' => $ratingsMetrics,
        'issuesMetrics'  => $issuesMetrics,
        'ratings'        => $ratings,
        'issues'         => $issues,
        'charts'         => $charts,
    ]);
}



private function dateSeries(string $from, string $to): array {
    $a = Carbon::parse($from)->startOfDay();
    $b = Carbon::parse($to)->startOfDay();
    $out = [];
    for ($d = $a->copy(); $d->lte($b); $d->addDay()) $out[] = $d->toDateString();
    return $out;
}

    public function exportCsv(Request $r): StreamedResponse
    {
        $r->validate([
            'from' => ['nullable','date'],
            'to'   => ['nullable','date'],
            'type' => ['nullable','in:ratings,issues,both'],

            'min_rating' => ['nullable','integer','min:1','max:5'],

            'issue_status'        => ['nullable','in:open,in_review,resolved,closed'],
            'severity'            => ['nullable','in:low,normal,high,critical'],
            'category'            => ['nullable','in:safety,overcharge,route,driver_behavior,passenger_behavior,vehicle,lost_item,payment,app_problem,other'],
            'forward_to_platform' => ['nullable','in:0,1'],
        ]);

        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();
        [$from, $to] = $this->resolveDateRange($r);
        $type = $r->input('type', 'both');

        $finalExpr = $this->ridesFinalWindowExpr();

        $driverIds = Driver::query()
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->select('id');

        $minRating = $r->input('min_rating');
        $minRating = is_numeric($minRating) ? (int)$minRating : null;
        if ($minRating !== null && ($minRating < 1 || $minRating > 5)) $minRating = null;

        $fileName = "partner_driver_quality_{$type}_" . date('Ymd_His') . ".csv";

        return response()->streamDownload(function () use ($type, $tenantId, $from, $to, $driverIds, $r, $finalExpr, $minRating) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'row_type','tenant_id','driver_id','ride_id','ride_final_at',
                'created_at',
                // rating
                'rating','comment','punctuality','courtesy','vehicle_condition','driving_skills',
                // issue
                'issue_id','category','title','status','severity','forward_to_platform','resolved_at','closed_at',
            ]);

            if ($type === 'ratings' || $type === 'both') {
                $q = DB::table('ratings as ra')
                    ->join('rides as r', 'ra.ride_id','=','r.id')
                    ->where('ra.tenant_id', $tenantId)
                    ->where('ra.rated_type','driver')
                    ->whereIn('ra.rated_id', $driverIds)
                    ->where('r.tenant_id', $tenantId)
                    ->whereIn('r.status', ['finished','canceled'])
                    ->whereRaw("$finalExpr BETWEEN ? AND ?", [$from.' 00:00:00', $to.' 23:59:59'])
                    ->orderBy('ra.id');

                if ($minRating !== null) $q->where('ra.rating','>=',$minRating);

                $q->select([
                    'ra.rated_id as driver_id',
                    'ra.ride_id',
                    'ra.created_at',
                    'ra.rating','ra.comment','ra.punctuality','ra.courtesy','ra.vehicle_condition','ra.driving_skills',
                    DB::raw("$finalExpr as ride_final_at"),
                ])->chunk(2000, function($rows) use ($out, $tenantId) {
                    foreach ($rows as $x) {
                        fputcsv($out, [
                            'rating',$tenantId,$x->driver_id,$x->ride_id,$x->ride_final_at,
                            $x->created_at,
                            $x->rating,$x->comment,$x->punctuality,$x->courtesy,$x->vehicle_condition,$x->driving_skills,
                            null,null,null,null,null,null,null,null,
                        ]);
                    }
                });
            }

            if ($type === 'issues' || $type === 'both') {
                $q = DB::table('ride_issues as ri')
                    ->join('rides as r', 'ri.ride_id','=','r.id')
                    ->where('ri.tenant_id', $tenantId)
                    ->whereIn('ri.driver_id', $driverIds)
                    ->where('r.tenant_id', $tenantId)
                    ->whereIn('r.status', ['finished','canceled'])
                    ->whereRaw("$finalExpr BETWEEN ? AND ?", [$from.' 00:00:00', $to.' 23:59:59'])
                    ->orderBy('ri.id');

                if ($st = $r->input('issue_status')) $q->where('ri.status',$st);
                if ($sev= $r->input('severity')) $q->where('ri.severity',$sev);
                if ($cat= $r->input('category')) $q->where('ri.category',$cat);
                if ($r->filled('forward_to_platform')) $q->where('ri.forward_to_platform', (int)$r->input('forward_to_platform') ? 1 : 0);

                $q->select([
                    'ri.driver_id','ri.ride_id','ri.id as issue_id',
                    'ri.created_at','ri.category','ri.title','ri.status','ri.severity','ri.forward_to_platform',
                    'ri.resolved_at','ri.closed_at',
                    DB::raw("$finalExpr as ride_final_at"),
                ])->chunk(2000, function($rows) use ($out, $tenantId) {
                    foreach ($rows as $x) {
                        fputcsv($out, [
                            'issue',$tenantId,$x->driver_id,$x->ride_id,$x->ride_final_at,
                            $x->created_at,
                            null,null,null,null,null,null,
                            $x->issue_id,$x->category,$x->title,$x->status,$x->severity,$x->forward_to_platform,$x->resolved_at,$x->closed_at,
                        ]);
                    }
                });
            }

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
