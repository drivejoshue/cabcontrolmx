<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSysAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();

        if (!$u) abort(403, 'No autenticado.');
        if (empty($u->is_sysadmin)) abort(403, 'Solo SysAdmin.');

        return $next($request);
    }
}
