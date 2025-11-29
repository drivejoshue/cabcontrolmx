<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\Ride;
use App\Models\TenantInvoice;

class DashboardController extends Controller
{
    public function index()
    {
        $tenantsCount   = Tenant::count();
        $driversCount   = Driver::count();
        $vehiclesCount  = Vehicle::count();
        $ridesToday     = Ride::whereDate('created_at', today())->count();

        // Últimos tenants creados
        $recentTenants = Tenant::orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Últimas facturas (si ya tienes TenantInvoice)
        $recentInvoices = TenantInvoice::orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Intentamos localizar al tenant "Orbana Global" (cuando lo registremos)
        $globalTenant = Tenant::where('slug', 'orbana-global')->first();

        return view('sysadmin.dashboard', compact(
            'tenantsCount',
            'driversCount',
            'vehiclesCount',
            'ridesToday',
            'recentTenants',
            'recentInvoices',
            'globalTenant'
        ));
    }
}
