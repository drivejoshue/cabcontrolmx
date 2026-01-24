<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Support\PartnerScope;
use Illuminate\Support\Facades\DB;

class PartnerRideController extends Controller
{
    public function show(int $rideId)
    {
        $scope = PartnerScope::current();

        $ride = DB::table('rides as r')
            ->leftJoin('drivers as d', function ($j) use ($scope) {
                $j->on('d.id','=','r.driver_id')->where('d.tenant_id','=',$scope->tenantId);
            })
            ->leftJoin('vehicles as v', function ($j) use ($scope) {
                $j->on('v.id','=','r.vehicle_id')->where('v.tenant_id','=',$scope->tenantId);
            })
            ->where('r.tenant_id', $scope->tenantId)
            ->where('r.id', $rideId)
            ->where(function ($w) use ($scope) {
                $w->where('d.partner_id', $scope->partnerId)
                  ->orWhere('v.partner_id', $scope->partnerId);
            })
            ->select([
                'r.*',
                'd.name as driver_name','d.phone as driver_phone',
                'v.economico as vehicle_economico','v.plate as vehicle_plate','v.type as vehicle_type',
            ])
            ->firstOrFail();

        $offers = DB::table('ride_offers')
            ->where('tenant_id', $scope->tenantId)
            ->where('ride_id', $rideId)
            ->orderBy('id')
            ->get();

        $issues = DB::table('ride_issues')
            ->where('tenant_id', $scope->tenantId)
            ->where('ride_id', $rideId)
            ->orderByDesc('id')
            ->get();

        return view('partner.rides.show', [
            'partner' => $scope->partner,
            'ride' => $ride,
            'offers' => $offers,
            'issues' => $issues,
        ]);
    }
}
