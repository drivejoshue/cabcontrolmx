<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantInvoice;
use Illuminate\Http\Request;

class TenantInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = TenantInvoice::with('tenant')->orderByDesc('issue_date');

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->input('tenant_id'));
        }

        $invoices = $query->paginate(50);
        $tenants  = Tenant::orderBy('name')->get();

        return view('sysadmin.invoices.index', [
            'invoices' => $invoices,
            'tenants'  => $tenants,
            'filters'  => [
                'tenant_id' => $request->input('tenant_id'),
            ],
        ]);
    }

    public function show(TenantInvoice $invoice)
    {
        $invoice->load('tenant');

        return view('sysadmin.invoices.show', [
            'invoice' => $invoice,
        ]);
    }
}
