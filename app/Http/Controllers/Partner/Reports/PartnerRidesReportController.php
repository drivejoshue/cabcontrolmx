<?php

namespace App\Http\Controllers\Partner\Reports;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\Geo\GoogleMapsService;

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

    public function exportCsv(Request $request): StreamedResponse
    {
        $partner   = $this->ctx($request);
        $tenantId  = $partner->tenant_id;
        $partnerId = $partner->id;

        $f = $this->normalizeFilters($request);

        $q = $this->ridesBaseQuery($tenantId, $partnerId);
        $this->applyReportFilters($q, $f);

        $q->leftJoin('drivers as d', function ($j) use ($tenantId) {
            $j->on('d.id', '=', 'r.driver_id')->where('d.tenant_id', '=', $tenantId);
        })->leftJoin('vehicles as v', function ($j) use ($tenantId) {
            $j->on('v.id', '=', 'r.vehicle_id')->where('v.tenant_id', '=', $tenantId);
        })->selectRaw("
            r.*,
            COALESCE(d.name, CONCAT('Driver #', r.driver_id)) as driver_name,
            v.economico as vehicle_economico,
            v.plate as vehicle_plate
        ")->orderByDesc('r.id');

        $filename = 'partner_rides_'.$partnerId.'_'.date('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id','estado','fecha_final','conductor','vehiculo','pasajero','telefono','monto','moneda','origen','destino'
            ]);

            $q->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    $amount = (float)($r->agreed_amount ?? $r->total_amount ?? $r->quoted_amount ?? 0);
                    $finalAt = $r->finished_at ?? $r->canceled_at ?? $r->requested_at ?? $r->created_at;
                    $veh = trim(($r->vehicle_economico ?? '') . ' ' . ($r->vehicle_plate ?? '')) ?: ('#' . ($r->vehicle_id ?? '—'));

                    fputcsv($out, [
                        $r->id,
                        $r->status,
                        $finalAt,
                        $r->driver_name ?? ('#'.$r->driver_id),
                        $veh,
                        $r->passenger_name ?? '',
                        $r->passenger_phone ?? '',
                        number_format($amount, 2, '.', ''),
                        $r->currency ?? 'MXN',
                        $r->origin_label ?? '',
                        $r->dest_label ?? '',
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
