<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantBillingProfile;
use App\Models\TenantInvoice;
use App\Models\Vehicle;
use App\Services\TenantBillingService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\User;
use App\Models\TenantDocument;

use Illuminate\Support\Facades\DB;

class TenantBillingController extends Controller
{
    /**
     * Muestra el perfil de facturación del tenant.
     */
public function show(Request $request, Tenant $tenant, TenantBillingService $billingService)
{
    /** @var TenantBillingProfile|null $profile */
    $profile = $tenant->billingProfile;

    if (!$profile) {
        $profile = new TenantBillingProfile([
            'tenant_id'          => $tenant->id,
            'plan_code'          => 'basic-per-vehicle',
            'billing_model'      => 'per_vehicle',
            'status'             => 'trial',
            'trial_vehicles'     => 5,
            'base_monthly_fee'   => 0,
            'included_vehicles'  => 0,
            'price_per_vehicle'  => 0,
            'max_vehicles'       => null,
            'invoice_day'        => 1,
            'commission_percent' => null,
            'commission_min_fee' => 0,
            'notes'              => null,
        ]);
    }

    // Tab actual (incluye documents)
    $tab = (string)$request->query('tab', 'billing');
    $allowedTabs = ['billing','wallet','invoices','users','vehicles','documents'];
    if (!in_array($tab, $allowedTabs, true)) {
        $tab = 'billing';
    }

    // Conteos rápidos (útiles en varios tabs)
    $activeVehicles = Vehicle::where('tenant_id', $tenant->id)->where('active', 1)->count();
    $totalVehicles  = Vehicle::where('tenant_id', $tenant->id)->count();

    // ¿Puede registrar nuevos vehículos?
    [$canRegisterNewVehicle, $canRegisterReason] = $billingService->canRegisterNewVehicle($tenant);

    // Última factura (para header / contexto)
    $lastInvoice = TenantInvoice::where('tenant_id', $tenant->id)
        ->orderByDesc('issue_date')
        ->orderByDesc('id')
        ->first();

    // Defaults para la vista (evita undefined variable)
    $wallet = null;
    $walletMovements = collect();
    $invoices = collect();
    $users = collect();
    $vehicles = null;          // paginator o null
    $vq = '';
    $vactive = '';
    $docs = collect();         // keyBy(type)

    // Cargar por tab (más pro: solo lo que se usa)
    if ($tab === 'wallet') {
        $wallet = DB::table('tenant_wallets')->where('tenant_id', $tenant->id)->first();

        $walletMovements = DB::table('tenant_wallet_movements')
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    if ($tab === 'invoices') {
        $invoices = TenantInvoice::where('tenant_id', $tenant->id)
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    if ($tab === 'users') {
        $users = User::where('tenant_id', $tenant->id)
            ->orderByDesc('id')
            ->limit(80)
            ->get();
    }

    if ($tab === 'vehicles') {
        $vq = trim((string)$request->query('vq', ''));
        $vactive = (string)$request->query('vactive', ''); // '', '1', '0'

        $vehiclesQuery = Vehicle::query()
            ->where('tenant_id', $tenant->id)
            ->when($vq !== '', function ($q) use ($vq) {
                $q->where(function ($w) use ($vq) {
                    $w->where('economico', 'like', "%{$vq}%")
                      ->orWhere('plate', 'like', "%{$vq}%")
                      ->orWhere('brand', 'like', "%{$vq}%")
                      ->orWhere('model', 'like', "%{$vq}%");
                });
            })
            ->when(in_array($vactive, ['0','1'], true), function ($q) use ($vactive) {
                $q->where('active', (int)$vactive);
            })
            ->orderByDesc('id');

        $vehicles = $vehiclesQuery
            ->paginate(25)
            ->appends($request->query());
    }

    $docs = collect();

    if ($tab === 'documents') {
        $docs = TenantDocument::where('tenant_id', $tenant->id)
            ->whereIn('type', [
                TenantDocument::TYPE_ID_OFFICIAL,
                TenantDocument::TYPE_PROOF_ADDRESS,
                TenantDocument::TYPE_TAX_CERTIFICATE,
            ])
            ->get()
            ->keyBy(function ($d) {
                return match ($d->type) {
                    TenantDocument::TYPE_ID_OFFICIAL     => 'id_official',
                    TenantDocument::TYPE_PROOF_ADDRESS   => 'proof_address',
                    TenantDocument::TYPE_TAX_CERTIFICATE => 'tax_certificate',
                    default => $d->type,
                };
            });
    }

    return view('sysadmin.tenants.billing.show', [
        'tenant'                => $tenant,
        'profile'               => $profile,

        'activeVehicles'        => $activeVehicles,
        'totalVehicles'         => $totalVehicles,
        'canRegisterNewVehicle' => $canRegisterNewVehicle,
        'canRegisterReason'     => $canRegisterReason,
        'lastInvoice'           => $lastInvoice,

        'wallet'          => $wallet,
        'walletMovements' => $walletMovements,
        'invoices'        => $invoices,
        'users'           => $users,

        'vehicles' => $vehicles,
        'vq'       => $vq,
        'vactive'  => $vactive,

        // NUEVO: docs para la pestaña documentos (sysadmin.tenant_documents.documents)
        'docs' => $docs,

        'tab' => $tab,
    ]);
}



    /**
     * Actualiza / crea el TenantBillingProfile del tenant.
     */
    public function update(Request $request, Tenant $tenant)
    {
        /** @var TenantBillingProfile|null $profile */
        $profile = $tenant->billingProfile ?: new TenantBillingProfile([
            'tenant_id' => $tenant->id,
        ]);

        $rules = [
            'plan_code'          => ['required', 'string', 'max:40'],
            'billing_model'      => ['required', 'in:per_vehicle,commission'],
            'status'             => ['required', 'in:trial,active,paused,canceled'],

            'trial_ends_at'      => ['nullable', 'date'],
            'trial_vehicles'     => ['nullable', 'integer', 'min:0', 'max:65535'],

            'base_monthly_fee'   => ['nullable', 'numeric', 'min:0'],
            'included_vehicles'  => ['nullable', 'integer', 'min:0'],
            'price_per_vehicle'  => ['nullable', 'numeric', 'min:0'],
            'max_vehicles'       => ['nullable', 'integer', 'min:0'],

            'invoice_day'        => ['required', 'integer', 'min:1', 'max:28'],

            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_min_fee' => ['nullable', 'numeric', 'min:0'],

            'notes'              => ['nullable', 'string'],
        ];

        $data = $request->validate($rules);

        // Normalizar y guardar
        $profile->tenant_id          = $tenant->id;
        $profile->plan_code          = $data['plan_code'];
        $profile->billing_model      = $data['billing_model'];
        $profile->status             = $data['status'];

        $profile->trial_ends_at      = $data['trial_ends_at'] ?? null;
        $profile->trial_vehicles     = $data['trial_vehicles'] ?? ($profile->trial_vehicles ?? 0);

        $profile->base_monthly_fee   = $data['base_monthly_fee']   ?? 0;
        $profile->included_vehicles  = $data['included_vehicles']  ?? 0;
        $profile->price_per_vehicle  = $data['price_per_vehicle']  ?? 0;
        $profile->max_vehicles       = $data['max_vehicles']       ?? null;

        $profile->invoice_day        = $data['invoice_day'];

        $profile->commission_percent = $data['commission_percent'] ?? null;
        $profile->commission_min_fee = $data['commission_min_fee'] ?? 0;

        $profile->notes              = $data['notes'] ?? null;

        $profile->save();

        return redirect()
            ->route('sysadmin.tenants.billing.show', $tenant)
            ->with('ok', 'Perfil de facturación actualizado correctamente.');
    }

    /**
     * Genera manualmente una factura mensual para el tenant (pruebas).
     */
    public function generateMonthly(
        Request $request,
        Tenant $tenant,
        TenantBillingService $billingService
    ) {
        try {
            // Opcional: permitir enviar una fecha de corte, si no, usa "hoy" en TZ del tenant
            $cutoffDateStr = $request->input('cutoff_date');

            $cutoffDate = $cutoffDateStr
                ? Carbon::parse($cutoffDateStr)
                : Carbon::now($tenant->timezone ?? config('app.timezone'));

            $invoice = $billingService->generateMonthlyInvoice($tenant, $cutoffDate);

            $period = $invoice->period_start->toDateString() . ' – ' . $invoice->period_end->toDateString();

            return redirect()
                ->route('sysadmin.tenants.billing.show', $tenant)
                ->with('ok', "Factura generada para el periodo {$period} (total {$invoice->total} {$invoice->currency}).");

        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('sysadmin.tenants.billing.show', $tenant)
                ->withErrors([
                    'billing' => 'No se pudo generar la factura: '.$e->getMessage(),
                ]);
        }
    }


    public function runMonthly(\App\Models\Tenant $tenant)
{
    // Puedes inyectar tu servicio real si ya lo tienes
    // p.ej. TenantBillingService $svc y llamar $svc->runMonthlyCycle($tenant)
    try {
        \DB::transaction(function () use ($tenant) {
            // Llama a tu servicio canónico (ajusta el nombre a tu implementación real)
            app(\App\Services\TenantBillingService::class)->runMonthlyCycle($tenant->id);
        });

        return back()->with('ok', 'Ciclo mensual ejecutado correctamente para el tenant #'.$tenant->id.'.');
    } catch (\Throwable $e) {
        \Log::error('BILLING_RUN_MONTHLY_FAIL', ['tenant_id'=>$tenant->id, 'e'=>$e->getMessage()]);
        return back()->withErrors('No se pudo correr el ciclo mensual: '.$e->getMessage());
    }
}

}
