<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TenantTopup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\TransferTopupNoticeReceipt;
use App\Mail\TransferTopupNoticeSubmitted;
use App\Models\ProviderProfile;
use Illuminate\Support\Facades\DB;



class TenantWalletTopupController extends Controller
{
    private function tenantId(): int
    {
        $tenantId = (int)(Auth::user()->tenant_id ?? 0);
        abort_if($tenantId <= 0, 403);
        return $tenantId;
    }

    private function supportEmail(): string
    {
        return (string) (config('billing.support_email') ?: 'soporte@orbana.mx');
    }

    private function buildBankAccounts(?ProviderProfile $provider): array
    {
        // Normaliza cuentas (1 o 2) al formato esperado por el Blade
        $accounts = [];

        if ($provider) {
            if (!empty($provider->acc1_clabe) || !empty($provider->acc1_account)) {
                $accounts[] = [
                    'id'          => 'acc_1',
                    'label'       => 'Cuenta 1',
                    'bank'        => $provider->acc1_bank ?: '—',
                    'beneficiary' => $provider->acc1_beneficiary ?: '—',
                    'clabe'       => $provider->acc1_clabe ?: '—',
                    'account'     => $provider->acc1_account ?: null,
                    'notes'       => $provider->acc1_notes ?? null,
                ];
            }

            if (!empty($provider->acc2_clabe) || !empty($provider->acc2_account)) {
                $accounts[] = [
                    'id'          => 'acc_2',
                    'label'       => 'Cuenta 2',
                    'bank'        => $provider->acc2_bank ?: '—',
                    'beneficiary' => $provider->acc2_beneficiary ?: '—',
                    'clabe'       => $provider->acc2_clabe ?: '—',
                    'account'     => $provider->acc2_account ?: null,
                    'notes'       => $provider->acc2_notes ?? null,
                ];
            }
        }

        // Fallback a config si no hay provider o no hay cuentas capturadas
        if (count($accounts) === 0) {
            $accounts[] = [
                'id'          => 'acc_1',
                'label'       => 'Cuenta 1',
                'bank'        => config('billing.transfer_bank', '—'),
                'beneficiary' => config('billing.transfer_beneficiary', '—'),
                'clabe'       => config('billing.transfer_clabe', '—'),
                'account'     => config('billing.transfer_account', null),
                'notes'       => config('billing.transfer_notes', null),
            ];
        }

        return $accounts;
    }

    public function create()
    {
        $tenantId = $this->tenantId();

        $provider = ProviderProfile::activeOne();
        $bankAccounts = $this->buildBankAccounts($provider);

        $wallet = app(\App\Services\TenantWalletService::class)->ensureWallet($tenantId);

        $topups = TenantTopup::where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('admin.wallet.topup_create', [
            'wallet'       => $wallet,
            'topups'       => $topups,
            'minTopup'     => 300,
            'provider'     => $provider,
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function store(Request $r)
    {
        $tenantId = $this->tenantId();

        $data = $r->validate([
            'amount' => ['required','numeric','min:200','max:200000'],
        ]);

        $amount = round((float) $data['amount'], 2);

        $externalRef = 'T'.$tenantId.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8));

        /** @var TenantTopup $topup */
        $topup = TenantTopup::create([
            'tenant_id'          => $tenantId,
            'provider'           => 'mercadopago',
            'method'             => null,
            'amount'             => $amount,
            'currency'           => 'MXN',
            'status'             => 'initiated',
            'external_reference' => $externalRef,
            'meta'               => [],
        ]);

        $mpToken   = (string) config('services.mercadopago.token');
        $isSandbox = (bool) config('services.mercadopago.sandbox', false);

        if ($mpToken === '' || !class_exists(\MercadoPago\MercadoPagoConfig::class)) {
            $topup->update([
                'status' => 'failed',
                'meta'   => array_merge((array) $topup->meta, [
                    'error' => 'MercadoPago no configurado (token o SDK v3 no disponible).',
                ]),
            ]);
            return back()->with('error', 'MercadoPago no está configurado (token/SDK).');
        }

        $publicUrl = rtrim((string) config('services.mercadopago.public_url', config('app.url')), '/');
        if ($publicUrl === '' || str_contains($publicUrl, 'localhost')) {
            $topup->update([
                'status' => 'failed',
                'meta'   => array_merge((array) $topup->meta, [
                    'error'      => 'URL pública inválida para MP. Configura MERCADOPAGO_PUBLIC_URL (sin localhost).',
                    'public_url' => $publicUrl,
                ]),
            ]);

            return back()->with('error', 'Configura MERCADOPAGO_PUBLIC_URL con tu URL pública (ngrok/dominio).');
        }

        try {
            \MercadoPago\MercadoPagoConfig::setAccessToken($mpToken);

            $client = new \MercadoPago\Client\Preference\PreferenceClient();

            $preferenceData = [
                'items' => [[
                    'title'       => 'Recarga Wallet Orbana',
                    'quantity'    => 1,
                    'unit_price'  => (float) $amount,
                    'currency_id' => 'MXN',
                ]],
                'external_reference' => $externalRef,
                'back_urls' => [
                    'success' => $publicUrl.'/admin/wallet/topup/return?status=success',
                    'pending' => $publicUrl.'/admin/wallet/topup/return?status=pending',
                    'failure' => $publicUrl.'/admin/wallet/topup/return?status=failure',
                ],
                'auto_return' => 'approved',
                'notification_url' => $publicUrl.'/api/webhooks/mercadopago',
                'payment_methods' => [
                    'installments' => 1,
                ],
            ];

            $preference = $client->create($preferenceData);

            $topup->update([
                'mp_preference_id' => (string) ($preference->id ?? ''),
                'init_point'       => $preference->init_point ?? null,
                'status'           => 'pending',
                'meta'             => array_merge((array) $topup->meta, [
                    'mp' => [
                        'sandbox'            => $isSandbox,
                        'sandbox_init_point' => $preference->sandbox_init_point ?? null,
                        'notification_url'   => $publicUrl.'/api/webhooks/mercadopago',
                    ],
                ]),
            ]);

            return redirect()->route('admin.wallet.topup.checkout', ['topup' => $topup->id]);

        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            $apiResponse = method_exists($e, 'getApiResponse') ? $e->getApiResponse() : null;

            $topup->update([
                'status' => 'failed',
                'meta'   => array_merge((array) $topup->meta, [
                    'mp_api_error' => [
                        'message' => $e->getMessage(),
                        'status'  => $apiResponse?->getStatusCode(),
                        'content' => $apiResponse?->getContent(),
                    ],
                ]),
            ]);

            return back()->with('error', 'Error MercadoPago: '.$e->getMessage());

        } catch (\Throwable $e) {
            $topup->update([
                'status' => 'failed',
                'meta'   => array_merge((array) $topup->meta, [
                    'exception' => $e->getMessage(),
                ]),
            ]);

            return back()->with('error', 'Error al generar link de pago: '.$e->getMessage());
        }
    }

    public function paymentReturn(Request $r)
    {
        return redirect()->route('admin.wallet.topup.create')
            ->with('ok', 'Pago regresó con estado: '.$r->get('status', '—').'. El saldo se acredita al confirmarse por webhook.');
    }

    public function checkout(TenantTopup $topup)
    {
        $tenantId = $this->tenantId();

        abort_if((int)$topup->tenant_id !== $tenantId, 404);

        $preferenceId = $topup->mp_preference_id;
        abort_if(empty($preferenceId), 404);

        $mpPublicKey = (string) config('services.mercadopago.public_key');
        abort_if($mpPublicKey === '', 500, 'MercadoPago public key no configurada.');

        return view('admin.wallet.topup_checkout', [
            'topup'        => $topup,
            'preferenceId' => $preferenceId,
            'mpPublicKey'  => $mpPublicKey,
        ]);
    }

    public function status(TenantTopup $topup)
    {
        $tenantId = $this->tenantId();
        abort_if((int)$topup->tenant_id !== $tenantId, 404);

        $topup->refresh();

        return response()->json([
            'ok'          => true,
            'status'      => $topup->status,
            'mp_status'   => $topup->mp_status,
            'credited_at' => $topup->credited_at ? $topup->credited_at->toDateTimeString() : null,
            'paid_at'     => $topup->paid_at ? $topup->paid_at->toDateTimeString() : null,
        ]);
    }

   public function storeTransferNotice(Request $r)
{
    $tenantId = $this->tenantId();

    Log::info('TRANSFER_NOTICE: incoming', [
        'tenant_id' => $tenantId,
        'user_id'   => Auth::id(),
        'has_file'  => $r->hasFile('proof'),
        'keys'      => array_keys($r->all()),
    ]);

    Log::info('HIT storeTransferNotice', [
  'tenant_id' => $tenantId,
  'has_file'  => $r->hasFile('proof'),
  'payload'   => $r->except(['proof', '_token']),
]);


    $provider = ProviderProfile::activeOne();
    $accounts = $this->buildBankAccounts($provider);

    $data = $r->validate([
        'amount'       => ['required','numeric','min:200','max:500000'],
        'reference'    => ['required','string','max:120'],
        'paid_at'      => ['nullable','date'],
        'account_id'   => ['nullable','string','in:acc_1,acc_2'],
        'account_slot' => ['nullable','in:1,2'],
        'proof'        => ['nullable','file','max:10240','mimes:jpg,jpeg,png,pdf'],
    ]);

    $amount = round((float)$data['amount'], 2);
    $externalRef = 'TB'.$tenantId.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8));

    $accountId = $data['account_id'] ?? null;
    if (!$accountId && !empty($data['account_slot'])) {
        $accountId = ((int)$data['account_slot'] === 2) ? 'acc_2' : 'acc_1';
    }
    $accountId = $accountId ?: 'acc_1';

    $slot = ($accountId === 'acc_2') ? 2 : 1;

    $accountSnap = collect($accounts)->firstWhere('id', $accountId) ?? ($accounts[0] ?? null);

    $proofPath = null;
    if ($r->hasFile('proof')) {
        $proofPath = $r->file('proof')->store("tenant_topups/proofs/tenant_{$tenantId}", 'public');
    }

    Log::info('TRANSFER_NOTICE: before_create', [
        'tenant_id'  => $tenantId,
        'provider'   => 'bank',
        'amount'     => $amount,
        'bank_ref'   => $data['reference'],
        'slot'       => $slot,
        'proof_path' => $proofPath,
    ]);

    try {
        DB::beginTransaction();

        $topup = TenantTopup::create([
            'tenant_id'             => $tenantId,
            'provider'              => 'bank',          // mantenlo así por ahora (para tu SysAdmin)
            'method'                => 'spei',
            'amount'                => $amount,
            'currency'              => 'MXN',
            'status'                => 'pending_review',
            'external_reference'    => $externalRef,
            'bank_ref'              => $data['reference'],
            'deposited_at'          => !empty($data['paid_at']) ? $data['paid_at'] : now(),
            'proof_path'            => $proofPath,
            'provider_account_slot' => $slot,
            'review_status'         => 'pending',
            'meta' => [
                'transfer' => [
                    'account_id'       => $accountId,
                    'account_slot'     => $slot,
                    'account_snapshot' => [
                        'label'       => $accountSnap['label'] ?? null,
                        'bank'        => $accountSnap['bank'] ?? null,
                        'beneficiary' => $accountSnap['beneficiary'] ?? null,
                        'clabe'       => $accountSnap['clabe'] ?? null,
                        'account'     => $accountSnap['account'] ?? null,
                    ],
                ],
                'submitted_by' => [
                    'user_id' => Auth::id(),
                    'email'   => Auth::user()->email,
                    'ip'      => $r->ip(),
                    'ua'      => substr((string)$r->userAgent(), 0, 250),
                ],
            ],
        ]);

        DB::commit();

        Log::info('TRANSFER_NOTICE: created', [
            'topup_id' => $topup->id,
        ]);

    } catch (\Throwable $e) {
        DB::rollBack();

        Log::error('TRANSFER_NOTICE: create_failed', [
            'tenant_id' => $tenantId,
            'error'     => $e->getMessage(),
        ]);

        // Aquí NO “ok”, aquí error real:
        return back()->with('error', 'No se pudo registrar la transferencia: '.$e->getMessage());
    }

    // Mail (no afecta el insert)
    try {
        $support = $this->supportEmail();
        $tenantEmail = (string)(Auth::user()->email ?? '');

        Mail::to($support)->send(new TransferTopupNoticeSubmitted($topup));
        if ($tenantEmail !== '') {
            Mail::to($tenantEmail)->send(new TransferTopupNoticeReceipt($topup));
        }
    } catch (\Throwable $e) {
        Log::warning('TRANSFER_NOTICE: mail_failed', [
            'topup_id' => $topup->id ?? null,
            'error'    => $e->getMessage(),
        ]);
    }

return back()->with('ok', "OK: Folio #{$topup->id} (provider={$topup->provider}, ref={$topup->external_reference})");
}

}
