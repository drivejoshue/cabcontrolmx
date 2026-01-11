<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SysRidesGenerationReportController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->input('from', now()->subDays(7)->format('Y-m-d'));
        $to   = $request->input('to',   now()->format('Y-m-d'));

        $tenantId         = $request->input('tenant_id');          // opcional
        $status           = $request->input('status');              // opcional
        $requestedChannel = $request->input('requested_channel');   // opcional
        $scheduled        = $request->input('scheduled');           // '', '1', '0'
        $standOnly        = $request->input('stand_only');          // '', '1', '0'

        $fromDt = Carbon::parse($from)->startOfDay();
        $toDt   = Carbon::parse($to)->endOfDay();

        // ========= Base =========
        $base = DB::table('rides as r')
            ->whereBetween('r.requested_at', [$fromDt, $toDt]);

        if ($tenantId)         $base->where('r.tenant_id', (int)$tenantId);
        if ($status)           $base->where('r.status', $status);
        if ($requestedChannel) $base->where('r.requested_channel', $requestedChannel);

        if ($scheduled === '1') $base->whereNotNull('r.scheduled_for');
        if ($scheduled === '0') $base->whereNull('r.scheduled_for');

        if ($standOnly === '1') $base->whereNotNull('r.stand_id');
        if ($standOnly === '0') $base->whereNull('r.stand_id');

        // ========= KPIs =========
        $total     = (clone $base)->count();
        $finishedN = (clone $base)->where('r.status', 'finished')->count();
        $canceledN = (clone $base)->where('r.status', 'canceled')->count();

        $money = (clone $base)
            ->where('r.status', 'finished')
            ->selectRaw("
                COALESCE(SUM(r.quoted_amount),0) AS sum_quote,
                COALESCE(SUM(r.total_amount),0)  AS sum_total,
                AVG(NULLIF(r.total_amount,0))    AS avg_total
            ")
            ->first();

        $ops = (clone $base)
            ->where('r.status', 'finished')
            ->selectRaw("
                AVG(r.duration_s)  AS avg_duration_s,
                AVG(r.distance_m)  AS avg_distance_m
            ")
            ->first();

        $sumQuote = (float)($money->sum_quote ?? 0);
        $sumTotal = (float)($money->sum_total ?? 0);
        $avgTotal = (float)($money->avg_total ?? 0);

        // ========= Serie diaria (GMV) =========
        $seriesDaily = (clone $base)
            ->where('r.status', 'finished')
            ->selectRaw("DATE(r.requested_at) as d, SUM(r.total_amount) as total, COUNT(*) as n")
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        // ========= Breakdown por tenant =========
        $byTenant = (clone $base)
            ->join('tenants as t', 't.id', '=', 'r.tenant_id')
            ->selectRaw("
                r.tenant_id,
                t.name as tenant_name,
                COUNT(*) as rides_n,
                SUM(CASE WHEN r.status='finished' THEN 1 ELSE 0 END) as finished_n,
                SUM(CASE WHEN r.status='canceled' THEN 1 ELSE 0 END) as canceled_n,
                COALESCE(SUM(CASE WHEN r.status='finished' THEN r.total_amount ELSE 0 END),0) as gmv_total
            ")
            ->groupBy('r.tenant_id','t.name')
            ->orderByDesc('gmv_total')
            ->limit(50)
            ->get();

        // ========= Breakdown por channel =========
        $byChannel = (clone $base)
            ->selectRaw("
                COALESCE(NULLIF(r.requested_channel,''),'(none)') as channel,
                COUNT(*) as rides_n,
                SUM(CASE WHEN r.status='finished' THEN 1 ELSE 0 END) as finished_n,
                COALESCE(SUM(CASE WHEN r.status='finished' THEN r.total_amount ELSE 0 END),0) as gmv_total
            ")
            ->groupBy('channel')
            ->orderByDesc('gmv_total')
            ->get();

        // ========= Breakdown por tipo (stand / scheduled / direct) =========
        // Regla: si viene de stand => stand; si no, si tiene scheduled_for => scheduled; si no => direct
        $byType = (clone $base)
            ->selectRaw("
                CASE
                  WHEN r.stand_id IS NOT NULL THEN 'stand'
                  WHEN r.scheduled_for IS NOT NULL THEN 'scheduled'
                  ELSE 'direct'
                END as type,
                COUNT(*) as rides_n,
                SUM(CASE WHEN r.status='finished' THEN 1 ELSE 0 END) as finished_n,
                COALESCE(SUM(CASE WHEN r.status='finished' THEN r.total_amount ELSE 0 END),0) as gmv_total
            ")
            ->groupBy('type')
            ->orderByDesc('gmv_total')
            ->get();

        // ========= Breakdown por stands (top) =========
        $byStand = (clone $base)
            ->whereNotNull('r.stand_id')
            ->join('taxi_stands as s', 's.id', '=', 'r.stand_id')
            ->selectRaw("
                r.stand_id,
                s.nombre as stand_name,
                s.codigo as stand_code,
                COUNT(*) as rides_n,
                SUM(CASE WHEN r.status='finished' THEN 1 ELSE 0 END) as finished_n,
                COALESCE(SUM(CASE WHEN r.status='finished' THEN r.total_amount ELSE 0 END),0) as gmv_total
            ")
            ->groupBy('r.stand_id','s.nombre','s.codigo')
            ->orderByDesc('gmv_total')
            ->limit(50)
            ->get();

        // ========= Selects para filtros =========
        $tenantsForSelect = DB::table('tenants')->orderBy('name')->get(['id','name']);

        $channelsForSelect = DB::table('rides')
            ->selectRaw("DISTINCT COALESCE(NULLIF(requested_channel,''),'(none)') as channel")
            ->orderBy('channel')
            ->pluck('channel');

        // ========= Tabla detalle (paginada) =========
        $rides = (clone $base)
            ->join('tenants as t', 't.id', '=', 'r.tenant_id')
            ->leftJoin('drivers as d', 'd.id', '=', 'r.driver_id')
            ->leftJoin('vehicles as v', 'v.id', '=', 'r.vehicle_id')
            ->leftJoin('taxi_stands as s', 's.id', '=', 'r.stand_id')
            ->select([
                'r.id','r.tenant_id','t.name as tenant_name',
                'r.status','r.requested_at','r.scheduled_for',
                'r.origin_label','r.dest_label',
                'r.distance_m','r.duration_s',
                'r.quoted_amount','r.total_amount','r.currency',
                'r.requested_channel','r.stand_id',
                DB::raw('s.nombre as stand_name'),
                DB::raw('s.codigo as stand_code'),
                DB::raw('d.name as driver_name'),
                DB::raw('v.economico as vehicle_economico'),
                DB::raw('v.plate as vehicle_plate'),
            ])
            ->orderByDesc('r.requested_at')
            ->paginate(30)
            ->withQueryString();

        return view('sysadmin.rides.generation.index', [
            'from' => $from,
            'to'   => $to,

            'filters' => [
                'tenant_id' => $tenantId,
                'status' => $status,
                'requested_channel' => $requestedChannel,
                'scheduled' => $scheduled,
                'stand_only' => $standOnly,
            ],

            'totals' => [
                'total' => $total,
                'finished' => $finishedN,
                'canceled' => $canceledN,
                'cancel_rate' => $total ? round(($canceledN / $total) * 100, 1) : 0.0,
                'sum_quote' => $sumQuote,
                'sum_total' => $sumTotal,
                'avg_total' => $avgTotal,
                'avg_duration_s' => (int)($ops->avg_duration_s ?? 0),
                'avg_distance_m' => (float)($ops->avg_distance_m ?? 0),
                'delta_sum' => $sumTotal - $sumQuote,
            ],

            'seriesDaily' => $seriesDaily,
            'byTenant'    => $byTenant,
            'byChannel'   => $byChannel,
            'byType'      => $byType,
            'byStand'     => $byStand,

            'tenantsForSelect'  => $tenantsForSelect,
            'channelsForSelect' => $channelsForSelect,

            'rides' => $rides,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $from = $request->input('from', now()->subDays(7)->format('Y-m-d'));
        $to   = $request->input('to',   now()->format('Y-m-d'));

        $tenantId         = $request->input('tenant_id');
        $status           = $request->input('status');
        $requestedChannel = $request->input('requested_channel');
        $scheduled        = $request->input('scheduled');
        $standOnly        = $request->input('stand_only');

        $fromDt = Carbon::parse($from)->startOfDay();
        $toDt   = Carbon::parse($to)->endOfDay();

        $q = DB::table('rides as r')
            ->join('tenants as t', 't.id', '=', 'r.tenant_id')
            ->leftJoin('drivers as d', 'd.id', '=', 'r.driver_id')
            ->leftJoin('vehicles as v', 'v.id', '=', 'r.vehicle_id')
            ->leftJoin('taxi_stands as s', 's.id', '=', 'r.stand_id')
            ->whereBetween('r.requested_at', [$fromDt, $toDt])
            ->when($tenantId, fn($qq) => $qq->where('r.tenant_id', (int)$tenantId))
            ->when($status, fn($qq) => $qq->where('r.status', $status))
            ->when($requestedChannel, fn($qq) => $qq->where('r.requested_channel', $requestedChannel))
            ->when($scheduled === '1', fn($qq) => $qq->whereNotNull('r.scheduled_for'))
            ->when($scheduled === '0', fn($qq) => $qq->whereNull('r.scheduled_for'))
            ->when($standOnly === '1', fn($qq) => $qq->whereNotNull('r.stand_id'))
            ->when($standOnly === '0', fn($qq) => $qq->whereNull('r.stand_id'))
            ->orderByDesc('r.requested_at')
            ->select([
                'r.id','r.status','r.requested_at','r.scheduled_for',
                'r.tenant_id','t.name as tenant_name',
                'r.requested_channel','r.stand_id',
                DB::raw('s.nombre as stand_name'),
                DB::raw('s.codigo as stand_code'),
                'r.quoted_amount','r.total_amount','r.currency',
                'r.distance_m','r.duration_s',
                DB::raw('d.name as driver_name'),
                DB::raw('v.economico as vehicle_economico'),
                DB::raw('v.plate as vehicle_plate'),
                'r.origin_label','r.dest_label',
            ]);

        $filename = "sys_generacion_{$from}_{$to}.csv";

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'ride_id','status','requested_at','scheduled_for',
                'tenant_id','tenant_name',
                'requested_channel','type','stand_id','stand_code','stand_name',
                'quoted_amount','total_amount','currency',
                'distance_m','duration_s',
                'driver_name','vehicle_economico','vehicle_plate',
                'origin_label','dest_label',
            ]);

            $q->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    $type = 'direct';
                    if (!empty($r->stand_id)) $type = 'stand';
                    elseif (!empty($r->scheduled_for)) $type = 'scheduled';

                    fputcsv($out, [
                        $r->id,
                        $r->status,
                        $r->requested_at,
                        $r->scheduled_for,
                        $r->tenant_id,
                        $r->tenant_name,
                        $r->requested_channel,
                        $type,
                        $r->stand_id,
                        $r->stand_code,
                        $r->stand_name,
                        $r->quoted_amount,
                        $r->total_amount,
                        $r->currency,
                        $r->distance_m,
                        $r->duration_s,
                        $r->driver_name,
                        $r->vehicle_economico,
                        $r->vehicle_plate,
                        $r->origin_label,
                        $r->dest_label,
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
