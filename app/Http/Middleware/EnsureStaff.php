<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureStaff
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();

        if (!$u) abort(401);

        $isStaff = !empty($u->is_admin) || !empty($u->is_dispatcher) || !empty($u->is_sysadmin);

        if (!$isStaff) abort(403);

        return $next($request);
    }
}
