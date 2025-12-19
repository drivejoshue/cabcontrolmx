<?php 

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTenantReady
{
    public function handle(Request $request, Closure $next)
{
    $user = $request->user();
    if (!$user) return redirect()->route('login');

    // Si ya estamos en pending, no re-redirigir a pending
    if ($request->routeIs('public.pending-tenant')) {
        return $next($request);
    }

    if (method_exists($user, 'hasVerifiedEmail') && !$user->hasVerifiedEmail()) {
        return redirect()->route('verification.notice');
    }

    if ((int)($user->tenant_id ?? 0) > 0) {
        $tenant = \App\Models\Tenant::find($user->tenant_id);
        if ($tenant && (int)$tenant->public_active !== 1) {
            return redirect()->route('public.pending-tenant');
        }
    }

    return $next($request);
}

}
