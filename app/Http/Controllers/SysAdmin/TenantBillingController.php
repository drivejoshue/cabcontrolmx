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

class TenantBillingController extends Controller
{
    /**
     * Muestra el perfil de facturación del tenant.
     */
    public function show(Tenant $tenant, TenantBillingService $billingService)
    {
        /** @var TenantBillingProfile|null $profile */
        $profile = $tenant->billingProfile;

        if (!$profile) {
            $profile = new TenantBillingProfile([
                'tenant_id'          => $tenant->id,
                'plan_code'          => 'basic-per-vehicle',
                'billing_model'      => 'per_vehicle', // o 'commission'
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

        // Vehículos del tenant
        $activeVehicles = Vehicle::where('tenant_id', $tenant->id)
            ->where('active', 1)
            ->count();

        $totalVehicles = Vehicle::where('tenant_id', $tenant->id)->count();

        // ¿Puede registrar nuevos vehículos?
        [$canRegisterNewVehicle, $canRegisterReason] = $billingService->canRegisterNewVehicle($tenant);

        // Última factura emitida
        $lastInvoice = TenantInvoice::where('tenant_id', $tenant->id)
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->first();

        return view('sysadmin.tenants.billing.show', [
            'tenant'                => $tenant,
            'profile'               => $profile,
            'activeVehicles'        => $activeVehicles,
            'totalVehicles'         => $totalVehicles,
            'canRegisterNewVehicle' => $canRegisterNewVehicle,
            'canRegisterReason'     => $canRegisterReason,
            'lastInvoice'           => $lastInvoice,
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
