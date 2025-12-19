<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if (!$u) abort(403, 'No autenticado.');

        // Regla única: admin tenant = isadmin = 1
        if (empty($u->is_admin)) abort(403, 'Solo administradores del tenant.');

        return $next($request);
    }
}
