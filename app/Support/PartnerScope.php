<?php

namespace App\Support;

use App\Models\Partner;
use Illuminate\Support\Facades\Auth;

class PartnerScope
{
    public int $tenantId;
    public int $partnerId;
    public ?Partner $partner = null;

    public static function current(): self
    {
        $u = Auth::user();
        if (!$u) abort(401);

        $tenantId = (int)($u->tenant_id ?? 0);
        if ($tenantId <= 0) abort(403, 'Tenant inválido');

        $partnerId = (int) session('partner_id');
        if ($partnerId <= 0) abort(403, 'Partner no seleccionado');

        $s = new self();
        $s->tenantId = $tenantId;
        $s->partnerId = $partnerId;

        // Cargar partner (asegura que es del tenant)
        $s->partner = Partner::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $partnerId)
            ->firstOrFail();

        return $s;
    }
}
