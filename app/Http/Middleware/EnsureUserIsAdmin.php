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
        if (!$u) {
            return $this->deny($request, 'No autenticado.');
        }

        // Regla única: admin tenant = isadmin = 1
        if (empty($u->is_admin)) {
            return $this->deny($request, 'Solo administradores del tenant.');
        }

        return $next($request);
    }

    private function deny(Request $request, string $msg): Response
    {
        // broadcasting/auth y XHR esperan JSON. Evita HTML.
        if ($request->expectsJson() || $request->is('broadcasting/auth')) {
            return response()->json(['message' => $msg], 403);
        }
        abort(403, $msg);
    }
}
