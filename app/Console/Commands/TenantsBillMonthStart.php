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

    protected $description = 'Día 1: genera factura mensual adelantada y la intenta cobrar por wallet.';

    public function __construct(
        private TenantBillingService $billing,
        private TenantWalletService $wallet
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        /**
         * Fecha base:
         * - Normal: primer día del mes actual
         * - Simulada: --date
         */
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::now()->startOfMonth();

        /**
         * Solo tenants:
         * - Modelo por vehículo
         * - Activos (los paused se reactivan solo al recargar)
         */
        $tenants = Tenant::with('billingProfile')
            ->whereHas('billingProfile', function ($q) {
                $q->where('billing_model', 'per_vehicle')
                  ->where('status', 'active');
            })
            ->get();

        foreach ($tenants as $tenant) {
            try {
                /**
                 * 1) Generar factura mensual (prepaid)
                 */
                $invoice = $this->billing->generateMonthInvoicePrepaid($tenant, $date);

                $this->info(
                    "Tenant {$tenant->id}: invoice mensual {$invoice->id} total={$invoice->total}"
                );

                /**
                 * 2) Intentar cobrar inmediatamente del wallet
                 */
                $this->billing->payInvoiceFromWallet($invoice, $this->wallet);

                /**
                 * 3) Validar estado del tenant
                 * (si no alcanzó saldo, se bloquea)
                 */
                $this->billing->recheckTenantBillingState($tenant->id);

            } catch (\Throwable $e) {
                $this->error("Tenant {$tenant->id}: ".$e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
