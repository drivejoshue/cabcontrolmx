<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $u = $request->user();

        // Bloqueo simple por flags si existen
        if (property_exists($u, 'active') && (int)($u->active ?? 1) === 0) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'Usuario desactivado. Contacta a tu administrador.'])
                ->onlyInput('email');
        }

        $isSysadmin   = !empty($u?->is_sysadmin);
        $isAdmin      = !empty($u?->is_admin);
        $isDispatcher = !empty($u?->is_dispatcher);

        $hasTenant = !empty($u?->tenant_id);

        // ✅ Partner: requiere default_partner_id + pertenencia vigente en partner_users
        $defaultPartnerId = (int)($u->default_partner_id ?? 0);

        $isPartnerMember = false;
        if ($hasTenant && $defaultPartnerId > 0) {
            $isPartnerMember = DB::table('partner_users')
                ->where('tenant_id', $u->tenant_id)
                ->where('partner_id', $defaultPartnerId)
                ->where('user_id', $u->id)
                ->whereNull('revoked_at')
                ->whereNotNull('accepted_at')
                ->exists();
        }

        // ✅ Regla de acceso WEB:
        // - sysadmin: ok (puede no tener tenant)
        // - admin/dispatcher: requieren tenant_id
        // - partner: requiere tenant_id + membership vigente
        $allowed =
            $isSysadmin ||
            (($isAdmin || $isDispatcher) && $hasTenant) ||
            $isPartnerMember;

        if (!$allowed) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'Este acceso es solo para personal autorizado (Admin/Dispatcher/SysAdmin/Partner).'])
                ->onlyInput('email');
        }

        // ✅ Contexto partner en sesión (para EnsurePartnerContext y scoping)
        if ($isPartnerMember) {
            $request->session()->put('partner_id', $defaultPartnerId);
        } else {
            $request->session()->forget('partner_id');
        }

        $request->session()->forget(['sysadmin_mfa_ok_at', 'sysadmin_mfa_ok_level']);

        // Opcional: si es sysadmin, forzar que lo primero sea stepup (si su home cae en /sysadmin)
        if ($isSysadmin) {
            // si quieres obligarlo SIEMPRE:
             return redirect()->route('sysadmin.stepup.show');
        }


        // ✅ Redirect único y consistente (NO uses route('dashboard') fijo)
        return redirect()->intended($u->preferredWebHomePath());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
