<?php
// app/Http/Controllers/Admin/DispatchSettingsController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;   // ✅ importa el facade
use App\Services\AutoDispatchService;
use App\Models\DispatchSetting;

class DispatchSettingsController extends Controller
{
   public function show(Request $request): JsonResponse
{
    $tenantId = (int) (
        $request->header('X-Tenant-ID')
        ?? $request->query('tenant_id', 1)
    );

    $s = \App\Services\AutoDispatchService::settings($tenantId);

    return response()->json([
        'auto_dispatch_enabled'           => (bool)  $s->enabled,
        'auto_dispatch_delay_s'           => (int)   $s->delay_s,
        'auto_dispatch_preview_radius_km' => (float) $s->radius_km,
        'auto_dispatch_preview_n'         => (int)   $s->limit_n,
        'offer_expires_sec'               => (int)   $s->expires_s,
        'auto_assign_if_single'           => (bool)  $s->auto_assign_if_single,
        // siempre en singular hacia el front
        'allow_fare_bidding'              => (bool)  $s->allow_fare_bidding,
    ]);
}




    // UI: /admin/dispatch-settings
    public function edit(Request $request)
    {
        $tenantId = (int)($request->header('X-Tenant-ID')
            ?? $request->query('tenant_id', Auth::user()->tenant_id ?? 1));

        $row = DispatchSetting::firstOrCreate(['tenant_id' => $tenantId]);

        return view('admin.dispatch_settings.edit', [
            'row'      => $row,
            'tenantId' => $tenantId,
        ]);
    }
}
