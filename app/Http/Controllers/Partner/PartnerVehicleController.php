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

        return view('partner.vehicles.show', compact('v'));
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

                // ✅ (Recomendado) exigir verificación
                if (($v->verification_status ?? 'pending') !== 'verified') {
                    return back()->withErrors([
                        'active' => 'No puedes activar: el vehículo aún no está verificado.'
                    ]);
                }

                // ✅ (Recomendado) exigir que tenga conductor asignado
                $hasOpenAssignment = DB::table('driver_vehicle_assignments')
                    ->where('tenant_id', $tenantId)
                    ->where('vehicle_id', (int)$v->id)
                    ->whereNull('end_at')
                    ->exists();

                if (!$hasOpenAssignment) {
                    return back()->withErrors([
                        'active' => 'No puedes activar: primero asigna un conductor.'
                    ]);
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

                // Billing event: único punto canónico
                PartnerBillingUIService::onVehicleActivated(
                    tenantId: $tenantId,
                    partnerId: $partnerId,
                    vehicleId: (int)$id,
                    activatedAt: $activatedAt,
                    actorUserId: auth()->id()
                );

                return back()->with('ok','Vehículo activado. A partir de hoy cuenta para cobro.');
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['billing' => $e->getMessage()]);
        }
    }

    public function assignDriver(Request $r, int $id)
    {
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        $data = $r->validate([
            'driver_id'       => ['required','integer'],
            'start_at'        => ['nullable','date'],
            'note'            => ['nullable','string','max:255'],
            'close_conflicts' => ['nullable','boolean'],
        ]);

        $vehicle = $this->findVehicleOr404($tenantId, $partnerId, $id);

        $driver = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->where('id', (int)$data['driver_id'])
            ->first();

        if (!$driver) {
            return back()->withErrors(['driver_id' => 'Conductor inválido o no pertenece a tu cuenta.'])->withInput();
        }

        $startAt = $data['start_at'] ?? now();

        DB::beginTransaction();
        try {
            if (!empty($data['close_conflicts'])) {
                DB::table('driver_vehicle_assignments')
                    ->where('tenant_id', $tenantId)
                    ->where('driver_id', $driver->id)
                    ->whereNull('end_at')
                    ->update(['end_at' => $startAt, 'updated_at' => now()]);

                DB::table('driver_vehicle_assignments')
                    ->where('tenant_id', $tenantId)
                    ->where('vehicle_id', $vehicle->id)
                    ->whereNull('end_at')
                    ->update(['end_at' => $startAt, 'updated_at' => now()]);
            }

            DB::table('driver_vehicle_assignments')->insert([
                'tenant_id'  => $tenantId,
                'driver_id'  => $driver->id,
                'vehicle_id' => $vehicle->id,
                'start_at'   => $startAt,
                'end_at'     => null,
                'note'       => $data['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['assign' => 'No se pudo asignar: ' . $e->getMessage()])->withInput();
        }

        return redirect()->route('partner.vehicles.show', ['id' => $vehicle->id])
            ->with('ok', 'Chofer asignado (no activa cobro).');
    }
}
