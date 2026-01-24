<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Passenger;
use App\Models\PassengerPlace;
use App\Services\CityResolver;
use Illuminate\Http\Request;

class PassengerPlacesController extends Controller
{
    /**
     * Upsert Casa/Trabajo (kind=home|work, slot=0)
     */
    public function upsert(Request $request, CityResolver $resolver)
{
    $data = $request->validate([
        'firebase_uid' => 'required|string|max:128',
        'kind'         => 'required|in:home,work,fav', // 👈 permitir fav
        'label'        => 'required|string|max:160',
        'address'      => 'nullable|string|max:255',
        'lat'          => 'required|numeric',
        'lng'          => 'required|numeric',
    ]);

    $passenger = Passenger::where('firebase_uid', $data['firebase_uid'])->first();
    if (! $passenger) {
        return response()->json([
            'ok'  => false,
            'msg' => 'Pasajero no encontrado, llama primero a /passenger/auth-sync.',
        ], 404);
    }

    $lat = (float) $data['lat'];
    $lng = (float) $data['lng'];

    $cityId = null;
    $resolved = $resolver->resolve($lat, $lng);
    if ($resolved && isset($resolved['city'])) {
        $cityId = $resolved['city']->id;
    }

    // ✅ slot fijo: home/work=0, fav=1 (un solo favorito)
    $slot = ($data['kind'] === 'fav') ? 1 : 0;

    $place = PassengerPlace::updateOrCreate(
        [
            'passenger_id' => $passenger->id,
            'kind'         => $data['kind'],
            'slot'         => $slot,
        ],
        [
            'city_id'   => $cityId,
            'label'     => $data['label'],
            'address'   => $data['address'] ?? null,
            'lat'       => $lat,
            'lng'       => $lng,
            'is_active' => 1,
        ]
    );

    return response()->json([
        'ok'   => true,
        'data' => [
            'id'           => $place->id,
            'passenger_id' => $passenger->id,
            'kind'         => $place->kind,
            'slot'         => $place->slot,
            'label'        => $place->label,
            'address'      => $place->address,
            'lat'          => $place->lat,
            'lng'          => $place->lng,
            'city_id'      => $place->city_id,
        ],
    ]);
}

    /**
     * Agregar Favorito (kind=fav, slot auto 1..N)
     */
  public function addFavorite(Request $request, CityResolver $resolver)
{
    $data = $request->validate([
        'firebase_uid' => 'required|string|max:128',
        'label'        => 'required|string|max:160',
        'address'      => 'nullable|string|max:255',
        'lat'          => 'required|numeric',
        'lng'          => 'required|numeric',
    ]);

    $passenger = Passenger::where('firebase_uid', $data['firebase_uid'])->first();
    if (! $passenger) {
        return response()->json([
            'ok'  => false,
            'msg' => 'Pasajero no encontrado, llama primero a /passenger/auth-sync.',
        ], 404);
    }

    $lat = (float) $data['lat'];
    $lng = (float) $data['lng'];

    $cityId = null;
    $resolved = $resolver->resolve($lat, $lng);
    if ($resolved && isset($resolved['city'])) {
        $cityId = $resolved['city']->id;
    }

    // siguiente slot SOLO considerando activos
    $maxSlot = PassengerPlace::where('passenger_id', $passenger->id)
        ->where('kind', 'fav')
        ->where('is_active', 1)
        ->max('slot');

    $nextSlot = max(1, (int) $maxSlot + 1);

    if ($nextSlot > 30) {
        return response()->json([
            'ok'  => false,
            'msg' => 'Límite de favoritos alcanzado (30).',
        ], 422);
    }

    // si existe un registro inactivo con ese slot, lo "revivimos"
    $place = PassengerPlace::where('passenger_id', $passenger->id)
        ->where('kind', 'fav')
        ->where('slot', $nextSlot)
        ->where('is_active', 0)
        ->first();

    if ($place) {
        $place->update([
            'city_id'   => $cityId,
            'label'     => $data['label'],
            'address'   => $data['address'] ?? null,
            'lat'       => $lat,
            'lng'       => $lng,
            'is_active' => 1,
        ]);
    } else {
        $place = PassengerPlace::create([
            'passenger_id' => $passenger->id,
            'city_id'      => $cityId,
            'kind'         => 'fav',
            'slot'         => $nextSlot,
            'label'        => $data['label'],
            'address'      => $data['address'] ?? null,
            'lat'          => $lat,
            'lng'          => $lng,
            'is_active'    => 1,
        ]);
    }

    return response()->json([
        'ok'   => true,
        'data' => [
            'id'           => $place->id,
            'passenger_id' => $passenger->id,
            'kind'         => $place->kind,
            'slot'         => $place->slot,
            'label'        => $place->label,
            'address'      => $place->address,
            'lat'          => (float) $place->lat,
            'lng'          => (float) $place->lng,
            'city_id'      => $place->city_id,
        ],
    ]);
}

    /**
     * Listar lugares guardados (home/work + favs)
     */
    public function list(Request $request)
    {
        $data = $request->validate([
            'firebase_uid' => 'required|string|max:128',
        ]);

        $passenger = Passenger::where('firebase_uid', $data['firebase_uid'])->first();
        if (! $passenger) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Pasajero no encontrado, llama primero a /passenger/auth-sync.',
            ], 404);
        }

        $items = PassengerPlace::where('passenger_id', $passenger->id)
            ->where('is_active', 1)
            ->orderByRaw("FIELD(kind,'home','work','fav')")
            ->orderBy('slot')
            ->get([
                'id','kind','slot','label','address','lat','lng','city_id','last_used_at','use_count'
            ]);

        return response()->json([
            'ok'   => true,
            'data' => [
                'passenger_id' => $passenger->id,
                'items'        => $items,
            ],
        ]);
    }

    /**
     * Desactivar (soft delete simple)
     */
    public function deactivate(Request $request, int $id)
    {
        $data = $request->validate([
            'firebase_uid' => 'required|string|max:128',
        ]);

        $passenger = Passenger::where('firebase_uid', $data['firebase_uid'])->first();
        if (! $passenger) {
            return response()->json(['ok'=>false,'msg'=>'Pasajero no encontrado.'], 404);
        }

        $place = PassengerPlace::where('id', $id)
            ->where('passenger_id', $passenger->id)
            ->first();

        if (! $place) {
            return response()->json(['ok'=>false,'msg'=>'Lugar no encontrado.'], 404);
        }

        $place->is_active = 0;
        $place->save();

        return response()->json(['ok'=>true]);
    }
}
