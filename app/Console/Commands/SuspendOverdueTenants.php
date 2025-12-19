<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SuspendOverdueTenants extends Command
{
    protected $signature = 'billing:suspend-overdue {--days=5 : Días de tolerancia después del vencimiento}';
    protected $description = 'Suspende tenants con invoice pending vencida + tolerancia y cierra turnos abiertos.';

    public function handle(): int
    {
        $graceDays = (int)$this->option('days');
        if ($graceDays < 0) $graceDays = 0;

        // =========================================================
        // FASE 1) Encontrar tenants per_vehicle activos con overdue
        // Condición:
        //  - billing_model='per_vehicle'
        //  - status='active'
        //  - existe tenant_invoices.status='pending'
        //  - hoy > due_date + graceDays
        // =========================================================
        $rows = DB::table('tenant_billing_profiles as p')
            ->join('tenant_invoices as i', 'i.tenant_id', '=', 'p.tenant_id')
            ->where('p.billing_model', 'per_vehicle')
            ->where('p.status', 'active')
            ->where('i.status', 'pending')
            ->whereNotNull('i.due_date')
            // i.due_date es DATE; comparamos con CURDATE()
            ->whereRaw('CURDATE() > DATE_ADD(i.due_date, INTERVAL ? DAY)', [$graceDays])
            // tomamos la más reciente por tenant (evitar duplicar trabajo)
            ->select('p.tenant_id', DB::raw('MAX(i.due_date) as max_due_date'))
            ->groupBy('p.tenant_id')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No hay tenants overdue.');
            return self::SUCCESS;
        }

        $this->info('Tenants overdue detectados: '.$rows->count());

        // =========================================================
        // FASE 2) Por cada tenant: pausar perfil + cerrar turnos abiertos
        // =========================================================
        foreach ($rows as $row) {
            $tenantId = (int)$row->tenant_id;

            DB::transaction(function () use ($tenantId) {

                // 2.A) Pausar billing profile
                DB::table('tenant_billing_profiles')
                    ->where('tenant_id', $tenantId)
                    ->where('billing_model', 'per_vehicle')
                    ->where('status', 'active')
                    ->update([
                        'status'            => 'paused',
                        'suspended_at'      => now(),
                        'suspension_reason' => 'payment_overdue',
                        'updated_at'        => now(),
                    ]);

                // 2.B) Cerrar turnos abiertos (bloqueo desde el turno)
                DB::table('driver_shifts')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('ended_at')
                    ->update([
                        'ended_at'   => now(),
                        'status'     => 'cerrado',
                        'notes'      => DB::raw("CONCAT('[AUTO] Cerrado por billing overdue. ', COALESCE(notes,''))"),
                        'updated_at' => now(),
                    ]);
            });

            $this->line("Tenant {$tenantId} => paused + shifts closed");
        }

        // =========================================================
        // FASE 3) Resultado
        // =========================================================
        $this->info('Proceso terminado.');
        return self::SUCCESS;
    }
}
