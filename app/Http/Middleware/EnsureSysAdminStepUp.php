<?php
// app/Http/Middleware/EnsureSysAdminStepUp.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSysAdminStepUp
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();

        if (!$u) abort(403, 'No autenticado.');
        if (empty($u->is_sysadmin)) abort(403, 'Solo SysAdmin.');

        $okAt = $request->session()->get('sysadmin_mfa_ok_at');
        if (!$okAt) {
            return redirect()->route(config('security.sysadmin_stepup_route'));
        }

        $ttl = (int) config('security.sysadmin_stepup_ttl', 900);
        $age = now()->diffInSeconds(\Illuminate\Support\Carbon::parse($okAt));

        if ($age > $ttl) {
            $request->session()->forget(['sysadmin_mfa_ok_at', 'sysadmin_mfa_ok_level']);
            return redirect()->route(config('security.sysadmin_stepup_route'));
        }

        return $next($request);
    }
}
