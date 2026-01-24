<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class PartnerController extends Controller
{
    private function tenantId(): int
    {
        return (int)auth()->user()->tenant_id;
    }

    private function guardOperatingModeOrAbort(): void
    {
        $t = auth()->user()->tenant;
        $mode = $t?->operating_mode ?? 'traditional';

        // Solo habilitar cuando el tenant esté en modo partner
        if (!in_array($mode, ['partner_network','hybrid','whitelabel'], true)) {
            abort(403, 'Este tenant no tiene habilitado el modo Partners.');
        }
    }

    public function index(Request $request)
    {
        $this->guardOperatingModeOrAbort();

        $tenantId = $this->tenantId();
        $q = trim((string)$request->input('q',''));

        $items = Partner::query()
            ->where('tenant_id', $tenantId)
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('name','like', "%{$q}%")
                      ->orWhere('code','like', "%{$q}%")
                      ->orWhere('contact_email','like', "%{$q}%")
                      ->orWhere('contact_phone','like', "%{$q}%");
                });
            })
            ->orderBy('id','desc')
            ->paginate(20)
            ->appends(['q' => $q]);

        return view('admin.partners.index', compact('items','q'));
    }

    public function create()
    {
        $this->guardOperatingModeOrAbort();

        $tenantId = $this->tenantId();

        // Users del tenant para asignar owner existente (opcional)
        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id','name','email']);

        $partner = new Partner();

        return view('admin.partners.create', compact('partner','users'));
    }

    public function store(Request $request)
    {
        $this->guardOperatingModeOrAbort();

        $tenantId = $this->tenantId();

        $data = $request->validate([
            'code' => ['nullable','string','max:32',
                Rule::unique('partners','code')->where(fn($q) => $q->where('tenant_id',$tenantId))
            ],
            'slug' => ['nullable','string','max:160',
                Rule::unique('partners','slug')->where(fn($q) => $q->where('tenant_id',$tenantId))
            ],
            'name' => ['required','string','max:190'],
            'kind' => ['required', Rule::in(['partner','recruiter','affiliate'])],
            'status' => ['required', Rule::in(['active','suspended','closed'])],
            'is_active' => ['nullable','boolean'],

            'contact_name' => ['nullable','string','max:190'],
            'contact_phone' => ['nullable','string','max:50'],
            'contact_email' => ['nullable','email','max:190'],

            'notes' => ['nullable','string'],

            // Alta de owner (opcional): o asignas user existente, o creas uno nuevo
            'owner_user_id' => ['nullable','integer'],
            'owner_email' => ['nullable','email','max:255', Rule::unique('users','email')],
            'owner_name' => ['nullable','string','max:255'],
            'owner_password' => ['nullable','string','min:6'],
        ]);

        // Si no mandan code, lo generamos
        $code = trim((string)($data['code'] ?? ''));
        if ($code === '') {
            $code = $this->generateTenantPartnerCode($tenantId);
        }

       $slug = trim((string)($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug($data['name']);
        }

        DB::beginTransaction();
        try {
            $partner = Partner::create([
                'tenant_id' => $tenantId,

                'code' => $code,
                'slug' => $slug !== '' ? $slug : null,
                'name' => $data['name'],
                'kind' => $data['kind'],
                'status' => $data['status'],
                'is_active' => !empty($data['is_active']) ? 1 : 0,

                'contact_name' => $data['contact_name'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,

                'notes' => $data['notes'] ?? null,

                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            // Owner: user existente
            $ownerUserId = (int)($data['owner_user_id'] ?? 0);
            if ($ownerUserId > 0) {
                $owner = User::where('tenant_id',$tenantId)->where('id',$ownerUserId)->firstOrFail();

                // set default_partner si no tiene
                if (empty($owner->default_partner_id)) {
                    $owner->default_partner_id = $partner->id;
                    $owner->save();
                }

                PartnerUser::updateOrCreate(
                    ['partner_id' => $partner->id, 'user_id' => $owner->id],
                    [
                        'tenant_id' => $tenantId,
                        'role' => 'owner',
                        'is_primary' => 1,
                        'invited_by' => auth()->id(),
                        'invited_at' => now(),
                        'accepted_at' => now(),
                        'revoked_at' => null,
                    ]
                );
            }

            // Owner: user nuevo
            $ownerEmail = trim((string)($data['owner_email'] ?? ''));
            if ($ownerEmail !== '') {
                if (empty($data['owner_password'])) {
                    throw new \RuntimeException('Si capturas owner_email, debes capturar owner_password.');
                }

                $owner = User::create([
                    'tenant_id' => $tenantId,
                    'default_partner_id' => $partner->id,
                    'name' => $data['owner_name'] ?: $partner->name,
                    'email' => $ownerEmail,
                    'password' => Hash::make($data['owner_password']),
                    'role' => \App\Enums\UserRole::NONE,   // partner-only
                    'active' => 1,
                    'is_admin' => 0,
                    'is_dispatcher' => 0,
                    'is_sysadmin' => 0,
                    'email_verified_at' => now(),
                ]);

                PartnerUser::create([
                    'tenant_id' => $tenantId,
                    'partner_id' => $partner->id,
                    'user_id' => $owner->id,
                    'role' => 'owner',
                    'is_primary' => 1,
                    'invited_by' => auth()->id(),
                    'invited_at' => now(),
                    'accepted_at' => now(),
                ]);
            }

            DB::commit();

            return redirect()
                ->route('admin.partners.show', $partner)
                ->with('status', 'Partner creado correctamente.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['create' => $e->getMessage()])->withInput();
        }
    }

   public function show($id)
{
    $tenantId = auth()->user()->tenant_id;

    $partner = DB::table('partners')
        ->where('tenant_id', $tenantId)
        ->where('id', $id)
        ->first();

    abort_if(!$partner, 404);

    $wallet = DB::table('partner_wallets')
        ->where('tenant_id', $tenantId)
        ->where('partner_id', $id)
        ->first();

    $balance = (float)($wallet->balance ?? 0);
    $currency = $wallet->currency ?? 'MXN';

    // Movimientos (stats + últimos)
    $movStats = DB::table('partner_wallet_movements')
        ->where('tenant_id', $tenantId)
        ->where('partner_id', $id)
        ->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN direction="credit" THEN amount ELSE 0 END) as total_credit,
            SUM(CASE WHEN direction="debit"  THEN amount ELSE 0 END) as total_debit
        ')
        ->first();

    $movLastAt = DB::table('partner_wallet_movements')
        ->where('tenant_id', $tenantId)
        ->where('partner_id', $id)
        ->max('created_at');

    $movements = DB::table('partner_wallet_movements')
        ->where('tenant_id', $tenantId)
        ->where('partner_id', $id)
        ->orderByDesc('id')
        ->limit(15)
        ->get();

    // Drivers: asignados vs reclutados (y desglose por status)
    $driversAssigned = DB::table('drivers')
        ->where('tenant_id', $tenantId)
        ->where('partner_id', $id)
        ->count();

    $driversRecruited = DB::table('drivers')
        ->where('tenant_id', $tenantId)
        ->where('recruited_by_partner_id', $id)
        ->count();

    $driversByStatus = DB::table('drivers')
        ->where('tenant_id', $tenantId)
        ->where('partner_id', $id)
        ->selectRaw('status, COUNT(*) c')
        ->groupBy('status')
        ->pluck('c','status');

    // Vehículos: asignados vs reclutados
    $vehiclesAssigned = DB::table('vehicles')
        ->where('tenant_id', $tenantId)
        ->where('partner_id', $id)
        ->count();

    $vehiclesRecruited = DB::table('vehicles')
        ->where('tenant_id', $tenantId)
        ->where('recruited_by_partner_id', $id)
        ->count();

    $vehiclesActive = DB::table('vehicles')
        ->where('tenant_id', $tenantId)
        ->where('partner_id', $id)
        ->where('active', 1)
        ->count();

    // Cargos diarios (resumen)
    $chargesStats = DB::table('partner_daily_charges')
        ->where('tenant_id', $tenantId)
        ->where('partner_id', $id)
        ->selectRaw('
            COUNT(*) as total,
            SUM(amount) as amount_total,
            SUM(unpaid_amount) as unpaid_total
        ')
        ->first();

    $lastChargeAt = DB::table('partner_daily_charges')
        ->where('tenant_id', $tenantId)
        ->where('partner_id', $id)
        ->max('charge_date');

    // Últimos drivers / vehículos (mini listados)
    $lastDrivers = DB::table('drivers')
        ->where('tenant_id', $tenantId)
        ->where('partner_id', $id)
        ->orderByDesc('id')
        ->limit(8)
        ->get(['id','name','phone','status','verification_status','created_at']);

    $lastVehicles = DB::table('vehicles')
        ->where('tenant_id', $tenantId)
        ->where('partner_id', $id)
        ->orderByDesc('id')
        ->limit(8)
        ->get(['id','economico','plate','brand','model','type','active','verification_status','created_at']);

    return view('admin.partners.show', compact(
        'partner','wallet','balance','currency',
        'movStats','movLastAt','movements',
        'driversAssigned','driversRecruited','driversByStatus',
        'vehiclesAssigned','vehiclesRecruited','vehiclesActive',
        'chargesStats','lastChargeAt',
        'lastDrivers','lastVehicles'
    ));
}

    public function edit(Partner $partner)
    {
        $this->guardOperatingModeOrAbort();

        $tenantId = $this->tenantId();
        abort_unless($partner->tenant_id === $tenantId, 404);

        return view('admin.partners.edit', compact('partner'));
    }

    public function update(Request $request, Partner $partner)
    {
        $this->guardOperatingModeOrAbort();

        $tenantId = $this->tenantId();
        abort_unless($partner->tenant_id === $tenantId, 404);

        $data = $request->validate([
            'code' => ['required','string','max:32',
                Rule::unique('partners','code')
                    ->where(fn($q) => $q->where('tenant_id',$tenantId))
                    ->ignore($partner->id)
            ],
            'slug' => ['nullable','string','max:160',
                Rule::unique('partners','slug')
                    ->where(fn($q) => $q->where('tenant_id',$tenantId))
                    ->ignore($partner->id)
            ],
            'name' => ['required','string','max:190'],
            'kind' => ['required', Rule::in(['partner','recruiter','affiliate'])],
            'status' => ['required', Rule::in(['active','suspended','closed'])],
            'is_active' => ['nullable','boolean'],

            'contact_name' => ['nullable','string','max:190'],
            'contact_phone' => ['nullable','string','max:50'],
            'contact_email' => ['nullable','email','max:190'],

            'notes' => ['nullable','string'],
        ]);

        $slug = trim((string)($data['slug'] ?? ''));
        if ($slug !== '') $slug = Str::slug($slug);

        $partner->update([
            'code' => $data['code'],
            'slug' => $slug !== '' ? $slug : null,
            'name' => $data['name'],
            'kind' => $data['kind'],
            'status' => $data['status'],
            'is_active' => !empty($data['is_active']) ? 1 : 0,

            'contact_name' => $data['contact_name'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,

            'notes' => $data['notes'] ?? null,

            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('admin.partners.edit', $partner)
            ->with('status', 'Partner actualizado.');
    }

    public function destroy(Partner $partner)
    {
        $this->guardOperatingModeOrAbort();

        $tenantId = $this->tenantId();
        abort_unless($partner->tenant_id === $tenantId, 404);

        $partner->delete(); // soft delete

        return redirect()
            ->route('admin.partners.index')
            ->with('status', 'Partner eliminado (soft delete).');
    }

    private function generateTenantPartnerCode(int $tenantId): string
    {
        // Ej: P-1-000123 (incremental simple con fallback)
        $base = 'P-'.$tenantId.'-';
        $n = (int)Partner::where('tenant_id',$tenantId)->max('id');
        $cand = $base . str_pad((string)($n+1), 6, '0', STR_PAD_LEFT);

        // Si por alguna razón existe, agrega random
        if (Partner::where('tenant_id',$tenantId)->where('code',$cand)->exists()) {
            $cand = $base . strtoupper(Str::random(6));
        }
        return $cand;
    }
}
