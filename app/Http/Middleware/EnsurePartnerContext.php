<?php

namespace App\Http\Middleware;

use App\Models\Partner;
use App\Models\PartnerUser;
use Closure;
use Illuminate\Http\Request;
use App\Services\PartnerWalletService;

class EnsurePartnerContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        // Sysadmin NO necesita portal partner (si quieres permitirlo, lo abrimos después).
        if ((bool)$user->is_sysadmin) {
            return redirect()->route('sysadmin.dashboard');
        }

        if (!$user->tenant_id) {
            abort(403, 'Usuario sin tenant.');
        }

        // 1) partner_id por ruta/query > session > default_partner_id > partner_users(primary)
        $partnerId = $request->route('partner')?->id
            ?? $request->route('partner')
            ?? $request->query('partner_id')
            ?? $request->session()->get('active_partner_id')
            ?? $user->default_partner_id;

        if (!$partnerId) {
            // Si no hay default, intenta tomar el primario (o el primero) de partner_users
            $m = PartnerUser::query()
                ->where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->whereNull('revoked_at')
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->first();

            $partnerId = $m?->partner_id;
        }

        if (!$partnerId) {
            abort(403, 'Sin partner asignado.');
        }

        $partner = Partner::query()
            ->where('id', $partnerId)
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (!$partner) {
            abort(403, 'Partner inválido para este tenant.');
        }

        // 2) Autorización:
        // - Tenant admin: puede entrar a cualquier partner del mismo tenant
        // - Partner user: debe existir partner_users vigente
        $isTenantAdmin = ($user->role?->value ?? (string)$user->role) === 'admin' || (bool)$user->is_admin;

        if (!$isTenantAdmin) {
            $pu = PartnerUser::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('partner_id', $partner->id)
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->first();

            if (!$pu) {
                abort(403, 'No perteneces a este partner.');
            }

            // Si quieres forzar aceptación de invitación:
            // if (!$pu->accepted_at) abort(403, 'Invitación pendiente.');
            $request->attributes->set('partnerUser', $pu);
        }

        PartnerWalletService::ensureWallet((int)$partner->tenant_id, (int)$partner->id, 'MXN');

        // 3) Persistir contexto (para navegación interna)
        $request->session()->put('active_partner_id', $partner->id);
        $request->attributes->set('partner', $partner);
        $request->attributes->set('partner_id', $partner->id);




        return $next($request);
    }
}
