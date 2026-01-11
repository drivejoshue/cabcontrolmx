<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TenantConsoleInvoiceController extends Controller
{
    private function assertInvoiceBelongs(Tenant $tenant, TenantInvoice $invoice): void
    {
        abort_unless((int)$invoice->tenant_id === (int)$tenant->id, 404);
    }

    public function markPaid(Request $request, Tenant $tenant, TenantInvoice $invoice)
    {
        $this->assertInvoiceBelongs($tenant, $invoice);

        $data = $request->validate([
            'payment_method' => ['nullable','string','max:30'], // transfer|wallet|cash|other
            'external_ref'   => ['nullable','string','max:80'],
            'notes'          => ['nullable','string','max:220'],
        ]);

        $pm    = trim((string)($data['payment_method'] ?? 'transfer'));
        $ref   = trim((string)($data['external_ref'] ?? ''));
        $notes = trim((string)($data['notes'] ?? ''));

        DB::transaction(function () use ($invoice, $pm, $ref, $notes) {

            $u = ['status' => 'paid', 'updated_at' => now()];

            if (Schema::hasColumn('tenant_invoices', 'paid_at')) {
                $u['paid_at'] = now();
            }
            if (Schema::hasColumn('tenant_invoices', 'payment_method')) {
                $u['payment_method'] = $pm;
            }
            if (Schema::hasColumn('tenant_invoices', 'external_ref')) {
                $u['external_ref'] = $ref ?: null;
            }
            if (Schema::hasColumn('tenant_invoices', 'notes') && $notes !== '') {
                $u['notes'] = DB::raw("CONCAT(COALESCE(notes,''), '\n[SYSADMIN] {$notes}')");
            }

            DB::table('tenant_invoices')->where('id', $invoice->id)->update($u);
        });

        Log::info('SYSADMIN_INVOICE_MARK_PAID', [
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice->id,
            'payment_method' => $pm,
            'external_ref' => $ref,
        ]);

        return back()->with('ok', "Factura #{$invoice->id} marcada como PAGADA.");
    }

    public function markPending(Request $request, Tenant $tenant, TenantInvoice $invoice)
    {
        $this->assertInvoiceBelongs($tenant, $invoice);

        $data = $request->validate([
            'notes' => ['nullable','string','max:220'],
        ]);

        $notes = trim((string)($data['notes'] ?? ''));

        DB::transaction(function () use ($invoice, $notes) {
            $u = ['status' => 'pending', 'updated_at' => now()];

            if (Schema::hasColumn('tenant_invoices', 'paid_at')) {
                $u['paid_at'] = null;
            }
            if (Schema::hasColumn('tenant_invoices', 'payment_method')) {
                $u['payment_method'] = null;
            }
            if (Schema::hasColumn('tenant_invoices', 'external_ref')) {
                $u['external_ref'] = null;
            }
            if (Schema::hasColumn('tenant_invoices', 'notes') && $notes !== '') {
                $u['notes'] = DB::raw("CONCAT(COALESCE(notes,''), '\n[SYSADMIN] BACK TO PENDING: {$notes}')");
            }

            DB::table('tenant_invoices')->where('id', $invoice->id)->update($u);
        });

        Log::warning('SYSADMIN_INVOICE_MARK_PENDING', ['tenant_id' => $tenant->id, 'invoice_id' => $invoice->id]);

        return back()->with('ok', "Factura #{$invoice->id} regresada a PENDING.");
    }

    public function void(Request $request, Tenant $tenant, TenantInvoice $invoice)
    {
        $this->assertInvoiceBelongs($tenant, $invoice);

        $data = $request->validate([
            'reason' => ['required','string','max:220'],
        ]);

        $reason = trim((string)$data['reason']);

        DB::transaction(function () use ($invoice, $reason) {
            $u = ['status' => 'void', 'updated_at' => now()];

            if (Schema::hasColumn('tenant_invoices', 'voided_at')) {
                $u['voided_at'] = now();
            }
            if (Schema::hasColumn('tenant_invoices', 'notes')) {
                $u['notes'] = DB::raw("CONCAT(COALESCE(notes,''), '\n[SYSADMIN] VOID: {$reason}')");
            }

            DB::table('tenant_invoices')->where('id', $invoice->id)->update($u);
        });

        Log::warning('SYSADMIN_INVOICE_VOID', ['tenant_id' => $tenant->id, 'invoice_id' => $invoice->id, 'reason' => $reason]);

        return back()->with('ok', "Factura #{$invoice->id} anulada (VOID).");
    }
}
