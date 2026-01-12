<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantBillingProfile;
use App\Models\TenantInvoice;
use App\Models\Vehicle;
use App\Services\TenantBillingService;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\TenantWalletService;
use Illuminate\Support\Facades\DB;


use Carbon\Carbon;

class TenantBillingController extends Controller
{
    /**
     * Pantalla "Mi plan / Facturación" que ve la central (/admin/billing).
     */
   public function plan(TenantBillingService $billingService, TenantWalletService $walletService)
{
    $user = Auth::user();
    if (!$user || !$user->tenant_id) abort(403, 'Usuario sin tenant asignado');

    $tenant  = $user->tenant;
    $profile = $tenant->billingProfile;

    if (!$profile) {
        $profile = new TenantBillingProfile([ /* defaults */ ]);
    }

    $activeVehicles = Vehicle::where('tenant_id', $tenant->id)->where('active', 1)->count();
    $totalVehicles  = Vehicle::where('tenant_id', $tenant->id)->count();

    [$canRegisterNewVehicle, $canRegisterReason] = $billingService->canRegisterNewVehicle($tenant);

    $invoices = TenantInvoice::where('tenant_id', $tenant->id)
        ->orderByDesc('issue_date')->orderByDesc('id')
        ->paginate(20);

    // WALLET
    $wallet = $walletService->ensureWallet((int)$tenant->id);
    $balance = (float)$wallet->balance;

    // “Lo debido” (draft + pending)
    $openAmountDue = (float) TenantInvoice::where('tenant_id', $tenant->id)
        ->whereIn('status', ['draft','pending'])
        ->sum('total');

    $minTopupSuggested = max(0, round($openAmountDue - $balance, 2));

    // Estimado próximo mes (solo per_vehicle)
    $nextMonthEstimate = null;
    if (($profile->billing_model ?? 'per_vehicle') === 'per_vehicle') {
        $nextMonthEstimate = round($billingService->monthlyAmountForVehicles($profile, $activeVehicles), 2);
    }

    // Trial countdown
    $trialEnds = null;
    $trialDaysLeft = null;
    if (($profile->status ?? 'trial') === 'trial') {
        $trialEnds = $billingService->trialEndsAt($profile);
        if ($trialEnds) {
            $trialDaysLeft = max(0, now()->startOfDay()->diffInDays($trialEnds->copy()->startOfDay(), false));
        }
    }

    


       return view('admin.billing.plan', [
        'tenant'                => $tenant,
        'profile'               => $profile,
        'activeVehicles'        => $activeVehicles,
        'totalVehicles'         => $totalVehicles,
        'canRegisterNewVehicle' => $canRegisterNewVehicle,
        'canRegisterReason'     => $canRegisterReason,
        'invoices'              => $invoices,

        // wallet props
        'wallet'               => $wallet,
        'walletBalance'        => $balance,
        'openAmountDue'        => $openAmountDue,
        'minTopupSuggested'    => $minTopupSuggested,
        'nextMonthEstimate'    => $nextMonthEstimate,
        'trialEndsAt'          => $trialEnds,
        'trialDaysLeft'        => $trialDaysLeft,
    ]);

    }

    /**
     * Detalle de una factura para la central (solo lectura).
     */
    public function invoiceShow(TenantInvoice $invoice)
    {
        $tenantId = Auth::user()->tenant_id ?? null;
        if ($invoice->tenant_id !== $tenantId) {
            abort(403, 'No puedes ver esta factura');
        }

        $tenant  = $invoice->tenant;
        $profile = $invoice->billingProfile;

        return view('admin.billing.invoice_show', [
            'invoice' => $invoice,
            'tenant'  => $tenant,
            'profile' => $profile,
        ]);
    }

    /**
     * Exportar la factura a CSV simple para el tenant.
     */
    public function invoiceCsv(TenantInvoice $invoice)
    {
        $tenantId = Auth::user()->tenant_id ?? null;
        if ($invoice->tenant_id !== $tenantId) {
            abort(403, 'No puedes descargar esta factura');
        }

        $filename = sprintf(
            'factura-tenant-%d-%d.csv',
            $invoice->tenant_id,
            $invoice->id
        );

        $callback = function () use ($invoice) {
            $out = fopen('php://output', 'w');

            // Separador ; para que Excel ES lo abra directo
            fputcsv($out, ['Campo', 'Valor'], ';');

            fputcsv($out, ['ID', $invoice->id], ';');
            fputcsv($out, ['Tenant', $invoice->tenant?->name ?? ('#'.$invoice->tenant_id)], ';');
            fputcsv($out, ['Periodo', $invoice->period_start.' → '.$invoice->period_end], ';');
            fputcsv($out, ['Emitida el', $invoice->issue_date], ';');
            fputcsv($out, ['Vence el', $invoice->due_date], ';');
            fputcsv($out, ['Status', $invoice->status], ';');
            fputcsv($out, ['Vehículos facturados', $invoice->vehicles_count], ';');
            fputcsv($out, ['Base mensual', $invoice->base_fee], ';');
            fputcsv($out, ['Cargo por vehículos extra', $invoice->vehicles_fee], ';');
            fputcsv($out, ['Total', $invoice->total], ';');
            fputcsv($out, ['Moneda', $invoice->currency], ';');

            fclose($out);
        };

        return response()->streamDownload(
            $callback,
            $filename,
            [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]
        );
    }



        /**
     * Exportar factura a PDF para el tenant.
     */
    public function invoicePdf(TenantInvoice $invoice)
    {
        $tenantId = Auth::user()->tenant_id ?? null;
        if ($invoice->tenant_id !== $tenantId) {
            abort(403, 'No puedes descargar esta factura');
        }

        $tenant  = $invoice->tenant;
        $profile = $invoice->billingProfile;

        $pdf = Pdf::loadView('admin.billing.invoice_pdf', [
                'invoice' => $invoice,
                'tenant'  => $tenant,
                'profile' => $profile,
            ])
            ->setPaper('letter', 'portrait');

        $filename = sprintf(
            'factura-tenant-%d-%d.pdf',
            $invoice->tenant_id,
            $invoice->id
        );

        return $pdf->download($filename);
    }




public function payWithWallet(TenantInvoice $invoice, TenantBillingService $billing, TenantWalletService $wallet)
{
    $tenantId = Auth::user()->tenant_id ?? null;
    if ($invoice->tenant_id !== $tenantId) abort(403);

    if ($invoice->status !== 'pending') {
        return back()->with('err', 'La factura no está en estado pending.');
    }

    $ok = $billing->payInvoiceFromWallet($invoice, $wallet);

    return $ok
        ? back()->with('ok', 'Factura pagada con wallet.')
        : back()->with('err', 'Saldo insuficiente en wallet.');
}


public function acceptTerms(TenantBillingService $billing, TenantWalletService $wallet)
{
    $user = Auth::user();
    $tenant = $user?->tenant;
    abort_if(!$tenant, 403);
    abort_if((int)$user->is_admin !== 1, 403);

    $p = $tenant->billingProfile;
    abort_if(!$p, 404);

    if (!empty($p->accepted_terms_at)) {
        return back()->with('ok', 'Los términos ya estaban aceptados.');
    }

    DB::transaction(function () use ($tenant, $p, $billing, $user) {
        $p->accepted_terms_at = now();
        $p->accepted_by_user_id = $user->id;
        $p->save();

        // Convertir SOLO la draft relevante (si existe) a pending
        $draft = TenantInvoice::where('tenant_id', $tenant->id)
            ->where('status', 'draft')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->first();

        if ($draft) {
            $draft->status = 'pending';
            $draft->save();
        }
    });

    // Intentar pagar la factura abierta del mes (si alcanza) y normalizar estado
    $inv = (new \ReflectionClass($billing))->hasMethod('findCurrentUnpaidInvoice')
        ? $billing->billingUiState($tenant, $wallet, now()) // solo para forzar ensureWallet/balance
        : null;

    $currentInv = TenantInvoice::where('tenant_id', $tenant->id)
        ->where('status', 'pending')
        ->orderByDesc('issue_date')->orderByDesc('id')
        ->first();

    if ($currentInv) {
        $billing->payInvoiceFromWallet($currentInv, $wallet);
    }

    $billing->recheckTenantBillingState($tenant, $wallet, now());

    return back()->with('ok', 'Términos aceptados. La facturación queda habilitada.');
}




}
