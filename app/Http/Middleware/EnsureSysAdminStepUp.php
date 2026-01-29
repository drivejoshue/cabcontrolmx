<?php
// app/Http/Middleware/EnsureSysAdminStepUp.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Carbon;

class EnsureSysAdminStepUp
{
    public function handle(Request $request, Closure $next): Response
    {
        // ✅ Flag global: en local lo pones false (SYSADMIN_TOTP_ENABLED=false)
        if (!config('security.sysadmin_totp_enabled', true)) {
            return $next($request);
        }

        $u = $request->user();

        if (!$u) abort(403, 'No autenticado.');
        if (empty($u->is_sysadmin)) abort(403, 'Solo SysAdmin.');

        $okAt = $request->session()->get('sysadmin_mfa_ok_at');
        if (!$okAt) {
            return redirect()->route(config('security.sysadmin_stepup_route'));
        }

        $ttl = (int) config('security.sysadmin_stepup_ttl', 900);
        $age = now()->diffInSeconds(Carbon::parse($okAt));

        if ($age > $ttl) {
            $request->session()->forget(['sysadmin_mfa_ok_at', 'sysadmin_mfa_ok_level']);
            return redirect()->route(config('security.sysadmin_stepup_route'));
        }

        return $next($request);
    }
}
