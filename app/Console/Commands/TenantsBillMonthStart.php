<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantBillingService;
use App\Services\TenantWalletService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TenantsBillMonthStart extends Command
{
    protected $signature = 'tenants:bill-month-start {--date= : YYYY-MM-DD para simular}';
    protected $description = 'Genera factura mensual por adelantado (mes completo) y cobra por wallet si hay saldo.';

    public function __construct(
        private TenantBillingService $billing,
        private TenantWalletService $wallet
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $d = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::now()->startOfMonth(); // default día 1

        $tenants = Tenant::with('billingProfile')->whereHas('billingProfile', function ($q) {
            $q->where('billing_model', 'per_vehicle')
              ->where('status', 'active');
        })->get();

        foreach ($tenants as $tenant) {
            try {
                $inv = $this->billing->generateMonthInvoicePrepaid($tenant, $d);
                $this->info("Tenant {$tenant->id}: invoice mes {$inv->id} pending total={$inv->total}");

                $this->billing->payInvoiceFromWallet($inv, $this->wallet);
            } catch (\Throwable $e) {
                $this->error("Tenant {$tenant->id}: ".$e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
