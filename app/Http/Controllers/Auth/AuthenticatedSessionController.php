<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        // Endurecimiento: este login WEB es solo para staff (Admin/Dispatcher/SysAdmin).
        $u = $request->user();

        $isStaff = !empty($u?->is_sysadmin) || !empty($u?->is_admin) || !empty($u?->is_dispatcher);

        // Opcional pero recomendado: staff debe pertenecer a tenant (excepto sysadmin).
        $hasTenant = !empty($u?->tenant_id);

        if (!$isStaff || (empty($u?->is_sysadmin) && !$hasTenant)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors([
                    'email' => 'Este acceso es solo para personal (Admin / Dispatcher).',
                ])
                ->onlyInput('email');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Que no mande a landing; se queda en login
        return redirect()->route('login');
    }
}
