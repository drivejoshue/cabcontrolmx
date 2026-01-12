<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\TenantTopup;
use App\Services\TenantWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\TransferTopupApprovedMail;
use App\Mail\TransferTopupRejectedMail;

class TransferTopupReviewController extends Controller
{
   public function index(Request $r)
{
    $status = $r->get('status', ''); // default: TODAS

    $q = TenantTopup::query()
        ->where('provider', 'bank');

    // Filtro opcional (si el usuario elige uno)
    if ($status !== '') {
        $q->where('status', $status);
    }

    // Orden: pendientes arriba, luego rechazadas/acreditadas, y dentro de cada grupo por id desc
    $q->orderByRaw("
        CASE
            WHEN status = 'pending_review' THEN 0
            WHEN status = 'rejected' THEN 1
            WHEN status = 'credited' THEN 2
            WHEN status = 'approved' THEN 3
            ELSE 9
        END ASC
    ")->orderByDesc('id');

    $items = $q->paginate(50)->withQueryString();

    return view('sysadmin.topups.transfer_index', compact('items', 'status'));
}


    public function show(TenantTopup $topup)
    {
        abort_if($topup->provider !== 'bank', 404);
        return view('sysadmin.topups.transfer_show', compact('topup'));
    }

    public function approve(Request $r, TenantTopup $topup, TenantWalletService $walletService)
    {
        abort_if($topup->provider !== 'bank', 404);

        $data = $r->validate([
            'review_notes' => ['nullable','string','max:500'],
        ]);

        // 1) Parte crítica: DB + wallet
        try {
            DB::transaction(function () use ($topup, $walletService, $data) {
                $topup->refresh();
                if ($topup->status !== 'pending_review') return; // idempotente

                $topup->update([
                    'review_status' => 'approved',
                    'review_notes'  => $data['review_notes'] ?? null,
                    'reviewed_by'   => Auth::id(),
                    'reviewed_at'   => now(),
                    'paid_at'       => $topup->paid_at ?? now(),
                    'status'        => 'approved',
                ]);

                // Moneda robusta
                $currency = 'MXN';
                if (isset($topup->currency)) {
                    $currency = is_string($topup->currency)
                        ? $topup->currency
                        : (is_array($topup->currency) ? ($topup->currency['code'] ?? 'MXN') : 'MXN');
                }

                $notes = 'Aprobado por SysAdmin. Ref: ' . ($topup->bank_ref ?? $topup->external_reference ?? '—');

                $walletService->creditTopup(
                    (int) $topup->tenant_id,
                    (float) $topup->amount,
                    (string) $topup->external_reference,
                    $notes,
                    $currency
                );

                $topup->update([
                    'status'      => 'credited',
                    'credited_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'No se pudo aprobar/acreditar. Revisa logs del servidor.');
        }

        // 2) Correo: best-effort (NO rompe flujo)
        try {
            $topup->refresh();
            $to = data_get($topup->meta, 'submitted_by.email') ?: optional($topup->tenant)->email;

            if ($to) {
                Mail::to($to)->send(new TransferTopupApprovedMail($topup));
            }
        } catch (\Throwable $e) {
            report($e);
            // No fallar el flujo: solo warning
            return back()->with('warning', 'Transferencia aprobada y saldo acreditado, pero no se pudo enviar el correo.');
        }

        return back()->with('ok', 'Transferencia aprobada y saldo acreditado.');
    }

    public function reject(Request $r, TenantTopup $topup)
    {
        abort_if($topup->provider !== 'bank', 404);

        $data = $r->validate([
            'review_notes' => ['required','string','max:500'],
        ]);

        // 1) Parte crítica: update de estado
        try {
            $topup->refresh();
            if ($topup->status !== 'pending_review') {
                return back()->with('warning', 'Ya no está pendiente.');
            }

            $topup->update([
                'review_status' => 'rejected',
                'review_notes'  => $data['review_notes'],
                'reviewed_by'   => Auth::id(),
                'reviewed_at'   => now(),
                'status'        => 'rejected',
            ]);
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'No se pudo rechazar la transferencia. Revisa logs del servidor.');
        }

        // 2) Correo: best-effort (NO rompe flujo)
        try {
            $to = data_get($topup->meta, 'submitted_by.email') ?: optional($topup->tenant)->email;

            if ($to) {
                Mail::to($to)->send(new TransferTopupRejectedMail($topup));
            }
        } catch (\Throwable $e) {
            report($e);
            return back()->with('warning', 'Transferencia rechazada, pero no se pudo enviar el correo.');
        }

        return back()->with('ok', 'Transferencia rechazada.');
    }
}
