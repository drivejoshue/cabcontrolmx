<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Services\PartnerPrepaidBillingService;

class PartnerPrepaidDailyCharge extends Command
{
    protected $signature = 'orbanamx:partner-prepaid-daily {--tenant=}';
    protected $description = 'Cobra diario en partner_mode (debita partner_wallet y liquida tenant_wallet).';

    public function handle(): int
    {
        $only = $this->option('tenant') ? (int)$this->option('tenant') : null;

        $q = Tenant::query()->where('partner_billing_wallet', 'partner_wallet');
        if ($only) $q->where('id', $only);

        $svc = app(PartnerPrepaidBillingService::class);

        foreach ($q->get(['id']) as $t) {
            $out = $svc->chargeOneDayForTenant((int)$t->id);
            $this->info("tenant {$t->id}: ".json_encode($out));
        }

        return 0;
    }
}