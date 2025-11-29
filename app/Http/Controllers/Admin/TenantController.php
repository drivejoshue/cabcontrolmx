<?php
//namespace App\Http\Controllers\Admin;


//use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;
// use App\Models\Tenant;


// class TenantController extends Controller
// {
// public function edit(Tenant $tenant)
// {
// //$this->authorize('view', $tenant); // opcional si defines Policy
// return view('admin.tenants.edit', compact('tenant'));
// }


// public function update(Request $request, Tenant $tenant)
// {
// //$this->authorize('update', $tenant); // opcional


// $data = $request->validate([
// 'name' => 'required|string|max:150',
// 'slug' => 'required|string|max:160',
// 'timezone' => 'required|string|max:64',
// 'utc_offset_minutes' => 'nullable|integer',
// 'latitud' => 'nullable|numeric',
// 'longitud' => 'nullable|numeric',
// 'allow_marketplace' => 'boolean',
// ]);


// $tenant->fill($data);
// $tenant->allow_marketplace = $request->boolean('allow_marketplace');
// $tenant->save();


// return redirect()->route('admin.tenants.edit', $tenant)->with('ok','Actualizado');
// }
// }