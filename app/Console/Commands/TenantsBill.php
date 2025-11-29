<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantBillingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TenantsBill extends Command
{
    /**
     * Nombre del comando.
     */
    protected $signature = 'tenants:bill {--date= : Fecha de corte (YYYY-MM-DD), por defecto hoy}';

    protected $description = 'Genera facturas mensuales para tenants con billing per_vehicle.';

    protected TenantBillingService $billingService;

    public function __construct(TenantBillingService $billingService)
    {
        parent::__construct();
        $this->billingService = $billingService;
    }

    public function handle(): int
    {
        $dateOption = $this->option('date');
        $cutoffDate = $dateOption
            ? Carbon::parse($dateOption)->startOfDay()
            : Carbon::today();

        $this->info("Generando facturas para fecha de corte: " . $cutoffDate->toDateString());

        $tenants = Tenant::with('billingProfile')
            ->whereHas('billingProfile', function ($q) {
                $q->whereIn('status', ['trial', 'active'])
                  ->where('billing_model', 'per_vehicle');
            })
            ->get();

        if ($tenants->isEmpty()) {
            $this->info('No hay tenants con billing per_vehicle configurado.');
            return Command::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $profile = $tenant->billingProfile;

            // Sólo facturamos en el día de corte del tenant
            $invoiceDay = $profile->invoice_day ?: 1;
            if ($cutoffDate->day !== (int) $invoiceDay) {
                $this->line("Tenant {$tenant->id} ({$tenant->name}) - hoy no es su día de corte ({$invoiceDay}).");
                continue;
            }

            try {
                $invoice = $this->billingService->generateMonthlyInvoice($tenant, $cutoffDate);

                $this->info(sprintf(
                    "Factura generada/recuperada para Tenant %d (%s): periodo %s a %s, total %.2f, vehículos %d",
                    $tenant->id,
                    $tenant->name,
                    $invoice->period_start->toDateString(),
                    $invoice->period_end->toDateString(),
                    $invoice->total,
                    $invoice->vehicles_count
                ));
            } catch (\Throwable $e) {
                $this->error("Error generando factura para Tenant {$tenant->id}: " . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
