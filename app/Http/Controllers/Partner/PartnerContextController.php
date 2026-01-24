<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerUser;
use Illuminate\Http\Request;

class PartnerContextController extends Controller
{
    public function switch(Request $request)
    {
        $user = $request->user();
        $tenantId = (int)$user->tenant_id;

        $partnerId = (int)$request->input('partner_id');
        if (!$partnerId) abort(422, 'partner_id requerido');

        $partner = Partner::where('id', $partnerId)->where('tenant_id', $tenantId)->firstOrFail();

        $isTenantAdmin = (($user->role?->value ?? (string)$user->role) === 'admin') || (bool)$user->is_admin;

        if (!$isTenantAdmin) {
            $ok = PartnerUser::where('tenant_id', $tenantId)
                ->where('partner_id', $partnerId)
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->exists();

            if (!$ok) abort(403);
        }

        $request->session()->put('active_partner_id', $partnerId);

        return redirect()->route('partner.dashboard');
    }
}
