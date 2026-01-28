<?php

namespace App\Http\Controllers\Partner;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Models\Tenant;
use App\Services\TenantBillingService;
use App\Services\PartnerBillingUIService;

class PartnerVehicleController extends BasePartnerController
{
    private const VEHICLE_TYPES = ['sedan', 'vagoneta', 'van', 'premium'];

    private function allowedYearMin(): int { return now()->year - 9; }
    private function allowedYearMax(): int { return now()->year; }

    private function findVehicleOr404(int $tenantId, int $partnerId, int $id)
    {
        $v = DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->where('id', $id)
            ->first();

        abort_if(!$v, 404);
        return $v;
    }

    public function index(Request $r)
    {
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();
        $q = trim($r->get('q',''));

        $vehicles = DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->when($q, function($qq) use ($q){
                $qq->where(function($w) use ($q){
                    $w->where('economico','like',"%$q%")
                      ->orWhere('plate','like',"%$q%")
                      ->orWhere('brand','like',"%$q%")
                      ->orWhere('model','like',"%$q%");
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('partner.vehicles.index', compact('vehicles','q'));
    }

    public function create()
    {
        $tenantId = $this->tenantId();
        $tenant = Tenant::with('billingProfile')->findOrFail($tenantId);

        // Gate a nivel tenant (se mantiene)
        [$canRegister, $billingMessage] = app(TenantBillingService::class)->canRegisterNewVehicle($tenant);

        $vehicleCatalog = DB::table('vehicle_catalog')
            ->where('active', 1)
            ->orderBy('brand')->orderBy('model')
            ->get();

        $years = range($this->allowedYearMax(), $this->allowedYearMin());

        return view('partner.vehicles.create', [
            'v' => null,
            'tenant' => $tenant,
            'canRegister' => $canRegister,
            'billingMessage' => $billingMessage,
            'vehicleCatalog' => $vehicleCatalog,
            'years' => $years,
        ]);
    }

    public function store(Request $r)
    {
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        $minYear = $this->allowedYearMin();
        $maxYear = $this->allowedYearMax();

       $maxFotoKb = 4096; // 4 MB (ajusta a lo que quieras)
        $data = $r->validate([
            'economico'  => ['required','string','max:20'],
            'plate'      => ['required','string','max:20'],
            'type'       => ['required', Rule::in(self::VEHICLE_TYPES)],
            'capacity'   => ['nullable','integer','min:1','max:10'],
            'color'      => ['nullable','string','max:40'],
            'year'       => ["nullable","integer","min:$minYear","max:$maxYear"],
            'policy_id'  => ['nullable','string','max:60'],

            // ✅ Foto: image + tipos permitidos + tamaño
            'foto'       => ['nullable','file','image','mimes:jpg,jpeg,png,webp','max:'.$maxFotoKb],

            'catalog_id' => ['nullable','integer','exists:vehicle_catalog,id'],
            'brand'      => ['nullable','string','max:60'],
            'model'      => ['nullable','string','max:80'],
        ], [
            'foto.image' => 'La foto debe ser una imagen válida.',
            'foto.mimes' => 'La foto debe ser JPG, PNG o WEBP.',
            'foto.max'   => 'La foto supera el tamaño máximo permitido (4 MB).',
        ]);


        // Unique per tenant
        if (DB::table('vehicles')->where('tenant_id',$tenantId)->where('economico',$data['economico'])->exists()) {
            return back()->withErrors(['economico' => 'Ya existe ese número económico en la central.'])->withInput();
        }
        if (DB::table('vehicles')->where('tenant_id',$tenantId)->where('plate',$data['plate'])->exists()) {
            return back()->withErrors(['plate' => 'Ya existe esa placa en la central.'])->withInput();
        }

        // Catalog reinforce
        if (!empty($data['catalog_id'])) {
            $cat = DB::table('vehicle_catalog')->where('id',$data['catalog_id'])->first();
            if ($cat) { $data['brand']=$cat->brand; $data['model']=$cat->model; }
        }

        $fotoPath = null;
        if ($r->hasFile('foto')) {
            $fotoPath = $r->file('foto')->store('vehicles','public');
        }

        $newId = null;

        try {
            DB::transaction(function () use ($tenantId, $partnerId, $data, $fotoPath, &$newId) {

                $newId = DB::table('vehicles')->insertGetId([
                    'tenant_id' => $tenantId,

                    // partner ownership
                    'partner_id' => $partnerId,
                    'recruited_by_partner_id' => $partnerId,
                    'partner_assigned_at' => null,   // <- se setea al ACTIVAR
                    'partner_left_at' => null,

                    'economico' => $data['economico'],
                    'plate' => $data['plate'],
                    'brand' => $data['brand'] ?? null,
                    'model' => $data['model'] ?? null,
                    'type' => $data['type'],
                    'color' => $data['color'] ?? null,
                    'year' => $data['year'] ?? null,
                    'capacity' => $data['capacity'] ?? 4,
                    'policy_id' => $data['policy_id'] ?? null,
                    'foto_path' => $fotoPath,

                    'active' => 0, // <- draft, NO cobrable

                    // verification defaults
                    'verification_status' => 'pending',
                    'verification_notes' => null,
                    'verified_by' => null,
                    'verified_at' => null,

                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // ❌ NO billing aquí (draft)
            });
        } catch (\Throwable $e) {
            if ($fotoPath && Storage::disk('public')->exists($fotoPath)) {
                Storage::disk('public')->delete($fotoPath);
            }
            return back()->withErrors(['vehicle' => $e->getMessage()])->withInput();
        }

        return redirect()->route('partner.vehicles.documents.index', ['id'=>$newId])
            ->with('ok','Vehículo creado (borrador). Siguiente paso: subir documentos.');
    }

 public function show(int $id)
{
    $tenantId  = $this->tenantId();
    $partnerId = $this->partnerId();

    $v = $this->findVehicleOr404($tenantId, $partnerId, $id);

    // Vigentes (end_at NULL)
    $currentDrivers = DB::table('driver_vehicle_assignments as a')
        ->join('drivers as d', function ($j) use ($tenantId) {
            $j->on('d.id', '=', 'a.driver_id')
              ->where('d.tenant_id', '=', $tenantId);
        })
        ->where('a.tenant_id', $tenantId)
        ->where('a.vehicle_id', (int)$v->id)
        ->whereNull('a.end_at')
        ->orderByDesc('a.start_at')
        ->select([
            'a.id as assignment_id',
            'a.driver_id',
            'a.start_at',
            'd.name',
            'd.phone',
            'd.foto_path',
        ])
        ->get();

    // Histórico (end_at NOT NULL)
    $assignmentHistory = DB::table('driver_vehicle_assignments as a')
        ->join('drivers as d', function ($j) use ($tenantId) {
            $j->on('d.id', '=', 'a.driver_id')
              ->where('d.tenant_id', '=', $tenantId);
        })
        ->where('a.tenant_id', $tenantId)
        ->where('a.vehicle_id', (int)$v->id)
        ->orderByDesc(DB::raw('COALESCE(a.end_at, a.start_at)'))
        ->select([
            'a.id as assignment_id',
            'a.driver_id',
            'a.start_at',
            'a.end_at',
            'd.name',
            'd.phone',
            'd.foto_path',
        ])
        ->limit(200)
        ->get();

    return view('partner.vehicles.show', compact('v','currentDrivers','assignmentHistory'));
}



    public function edit(int $id)
    {
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();
        $v = $this->findVehicleOr404($tenantId, $partnerId, $id);

        $vehicleCatalog = DB::table('vehicle_catalog')
            ->where('active', 1)
            ->orderBy('brand')->orderBy('model')
            ->get();

        $years = range($this->allowedYearMax(), $this->allowedYearMin());

        return view('partner.vehicles.edit', compact('v','vehicleCatalog','years'));
    }

    public function update(Request $r, int $id)
    {
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        $v = $this->findVehicleOr404($tenantId, $partnerId, $id);

        $minYear = $this->allowedYearMin();
        $maxYear = $this->allowedYearMax();

        $data = $r->validate([
            'economico'  => ['required','string','max:20'],
            'plate'      => ['required','string','max:20'],
            'type'       => ['required', Rule::in(self::VEHICLE_TYPES)],
            'capacity'   => ['nullable','integer','min:1','max:10'],
            'color'      => ['nullable','string','max:40'],
            'year'       => ["nullable","integer","min:$minYear","max:$maxYear"],
            'policy_id'  => ['nullable','string','max:60'],
            'foto'       => ['nullable','image','max:2048'],
            'catalog_id' => ['nullable','integer','exists:vehicle_catalog,id'],
            'brand'      => ['nullable','string','max:60'],
            'model'      => ['nullable','string','max:80'],
            // 👇 NOTA: NO active aquí. Active se controla por activate/suspend.
        ]);

        if (DB::table('vehicles')->where('tenant_id',$tenantId)->where('economico',$data['economico'])->where('id','<>',$id)->exists()) {
            return back()->withErrors(['economico'=>'Ya existe otro vehículo con ese económico.'])->withInput();
        }
        if (DB::table('vehicles')->where('tenant_id',$tenantId)->where('plate',$data['plate'])->where('id','<>',$id)->exists()) {
            return back()->withErrors(['plate'=>'Ya existe otro vehículo con esa placa.'])->withInput();
        }

        if (!empty($data['catalog_id'])) {
            $cat = DB::table('vehicle_catalog')->where('id',$data['catalog_id'])->first();
            if ($cat) { $data['brand']=$cat->brand; $data['model']=$cat->model; }
        }

        $fotoPath = $v->foto_path;
        if ($r->hasFile('foto')) {
            if ($fotoPath && Storage::disk('public')->exists($fotoPath)) {
                Storage::disk('public')->delete($fotoPath);
            }
            $fotoPath = $r->file('foto')->store('vehicles','public');
        }

        DB::table('vehicles')
            ->where('tenant_id',$tenantId)
            ->where('partner_id',$partnerId)
            ->where('id',$id)
            ->update([
                'economico' => $data['economico'],
                'plate' => $data['plate'],
                'brand' => $data['brand'] ?? null,
                'model' => $data['model'] ?? null,
                'type' => $data['type'],
                'color' => $data['color'] ?? null,
                'year' => $data['year'] ?? null,
                'capacity' => $data['capacity'] ?? 4,
                'policy_id' => $data['policy_id'] ?? null,
                'foto_path' => $fotoPath,
                'updated_at' => now(),
            ]);

        return redirect()->route('partner.vehicles.show',['id'=>$id])->with('ok','Vehículo actualizado.');
    }

    public function suspend(int $id)
    {
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        $this->findVehicleOr404($tenantId, $partnerId, $id);

        DB::table('vehicles')
            ->where('tenant_id',$tenantId)
            ->where('partner_id',$partnerId)
            ->where('id',$id)
            ->update([
                'active' => 0,
                'updated_at' => now(),
            ]);

        return back()->with('ok','Vehículo suspendido.');
    }

    public function activate(Request $r, int $id)
    {
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        try {
            return DB::transaction(function () use ($tenantId, $partnerId, $id) {

                // Lock para idempotencia / doble click
                $v = DB::table('vehicles')
                    ->where('tenant_id', $tenantId)
                    ->where('partner_id', $partnerId)
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                abort_if(!$v, 404);

                if ((int)($v->active ?? 0) === 1) {
                    return back()->with('ok','El vehículo ya está activo.');
                }

              

                // ✅ Gate + saldo SOLO aquí (activar = iniciar cobro)
                $tenant = \App\Models\Tenant::with('billingProfile')->findOrFail($tenantId);
                $svc = app(\App\Services\PartnerPrepaidBillingService::class);

                $gate = $svc->partnerGateState($tenantId, $partnerId);
                if (($gate['state'] ?? 'ok') === 'blocked') {
                    return back()->withErrors([
                        'wallet' => 'Tu cuenta está bloqueada por falta de saldo. Recarga para reactivar.'
                    ]);
                }

                $req = $svc->requiredToAddVehicleToday($tenant, $partnerId, now(), 1);
                if (($req['topup_needed'] ?? 0) > 0.0001) {
                    return back()->withErrors([
                        'wallet' => 'Saldo insuficiente. Te faltan $'.number_format($req['topup_needed'],2).' '.$req['currency'].
                                    ' para activar 1 vehículo hoy ('.$req['period_start'].' → '.$req['period_end'].').'
                    ]);
                }

                $activatedAt = now();

                // Activar + setear partner_assigned_at si está null
                DB::table('vehicles')
                    ->where('tenant_id', $tenantId)
                    ->where('partner_id', $partnerId)
                    ->where('id', $id)
                    ->update([
                        'active' => 1,
                        'partner_assigned_at' => $v->partner_assigned_at ?: $activatedAt,
                        'updated_at' => $activatedAt,
                    ]);

               

                return back()->with('ok','Vehículo activado. A partir de hoy cuenta para cobro.');
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['billing' => $e->getMessage()]);
        }
    }

   
}
