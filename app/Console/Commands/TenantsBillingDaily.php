<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantInvoice;
use App\Services\TenantBillingService;
use App\Services\TenantWalletService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TenantsBillingDaily extends Command
{
    protected $signature = 'tenants:billing-daily {--date= : YYYY-MM-DD para simular fecha}';
    protected $description = 'Orquesta trial->post-trial invoice, auto-cobro por wallet y suspensión.';

    public function __construct(
        private TenantBillingService $billing,
        private TenantWalletService $wallet
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::now();

        $tenants = Tenant::with('billingProfile')->whereHas('billingProfile', function ($q) {
            $q->where('billing_model', 'per_vehicle')
              ->whereIn('status', ['trial','active','paused']);
        })->get();

        foreach ($tenants as $tenant) {
            $p = $tenant->billingProfile;

            // 1) Trial vencido => generar invoice activación y pausar
            try {
                $inv = $this->billing->ensurePostTrialActivationInvoice($tenant, $now);
                if ($inv) $this->info("Tenant {$tenant->id}: activation invoice {$inv->id} ({$inv->status})");
            } catch (\Throwable $e) {
                // No es fatal; solo informativo
            }

            // 2) Auto-cobrar cualquier invoice pending del tenant si alcanza wallet
            $pendings = TenantInvoice::where('tenant_id', $tenant->id)
                ->where('status', 'pending')
                ->orderBy('due_date')
                ->get();

            foreach ($pendings as $invoice) {
                $paid = $this->billing->payInvoiceFromWallet($invoice, $this->wallet);
                if ($paid) {
                    $this->info("Tenant {$tenant->id}: invoice {$invoice->id} pagada por wallet.");
                } else {
                    $bal = $this->wallet->getBalance($tenant->id);
                    $this->line("Tenant {$tenant->id}: invoice {$invoice->id} pendiente. balance={$bal}");
                }
            }
        }

        return Command::SUCCESS;
    }
}
