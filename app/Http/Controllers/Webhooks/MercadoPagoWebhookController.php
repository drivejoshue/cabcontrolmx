<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\TenantTopup;
use App\Services\TenantWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;

class MercadoPagoWebhookController extends Controller
{
    public function handle(Request $request, TenantWalletService $wallet)
    {
        // Permite ping GET
        if ($request->isMethod('get')) {
            return response()->json(['ok' => true]);
        }

        // 1) Resolver paymentId
        $paymentId = $request->input('data.id')
            ?? $request->input('id')
            ?? $request->query('data.id')
            ?? $request->query('id')
            ?? null;

        Log::info('MP webhook received', [
            'method'       => $request->method(),
            'type'         => $request->input('type'),
            'action'       => $request->input('action'),
            'payment_id'   => $paymentId,
            'live_mode'    => $request->input('live_mode'),
            'x_signature'  => $request->header('x-signature'),
            'x_request_id' => $request->header('x-request-id'),
        ]);

        if (!$paymentId) {
            return response()->json(['ok' => true]);
        }

        // 2) Firma: en local NO bloquees si faltan headers; en prod sí conviene exigir.
        $mustEnforce = app()->environment('production');
        if (!$this->verifySignatureIfPossible($request, (string)$paymentId, $mustEnforce)) {
            Log::warning('MP webhook signature invalid', [
                'payment_id'   => (string)$paymentId,
                'x_request_id' => $request->header('x-request-id'),
            ]);
            return response()->json(['ok' => false, 'error' => 'invalid_signature'], 401);
        }

        // 3) Token
        $token = (string) config('services.mercadopago.token');
        if ($token === '') {
            Log::warning('MP webhook: missing access token');
            return response()->json(['ok' => true]);
        }

        // Si MP manda un ID de prueba NO numérico, no intentes consultar API
        if (!ctype_digit((string)$paymentId)) {
            return response()->json(['ok' => true]);
        }

        try {
            MercadoPagoConfig::setAccessToken($token);

            $client  = new PaymentClient();
            $payment = $client->get((int)$paymentId);

            // Si por alguna razón no vino
            if (!$payment) return response()->json(['ok' => true]);

            $externalRef = $payment->external_reference ?? null;
            if (!$externalRef) return response()->json(['ok' => true]);

            $topup = TenantTopup::where('external_reference', $externalRef)->first();
            if (!$topup) return response()->json(['ok' => true]);

            $status        = (string) ($payment->status ?? '');
            $statusDetail  = (string) ($payment->status_detail ?? '');
            $paymentTypeId = $payment->payment_type_id ?? null;
            $methodId      = $payment->payment_method_id ?? null;
            $approvedAt    = $payment->date_approved ?? null;

            $topup->update([
                'mp_payment_id'    => (string)($payment->id ?? $paymentId),
                'mp_status'        => $status ?: null,
                'mp_status_detail' => $statusDetail ?: null,
                'method'           => $paymentTypeId,
                'paid_at'          => $approvedAt,
                'meta'             => array_merge((array)($topup->meta ?? []), [
                    'mp_payment' => [
                        'status'             => $status,
                        'status_detail'      => $statusDetail,
                        'type'               => $paymentTypeId,
                        'method'             => $methodId,
                        'transaction_amount' => $payment->transaction_amount ?? null,
                        'currency_id'        => $payment->currency_id ?? null,
                    ],
                ]),
            ]);

            if ($status === 'approved') {
                if ($topup->credited_at === null) {
                    $wallet->creditTopup(
                        tenantId: (int) $topup->tenant_id,
                        amount: (float) $topup->amount,
                        externalRef: 'mp:' . (string) $paymentId,
                        notes: 'Recarga MercadoPago',
                        currency: $topup->currency ?? 'MXN',
                    );

                    $topup->update([
                        'status'      => 'approved',
                        'credited_at' => now(),
                    ]);
                } else {
                    if (($topup->status ?? '') !== 'approved') {
                        $topup->update(['status' => 'approved']);
                    }
                }
            } else {
                $topup->update([
                    'status' => $status ?: ($topup->status ?? 'pending'),
                ]);
            }

        } catch (MPApiException $e) {
            // Esto es EXACTAMENTE lo que te está pasando con el ID dummy.
            // No rompas la prueba: loguea y responde 200.
            $apiResponse = method_exists($e, 'getApiResponse') ? $e->getApiResponse() : null;

            Log::error('MP webhook MPApiException', [
                'payment_id' => (string)$paymentId,
                'message'    => $e->getMessage(),
                'status'     => $apiResponse?->getStatusCode(),
                'content'    => $apiResponse?->getContent(),
            ]);

            return response()->json(['ok' => true]);

        } catch (\Throwable $e) {
            Log::error('MP webhook error', [
                'payment_id' => (string)$paymentId,
                'error'      => $e->getMessage(),
            ]);
            return response()->json(['ok' => true]);
        }

        return response()->json(['ok' => true]);
    }

    private function verifySignatureIfPossible(Request $request, string $dataId, bool $mustEnforce): bool
    {
        $secret = (string) config('services.mercadopago.webhook_secret');
        $isSandbox = (bool) config('services.mercadopago.sandbox', false);
        if ($isSandbox) return true;
        // Si no hay secret configurado: en dev permitimos; en prod exigir.
        if ($secret === '') return !$mustEnforce;

        $xSignature = (string) $request->header('x-signature', '');
        $xRequestId = (string) $request->header('x-request-id', '');

        // Si faltan headers: en dev permitimos; en prod rechazamos.
        if ($xSignature === '' || $xRequestId === '') return !$mustEnforce;

        $ts = null; $hash = null;
        foreach (explode(',', $xSignature) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($k === 'ts') $ts = $v;
            if ($k === 'v1') $hash = $v;
        }
        if (!$ts || !$hash) return false;

        $manifest = "id:{$dataId};request-id:{$xRequestId};ts:{$ts};";
        $calc = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($calc, $hash);
    }
}
