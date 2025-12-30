<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    /**
     * Listado con búsqueda y orden.
     * Rutas esperadas:
     *  GET /sysadmin/tenants -> name: sysadmin.tenants.index
     */
    public function index(Request $request)
    {
        $q     = trim((string)$request->input('q', ''));
        $sort  = $request->input('sort', 'id');              // id|name|slug|created_at
        $dir   = strtolower($request->input('dir', 'asc'));  // asc|desc
        $dir   = in_array($dir, ['asc','desc']) ? $dir : 'asc';

        $sortable = ['id','name','slug','created_at'];
        if (!in_array($sort, $sortable)) {
            $sort = 'id';
        }

        $tenants = Tenant::query()
            ->when($q !== '', function($qb) use ($q) {
                $qb->where(function($qq) use ($q) {
                    $qq->where('name','like', "%{$q}%")
                       ->orWhere('slug','like', "%{$q}%")
                       ->orWhere('timezone','like', "%{$q}%");
                });
            })
            ->orderBy($sort, $dir)
            ->paginate(20)
            ->appends(compact('q','sort','dir'));

        return view('sysadmin.tenants.index', compact('tenants','q','sort','dir'));
    }

    /**
     * Form de creación
     * GET /sysadmin/tenants/create -> name: sysadmin.tenants.create
     */
    public function create()
    {
        // Si quieres, podrías pasar una lista de timezones soportados.
        return view('sysadmin.tenants.create');
    }

    /**
     * POST /sysadmin/tenants -> name: sysadmin.tenants.store
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'               => ['required','string','max:255'],
            'slug'               => ['nullable','string','max:255', 'regex:/^[a-z0-9\-]+$/i'],
            'timezone'           => ['required','string','max:64'],
            'latitud'            => ['nullable','numeric'],
            'longitud'           => ['nullable','numeric'],
            'coverage_radius_km' => ['nullable','numeric','min:0'],
            'allow_marketplace'  => ['nullable','boolean'],

            // Admin inicial del tenant
            'admin_name'         => ['required','string','max:255'],
            'admin_email'        => ['required','email','max:255', Rule::unique('users','email')],
            'admin_password'     => ['required','string','min:6'],
        ]);

        // Slug normalizado y único
        $slug = $data['slug'] ?: Str::slug($data['name']);
        $slug = $this->ensureUniqueSlug($slug);

        try {
            DB::beginTransaction();

            /** @var Tenant $tenant */
            $tenant = Tenant::create([
                'name'               => $data['name'],
                'slug'               => $slug,
                'timezone'           => $data['timezone'],
                'latitud'            => $data['latitud'] ?? null,
                'longitud'           => $data['longitud'] ?? null,
                'coverage_radius_km' => $data['coverage_radius_km'] ?? null,
                'allow_marketplace'  => !empty($data['allow_marketplace']) ? 1 : 0,
            ]);

            // Usuario admin del tenant
            User::create([
                'tenant_id'         => $tenant->id,
                'name'              => $data['admin_name'],
                'email'             => $data['admin_email'],
                'password'          => Hash::make($data['admin_password']),
                'email_verified_at' => now(),
                'is_admin'          => true,
                'is_sysadmin'       => false,
            ]);

            DB::commit();

            return redirect()
                ->route('sysadmin.tenants.edit', $tenant)
                ->with('status', 'Tenant creado y usuario admin generado correctamente.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('SYSADMIN: error al crear tenant', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withErrors(['create' => 'No se pudo crear el tenant: '.$e->getMessage()])
                         ->withInput();
        }
    }

    /**
     * GET /sysadmin/tenants/{tenant}/edit -> name: sysadmin.tenants.edit
     */
    public function edit(Tenant $tenant)
    {
        return view('sysadmin.tenants.edit', compact('tenant'));
    }

    /**
     * POST /sysadmin/tenants/{tenant} -> name: sysadmin.tenants.update
     * (Si prefieres PUT/PATCH, ajusta las rutas/blades.)
     */
    public function update(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'name'               => ['required','string','max:255'],
            'slug'               => ['required','string','max:255', 'regex:/^[a-z0-9\-]+$/i', Rule::unique('tenants','slug')->ignore($tenant->id)],
            'timezone'           => ['required','string','max:64'],
            'latitud'            => ['nullable','numeric'],
            'longitud'           => ['nullable','numeric'],
            'coverage_radius_km' => ['nullable','numeric','min:0'],
            'allow_marketplace'  => ['nullable','boolean'],
        ]);

        try {
            $tenant->update([
                'name'               => $data['name'],
                'slug'               => $data['slug'],
                'timezone'           => $data['timezone'],
                'latitud'            => $data['latitud'] ?? null,
                'longitud'           => $data['longitud'] ?? null,
                'coverage_radius_km' => $data['coverage_radius_km'] ?? null,
                'allow_marketplace'  => !empty($data['allow_marketplace']) ? 1 : 0,
            ]);

            return redirect()
                ->route('sysadmin.tenants.edit', $tenant)
                ->with('status', 'Tenant actualizado correctamente.');
        } catch (\Throwable $e) {
            Log::error('SYSADMIN: error al actualizar tenant', [
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);
            return back()->withErrors(['update' => 'No se pudo actualizar el tenant: '.$e->getMessage()])
                         ->withInput();
        }
    }

    /**
     * Asegura unicidad de slug agregando sufijo incremental si es necesario.
     */
    private function ensureUniqueSlug(string $base): string
    {
        $slug = Str::slug($base);
        if ($slug === '') {
            $slug = 'tenant';
        }

        $exists = Tenant::where('slug', $slug)->exists();
        if (!$exists) return $slug;

        $i = 2;
        while (Tenant::where('slug', "{$slug}-{$i}")->exists()) {
            $i++;
            if ($i > 2000) { // guardarrail
                $slug = $slug . '-' . Str::random(4);
                break;
            }
        }

        return "{$slug}-{$i}";
    }
}
