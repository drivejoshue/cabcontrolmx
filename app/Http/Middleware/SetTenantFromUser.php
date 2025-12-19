<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetTenantFromUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Por defecto, sin tenant (rutas públicas/guest)
        $tenantId = $user?->tenant_id;

        // Si quieres, evita “ensuciar” el contenedor con null
        // pero en general es útil que siempre exista la key.
        app()->instance('currentTenantId', $tenantId ?: null);

        return $next($request);
    }
}
