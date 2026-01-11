<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TenantConsoleController extends Controller
{
    public function recheck(Tenant $tenant)
    {
        $profile = $tenant->billingProfile;

        if (!$profile) {
            return back()->withErrors(['billing' => 'Este tenant no tiene billing_profile.']);
        }

        $st = strtolower((string)$profile->status);
        $bm = strtolower((string)$profile->billing_model);

        // Última factura pending
        $inv = DB::table('tenant_invoices')
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->first();

        $msg = "Modelo={$bm}; status={$st}.";
        if ($inv) {
            $msg .= " Última invoice: #{$inv->id} status={$inv->status} due_date={$inv->due_date} total={$inv->total} {$inv->currency}.";
        } else {
            $msg .= " Sin invoices registradas.";
        }

        Log::info('SYSADMIN_TENANT_RECHECK', ['tenant_id' => $tenant->id, 'msg' => $msg]);

        return back()->with('ok', 'Recheck OK: '.$msg);
    }

    public function pause(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'reason' => ['nullable','string','max:120'],
            'close_open_shifts' => ['nullable','boolean'],
        ]);

        $reason = trim((string)($data['reason'] ?? 'manual_pause'));
        $close  = !empty($data['close_open_shifts']);

        DB::transaction(function () use ($tenant, $reason, $close) {

            // billing profile => paused
            $updates = ['status' => 'paused', 'updated_at' => now()];

            if (Schema::hasColumn('tenant_billing_profiles', 'suspended_at')) {
                $updates['suspended_at'] = now();
            }
            if (Schema::hasColumn('tenant_billing_profiles', 'suspension_reason')) {
                $updates['suspension_reason'] = $reason ?: 'manual_pause';
            }

            DB::table('tenant_billing_profiles')
                ->where('tenant_id', $tenant->id)
                ->update($updates);

            if ($close) {
                $this->closeOpenShiftsInternal($tenant->id, 'Cerrado por sysadmin (pause).');
            }
        });

        Log::warning('SYSADMIN_TENANT_PAUSED', [
            'tenant_id' => $tenant->id,
            'reason' => $reason,
            'close_open_shifts' => $close,
        ]);

        return back()->with('ok', 'Tenant pausado manualmente.');
    }

    public function activate(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'note' => ['nullable','string','max:160'],
            'clear_overdue_invoices' => ['nullable','boolean'],
        ]);

        $note  = trim((string)($data['note'] ?? ''));
        $clear = !empty($data['clear_overdue_invoices']);

        DB::transaction(function () use ($tenant, $note, $clear) {

            // profile => active
            $updates = ['status' => 'active', 'updated_at' => now()];

            if (Schema::hasColumn('tenant_billing_profiles', 'suspended_at')) {
                $updates['suspended_at'] = null;
            }
            if (Schema::hasColumn('tenant_billing_profiles', 'suspension_reason')) {
                $updates['suspension_reason'] = null;
            }
            if ($note && Schema::hasColumn('tenant_billing_profiles', 'notes')) {
                // append
                DB::table('tenant_billing_profiles')
                    ->where('tenant_id', $tenant->id)
                    ->update($updates);

                DB::table('tenant_billing_profiles')
                    ->where('tenant_id', $tenant->id)
                    ->update([
                        'notes' => DB::raw("CONCAT(COALESCE(notes,''), '\n[SYSADMIN] {$note}')"),
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('tenant_billing_profiles')
                    ->where('tenant_id', $tenant->id)
                    ->update($updates);
            }

            // opcional: marcar invoices pending como "void" o algo, yo recomiendo NO tocar aquí.
            // pero si quieres liberar al tenant sin wallet, normalmente lo correcto es marcar paid la invoice.
            if ($clear) {
                // Solo si existe la columna status y tu negocio lo permite:
                DB::table('tenant_invoices')
                    ->where('tenant_id', $tenant->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'void', 'updated_at' => now()]);
            }
        });

        Log::info('SYSADMIN_TENANT_ACTIVATED', ['tenant_id' => $tenant->id, 'note' => $note, 'clear_pending' => $clear]);

        return back()->with('ok', 'Tenant activado manualmente.');
    }

    public function cancel(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'reason' => ['nullable','string','max:160'],
            'close_open_shifts' => ['nullable','boolean'],
        ]);

        $reason = trim((string)($data['reason'] ?? 'manual_cancel'));
        $close  = !empty($data['close_open_shifts']);

        DB::transaction(function () use ($tenant, $reason, $close) {

            $updates = ['status' => 'canceled', 'updated_at' => now()];

            if (Schema::hasColumn('tenant_billing_profiles', 'canceled_at')) {
                $updates['canceled_at'] = now();
            }
            if (Schema::hasColumn('tenant_billing_profiles', 'suspension_reason')) {
                $updates['suspension_reason'] = $reason ?: 'manual_cancel';
            }

            DB::table('tenant_billing_profiles')
                ->where('tenant_id', $tenant->id)
                ->update($updates);

            if ($close) {
                $this->closeOpenShiftsInternal($tenant->id, 'Cerrado por sysadmin (cancel).');
            }
        });

        Log::warning('SYSADMIN_TENANT_CANCELED', ['tenant_id' => $tenant->id, 'reason' => $reason, 'close_open_shifts' => $close]);

        return back()->with('ok', 'Tenant cancelado manualmente.');
    }

    public function closeOpenShifts(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'note' => ['nullable','string','max:160'],
        ]);

        $note = trim((string)($data['note'] ?? 'Cerrado por sysadmin.'));
        $count = $this->closeOpenShiftsInternal($tenant->id, $note);

        Log::warning('SYSADMIN_CLOSE_OPEN_SHIFTS', ['tenant_id' => $tenant->id, 'count' => $count, 'note' => $note]);

        return back()->with('ok', "Turnos abiertos cerrados: {$count}");
    }

    private function closeOpenShiftsInternal(int $tenantId, string $note): int
    {
        // Ajusta status si tu dominio usa otro valor
        $updates = [
            'ended_at'   => now(),
            'status'     => 'cerrado',
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('driver_shifts', 'notes')) {
            $updates['notes'] = DB::raw("CONCAT('[AUTO] {$note} ', COALESCE(notes,''))");
        }

        return (int) DB::table('driver_shifts')
            ->where('tenant_id', $tenantId)
            ->whereNull('ended_at')
            ->update($updates);
    }
}
