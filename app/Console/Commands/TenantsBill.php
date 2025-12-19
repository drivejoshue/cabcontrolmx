<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantBillingService;
use App\Services\TenantWalletService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TenantsBill extends Command
{
    protected $signature = 'tenants:bill {--date= : Fecha de referencia (YYYY-MM-DD), default hoy}';
    protected $description = 'Wallet billing: trial->activación prorrateada, mes-prepago día 1, suspensión por overdue.';

    public function __construct(
        protected TenantBillingService $billing,
        protected TenantWalletService $wallet,
    ) { parent::__construct(); }

    public function handle(): int
    {
        $ref = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::today();

        $this->info("tenants:bill ref={$ref->toDateString()}");

        $tenants = Tenant::with('billingProfile')->whereHas('billingProfile')->get();

        foreach ($tenants as $t) {
            $p = $t->billingProfile;
            if (!$p) continue;

            // 0) asegurar wallet row
            $this->wallet->ensureWallet($t->id);

            // Solo per_vehicle (commission lo ignoramos aquí)
            if ($p->billing_model !== 'per_vehicle') continue;

            // A) Trial expirado -> invoice activación + suspensión
            try {
                $inv = $this->billing->ensureActivationInvoiceIfNeeded($t, $ref);
                if ($inv) {
                    $this->line("Tenant {$t->id} activation-invoice #{$inv->id} status={$inv->status} total={$inv->total}");
                }
            } catch (\Throwable $e) {
                $this->error("Tenant {$t->id} activation error: ".$e->getMessage());
            }

            // B) Día 1 -> invoice mensual adelantada + intento de cobro
            if ($ref->day === 1 && strtolower((string)$p->status) === 'active') {
                try {
                    $inv = $this->billing->generateMonthlyInvoiceEomPrepaid($t, $ref);

                    // Intento de cobro inmediato por wallet
                    if ($inv->status === 'pending') {
                        $ok = $this->wallet->debitIfEnough($t->id, (float)$inv->total, 'invoice', (int)$inv->id, [
                            'kind' => 'monthly_prepaid',
                            'period_start' => $inv->period_start,
                            'period_end' => $inv->period_end,
                        ]);

                        if ($ok) {
                            DB::table('tenant_invoices')->where('id', $inv->id)->update([
                                'status' => 'paid',
                                'paid_at' => now(),
                                'updated_at' => now(),
                            ]);
                            $this->info("Tenant {$t->id} invoice #{$inv->id} PAID via wallet");
                        } else {
                            $this->line("Tenant {$t->id} invoice #{$inv->id} pending (no funds)");
                        }
                    }
                } catch (\Throwable $e) {
                    $this->error("Tenant {$t->id} month-prepaid error: ".$e->getMessage());
                }
            }

            // C) Overdue -> suspender
            $overdue = DB::table('tenant_invoices')
                ->where('tenant_id', $t->id)
                ->where('status', 'pending')
                ->whereDate('due_date', '<', $ref->toDateString())
                ->orderBy('due_date')
                ->first();

            if ($overdue) {
                DB::table('tenant_billing_profiles')->where('id', $p->id)->update([
                    'status' => 'paused',
                    'suspended_at' => now(),
                    'suspension_reason' => 'payment_overdue',
                    'updated_at' => now(),
                ]);
                $this->warn("Tenant {$t->id} suspended: overdue invoice #{$overdue->id}");
            }
        }

        return Command::SUCCESS;
    }
}
