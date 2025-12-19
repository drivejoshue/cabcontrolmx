<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Tenant;

class EnsureTenantOnboarded
{
    public function handle($request, Closure $next)
    {
        $u = $request->user();
        if (!$u) return $next($request);

        // SysAdmin siempre pasa
        if (!empty($u->is_sysadmin)) return $next($request);

        // Solo aplica a admins tenant (is_admin = 1)
        if (empty($u->is_admin)) return $next($request);

        // Si es sysadmin pero también admin, seguir como sysadmin
        // Esto evita que sysadmin con is_admin=1 entre en onboarding

        // Si no tiene tenant_id => mándalo a Mi central (setup)
        if (empty($u->tenant_id)) {
            return redirect()->route('admin.tenant.edit');
        }

        $tenant = Tenant::find($u->tenant_id);
        if (!$tenant) {
            // Si no encuentra tenant, dejar que siga (o redirigir a setup)
            return redirect()->route('admin.tenant.edit');
        }

        // Evitar loop
        if ($request->is('admin/onboarding*') || 
            
            $request->is('admin/logout') ||
            $request->is('logout')) {
            return $next($request);
        }

        if (is_null($tenant->onboarding_done_at)) {
            return redirect()->route('admin.onboarding');
        }

        return $next($request);
    }
}