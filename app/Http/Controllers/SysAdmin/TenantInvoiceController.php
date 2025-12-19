<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\TenantInvoice;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class TenantInvoiceController extends Controller
{
    /**
     * Listado de facturas a tenants (SysAdmin).
     *
     * Filtros:
     *  - tenant_id
     *  - status (pending, paid, overdue, canceled, etc.)
     *  - rango de fechas (issue_from / issue_to)
     */
    public function index(Request $request)
    {
        $query = TenantInvoice::query()
            ->with(['tenant', 'billingProfile'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id');

        // Filtro por tenant
        if ($tenantId = $request->input('tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }

        // Filtro por status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filtro por rango de fecha de emisión
        if ($from = $request->input('issue_from')) {
            $query->whereDate('issue_date', '>=', $from);
        }
        if ($to = $request->input('issue_to')) {
            $query->whereDate('issue_date', '<=', $to);
        }

        $invoices = $query->paginate(30)->withQueryString();

        // Para combo de tenants y estados
        $tenants = Tenant::orderBy('name')->get(['id', 'name']);
        $statuses = ['pending','paid','overdue','canceled'];

        return view('sysadmin.invoices.index', [
            'invoices' => $invoices,
            'tenants'  => $tenants,
            'statuses' => $statuses,
            'filters'  => [
                'tenant_id'  => $tenantId,
                'status'     => $status,
                'issue_from' => $from,
                'issue_to'   => $to,
            ],
        ]);
    }

    /**
     * Detalle de una factura.
     */
    public function show(TenantInvoice $invoice)
    {
        $invoice->load(['tenant', 'billingProfile']);

        $profile = $invoice->billingProfile;

        // Pequeño desglose para modelo per_vehicle
        $extraVehicles = null;
        if ($profile && $profile->billing_model === 'per_vehicle') {
            $included = (int)($profile->included_vehicles ?? 0);
            $extraVehicles = max(0, (int)$invoice->vehicles_count - $included);
        }

        return view('sysadmin.invoices.show', [
            'invoice'       => $invoice,
            'tenant'        => $invoice->tenant,
            'profile'       => $profile,
            'extraVehicles' => $extraVehicles,
        ]);
    }


     public function markPaid(Request $request, TenantInvoice $invoice)
    {
        if ($invoice->status === 'paid') {
            return back()->with('ok', 'La factura ya estaba marcada como pagada.');
        }

        $invoice->status = 'paid';
        // Si más adelante agregas paid_at, aquí se setea:
        // $invoice->paid_at = now();
        $invoice->save();

        return redirect()
            ->route('sysadmin.invoices.show', $invoice)
            ->with('ok', 'Factura marcada como pagada correctamente.');
    }

    public function markCanceled(Request $request, TenantInvoice $invoice)
    {
        if ($invoice->status === 'canceled') {
            return back()->with('ok', 'La factura ya estaba marcada como cancelada.');
        }

        $invoice->status = 'canceled';
        $invoice->save();

        return redirect()
            ->route('sysadmin.invoices.show', $invoice)
            ->with('ok', 'Factura marcada como cancelada.');
    }


     /**
     * Descargar PDF de una factura concreta.
     */
    public function downloadPdf(TenantInvoice $invoice)
    {
        $invoice->load('tenant', 'billingProfile');

        $pdf = Pdf::loadView('sysadmin.invoices.pdf', [
            'invoice' => $invoice,
        ]);

        $filename = sprintf(
            'orbana-invoice-%d-%s.pdf',
            $invoice->id,
            $invoice->issue_date?->format('Ymd') ?? now()->format('Ymd')
        );

        return $pdf->download($filename);
    }

    /**
     * Exportar todas las facturas a CSV (para SysAdmin).
     */
    public function exportCsv(Request $request)
    {
        $invoices = TenantInvoice::with('tenant')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get();

        $filename = 'orbana-tenant-invoices-' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($invoices) {
            $out = fopen('php://output', 'w');

            // Cabecera
            fputcsv($out, [
                'invoice_id',
                'tenant_id',
                'tenant_name',
                'period_start',
                'period_end',
                'issue_date',
                'due_date',
                'status',
                'vehicles_count',
                'base_fee',
                'vehicles_fee',
                'total',
                'currency',
            ]);

            foreach ($invoices as $inv) {
                fputcsv($out, [
                    $inv->id,
                    $inv->tenant_id,
                    optional($inv->tenant)->name,
                    optional($inv->period_start)->toDateString(),
                    optional($inv->period_end)->toDateString(),
                    optional($inv->issue_date)->toDateString(),
                    optional($inv->due_date)->toDateString(),
                    $inv->status,
                    $inv->vehicles_count,
                    $inv->base_fee,
                    $inv->vehicles_fee,
                    $inv->total,
                    $inv->currency,
                ]);
            }

            fclose($out);
        }, 200, $headers);
    }
}
