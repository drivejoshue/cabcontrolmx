<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartnerTopup;
use App\Services\PartnerWalletService;
use Illuminate\Http\Request;

class PartnerTopupAdminController extends Controller
{
public function index(Request $request)
{
    $tenantId = (int) auth()->user()->tenant_id;

    $status    = trim((string) $request->get('status',''));
    $partnerId = (int) $request->get('partner_id', 0);
    $q         = trim((string) $request->get('q',''));

    $partners = \App\Models\Partner::query()
        ->where('tenant_id', $tenantId)
        ->orderBy('name')
        ->get(['id','name']);

    $items = \App\Models\PartnerTopup::query()
        ->from('partner_topups') // opcional pero deja claro el alias base
        ->where('partner_topups.tenant_id', $tenantId)
        ->when($status !== '', fn($qb) => $qb->where('partner_topups.status', $status))
        ->when($partnerId > 0, fn($qb) => $qb->where('partner_topups.partner_id', $partnerId))
        ->when($q !== '', function ($qb) use ($q) {
            $qb->where(function ($w) use ($q) {
                $w->where('partner_topups.bank_ref','like',"%{$q}%")
                  ->orWhere('partner_topups.external_reference','like',"%{$q}%")
                  ->orWhere('partner_topups.amount','like',"%{$q}%");
            });
        })
        ->leftJoin('partners as p', function ($j) use ($tenantId) {
            $j->on('p.id','=','partner_topups.partner_id')
              ->where('p.tenant_id','=',$tenantId);
        })
        ->select('partner_topups.*','p.name as partner_name')
        ->orderByDesc('partner_topups.id')
        ->paginate(20)
        ->withQueryString();

    return view('admin.partner_topups.index', compact('items','partners','status','partnerId','q'));
}


    public function show(Request $request, int $id)
    {
        $tenantId = (int)($request->user()->tenant_id ?? 0);

        $topup = PartnerTopup::where('tenant_id', $tenantId)->where('id', $id)->firstOrFail();
        return view('admin.partner_topups.show', compact('topup'));
    }

    public function approve(Request $request, int $id)
    {
        $tenantId = (int)($request->user()->tenant_id ?? 0);

        $topup = PartnerTopup::where('tenant_id', $tenantId)->where('id', $id)->firstOrFail();

        // marca revisión
        $topup->reviewed_by = $request->user()->id;
        $topup->reviewed_at = now();
        $topup->review_status = 'approved';
        $topup->status = 'approved';
        $topup->save();

        // acredita (idempotente)
        PartnerWalletService::applyTopup($topup, $request->user()->id);

        return redirect()->route('admin.partner_topups.show', $topup->id)->with('success', 'Topup aprobado y acreditado.');
    }

    public function reject(Request $request, int $id)
    {
        $tenantId = (int)($request->user()->tenant_id ?? 0);

        $data = $request->validate([
            'review_notes' => ['nullable','string','max:500'],
        ]);

        $topup = PartnerTopup::where('tenant_id', $tenantId)->where('id', $id)->firstOrFail();

        $topup->reviewed_by = $request->user()->id;
        $topup->reviewed_at = now();
        $topup->review_status = 'rejected';
        $topup->review_notes = $data['review_notes'] ?? null;
        $topup->status = 'rejected';
        $topup->save();

        return redirect()->route('admin.partner_topups.show', $topup->id)->with('success', 'Topup rechazado.');
    }
}
