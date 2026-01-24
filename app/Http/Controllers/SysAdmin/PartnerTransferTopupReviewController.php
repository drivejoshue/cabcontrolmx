<?php
namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\PartnerTopup;
use App\Services\PartnerWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PartnerTransferTopupReviewController extends Controller
{
    public function index(Request $r)
    {
        $status = $r->get('status', '');

        $q = PartnerTopup::query()->where('provider', 'bank');

        if ($status !== '') {
            $q->where('status', $status);
        }

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

       return view('sysadmin.topups.partner_transfer_index', compact('items', 'status'));

    }

    public function show(PartnerTopup $topup)
    {
        abort_if($topup->provider !== 'bank', 404);
       return view('sysadmin.topups.partner_transfer_show', compact('topup'));

    }

    public function approve(Request $r, PartnerTopup $topup)
    {
        abort_if($topup->provider !== 'bank', 404);

        $data = $r->validate([
            'review_notes' => ['nullable','string','max:500'],
        ]);

        try {
            DB::transaction(function () use ($topup, $data) {
                $t = PartnerTopup::whereKey($topup->id)->lockForUpdate()->firstOrFail();

                if ($t->status !== 'pending_review') {
                    return;
                }

                $t->review_status = 'approved';
                $t->review_notes  = $data['review_notes'] ?? null;
                $t->reviewed_by   = Auth::id();
                $t->reviewed_at   = now();
                $t->status        = 'approved';
                $t->paid_at       = $t->paid_at ?? now();
                $t->save();
            });

            // Acreditación financiera (idempotente)
            PartnerWalletService::applyTopup($topup->fresh(), Auth::id());

        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'No se pudo aprobar/acreditar. Revisa logs.');
        }

        return back()->with('ok', 'Transferencia de Partner aprobada y saldo acreditado.');
    }

    public function reject(Request $r, PartnerTopup $topup)
{
    abort_if($topup->provider !== 'bank', 404);

    $data = $r->validate([
        'review_notes' => ['required','string','max:500'],
    ]);

    try {
        DB::transaction(function () use ($topup, $data) {
            $t = PartnerTopup::whereKey($topup->id)->lockForUpdate()->firstOrFail();

            if ($t->status !== 'pending_review') {
                return; // idempotente
            }

            $t->review_status = 'rejected';
            $t->review_notes  = $data['review_notes'];
            $t->reviewed_by   = Auth::id();
            $t->reviewed_at   = now();
            $t->status        = 'rejected';
            $t->save();
        });
    } catch (\Throwable $e) {
        report($e);
        return back()->with('error', 'No se pudo rechazar. Revisa logs.');
    }

    return back()->with('ok', 'Transferencia rechazada.');
}

}
