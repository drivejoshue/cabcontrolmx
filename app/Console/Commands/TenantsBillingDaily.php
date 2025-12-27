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

    protected $description = 'Proceso diario: maneja fin de trial, intenta cobros por wallet y suspende por saldo insuficiente.';

    public function __construct(
        private TenantBillingService $billing,
        private TenantWalletService $wallet
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        /**
         * Fecha de ejecución:
         * - Normal: hoy
         * - Simulada: --date=YYYY-MM-DD
         */
        $now = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::now()->startOfDay();

        /**
         * Tomamos solo tenants:
         * - Modelo por vehículo
         * - En trial, active o paused
         * (commission NO entra aquí)
         */
        $tenants = Tenant::with('billingProfile')
            ->whereHas('billingProfile', function ($q) {
                $q->where('billing_model', 'per_vehicle')
                  ->whereIn('status', ['trial', 'active', 'paused']);
            })
            ->get();

        foreach ($tenants as $tenant) {
            $profile = $tenant->billingProfile;
          $this->line("Tenant {$tenant->id}: start (profile_status={$profile->status})");

            /**
             * 1) TRIAL → POST-TRIAL
             * Si el trial venció:
             * - Genera invoice de activación
             * - Cambia status a paused si no hay saldo
             */
            try {
                $invoice = $this->billing->ensurePostTrialActivationInvoice($tenant, $now);
                if ($invoice) {
                    $this->info("Tenant {$tenant->id}: invoice activación {$invoice->id} ({$invoice->status})");
                }
            } catch (\Throwable $e) {
                // Error no crítico, no detenemos el cron
                $this->warn("Tenant {$tenant->id}: error post-trial ".$e->getMessage());
            }

            /**
             * 2) INTENTAR COBRO AUTOMÁTICO
             * Recorremos invoices pending y tratamos de pagarlas con wallet
             */
            $pendingInvoices = TenantInvoice::where('tenant_id', $tenant->id)
                ->where('status', 'pending')
                ->orderBy('due_date')
                ->get();

            foreach ($pendingInvoices as $invoice) {
                $paid = $this->billing->payInvoiceFromWallet($invoice, $this->wallet);

                if ($paid) {
                    $this->info("Tenant {$tenant->id}: invoice {$invoice->id} pagada por wallet.");
                } else {
                    $balance = $this->wallet->getBalance($tenant->id);
                    $this->line("Tenant {$tenant->id}: invoice {$invoice->id} sigue pending (balance={$balance}).");
                }
            }

            /**
             * 3) VALIDAR ESTADO DEL TENANT (BLOQUEO)
             * Si después de intentar cobrar:
             * - El saldo no alcanza para cerrar el mes
             * - Se bloquea automáticamente
             */
            $this->info("Tenant {$tenant->id}: recheck billing state");

           $this->billing->recheckTenantBillingState(
    $tenant,
    $this->wallet,
    $now
);

        }

        return Command::SUCCESS;
    }
}
