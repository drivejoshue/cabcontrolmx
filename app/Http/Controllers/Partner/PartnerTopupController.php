<?php
namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerTopup;
use App\Models\ProviderProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class PartnerTopupController extends Controller
{
    private function tenantId(): int
    {
        $tenantId = (int)(Auth::user()->tenant_id ?? 0);
        abort_if($tenantId <= 0, 403);
        return $tenantId;
    }

    private function partnerId(): int
    {
        $partnerId = (int) session('partner_id');
        abort_if($partnerId <= 0, 403, 'Falta contexto de partner (session partner_id).');
        return $partnerId;
    }




    private function supportEmail(): string
    {
        return (string) (config('billing.support_email') ?: 'soporte@orbana.mx');
    }



    private function buildBankAccounts(?ProviderProfile $provider): array
    {
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

        // Fallback si aún no capturan provider profile
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


 public function index(Request $r)
    {
        $tenantId  = (int) auth()->user()->tenant_id;
        $partnerId = (int) session('partner_id');

        abort_if($partnerId <= 0, 403, 'Falta contexto de partner (session partner_id).');

        $items = PartnerTopup::query()
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('partner.topups.index', compact('items'));
    }

    public function create()
    {
        $tenantId  = (int) auth()->user()->tenant_id;
        $partnerId = (int) session('partner_id');

        abort_if($partnerId <= 0, 403, 'Falta contexto de partner (session partner_id).');

        $partner = Partner::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($partnerId)
            ->firstOrFail();

        /**
         * Cuentas bancarias:
         * Ideal: reutiliza EXACTAMENTE la misma fuente que en tenant.
         * - si ya lo tienes en config/orbanamx.php -> úsalo aquí
         * - si lo tienes en DB -> cámbialo aquí por tu query real
         *
         * Estructura esperada por el Blade:
         * [
         *   ['slot'=>'0','label'=>'Cuenta 1','beneficiary'=>'...','bank'=>'...','clabe'=>'...','account'=>null,'notes'=>null],
         *   ...
         * ]
         */
         $provider = ProviderProfile::query()
            ->where('active', 1)
            ->orderByDesc('id')
            ->first();

        if (!$provider) {
            $provider = ProviderProfile::query()->orderByDesc('id')->first();
        }

        $accounts = $this->buildBankAccounts($provider);

        $supportEmail = $this->supportEmail();


        // Referencia sugerida: estable, legible
        $suggestedRef = 'ORBANA-P' . $partnerId . '-T' . $tenantId;

        // Por compat con tu Blade (tenant id)
        $tid = $tenantId;

        return view('partner.topups.create', compact(
            'partner',
            'accounts',
            'supportEmail',
            'suggestedRef',
            'tid'
        ));
    }

public function store(Request $r)
{
    $tenantId  = $this->tenantId();
    $partnerId = $this->partnerId();

    $data = $r->validate([
        'amount'                => ['required','numeric','min:50','max:50000'],
        'method'                => ['required','in:transfer'],
        // del form llega acc_1 / acc_2
        'provider_account_slot' => ['required','in:acc_1,acc_2'],
        'bank_ref'              => ['nullable','string','max:190'],
        'proof'                 => ['nullable','file','max:4096','mimes:jpg,jpeg,png,pdf'],
    ]);

    // acc_1/acc_2 -> 1/2 (DB espera tinyint)
    $slotMap = [
        'acc_1' => 1,
        'acc_2' => 2,
    ];
    $slotInt = $slotMap[$data['provider_account_slot']] ?? null;
    abort_if(!$slotInt, 422, 'Cuenta destino inválida.');

    $proofPath = null;
    if ($r->hasFile('proof')) {
        $proofPath = $r->file('proof')->store('partner_topups', 'public');
    }

    $externalRef = 'PT-' . strtoupper(Str::random(12));

    $topup = PartnerTopup::create([
        'tenant_id'  => $tenantId,
        'partner_id' => $partnerId,

        'provider' => 'bank',
        'method'   => 'transfer',

        // ✅ tinyint en BD
        'provider_account_slot' => $slotInt,

        'amount'   => round((float) $data['amount'], 2),
        'currency' => 'MXN',

        'bank_ref'   => $data['bank_ref'] ?? null,
        'proof_path' => $proofPath,

        'status'        => 'pending_review',
        'review_status' => null,
        'review_notes'  => null,

        'external_reference' => $externalRef,

        // útil para auditoría / UI
        'meta' => [
            'source' => 'partner_portal',
            'suggested_ref' => "ORBANA-P{$partnerId}-T{$tenantId}",
            'provider_account_slot' => $data['provider_account_slot'], // acc_1/acc_2 (texto)
            'submitted_by' => [
                'user_id' => auth()->id(),
                'email'   => auth()->user()->email ?? null,
                'name'    => auth()->user()->name ?? null,
            ],
        ],
    ]);

    return redirect()
        ->route('partner.topups.show', $topup)
        ->with('ok', 'Recarga enviada a revisión.');
}



    public function show(PartnerTopup $topup)
    {
        $tenantId  = (int) auth()->user()->tenant_id;
        $partnerId = (int) session('partner_id');

        abort_if((int) $topup->tenant_id !== $tenantId || (int) $topup->partner_id !== $partnerId, 404);

        return view('partner.topups.show', compact('topup'));
    }

    public function resubmit(Request $r, PartnerTopup $topup)
    {
        $tenantId  = (int) auth()->user()->tenant_id;
        $partnerId = (int) session('partner_id');

        abort_if((int) $topup->tenant_id !== $tenantId || (int) $topup->partner_id !== $partnerId, 404);
        abort_if(strtolower((string) $topup->status) !== 'rejected', 404);

        $data = $r->validate([
            'bank_ref' => ['nullable', 'string', 'max:190'],
            'proof'    => ['required', 'file', 'max:4096', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $proofPath = $r->file('proof')->store('partner_topups', 'public');

        $meta = is_array($topup->meta)
            ? $topup->meta
            : (array) ($topup->meta ? json_decode((string) $topup->meta, true) : []);

        $meta['resubmitted_at'] = now()->toDateTimeString();

        $topup->update([
            'bank_ref'      => $data['bank_ref'] ?? $topup->bank_ref,
            'proof_path'    => $proofPath,
            'status'        => 'pending_review',
            'review_status' => null,
            'review_notes'  => null,
            'reviewed_by'   => null,
            'reviewed_at'   => null,
            'meta'          => $meta,
        ]);

        return back()->with('ok', 'Comprobante reenviado. Quedó nuevamente en revisión.');
    }
}
