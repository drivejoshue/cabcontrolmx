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
        $cityId   = (int) $r->get('city_id', 0);
        $q        = trim((string) $r->get('q', ''));
        $category = trim((string) $r->get('category', ''));
        $active   = $r->get('active', '');
        $featured = $r->get('featured', '');

        $places = CityPlace::query()
            ->with('city:id,name')
            ->when($cityId > 0, fn ($qq) => $qq->where('city_id', $cityId))
            ->when($q !== '', fn ($qq) => $qq->where(function ($w) use ($q) {
                $w->where('label', 'like', "%{$q}%")
                  ->orWhere('address', 'like', "%{$q}%");
            }))
            ->when($category !== '', fn ($qq) => $qq->where('category', $category))
            ->when($active !== '', fn ($qq) => $qq->where('is_active', (int) $active === 1))
            ->when($featured !== '', fn ($qq) => $qq->where('is_featured', (int) $featured === 1))
            ->orderByDesc('is_featured')
            ->orderByDesc('priority')
            ->orderBy('label')
            ->paginate(25)
            ->withQueryString();

        $cities = City::query()->orderBy('name')->get(['id', 'name']);

        $categories = CityPlace::query()
            ->whereNotNull('category')
            ->select('category')->distinct()->orderBy('category')->pluck('category');

        return view('sysadmin.city_places.index', compact(
            'places',
            'cities',
            'categories',
            'cityId',
            'q',
            'category',
            'active',
            'featured'
        ));
    }

    public function create(Request $r)
    {
        $cities = City::query()->orderBy('name')->get(['id', 'name']);

        $place = new CityPlace([
            'city_id'     => (int) $r->get('city_id', 0) ?: ($cities->first()->id ?? null),
            'priority'    => 0,
            'is_featured' => true,
            'is_active'   => true,

            // defaults tarifa especial
            'fare_is_active'            => false,
            'fare_radius_m'             => 0,
            'fare_near_origin_radius_m' => 0,
            'fare_rule'                 => [
                'enabled'    => false,
                'applies_to' => 'destination',
                'mode'       => 'extra_fixed_tiered',
                'label'      => 'Tarifa especial',
                'full_extra' => 0,
                'near_extra' => 0,
            ],
        ]);

        return view('sysadmin.city_places.create', compact('place', 'cities'));
    }

    public function store(Request $r)
    {
        $data = $this->validatePayload($r);

        $data = $this->normalizeAndBuildFareRule($data);

        $place = CityPlace::create($data);

        return redirect()
            ->route('sysadmin.city-places.show', $place)
            ->with('success', 'Lugar creado.');
    }

    public function show(CityPlace $city_place)
    {
        $city_place->load('city:id,name');
        return view('sysadmin.city_places.show', ['place' => $city_place]);
    }

    public function edit(CityPlace $city_place)
    {
        $cities = City::query()->orderBy('name')->get(['id', 'name']);

        return view('sysadmin.city_places.edit', [
            'place'   => $city_place,
            'cities'  => $cities,
        ]);
    }

    public function update(Request $r, CityPlace $city_place)
    {
        $data = $this->validatePayload($r);

        $data = $this->normalizeAndBuildFareRule($data);

        $city_place->update($data);

        return redirect()
            ->route('sysadmin.city-places.show', $city_place)
            ->with('success', 'Lugar actualizado.');
    }

    public function destroy(CityPlace $city_place)
    {
        $city_place->delete();

        return redirect()
            ->route('sysadmin.city-places.index')
            ->with('success', 'Lugar eliminado.');
    }

    /**
     * Validación unificada store/update.
     */
    private function validatePayload(Request $r): array
    {
        return $r->validate([
            'city_id'     => ['required', 'exists:cities,id'],
            'label'       => ['required', 'string', 'max:160'],
            'address'     => ['nullable', 'string', 'max:255'],
            'lat'         => ['required', 'numeric', 'between:-90,90'],
            'lng'         => ['required', 'numeric', 'between:-180,180'],
            'category'    => ['nullable', 'string', 'max:40'],
            'priority'    => ['required', 'integer', 'min:0', 'max:9999'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active'   => ['nullable', 'boolean'],

            // Tarifa especial (CityPlace)
            'fare_is_active'            => ['nullable', 'boolean'],
            'fare_radius_m'             => ['nullable', 'integer', 'min:0', 'max:50000'],
            'fare_near_origin_radius_m' => ['nullable', 'integer', 'min:0', 'max:50000'],

            // Regla (UI -> JSON)
            'fare_rule_enabled' => ['nullable', 'boolean'],
            'fare_rule_label'   => ['nullable', 'string', 'max:60'],
            'fare_rule_mode'    => ['nullable', 'in:extra_fixed_tiered,total_fixed_tiered'],

            'fare_full_extra'   => ['nullable', 'integer', 'min:0', 'max:999999'],
            'fare_near_extra'   => ['nullable', 'integer', 'min:0', 'max:999999'],
            'fare_full_total'   => ['nullable', 'integer', 'min:0', 'max:999999'],
            'fare_near_total'   => ['nullable', 'integer', 'min:0', 'max:999999'],
        ]);
    }

    /**
     * Normaliza booleanos/ints y construye fare_rule (array) desde campos del form.
     *
     * Reglas:
     * - fare_is_active controla el switch maestro (query lo usa).
     * - fare_rule.enabled controla la regla (por JSON).
     * - fare_radius_m y fare_near_origin_radius_m se guardan como ints.
     * - applies_to se queda fijo en destination (v1).
     */
    private function normalizeAndBuildFareRule(array $data): array
    {
        // flags base
        $data['is_featured'] = (bool) ($data['is_featured'] ?? false);
        $data['is_active']   = (bool) ($data['is_active'] ?? false);

        // flags + radios tarifa
        $data['fare_is_active']            = (bool) ($data['fare_is_active'] ?? false);
        $data['fare_radius_m']             = (int) ($data['fare_radius_m'] ?? 0);
        $data['fare_near_origin_radius_m'] = (int) ($data['fare_near_origin_radius_m'] ?? 0);

        // construir JSON fare_rule desde campos virtuales
        $ruleEnabled = (bool) ($data['fare_rule_enabled'] ?? false);
        $ruleMode    = (string) ($data['fare_rule_mode'] ?? 'extra_fixed_tiered');
        $ruleLabel   = trim((string) ($data['fare_rule_label'] ?? 'Tarifa especial'));

        $fareRule = [
            'enabled'    => $ruleEnabled,
            'applies_to' => 'destination',
            'mode'       => $ruleMode,
            'label'      => $ruleLabel !== '' ? $ruleLabel : 'Tarifa especial',
        ];

        if ($ruleMode === 'total_fixed_tiered') {
            $full = (int) ($data['fare_full_total'] ?? 0);
            $near = (int) ($data['fare_near_total'] ?? $full);

            $fareRule['full_total'] = $full;
            $fareRule['near_total'] = $near;
        } else {
            // default: extra_fixed_tiered
            $full = (int) ($data['fare_full_extra'] ?? 0);
            $near = (int) ($data['fare_near_extra'] ?? $full);

            $fareRule['full_extra'] = $full;
            $fareRule['near_extra'] = $near;
        }

        $data['fare_rule'] = $fareRule;

        // limpiar campos virtuales para que no intenten guardarse como columnas
        unset(
            $data['fare_rule_enabled'],
            $data['fare_rule_label'],
            $data['fare_rule_mode'],
            $data['fare_full_extra'],
            $data['fare_near_extra'],
            $data['fare_full_total'],
            $data['fare_near_total'],
        );

        return $data;
    }
}
