<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetTenantFromUser
{
    public function handle(Request $request, Closure $next)
    {
        $tenantId = optional($request->user())->tenant_id;

        app()->instance('currentTenantId', $tenantId); // lo usa el trait

        return $next($request);
    }
}
