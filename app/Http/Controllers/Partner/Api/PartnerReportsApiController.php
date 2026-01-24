<?php

namespace App\Http\Controllers\Partner\Api;

use App\Http\Controllers\Controller;
use App\Support\PartnerScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartnerReportsApiController extends Controller
{
    public function ridesSummary(Request $request)
    {
        $scope = PartnerScope::current();
        $tz = DB::table('tenants')->where('id', $scope->tenantId)->value('timezone') ?: 'America/Mexico_City';

        $range = $request->string('range')->toString();
        if (!in_array($range, ['today','7d','30d','custom'], true)) $range = '7d';

        [$from, $to] = $this->resolveRange($range, $tz, $request->input('from'), $request->input('to'));

        $status = strtolower((string)$request->input('status', ''));
        $driverId = (int) $request->input('driver_id', 0);
        $vehicleId = (int) $request->input('vehicle_id', 0);
        $payment = strtolower((string)$request->input('payment_method', ''));
        $channel = strtolower((string)$request->input('requested_channel', ''));
        $qtext = trim((string)$request->input('q', ''));

        $base = DB::table('rides as r')
            ->leftJoin('drivers as d', function ($j) use ($scope) {
                $j->on('d.id','=','r.driver_id')->where('d.tenant_id','=',$scope->tenantId);
            })
            ->leftJoin('vehicles as v', function ($j) use ($scope) {
                $j->on('v.id','=','r.vehicle_id')->where('v.tenant_id','=',$scope->tenantId);
            })
            ->where('r.tenant_id', $scope->tenantId)
            ->whereBetween('r.requested_at', [$from, $to])
            ->where(function ($w) use ($scope) {
                $w->where('d.partner_id', $scope->partnerId)
                  ->orWhere('v.partner_id', $scope->partnerId);
            });

        if ($status !== '') $base->whereRaw('LOWER(r.status) = ?', [$status]);
        if ($driverId > 0) $base->where('r.driver_id', $driverId);
        if ($vehicleId > 0) $base->where('r.vehicle_id', $vehicleId);
        if ($payment !== '') $base->whereRaw('LOWER(r.payment_method) = ?', [$payment]);
        if ($channel !== '') $base->whereRaw('LOWER(r.requested_channel) = ?', [$channel]);
        if ($qtext !== '') {
            $like = '%' . str_replace(['%','_'], ['\%','\_'], $qtext) . '%';
            $base->where(function ($w) use ($like) {
                $w->where('r.passenger_name','like',$like)
                  ->orWhere('r.passenger_phone','like',$like)
                  ->orWhere('r.origin_label','like',$like)
                  ->orWhere('r.dest_label','like',$like)
                  ->orWhere('d.name','like',$like)
                  ->orWhere('v.economico','like',$like)
                  ->orWhere('v.plate','like',$like);
            });
        }

        $by = (clone $base)->selectRaw('LOWER(r.status) st, COUNT(*) c')->groupBy('st')->pluck('c','st')->toArray();
        $total = array_sum(array_map('intval',$by));
        $canceled = (int)(($by['canceled'] ?? 0) + ($by['cancelled'] ?? 0));
        $finished = (int)(($by['finished'] ?? 0) + ($by['completed'] ?? 0));

        $activeStates = ['accepted','arrived','onboard','on_ride','started','enroute','pending','requested'];
        $active = 0; foreach ($activeStates as $st) $active += (int)($by[$st] ?? 0);

        $revenue = (float)(clone $base)->whereRaw('LOWER(r.status) IN ("finished","completed")')->sum('r.total_amount');

        return response()->json([
            'ok' => true,
            'data' => compact('total','active','finished','canceled','revenue','by'),
        ]);
    }

    private function resolveRange(string $range, string $tz, ?string $fromIn, ?string $toIn): array
    {
        $now = now($tz);

        if ($range === 'today') return [$now->copy()->startOfDay()->format('Y-m-d H:i:s'), $now->copy()->endOfDay()->format('Y-m-d H:i:s')];
        if ($range === '30d') return [$now->copy()->subDays(29)->startOfDay()->format('Y-m-d H:i:s'), $now->copy()->endOfDay()->format('Y-m-d H:i:s')];
        if ($range === '7d') return [$now->copy()->subDays(6)->startOfDay()->format('Y-m-d H:i:s'), $now->copy()->endOfDay()->format('Y-m-d H:i:s')];

        $from = $fromIn ? date('Y-m-d H:i:s', strtotime($fromIn)) : $now->copy()->subDays(6)->startOfDay()->format('Y-m-d H:i:s');
        $to   = $toIn ? date('Y-m-d H:i:s', strtotime($toIn)) : $now->copy()->endOfDay()->format('Y-m-d H:i:s');
        return [$from, $to];
    }
}
