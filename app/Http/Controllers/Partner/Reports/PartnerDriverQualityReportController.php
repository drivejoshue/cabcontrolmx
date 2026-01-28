<?php

namespace App\Http\Controllers\Partner\Reports;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

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
  private function ridesFinalWindowExpr(string $alias = 'r'): string
{
    // Usa SIEMPRE el alias real del join a rides
    return "COALESCE($alias.finished_at, $alias.canceled_at)";
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
        $finalExpr = $this->ridesFinalWindowExpr('r');

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

       $finalExpr = $this->ridesFinalWindowExpr('r');


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

  





public function exportPdf(Request $r)
{
    $r->validate([
        'from' => ['nullable','date'],
        'to'   => ['nullable','date'],

        'driver_id' => ['nullable','integer'],
        'q'         => ['nullable','string','max:120'],

        'min_rating' => ['nullable','integer','min:1','max:5'],

        'issue_status'        => ['nullable','in:open,in_review,resolved,closed'],
        'severity'            => ['nullable','in:low,normal,high,critical'],
        'category'            => ['nullable','in:safety,overcharge,route,driver_behavior,passenger_behavior,vehicle,lost_item,payment,app_problem,other'],
        'forward_to_platform' => ['nullable','in:0,1'],

        'export_scope' => ['nullable','in:limit,all'],
        'limit_rows'   => ['nullable','integer','min:100','max:5000'],
        'force'        => ['nullable','in:0,1'],
        'only_with'    => ['nullable','in:ratings,issues,any'],
    ]);

    $tenantId  = $this->tenantId();
    $partnerId = $this->partnerId();

    // Partner branding
    $partner = DB::table('partners')
        ->where('tenant_id', $tenantId)
        ->where('id', $partnerId)
        ->first();

    abort_if(!$partner, 404, 'Partner no encontrado.');

    $partnerBrand = [
        'name'          => $partner->name ?? ('Partner #'.$partnerId),
        'city'          => $partner->city ?? '',
        'state'         => $partner->state ?? '',
        'contact_phone' => $partner->contact_phone ?? '',
        'contact_email' => $partner->contact_email ?? '',
        'legal_name'    => $partner->legal_name ?? '',
        'rfc'           => $partner->rfc ?? '',
    ];

    [$from, $to] = $this->resolveDateRange($r);

    $filters = [
        'from' => $from,
        'to'   => $to,
        'driver_id' => $r->input('driver_id'),
        'q'         => trim((string)$r->input('q')),
        'min_rating'=> $r->input('min_rating'),
        'issue_status' => $r->input('issue_status'),
        'severity'     => $r->input('severity'),
        'category'     => $r->input('category'),
        'forward_to_platform' => $r->filled('forward_to_platform') ? (int)$r->input('forward_to_platform') : null,
        'only_with' => $r->input('only_with'),
    ];

    $policy = $this->reportExportPolicy($r);

    // =========================
    // 1) Base drivers + aggs
    // =========================
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
            COALESCE(ra.r1,0) as r1,
            COALESCE(ra.r2,0) as r2,
            COALESCE(ra.r3,0) as r3,
            COALESCE(ra.r4,0) as r4,
            COALESCE(ra.r5,0) as r5,
            ra.last_rating_at,

            COALESCE(ia.issues_count,0) as issues_count,
            COALESCE(ia.issues_openish,0) as issues_openish,
            COALESCE(ia.sev_critical,0) as sev_critical,
            COALESCE(ia.sev_high,0) as sev_high,
            COALESCE(ia.sev_normal,0) as sev_normal,
            COALESCE(ia.sev_low,0) as sev_low,
            ia.last_issue_at,
            ia.avg_resolve_hours
        ");

    if ($only = $r->input('only_with')) {
        if ($only === 'ratings') $drivers->whereRaw('COALESCE(ra.ratings_count,0) > 0');
        elseif ($only === 'issues') $drivers->whereRaw('COALESCE(ia.issues_count,0) > 0');
        elseif ($only === 'any') $drivers->whereRaw('(COALESCE(ra.ratings_count,0) > 0 OR COALESCE(ia.issues_count,0) > 0)');
    }

    $drivers = $drivers
        ->orderByDesc('issues_openish')
        ->orderByDesc('sev_critical')
        ->orderByDesc('ratings_count');

    $totalFiltered = (clone $drivers)->count();

    $applyLimit = ($policy['scope'] === 'limit');
    if ($applyLimit) {
        $drivers = $drivers->limit($policy['limitRows']);
    } else {
        if ($totalFiltered > $policy['limitRows'] && !$policy['force']) {
            abort(422, "El reporte tiene {$totalFiltered} registros. Para exportar TODO agrega ?force=1 o usa ?export_scope=limit&limit_rows={$policy['limitRows']}.");
        }
        if ($totalFiltered > 15000) {
            abort(422, "El reporte excede 15000 registros ({$totalFiltered}). Ajusta filtros o exporta por rangos.");
        }
    }

    $rows = $drivers->get();

    // =========================
    // 2) KPIs correctos (NO dependen del recorte $rows)
    // =========================
   $finalExpr = $this->ridesFinalWindowExpr('r2');

    $driverIds = Driver::query()
        ->where('tenant_id', $tenantId)
        ->where('partner_id', $partnerId)
        ->select('id');

    $minRating = $r->input('min_rating');
    $minRating = is_numeric($minRating) ? (int)$minRating : null;
    if ($minRating !== null && ($minRating < 1 || $minRating > 5)) $minRating = null;

    // Ratings global (sobre filtros, sin limit)
   $ratingsAggAll = DB::table('ratings as ra')
    ->join('rides as r2', 'ra.ride_id','=','r2.id')
    ->where('ra.tenant_id', $tenantId)
    ->where('ra.rated_type','driver')
    ->whereIn('ra.rated_id', $driverIds)
    ->where('r2.tenant_id', $tenantId)
    ->whereIn('r2.status', ['finished','canceled'])
    ->whereRaw("$finalExpr BETWEEN ? AND ?", [$from.' 00:00:00', $to.' 23:59:59']);

    if ($r->filled('driver_id')) $ratingsAggAll->where('ra.rated_id', (int)$r->input('driver_id'));
    if ($minRating !== null) $ratingsAggAll->where('ra.rating','>=',$minRating);

    $ratingsTotals = (clone $ratingsAggAll)->selectRaw("
        COUNT(*) as cnt,
        AVG(ra.rating) as avg_rating
    ")->first();

    // Issues global
   $issuesAggAll = DB::table('ride_issues as ri')
    ->join('rides as r2', 'ri.ride_id','=','r2.id')
    ->where('ri.tenant_id', $tenantId)
    ->whereIn('ri.driver_id', $driverIds)
    ->where('r2.tenant_id', $tenantId)
    ->whereIn('r2.status', ['finished','canceled'])
    ->whereRaw("$finalExpr BETWEEN ? AND ?", [$from.' 00:00:00', $to.' 23:59:59']);


    if ($r->filled('driver_id')) $issuesAggAll->where('ri.driver_id', (int)$r->input('driver_id'));
    if ($st = $r->input('issue_status')) $issuesAggAll->where('ri.status',$st);
    if ($sev= $r->input('severity')) $issuesAggAll->where('ri.severity',$sev);
    if ($cat= $r->input('category')) $issuesAggAll->where('ri.category',$cat);
    if ($r->filled('forward_to_platform')) $issuesAggAll->where('ri.forward_to_platform', (int)$r->input('forward_to_platform') ? 1 : 0);

    $issuesTotals = (clone $issuesAggAll)->selectRaw("
        COUNT(*) as cnt,
        SUM(ri.status IN ('open','in_review')) as openish
    ")->first();

    $kpi = [
        'drivers_total' => (int) Driver::query()
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->count(),
        'drivers_in_report' => (int) $totalFiltered,

        'ratings_total' => (int)($ratingsTotals->cnt ?? 0),
        'rating_avg_weighted' => (float)($ratingsTotals->avg_rating ?? 0),

        'issues_total' => (int)($issuesTotals->cnt ?? 0),
        'issues_openish_total' => (int)($issuesTotals->openish ?? 0),
    ];

    // =========================
    // 3) Detalle (tablas) - con hard limits
    // =========================
   $DETAIL_LIMIT = min(800, (int)$policy['limitRows']);

$ratingsRows = (clone $ratingsAggAll)
    ->join('drivers as d', 'ra.rated_id','=','d.id')
    ->selectRaw("
        ra.id,
        ra.ride_id,
        ra.rated_id as driver_id,
        d.name as driver_name,
        ra.rating,
        ra.comment,
        ra.created_at,
        $finalExpr as ride_final_at
    ")
    ->orderByDesc('ra.id')
    ->limit($DETAIL_LIMIT)
    ->get()
    ->map(function($x){
        $x->comment = Str::limit((string)$x->comment, 120);
        return $x;
    });


    $issuesRows = (clone $issuesAggAll)
        ->join('drivers as d', function($j){
            $j->on('ri.driver_id','=','d.id');
        })
        ->selectRaw("
            ri.id,
            ri.ride_id,
            ri.driver_id,
            d.name as driver_name,
            ri.category,
            ri.title,
            ri.status,
            ri.severity,
            ri.forward_to_platform,
            ri.created_at,
            ri.resolved_at,
            $finalExpr as ride_final_at
        ")
        ->orderByDesc('ri.id')
        ->limit($DETAIL_LIMIT)
        ->get()
        ->map(function($x){
            $x->title = Str::limit((string)$x->title, 120);
            return $x;
        });

    // =========================
    // 4) Charts (con color)
    // =========================
    $charts = $this->buildDriverQualityChartsPngColored($rows, [
        'accent' => '#00CCFF', // Orbana blue
        'ink'    => '#0b1220',
        'muted'  => '#64748b',
        'soft'   => '#eef2ff',
    ]);

    $brand = [
        'name'      => 'Orbana',
        'accent'    => '#0b1220',
        'logo_path' => public_path('images/logo.png'),
    ];

    $generatedAt = now()->format('Y-m-d H:i');

    $pdf = Pdf::loadView('partner.reports.driver_quality.pdf', [
        'tenantId'     => $tenantId,
        'partnerId'    => $partnerId,
        'partnerBrand' => $partnerBrand,

        'filters'      => $filters,
        'policy'       => $policy,
        'totalFiltered'=> $totalFiltered,

        'kpi'          => $kpi,
        'rows'         => $rows,

        'ratingsRows'  => $ratingsRows,
        'issuesRows'   => $issuesRows,
        'detailLimit'  => $DETAIL_LIMIT,

        'charts'       => $charts,
        'brand'        => $brand,
        'generatedAt'  => $generatedAt,
    ])->setPaper('a4','portrait');

    $filename = 'partner_driver_quality_'.$partnerId.'_'.date('Ymd_His').'.pdf';
    return $pdf->download($filename);
}


/**
 * Policy simple para PDF.
 */
private function reportExportPolicy(Request $r): array
{
    $scope = $r->input('export_scope', 'limit'); // default limit
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

/**
 * Genera 2 charts simples como PNG embebible:
 * - ratings_dist (barras)
 * - issues_severity (barras)
 *
 * Retorna: ['ratings_png' => 'data:image/png;base64,...', 'issues_png' => ...]
 */
private function buildDriverQualityChartsPngColored($rows, array $pal = []): array
{
    $accent = $pal['accent'] ?? '#00CCFF';
    $muted  = $pal['muted']  ?? '#64748b';

    // Issues por severidad (sumando de $rows)
    $sev = [
        'critical' => (int)$rows->sum('sev_critical'),
        'high'     => (int)$rows->sum('sev_high'),
        'normal'   => (int)$rows->sum('sev_normal'),
        'low'      => (int)$rows->sum('sev_low'),
    ];

    $issuesSevPng = $this->simpleBarChartPng(
        title: 'Issues por severidad',
        labels: array_keys($sev),
        values: array_values($sev),
        barHex: $accent,
        axisHex: $muted
    );

    // Distribución ratings 1..5 (sumando r1..r5)
    $dist = [
        1 => (int)$rows->sum('r1'),
        2 => (int)$rows->sum('r2'),
        3 => (int)$rows->sum('r3'),
        4 => (int)$rows->sum('r4'),
        5 => (int)$rows->sum('r5'),
    ];

    $ratingsDistPng = $this->simpleBarChartPng(
        title: 'Distribución de ratings',
        labels: array_map('strval', array_keys($dist)),
        values: array_values($dist),
        barHex: $accent,
        axisHex: $muted
    );

    return [
        'issues_sev_png'   => $issuesSevPng,
        'ratings_dist_png' => $ratingsDistPng,
    ];
}

private function simpleBarChartPng(
    string $title,
    array $labels,
    array $values,
    string $barHex = '#00CCFF',
    string $axisHex = '#64748b'
): ?string {
    if (!function_exists('imagecreatetruecolor')) return null;

    $w = 1100; $h = 420;
    $im = imagecreatetruecolor($w, $h);

    // background blanco
    $white = imagecolorallocate($im, 255, 255, 255);
    imagefill($im, 0, 0, $white);

    // colores
    [$br,$bg,$bb] = sscanf($barHex, "#%02x%02x%02x");
    [$ar,$ag,$ab] = sscanf($axisHex, "#%02x%02x%02x");

    // barra con alpha suave
    $bar = imagecolorallocatealpha($im, $br, $bg, $bb, 35);
    $barStroke = imagecolorallocate($im, $br, $bg, $bb);
    $axis = imagecolorallocate($im, $ar, $ag, $ab);
    $grid = imagecolorallocatealpha($im, $ar, $ag, $ab, 85);
    $text = imagecolorallocate($im, 15, 23, 42);

    // layout
    $padL = 60; $padR = 30; $padT = 40; $padB = 70;
    $plotW = $w - $padL - $padR;
    $plotH = $h - $padT - $padB;

    // título
    imagestring($im, 5, $padL, 12, $title, $text);

    $max = max(1, max($values));
    $n = max(1, count($values));
    $gap = 16;
    $barW = (int)(($plotW - ($gap * ($n - 1))) / $n);

    // grid + axis
    imageline($im, $padL, $padT + $plotH, $padL + $plotW, $padT + $plotH, $axis);
    imageline($im, $padL, $padT, $padL, $padT + $plotH, $axis);

    for ($i=1; $i<=4; $i++) {
        $y = (int)($padT + $plotH - ($plotH * ($i/4)));
        imageline($im, $padL, $y, $padL + $plotW, $y, $grid);
    }

    for ($i=0; $i<$n; $i++) {
        $x1 = $padL + $i * ($barW + $gap);
        $v = (int)$values[$i];
        $bh = (int) round(($v / $max) * $plotH);

        $y1 = $padT + $plotH - $bh;
        $x2 = $x1 + $barW;
        $y2 = $padT + $plotH;

        imagefilledrectangle($im, $x1, $y1, $x2, $y2, $bar);
        imagerectangle($im, $x1, $y1, $x2, $y2, $barStroke);

        // value
        imagestring($im, 3, $x1 + 4, max($padT, $y1 - 14), (string)$v, $axis);
        // label
        $lab = (string)$labels[$i];
        imagestring($im, 3, $x1 + 2, $padT + $plotH + 10, $lab, $axis);
    }

    ob_start();
    imagepng($im);
    $bin = ob_get_clean();
    imagedestroy($im);

    return 'data:image/png;base64,' . base64_encode($bin);
}



/**
 * Chart barras múltiples.
 */
private function chartBar(string $title, array $data, int $w = 820, int $h = 240): ?string
{
    $padL = 60; $padR = 20; $padT = 34; $padB = 44;

    $im = imagecreatetruecolor($w, $h);

    // Colores suaves (NO negro)
    $white = imagecolorallocate($im, 255,255,255);
    $ink   = imagecolorallocate($im, 15,23,42);
    $muted = imagecolorallocate($im, 100,116,139);
    $grid  = imagecolorallocate($im, 226,232,240);

    // Orbana #00CCFF (0,204,255) + un tono más oscuro para borde
    $barFill = imagecolorallocate($im, 0,204,255);
    $barEdge = imagecolorallocate($im, 0,140,175);

    imagefilledrectangle($im, 0,0, $w,$h, $white);

    // Title
    imagestring($im, 5, 10, 10, $title, $ink);

    // Axis
    $x0 = $padL; $y0 = $padT; $x1 = $w-$padR; $y1 = $h-$padB;
    imageline($im, $x0, $y1, $x1, $y1, $grid); // baseline
    imageline($im, $x0, $y0, $x0, $y1, $grid);

    // Light horizontal grid (3 lines)
    for ($k=1;$k<=3;$k++){
        $yy = (int)($y1 - ($k/4)*($y1-$y0));
        imageline($im, $x0, $yy, $x1, $yy, $grid);
    }

    $vals = array_map(fn($v)=>(float)$v, array_values($data));
    $max = max(1.0, max($vals));

    $n = max(1, count($data));
    $plotW = ($x1-$x0);
    $slot = $plotW / $n;
    $barW = max(14, (int)($slot * 0.55));

    $i = 0;
    foreach ($data as $label => $value) {
        $v = (float)$value;

        $bx0 = (int)($x0 + $i*$slot + ($slot-$barW)/2);
        $bx1 = $bx0 + $barW;

        $barH = (int)(($y1-$y0) * ($v / $max));
        $by1 = $y1;
        $by0 = $by1 - $barH;

        // Bar (fill + edge)
        imagefilledrectangle($im, $bx0, $by0, $bx1, $by1, $barFill);
        imagerectangle($im, $bx0, $by0, $bx1, $by1, $barEdge);

        // Value above bar
        $txt = (string)((int)$v);
        imagestring($im, 3, $bx0, max($y0, $by0-14), $txt, $muted);

        // Label
        $lbl = (string)$label;
        imagestring($im, 3, $bx0, $y1+12, $lbl, $muted);

        $i++;
    }

    ob_start();
    imagepng($im);
    $png = ob_get_clean();
    imagedestroy($im);

    return 'data:image/png;base64,'.base64_encode($png);
}


/**
 * Chart barra única con max configurable (útil para rating 0..5).
 */
private function chartBarSingle(string $title, array $data, float $maxScale): ?string
{
    $w = 820; $h = 200;
    $padL = 60; $padR = 20; $padT = 30; $padB = 40;

    $im = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($im, 255,255,255);
    $ink   = imagecolorallocate($im, 15,23,42);
    $muted = imagecolorallocate($im, 100,116,139);
    $line  = imagecolorallocate($im, 226,232,240);
    $bar   = imagecolorallocate($im, 11,18,32);

    imagefilledrectangle($im, 0,0, $w,$h, $white);
    imagestring($im, 5, 10, 8, $title, $ink);

    imageline($im, $padL, $padT, $padL, $h-$padB, $line);
    imageline($im, $padL, $h-$padB, $w-$padR, $h-$padB, $line);

    $plotW = ($w-$padL-$padR);
    $barW = (int)($plotW * 0.45);

    $i = 0;
    foreach ($data as $label => $value) {
        $v = (float)$value;
        $barH = (int)(($h-$padT-$padB) * min(1.0, ($v / max(0.01,$maxScale))));
        $x0 = $padL + 60;
        $x1 = $x0 + $barW;
        $y1 = $h - $padB;
        $y0 = $y1 - $barH;

        imagefilledrectangle($im, $x0, $y0, $x1, $y1, $bar);

        imagestring($im, 4, $x0, $y0-16, (string)$v.' / '.$maxScale, $muted);
        imagestring($im, 3, $x0, $h-$padB+10, (string)$label, $muted);

        $i++;
    }

    ob_start();
    imagepng($im);
    $png = ob_get_clean();
    imagedestroy($im);

    return 'data:image/png;base64,'.base64_encode($png);
}

}
