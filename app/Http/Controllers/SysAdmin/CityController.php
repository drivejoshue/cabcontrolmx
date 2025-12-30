<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class CityController extends Controller
{
    public function index(Request $r)
    {
        $q = trim((string)$r->get('q', ''));

        $cities = City::query()
            ->when($q !== '', fn($qq) => $qq->where('name', 'like', "%{$q}%")
                ->orWhere('slug', 'like', "%{$q}%"))
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('sysadmin.cities.index', compact('cities', 'q'));
    }

    public function create()
    {
        $city = new City([
            'timezone' => 'America/Mexico_City',
            'radius_km' => 30,
            'is_active' => true,
        ]);

        return view('sysadmin.cities.create', compact('city'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name'       => ['required','string','max:120'],
            'slug'       => ['nullable','string','max:160', 'unique:cities,slug'],
            'timezone'   => ['nullable','string','max:64'],
            'center_lat' => ['required','numeric','between:-90,90'],
            'center_lng' => ['required','numeric','between:-180,180'],
            'radius_km'  => ['required','numeric','min:1','max:999'],
            'is_active'  => ['nullable','boolean'],
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['timezone'] = $data['timezone'] ?: 'America/Mexico_City';
        $data['is_active'] = (bool)($data['is_active'] ?? false);

        City::create($data);

        return redirect()->route('sysadmin.cities.index')->with('success', 'Ciudad creada.');
    }

    public function show(City $city)
    {
        $city->loadCount('places');
        return view('sysadmin.cities.show', compact('city'));
    }

    public function edit(City $city)
    {
        return view('sysadmin.cities.edit', compact('city'));
    }

    public function update(Request $r, City $city)
    {
        $data = $r->validate([
            'name'       => ['required','string','max:120'],
            'slug'       => ['nullable','string','max:160', Rule::unique('cities','slug')->ignore($city->id)],
            'timezone'   => ['nullable','string','max:64'],
            'center_lat' => ['required','numeric','between:-90,90'],
            'center_lng' => ['required','numeric','between:-180,180'],
            'radius_km'  => ['required','numeric','min:1','max:999'],
            'is_active'  => ['nullable','boolean'],
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['timezone'] = $data['timezone'] ?: 'America/Mexico_City';
        $data['is_active'] = (bool)($data['is_active'] ?? false);

        $city->update($data);

        return redirect()->route('sysadmin.cities.show', $city)->with('success', 'Ciudad actualizada.');
    }

    public function destroy(City $city)
    {
        $city->delete(); // FK city_places ON DELETE CASCADE
        return redirect()->route('sysadmin.cities.index')->with('success', 'Ciudad eliminada.');
    }
}
