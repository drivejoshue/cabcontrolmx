<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::orderBy('id')->paginate(20);

        return view('sysadmin.tenants.index', compact('tenants'));
    }

    public function create()
    {
        return view('sysadmin.tenants.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:255',
            'slug'                 => 'nullable|string|max:255',
            'timezone'             => 'required|string|max:64',
            'latitud'              => 'nullable|numeric',
            'longitud'             => 'nullable|numeric',
            'coverage_radius_km'   => 'nullable|numeric',
            'allow_marketplace'    => 'nullable|boolean',

            // Datos del usuario admin del tenant
            'admin_name'           => 'required|string|max:255',
            'admin_email'          => 'required|email|max:255',
            'admin_password'       => 'required|string|min:6',
        ]);

        // Slug por defecto
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $tenant = null;

        DB::transaction(function () use (&$tenant, $data) {

            $tenant = Tenant::create([
                'name'               => $data['name'],
                'slug'               => $data['slug'],
                'timezone'           => $data['timezone'],
                'latitud'            => $data['latitud'] ?? null,
                'longitud'           => $data['longitud'] ?? null,
                'coverage_radius_km' => $data['coverage_radius_km'] ?? null,
                'allow_marketplace'  => !empty($data['allow_marketplace']) ? 1 : 0,
            ]);

            // Usuario admin del tenant
            $user = User::create([
                'tenant_id'         => $tenant->id,
                'name'              => $data['admin_name'],
                'email'             => $data['admin_email'],
                'password'          => bcrypt($data['admin_password']),
                'email_verified_at' => now(),
                'is_admin'          => true,
            ]);

           
        });

        return redirect()
            ->route('sysadmin.tenants.edit', $tenant)
            ->with('status', 'Tenant creado y usuario admin generado correctamente.');
    }

    public function edit(Tenant $tenant)
    {
        return view('sysadmin.tenants.edit', compact('tenant'));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'name'               => 'required|string|max:255',
            'slug'               => 'required|string|max:255',
            'timezone'           => 'required|string|max:64',
            'latitud'            => 'nullable|numeric',
            'longitud'           => 'nullable|numeric',
            'coverage_radius_km' => 'nullable|numeric',
            'allow_marketplace'  => 'nullable|boolean',
        ]);

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
    }
}
