<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TenantTopup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;

class TenantWalletTopupController extends Controller
{
    public function create()
    {
        $tenantId = (int)(Auth::user()->tenant_id ?? 0);
        abort_if($tenantId <= 0, 403);

        $wallet = app(\App\Services\TenantWalletService::class)->ensureWallet($tenantId);

        $topups = TenantTopup::where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('admin.wallet.topup_create', [
            'wallet'   => $wallet,
            'topups'   => $topups,
            'minTopup' => 300,
        ]);
    }

    public function store(Request $r)
    {
        $tenantId = (int) (Auth::user()->tenant_id ?? 0);
        abort_if($tenantId <= 0, 403);

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

        // URL pública para MP (ngrok o dominio)
        $publicUrl = rtrim((string) config('services.mercadopago.public_url', config('app.url')), '/');
        if ($publicUrl === '' || str_contains($publicUrl, 'localhost')) {
            // Esto evita que te quedes con URLs inválidas para MP
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

                // IMPORTANTE: apunta al API webhook (sin CSRF)
                'notification_url' => $publicUrl.'/api/webhooks/mercadopago',

                'payment_methods' => [
                    'installments' => 1,
                ],
            ];

            $preference = $client->create($preferenceData);

            $initPoint        = $preference->init_point ?? null;
            $sandboxInitPoint = $preference->sandbox_init_point ?? null;

            $topup->update([
                'mp_preference_id' => (string) ($preference->id ?? ''),
                'init_point'       => $initPoint,
                'status'           => 'pending',
                'meta'             => array_merge((array) $topup->meta, [
                    'mp' => [
                        'sandbox'            => $isSandbox,
                        'sandbox_init_point' => $sandboxInitPoint,
                        'notification_url'   => $publicUrl.'/api/webhooks/mercadopago',
                    ],
                ]),
            ]);

            // Para Bricks ya no usamos el init_point aquí, solo vamos a una vista interna
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
            ->with('ok', 'Pago regresó con estado: ' . $r->get('status', '—') . '. El saldo se acredita al confirmarse por webhook.');
    }

    public function checkout(TenantTopup $topup)
    {
        $tenantId = (int)(Auth::user()->tenant_id ?? 0);
        abort_if($tenantId <= 0, 403);

        // Asegura que el topup pertenezca al tenant actual
        abort_if((int)$topup->tenant_id !== $tenantId, 404);

        // Para Bricks necesitamos el preference_id y la public key
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
        $tenantId = (int)(Auth::user()->tenant_id ?? 0);
        abort_if($tenantId <= 0, 403);
        abort_if((int)$topup->tenant_id !== $tenantId, 404);

        // Refrescamos desde DB
        $topup->refresh();

        return response()->json([
            'ok'          => true,
            'status'      => $topup->status,
            'mp_status'   => $topup->mp_status,
            'credited_at' => $topup->credited_at ? $topup->credited_at->toDateTimeString() : null,
            'paid_at'     => $topup->paid_at ? $topup->paid_at->toDateTimeString() : null,
        ]);
    }
}
