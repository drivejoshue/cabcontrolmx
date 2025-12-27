<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanDispatch
{
  public function handle(Request $request, Closure $next): Response
{
    $u = $request->user();
    if (!$u) abort(403, 'No autenticado.');

    if (!empty($u->is_sysadmin)) abort(403, 'SysAdmin no entra al Dispatch.');

    // Recomendado: Dispatch solo tiene sentido si pertenece a un tenant
    if (empty($u->tenant_id)) abort(403, 'Sin tenant.');

    $isAdmin = !empty($u->is_admin);
    $isDispatcher = !empty($u->is_dispatcher);

    if (!$isAdmin && !$isDispatcher) {
        abort(403, 'No autorizado para Dispatch.');
    }

    return $next($request);
}

}
