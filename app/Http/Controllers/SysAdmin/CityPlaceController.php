<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\CityPlace;
use Illuminate\Http\Request;

class CityPlaceController extends Controller
{
    public function index(Request $r)
    {
        $cityId   = (int)$r->get('city_id', 0);
        $q        = trim((string)$r->get('q', ''));
        $category = trim((string)$r->get('category', ''));
        $active   = $r->get('active', '');
        $featured = $r->get('featured', '');

        $places = CityPlace::query()
            ->with('city:id,name')
            ->when($cityId > 0, fn($qq) => $qq->where('city_id', $cityId))
            ->when($q !== '', fn($qq) => $qq->where(function ($w) use ($q) {
                $w->where('label', 'like', "%{$q}%")
                  ->orWhere('address', 'like', "%{$q}%");
            }))
            ->when($category !== '', fn($qq) => $qq->where('category', $category))
            ->when($active !== '', fn($qq) => $qq->where('is_active', (int)$active === 1))
            ->when($featured !== '', fn($qq) => $qq->where('is_featured', (int)$featured === 1))
            ->orderByDesc('is_featured')
            ->orderByDesc('priority')
            ->orderBy('label')
            ->paginate(25)
            ->withQueryString();

        $cities = City::query()->orderBy('name')->get(['id','name']);
        $categories = CityPlace::query()
            ->whereNotNull('category')
            ->select('category')->distinct()->orderBy('category')->pluck('category');

        return view('sysadmin.city_places.index', compact(
            'places','cities','categories','cityId','q','category','active','featured'
        ));
    }

    public function create(Request $r)
    {
        $cities = City::query()->orderBy('name')->get(['id','name']);

        $place = new CityPlace([
            'city_id'     => (int)$r->get('city_id', 0) ?: ($cities->first()->id ?? null),
            'priority'    => 0,
            'is_featured' => true,
            'is_active'   => true,
        ]);

        return view('sysadmin.city_places.create', compact('place','cities'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'city_id'     => ['required','exists:cities,id'],
            'label'       => ['required','string','max:160'],
            'address'     => ['nullable','string','max:255'],
            'lat'         => ['required','numeric','between:-90,90'],
            'lng'         => ['required','numeric','between:-180,180'],
            'category'    => ['nullable','string','max:40'],
            'priority'    => ['required','integer','min:0','max:9999'],
            'is_featured' => ['nullable','boolean'],
            'is_active'   => ['nullable','boolean'],
        ]);

        $data['is_featured'] = (bool)($data['is_featured'] ?? false);
        $data['is_active']   = (bool)($data['is_active'] ?? false);

        $place = CityPlace::create($data);

        return redirect()->route('sysadmin.city-places.show', $place)
            ->with('success', 'Lugar creado.');
    }

    public function show(CityPlace $city_place)
    {
        $city_place->load('city:id,name');
        return view('sysadmin.city_places.show', ['place' => $city_place]);
    }

    public function edit(CityPlace $city_place)
    {
        $cities = City::query()->orderBy('name')->get(['id','name']);
        return view('sysadmin.city_places.edit', [
            'place' => $city_place,
            'cities' => $cities,
        ]);
    }

    public function update(Request $r, CityPlace $city_place)
    {
        $data = $r->validate([
            'city_id'     => ['required','exists:cities,id'],
            'label'       => ['required','string','max:160'],
            'address'     => ['nullable','string','max:255'],
            'lat'         => ['required','numeric','between:-90,90'],
            'lng'         => ['required','numeric','between:-180,180'],
            'category'    => ['nullable','string','max:40'],
            'priority'    => ['required','integer','min:0','max:9999'],
            'is_featured' => ['nullable','boolean'],
            'is_active'   => ['nullable','boolean'],
        ]);

        $data['is_featured'] = (bool)($data['is_featured'] ?? false);
        $data['is_active']   = (bool)($data['is_active'] ?? false);

        $city_place->update($data);

        return redirect()->route('sysadmin.city-places.show', $city_place)
            ->with('success', 'Lugar actualizado.');
    }

    public function destroy(CityPlace $city_place)
    {
        $city_place->delete();
        return redirect()->route('sysadmin.city-places.index')->with('success', 'Lugar eliminado.');
    }
}
