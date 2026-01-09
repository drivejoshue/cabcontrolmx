<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\TenantBillingService;
use App\Models\Tenant;

class VehicleController extends Controller
{
    private const VEHICLE_TYPES = ['sedan', 'vagoneta', 'van', 'premium'];

    private function tenantId(): int
    {
        $tid = Auth::user()->tenant_id ?? null;
        if (!$tid) {
            abort(403, 'Usuario sin tenant asignado');
        }
        return (int) $tid;
    }

    private function allowedYearMin(): int
    {
        // "10 años a la fecha" => últimos 10 incluyendo el actual
        // Ej: 2026 -> 2026..2017 (10 valores)
        return now()->year - 9;
    }

    private function allowedYearMax(): int
    {
        return now()->year;
    }

    private function normalizeType(?string $type): ?string
    {
        $t = strtolower(trim((string) $type));
        return in_array($t, self::VEHICLE_TYPES, true) ? $t : null;
    }

    public function index(Request $r)
    {
        $tenantId = $this->tenantId();
        $q = trim($r->get('q', ''));

        $vehicles = DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->when($q, function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('economico', 'like', "%$q%")
                        ->orWhere('plate', 'like', "%$q%")
                        ->orWhere('brand', 'like', "%$q%")
                        ->orWhere('model', 'like', "%$q%");
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.vehicles.index', compact('vehicles', 'q'));
    }

    public function create()
    {
        $tenantId = $this->tenantId();
        $tenant   = Tenant::with('billingProfile')->findOrFail($tenantId);
        $profile  = $tenant->billingProfile;

        [$canRegister, $billingMessage] = app(TenantBillingService::class)
            ->canRegisterNewVehicle($tenant);

        $activeVehicles = DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('active', 1)
            ->count();

        $vehicleCatalog = DB::table('vehicle_catalog')
            ->where('active', 1)
            ->orderBy('brand')
            ->orderBy('model')
            ->get();

        $years = range($this->allowedYearMax(), $this->allowedYearMin());

        return view('admin.vehicles.create', [
            'v'              => null,
            'tenant'         => $tenant,
            'profile'        => $profile,
            'canRegister'    => $canRegister,
            'billingMessage' => $billingMessage,
            'activeVehicles' => $activeVehicles,
            'vehicleCatalog' => $vehicleCatalog,
            'years'          => $years,
        ]);
    }

    public function store(Request $r)
    {
        $tenantId = $this->tenantId();
        $tenant = Tenant::with('billingProfile')->findOrFail($tenantId);

        $minYear = $this->allowedYearMin();
        $maxYear = $this->allowedYearMax();

        $data = $r->validate([
            'economico'  => 'required|string|max:20',
            'plate'      => 'required|string|max:20',
            // Enum manda (no el catálogo)
            'type'       => 'required|in:' . implode(',', self::VEHICLE_TYPES),
            'capacity'   => 'nullable|integer|min:1|max:10',
            'color'      => 'nullable|string|max:40',
            'year'       => "nullable|integer|min:$minYear|max:$maxYear",
            'policy_id'  => 'nullable|string|max:60',
            'foto'       => 'nullable|image|max:2048',

            // catálogo (solo refuerza marca/modelo)
            'catalog_id' => 'nullable|integer|exists:vehicle_catalog,id',
            'brand'      => 'nullable|string|max:60',
            'model'      => 'nullable|string|max:80',
        ], [
            'type.required' => 'Selecciona el tipo de vehículo.',
            'type.in'       => 'Tipo inválido. Usa: sedan, vagoneta, van, premium.',
            'year.min'      => "El año debe ser $minYear o más reciente.",
            'year.max'      => "El año no puede ser mayor a $maxYear.",
        ]);

        // Normaliza type SIEMPRE desde enum
        $data['type'] = $this->normalizeType($data['type']) ?? 'sedan'; // fallback defensivo

        // Billing check ANTES de insertar
        [$allowed, $reason] = app(TenantBillingService::class)
            ->canRegisterNewVehicle($tenant);

        if (!$allowed) {
            return back()
                ->withErrors(['billing' => $reason])
                ->withInput();
        }

        // Si viene catalog_id, reforzamos SOLO brand/model (no type)
        if (!empty($data['catalog_id'])) {
            $cat = DB::table('vehicle_catalog')->where('id', $data['catalog_id'])->first();
            if ($cat) {
                $data['brand'] = $cat->brand;
                $data['model'] = $cat->model;
            }
        }

        return DB::transaction(function () use ($r, $data, $tenantId) {

            $existsEco = DB::table('vehicles')
                ->where('tenant_id', $tenantId)
                ->where('economico', $data['economico'])
                ->exists();

            if ($existsEco) {
                return back()
                    ->withErrors(['economico' => 'Ya existe ese número económico.'])
                    ->withInput();
            }

            $existsPlate = DB::table('vehicles')
                ->where('tenant_id', $tenantId)
                ->where('plate', $data['plate'])
                ->exists();

            if ($existsPlate) {
                return back()
                    ->withErrors(['plate' => 'Ya existe esa placa.'])
                    ->withInput();
            }

            $fotoPath = null;
            if ($r->hasFile('foto')) {
                $fotoPath = $r->file('foto')->store('vehicles', 'public');
            }

            $id = DB::table('vehicles')->insertGetId([
                'tenant_id'  => $tenantId,
                'economico'  => $data['economico'],
                'plate'      => $data['plate'],
                'brand'      => $data['brand'] ?? null,
                'model'      => $data['model'] ?? null,

                // Enum manda
                'type'       => $data['type'],

                'color'      => $data['color'] ?? null,
                'year'       => $data['year'] ?? null,
                'capacity'   => $data['capacity'] ?? 4,
                'policy_id'  => $data['policy_id'] ?? null,
                'foto_path'  => $fotoPath,
                'active'     => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return redirect()
                ->route('vehicles.documents.index', ['id' => $id])
                ->with('ok', 'Vehículo creado. Ahora sube los documentos requeridos para verificación.');
        });
    }

    public function show(int $id)
    {
        $tenantId = $this->tenantId();

        $v = DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        abort_if(!$v, 404);

        $currentDrivers = DB::table('driver_vehicle_assignments as a')
            ->join('drivers as d', 'd.id', '=', 'a.driver_id')
            ->where('a.tenant_id', $tenantId)
            ->where('a.vehicle_id', $id)
            ->whereNull('a.end_at')
            ->orderBy('a.start_at', 'desc')
            ->select([
                'a.id as assignment_id',
                'a.start_at',
                'd.id as driver_id',
                'd.name',
                'd.phone',
                'd.foto_path',
            ])
            ->get();

        $assignments = DB::table('driver_vehicle_assignments as a')
            ->join('drivers as d', 'd.id', '=', 'a.driver_id')
            ->where('a.tenant_id', $tenantId)
            ->where('a.vehicle_id', $id)
            ->orderBy('a.start_at', 'desc')
            ->select([
                'a.start_at', 'a.end_at', 'a.note',
                'd.id as driver_id', 'd.name', 'd.phone',
            ])
            ->get();

        return view('admin.vehicles.show', compact('v', 'currentDrivers', 'assignments'));
    }

    public function assignDriver(Request $r, int $id)
    {
        $tenantId = $this->tenantId();

        $data = $r->validate([
            'driver_id'       => 'required|integer',
            'start_at'        => 'nullable|date',
            'note'            => 'nullable|string|max:255',
            'close_conflicts' => 'nullable|boolean',
        ]);

        $vehicle = DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        abort_if(!$vehicle, 404);

        $driver = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('id', $data['driver_id'])
            ->first();

        if (!$driver) {
            return back()->withErrors(['driver_id' => 'Conductor inválido'])->withInput();
        }

        $startAt = $data['start_at'] ?? now();

        DB::beginTransaction();
        try {
            if (!empty($data['close_conflicts'])) {
                DB::table('driver_vehicle_assignments')
                    ->where('tenant_id', $tenantId)
                    ->where('driver_id', $data['driver_id'])
                    ->whereNull('end_at')
                    ->update(['end_at' => $startAt, 'updated_at' => now()]);

                DB::table('driver_vehicle_assignments')
                    ->where('tenant_id', $tenantId)
                    ->where('vehicle_id', $id)
                    ->whereNull('end_at')
                    ->update(['end_at' => $startAt, 'updated_at' => now()]);
            }

            DB::table('driver_vehicle_assignments')->insert([
                'tenant_id'  => $tenantId,
                'driver_id'  => $data['driver_id'],
                'vehicle_id' => $id,
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

        return redirect()->route('vehicles.show', ['id' => $id])->with('ok', 'Chofer asignado.');
    }

    public function edit(int $id)
    {
        $tenantId = $this->tenantId();

        $v = DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        abort_if(!$v, 404);

        $tenant   = Tenant::with('billingProfile')->findOrFail($tenantId);
        $profile  = $tenant->billingProfile;

        [$canRegister, $billingMessage] = app(TenantBillingService::class)
            ->canRegisterNewVehicle($tenant);

        $activeVehicles = DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('active', 1)
            ->count();

        $vehicleCatalog = DB::table('vehicle_catalog')
            ->where('active', 1)
            ->orderBy('brand')
            ->orderBy('model')
            ->get();

        $years = range($this->allowedYearMax(), $this->allowedYearMin());

        return view('admin.vehicles.edit', [
            'v'              => $v,
            'tenant'         => $tenant,
            'profile'        => $profile,
            'canRegister'    => $canRegister,
            'billingMessage' => $billingMessage,
            'activeVehicles' => $activeVehicles,
            'vehicleCatalog' => $vehicleCatalog,
            'years'          => $years,
        ]);
    }

    public function update(Request $r, int $id)
    {
        $tenantId = $this->tenantId();

        $minYear = $this->allowedYearMin();
        $maxYear = $this->allowedYearMax();

        $data = $r->validate([
            'economico'  => 'required|string|max:20',
            'plate'      => 'required|string|max:20',
            // Enum manda (no el catálogo)
            'type'       => 'required|in:' . implode(',', self::VEHICLE_TYPES),
            'capacity'   => 'nullable|integer|min:1|max:10',
            'color'      => 'nullable|string|max:40',
            'year'       => "nullable|integer|min:$minYear|max:$maxYear",
            'policy_id'  => 'nullable|string|max:60',
            'active'     => 'nullable|boolean',
            'foto'       => 'nullable|image|max:2048',

            'catalog_id' => 'nullable|integer|exists:vehicle_catalog,id',
            'brand'      => 'nullable|string|max:60',
            'model'      => 'nullable|string|max:80',
        ], [
            'type.required' => 'Selecciona el tipo de vehículo.',
            'type.in'       => 'Tipo inválido. Usa: sedan, vagoneta, van, premium.',
            'year.min'      => "El año debe ser $minYear o más reciente.",
            'year.max'      => "El año no puede ser mayor a $maxYear.",
        ]);

        $data['type'] = $this->normalizeType($data['type']) ?? 'sedan';

        $v = DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        abort_if(!$v, 404);

        $existsEco = DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('economico', $data['economico'])
            ->where('id', '<>', $id)
            ->exists();

        if ($existsEco) {
            return back()->withErrors(['economico' => 'Ya existe otro vehículo con ese número económico.'])->withInput();
        }

        $existsPlate = DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('plate', $data['plate'])
            ->where('id', '<>', $id)
            ->exists();

        if ($existsPlate) {
            return back()->withErrors(['plate' => 'Ya existe otro vehículo con esa placa.'])->withInput();
        }

        // Si viene catalog_id, reforzamos SOLO brand/model (no type)
        if (!empty($data['catalog_id'])) {
            $cat = DB::table('vehicle_catalog')->where('id', $data['catalog_id'])->first();
            if ($cat) {
                $data['brand'] = $cat->brand;
                $data['model'] = $cat->model;
            }
        }

        $fotoPath = $v->foto_path;
        if ($r->hasFile('foto')) {
            if ($fotoPath && Storage::disk('public')->exists($fotoPath)) {
                Storage::disk('public')->delete($fotoPath);
            }
            $fotoPath = $r->file('foto')->store('vehicles', 'public');
        }

        DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->update([
                'economico' => $data['economico'],
                'plate'     => $data['plate'],
                'brand'     => $data['brand'] ?? null,
                'model'     => $data['model'] ?? null,

                // Enum manda
                'type'      => $data['type'],

                'color'     => $data['color'] ?? null,
                'year'      => $data['year'] ?? null,
                'capacity'  => $data['capacity'] ?? 4,
                'policy_id' => $data['policy_id'] ?? null,
                'foto_path' => $fotoPath,
                'active'    => (int)($data['active'] ?? 1),
                'updated_at'=> now(),
            ]);

        return redirect()->route('vehicles.show', ['id' => $id])->with('ok', 'Vehículo actualizado.');
    }

    public function destroy(int $id)
    {
        $tenantId = $this->tenantId();

        $v = DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        abort_if(!$v, 404);

        DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->update([
                'active' => 0,
                'updated_at' => now(),
            ]);

        return redirect()->route('vehicles.index')->with('ok', 'Vehículo desactivado.');
    }
}
