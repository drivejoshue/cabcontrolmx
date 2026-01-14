<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OrbanaCoreOnly
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        if (!$u) abort(403);

        // Solo tenant 100
        if ((int)($u->tenant_id ?? 0) !== 100) {
            abort(403, 'Solo Orbana Core');
        }

       

        return $next($request);
    }
}
