<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\TenantBillingProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use App\Models\BillingPlan; // nuevo
use Illuminate\Support\Facades\Log;

class TenantSignupController extends Controller
{
    public function landing() { return view('public.landing'); }
    public function show() { return view('public.signup'); }
    public function success() { return view('public.success'); }

  public function store(Request $request)
{
    $turnstileEnabled = (bool) (
        config('services.turnstile.site_key') &&
        config('services.turnstile.secret_key')
    );

    $rules = [
        'central_name' => ['required','string','max:150'],
        'city'         => ['nullable','string','max:120'],
        'timezone'     => ['nullable','string','max:64'],

        'owner_name'   => ['required','string','max:150'],
        'owner_email'  => ['required','email','max:190', 'unique:users,email'],
        'password'     => ['required','string','min:8','max:72','confirmed'],

        'notification_email' => ['nullable','email','max:190'],
        'phone'              => ['nullable','string','max:30'],
    ];

    if ($turnstileEnabled) {
        $rules['cf-turnstile-response'] = ['required','string'];
    }

    $data = $request->validate($rules);

    if ($turnstileEnabled) {
        $check = $this->verifyTurnstile(
            $data['cf-turnstile-response'],
            $request->ip()
        );

        if (!$check['ok']) {
            return back()
                ->withErrors(['cf-turnstile-response' => $check['message'] ?? 'Verificación anti-bot falló. Intenta de nuevo.'])
                ->withInput();
        }
    }

    // Normaliza email (mejor hacerlo ANTES de crear)
    $data['owner_email'] = mb_strtolower(trim($data['owner_email']));

    $tz = $this->safeTimezone($data['timezone'] ?? null);

    $user = DB::transaction(function () use ($data, $tz) {
        $baseSlug = Str::slug($data['central_name']) ?: ('tenant-'.Str::lower(Str::random(6)));
        $tenant = $this->createTenantWithUniqueSlug($data, $tz, $baseSlug);

        $user = User::create([
            'name'      => $data['owner_name'],
            'email'     => $data['owner_email'],
            'password'  => Hash::make($data['password']),
            'tenant_id' => $tenant->id,
            'is_admin'  => 1,
        ]);

        $this->ensureTrialBillingProfile($tenant->id, $tz);

        return $user;
    });

    auth()->login($user);
    $user->sendEmailVerificationNotification();
    return redirect()->route('verification.notice');
}





    private function safeTimezone(?string $tz): string
    {
        $tz = $tz ?: 'America/Mexico_City';
        return in_array($tz, \DateTimeZone::listIdentifiers(), true)
            ? $tz
            : 'America/Mexico_City';
    }

    private function createTenantWithUniqueSlug(array $data, string $tz, string $baseSlug): Tenant
    {
        // Requiere índice UNIQUE en tenants.slug para ser 100% seguro.
        $attempts = 0;

        while (true) {
            $attempts++;

            $slug = $baseSlug;
            if ($attempts > 1) {
                $slug = $baseSlug . '-' . Str::lower(Str::random(4));
            }

            try {
                return Tenant::create([
                    'name' => $data['central_name'],
                    'slug' => $slug,
                    'timezone' => $tz,

                    // Campos opcionales (si existen en tu tabla)
                    'notification_email' => $data['notification_email'] ?? $data['owner_email'],
                    'public_phone'       => $data['phone'] ?? null,
                    'public_city'        => $data['city'] ?? null,
                    'public_active'      => 0,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Si es colisión de UNIQUE slug, reintenta
                if ($attempts < 8 && str_contains(strtolower($e->getMessage()), 'duplicate')) {
                    continue;
                }
                throw $e;
            }
        }
    }

  private function ensureTrialBillingProfile(int $tenantId, string $tz): void
{
    if (!class_exists(TenantBillingProfile::class)) return;
    if (!Schema::hasTable('tenant_billing_profiles')) return;

    $planCode = 'PV_STARTER';

    // Lee el plan desde tabla (si existe)
    $plan = null;
    if (Schema::hasTable('billing_plans')) {
        $plan = BillingPlan::where('code', $planCode)->where('active', 1)->first();
    }

    // Defaults seguros (fallback)
    $baseMonthly = 0.00;
    $includedVehicles = 5;
    $pricePerVehicle = 299.00;
    $currency = 'MXN';

    if ($plan) {
        $baseMonthly = (float)$plan->base_monthly_fee;
        $includedVehicles = (int)$plan->included_vehicles;
        $pricePerVehicle = (float)$plan->price_per_vehicle;
        $currency = $plan->currency ?: 'MXN';
    } else {
        Log::warning('BillingPlan missing, using fallback defaults', [
            'plan_code' => $planCode,
            'tenant_id' => $tenantId,
        ]);
    }

    $payload = [
        'plan_code'      => $planCode,
        'billing_model'  => 'per_vehicle',
        'status'         => 'trial',
        'trial_ends_at'  => Carbon::now($tz)->addDays(14)->toDateString(),
        'trial_vehicles' => 5,
    ];

    $columns = Schema::getColumnListing('tenant_billing_profiles');

    if (in_array('currency', $columns, true))         $payload['currency'] = $currency;
    if (in_array('base_monthly_fee', $columns, true)) $payload['base_monthly_fee'] = $baseMonthly;
    if (in_array('included_vehicles', $columns, true))$payload['included_vehicles'] = $includedVehicles;
    if (in_array('price_per_vehicle', $columns, true))$payload['price_per_vehicle'] = $pricePerVehicle;

    TenantBillingProfile::updateOrCreate(
        ['tenant_id' => $tenantId],
        $payload
    );
}


     private function verifyTurnstile(string $token, ?string $ip = null): array
{
    $secret = config('services.turnstile.secret_key');
    if (!$secret) {
        return ['ok' => false, 'message' => 'Turnstile no está configurado (secret).'];
    }

    try {
        $resp = Http::asForm()
            ->timeout(6)
            ->retry(2, 250) // 2 reintentos, 250ms
            ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret'   => $secret,
                'response' => $token,
                // remoteip es opcional; si lo mandas y hay proxies raros puede fallar.
                // Si quieres mantenerlo, déjalo solo cuando venga:
                'remoteip' => $ip ?: null,
            ]);

        if (!$resp->ok()) {
            return ['ok' => false, 'message' => 'No se pudo validar Turnstile (respuesta no OK).'];
        }

        $json = $resp->json();
        $success  = (bool)($json['success'] ?? false);
        $hostname = (string)($json['hostname'] ?? '');
        $errors   = (array)($json['error-codes'] ?? []);

        if (!$success) {
            return ['ok' => false, 'message' => 'Verificación anti-bot falló. Recarga e intenta de nuevo.', 'errors' => $errors];
        }

        // Valida que el token sea para ESTE host (evita tokens de otro dominio)
        $allowedHosts = [
            'dispatch.orbana.mx',
            // si también lo usas en otros subdominios, agrega aquí
        ];

        if ($hostname && !in_array($hostname, $allowedHosts, true)) {
            return ['ok' => false, 'message' => 'Verificación inválida para este dominio. Recarga la página.'];
        }

        return ['ok' => true];

    } catch (\Throwable $e) {
        Log::warning('Turnstile verify exception', [
            'msg' => $e->getMessage(),
        ]);
        return ['ok' => false, 'message' => 'No se pudo validar la verificación anti-bot. Intenta de nuevo.'];
    }
}

}
