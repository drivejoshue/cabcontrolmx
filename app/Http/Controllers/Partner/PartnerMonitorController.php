<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Tenant;

class PartnerMonitorController extends Controller
{
    public function index()
    {
        $partnerId = (int) session('partner_id');
        abort_unless($partnerId > 0, 403, 'Sin partner en sesión');

        $partner = Partner::query()
            ->select('id','tenant_id','name')
            ->findOrFail($partnerId);

        $tenant = Tenant::query()
            ->select('id','name','latitud','longitud','coverage_radius_km')
            ->findOrFail((int)$partner->tenant_id);

        $ccTenant = [
            'id'   => (int)$tenant->id,
            'name' => (string)$tenant->name,
            'map'  => [
                'lat'       => $tenant->latitud !== null ? (float)$tenant->latitud : 19.4326,
                'lng'       => $tenant->longitud !== null ? (float)$tenant->longitud : -99.1332,
                'zoom'      =>  14,
                'radius_km' => $tenant->coverage_radius_km ? (float)$tenant->coverage_radius_km : 8,
            ],
        ];

        return view('partner.monitor.index', compact('tenant','partner','ccTenant'));
    }
}
