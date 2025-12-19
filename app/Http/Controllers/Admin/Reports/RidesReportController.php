<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\DriverLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RidesReportController extends Controller
{
    public function index(Request $request)
    {
        $from   = $request->input('from', now()->subDays(7)->format('Y-m-d'));
        $to     = $request->input('to',   now()->format('Y-m-d'));
        $status = $request->input('status');

        // 🔹 Tenant actual (admin de central)
        $tenantId = auth()->user()->tenant_id ?? null;
        if (!$tenantId) {
            abort(403, 'Usuario sin tenant asignado');
        }

        // 🔹 Base: por tenant + fechas
        $qBase = Ride::query()
            ->where('rides.tenant_id', $tenantId)
            ->whereDate('rides.requested_at', '>=', $from)
            ->whereDate('rides.requested_at', '<=', $to);

        // Conteos
        $total     = (clone $qBase)->count();
        $finishedN = (clone $qBase)->where('rides.status', 'finished')->count();
        $canceledN = (clone $qBase)->where('rides.status', 'canceled')->count();

        // Ingresos cotizados (solo finalizados)
        $quoted = (clone $qBase)->where('rides.status', 'finished')
            ->selectRaw('
                COALESCE(SUM(rides.quoted_amount),0) AS sum_quote,
                AVG(NULLIF(rides.quoted_amount,0))   AS avg_quote
            ')
            ->first();

        // Ingresos cobrados (solo finalizados)
        $collected = (clone $qBase)->where('rides.status', 'finished')
            ->selectRaw('
                COALESCE(SUM(rides.total_amount),0) AS sum_collected,
                AVG(NULLIF(rides.total_amount,0))   AS avg_collected,
                COUNT(*)                            AS finished_count,
                COUNT(rides.total_amount)           AS collected_count
            ')
            ->first();

        // Métricas operativas (solo finalizados)
        $ops = (clone $qBase)->where('rides.status', 'finished')
            ->selectRaw('
                AVG(rides.duration_s) AS avg_duration_s,
                AVG(rides.distance_m) AS avg_distance_m
            ')
            ->first();

        // Listado (joins + filtro de estado)
        $qList = (clone $qBase)
            ->leftJoin('drivers as d', 'd.id', '=', 'rides.driver_id')
            ->leftJoin('vehicles as v', 'v.id', '=', 'rides.vehicle_id')
            ->when($status, fn ($qq) => $qq->where('rides.status', $status));

        $rides = $qList->orderByDesc('rides.requested_at')
            ->paginate(20, [
                'rides.id','rides.status','rides.requested_at','rides.scheduled_for',
                'rides.origin_label','rides.dest_label',
                'rides.origin_lat','rides.origin_lng','rides.dest_lat','rides.dest_lng',
                'rides.duration_s','rides.distance_m',
                'rides.quoted_amount','rides.total_amount','rides.currency',
                'rides.passenger_name','rides.passenger_phone','rides.requested_channel',
                DB::raw('d.name as driver_name'),
                DB::raw('d.phone as driver_phone'),
                DB::raw('v.economico as vehicle_economico'),
                DB::raw('v.plate as vehicle_plate'),
            ])
            ->withQueryString();

        // Totales seguros
        $sumQuote     = (float)($quoted->sum_quote ?? 0);
        $avgQuote     = (float)($quoted->avg_quote ?? 0);
        $sumCollected = (float)($collected->sum_collected ?? 0);
        $avgCollected = (float)($collected->avg_collected ?? 0);
        $finCount     = (int)($collected->finished_count ?? 0);
        $colCount     = (int)($collected->collected_count ?? 0);

        return view('admin.reports.rides.index', [
            'from'   => $from,
            'to'     => $to,
            'status' => $status,
            'totals' => [
                'total'            => $total,
                'finished'         => $finishedN,
                'canceled'         => $canceledN,
                'cancel_rate'      => $total ? round(($canceledN / $total) * 100, 1) : 0.0,
                'avg_duration_s'   => (int)($ops->avg_duration_s ?? 0),
                'avg_distance_m'   => (float)($ops->avg_distance_m ?? 0),
                'sum_quote'        => $sumQuote,
                'avg_quote'        => $avgQuote,
                'sum_collected'    => $sumCollected,
                'avg_collected'    => $avgCollected,
                'collect_rate_pct' => $finCount ? round(100 * $colCount / $finCount, 1) : 0.0,
                'delta_sum'        => $sumCollected - $sumQuote,
            ],
            'rides'  => $rides,
        ]);
    }

    public function show(Request $request, Ride $ride)
    {
        $tenantId = auth()->user()->tenant_id ?? null;
        if (!$tenantId) {
            abort(403, 'Usuario sin tenant asignado');
        }

        // 🔒 No ver viajes de otro tenant
        if ((int)$ride->tenant_id !== (int)$tenantId) {
            abort(404);
        }

        $history = $ride->statusHistory()->get();

        $start = $ride->onboard_at ?? $ride->accepted_at ?? $ride->requested_at;
        $end   = $ride->finished_at ?? $ride->canceled_at ?? $ride->requested_at;

        $breadcrumbs    = collect();
        $crumbsToPickup = collect();
        $crumbsOnTrip   = collect();
        $viaWave        = false;

        $driver = $ride->driver_id
            ? Driver::select('id','name','phone','email')->find($ride->driver_id)
            : null;

        $vehicle = $ride->vehicle_id
            ? Vehicle::select('id','economico','plate','brand','model','color','year')->find($ride->vehicle_id)
            : null;

        if ($ride->driver_id && $start && $end) {
            $breadcrumbs = DriverLocation::query()
                ->where('driver_id', $ride->driver_id)
                // si driver_locations tiene tenant_id, descomenta:
                // ->where('tenant_id', $tenantId)
                ->whereBetween('reported_at', [$start, $end])
                ->orderBy('reported_at')
                ->limit(2000)
                ->get(['lat','lng','reported_at']);

            $acceptedEvent = $ride->statusHistory()
                ->where('new_status', 'accepted')
                ->orderBy('id')
                ->first();

            $acceptedAt = optional($acceptedEvent)->created_at;
            $arrivedAt  = $ride->arrived_at;
            $onboardAt  = $ride->onboard_at;
            $finishedAt = $ride->finished_at ?? $ride->canceled_at;

            if ($acceptedAt && $arrivedAt) {
                $crumbsToPickup = $breadcrumbs
                    ->whereBetween('reported_at', [$acceptedAt, $arrivedAt])
                    ->values();
            }

            if ($onboardAt && $finishedAt) {
                $crumbsOnTrip = $breadcrumbs
                    ->whereBetween('reported_at', [$onboardAt, $finishedAt])
                    ->values();
            }

            $meta = $acceptedEvent?->meta;
            if (is_string($meta)) {
                $meta = json_decode($meta, true);
            }
            $meta = is_array($meta) ? $meta : [];

            $viaWave = !empty($meta['offer_id'] ?? ($meta['offer']['id'] ?? null));

            $fallbackVehicle = [
                'economico' => $meta['economico'] ?? null,
                'plate'     => $meta['plate'] ?? null,
            ];
        } else {
            $fallbackVehicle = ['economico' => null, 'plate' => null];
        }

        return view('admin.reports.rides.show', [
            'ride'           => $ride,
            'history'        => $history,
            'breadcrumbs'    => $breadcrumbs,
            'timeWindow'     => ['start' => $start, 'end' => $end],
            'crumbsToPickup' => $crumbsToPickup->map(fn ($b) => [$b->lat, $b->lng])->values(),
            'crumbsOnTrip'   => $crumbsOnTrip->map(fn ($b) => [$b->lat, $b->lng])->values(),
            'viaWave'        => $viaWave,
            'driver'         => $driver,
            'vehicle'        => $vehicle,
            'fallbackVehicle'=> $fallbackVehicle,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $from   = $request->input('from', now()->subDays(7)->format('Y-m-d'));
        $to     = $request->input('to',   now()->format('Y-m-d'));
        $status = $request->input('status');

        $tenantId = auth()->user()->tenant_id ?? null;
        if (!$tenantId) {
            abort(403, 'Usuario sin tenant asignado');
        }

        $q = Ride::query()
            ->where('rides.tenant_id', $tenantId)
            ->when($status, fn ($qq) => $qq->where('rides.status', $status))
            ->whereDate('rides.requested_at', '>=', $from)
            ->whereDate('rides.requested_at', '<=', $to)
            ->orderByDesc('rides.requested_at');

        $filename = "rides_{$from}_{$to}" . ($status ? "_{$status}" : "") . ".csv";

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($q) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'id','status','requested_at','accepted_at','arrived_at','onboard_at','finished_at','canceled_at',
                'origin_label','dest_label','distance_m','duration_s','quoted_amount','total_amount','currency',
                'canceled_by','cancel_reason',
            ]);

            $q->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->id,
                        $r->status,
                        $r->requested_at,
                        $r->accepted_at,
                        $r->arrived_at,
                        $r->onboard_at,
                        $r->finished_at,
                        $r->canceled_at,
                        $r->origin_label,
                        $r->dest_label,
                        $r->distance_m,
                        $r->duration_s,
                        $r->quoted_amount,
                        $r->total_amount,
                        $r->currency,
                        $r->canceled_by,
                        $r->cancel_reason,
                    ]);
                }
            });

            fclose($out);
        }, 200, $headers);
    }
}
