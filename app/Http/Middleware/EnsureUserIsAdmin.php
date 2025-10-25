<?php  
// app/Http/Middleware/EnsureUserIsAdmin.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->can('admin')) {
            abort(403, 'Solo administradores.');
        }
        return $next($request);
    }
}
