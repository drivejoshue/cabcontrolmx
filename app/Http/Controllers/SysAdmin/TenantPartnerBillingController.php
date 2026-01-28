<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\PartnerWalletService;
use Illuminate\Support\Facades\Auth;


class TenantPartnerBillingController extends Controller
{
    private function assertBelongs(Tenant $tenant, Partner $partner): void
    {
        abort_unless((int)$partner->tenant_id === (int)$tenant->id, 404);
    }

    public function show(Request $r, Tenant $tenant, Partner $partner)
    {
        $this->assertBelongs($tenant, $partner);

        $wallet = PartnerWalletService::ensureWallet($tenant->id, $partner->id);

        $movements = DB::table('partner_wallet_movements')
            ->where('tenant_id', $tenant->id)
            ->where('partner_id', $partner->id)
            ->orderByDesc('id')
            ->paginate(50);

        $topups = DB::table('partner_topups')
            ->where('tenant_id', $tenant->id)
            ->where('partner_id', $partner->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('sysadmin.partners.show', [
            'tenant'    => $tenant,
            'partner'   => $partner,
            'wallet'    => $wallet,
            'movements' => $movements,
            'topups'    => $topups,
        ]);
    }





}
