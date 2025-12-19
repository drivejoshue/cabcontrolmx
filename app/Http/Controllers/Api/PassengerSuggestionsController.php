<?php 
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Passenger;
use App\Models\PassengerPlace;
use App\Models\CityPlace;
use App\Models\Ride;
use App\Services\CityResolver;
use Illuminate\Http\Request;

class PassengerSuggestionsController extends Controller
{
 public function suggestions(Request $request, CityResolver $resolver)
{
    $data = $request->validate([
        'firebase_uid' => 'required|string|max:128',
        'lat' => 'required|numeric',
        'lng' => 'required|numeric',
    ]);

    $passenger = Passenger::where('firebase_uid', $data['firebase_uid'])->first();
    if (! $passenger) {
        return response()->json(['ok'=>false,'msg'=>'Pasajero no encontrado, llama primero /passenger/auth-sync.'], 404);
    }

    $lat = (float)$data['lat'];
    $lng = (float)$data['lng'];

    $resolved = $resolver->resolve($lat, $lng);
    $city = $resolved ? $resolved['city'] : null;

    // saved home/work/fav (slot 0)
    $saved = PassengerPlace::where('passenger_id', $passenger->id)
        ->where('is_active', 1)
        ->whereIn('kind', ['home','work','fav'])
        ->get()
        ->keyBy('kind');

    $usedKeys = []; // dedupe por ~100m

    // Slots fijos
    $items = [null, null, null];

    // Slot 0: home
    if (isset($saved['home'])) {
        $items[0] = $this->mapSavedPlace($saved['home'], 'home', $usedKeys);
    }

    // Slot 1: work
    if (isset($saved['work'])) {
        $items[1] = $this->mapSavedPlace($saved['work'], 'work', $usedKeys);
    }

    // Slot 2: fav > recent > current
    if (isset($saved['fav'])) {
        $items[2] = $this->mapSavedPlace($saved['fav'], 'fav', $usedKeys);
    } else {
        $recent = $this->pickRecentDestination($passenger->id, $city, $usedKeys);
        $items[2] = $recent ?: $this->mapCurrentItem($lat, $lng, $usedKeys);
    }

    // Rellenar huecos home/work con suggested (y si no hay city, usar current)
    for ($i = 0; $i < 2; $i++) {
        if ($items[$i] !== null) continue;

        $fallback = $city ? $this->pickCityFallback($city->id, $passenger->id, $usedKeys) : null;
        $items[$i] = $fallback ?: $this->mapCurrentItem($lat, $lng, $usedKeys);
    }

    // Por seguridad: si algo quedara null, rellena con current
    for ($i = 0; $i < 3; $i++) {
        if ($items[$i] === null) {
            $items[$i] = $this->mapCurrentItem($lat, $lng, $usedKeys);
        }
    }

    return response()->json([
        'ok' => true,
        'city' => $city ? [
            'id' => $city->id,
            'name' => $city->name,
            'slug' => $city->slug,
            'timezone' => $city->timezone,
        ] : null,
        'items' => $items,
    ]);
}

private function mapSavedPlace(PassengerPlace $p, string $kind, array &$usedKeys): array
{
    $this->markUsed($p->lat, $p->lng, $usedKeys);

    return [
        'id'      => $p->id,          // 👈 importante para poder desactivar desde app
        'type'    => $kind,           // home|work|fav
        'label'   => $p->label,
        'lat'     => (float)$p->lat,
        'lng'     => (float)$p->lng,
        'address' => $p->address,
        'source'  => 'saved',
    ];
}

private function mapCurrentItem(float $lat, float $lng, array &$usedKeys): array
{
    $this->markUsed($lat, $lng, $usedKeys);

    return [
        'id'      => null,
        'type'    => 'current',
        'label'   => 'Ubicación actual',
        'lat'     => $lat,
        'lng'     => $lng,
        'address' => null,
        'source'  => 'current',
    ];
}

private function pickRecentDestination(int $passengerId, $cityOrNull, array &$usedKeys): ?array
{
    $rides = Ride::where('passenger_id', $passengerId)
        ->whereNotNull('dest_lat')
        ->whereNotNull('dest_lng')
        ->orderByDesc('requested_at')
        ->limit(30)
        ->get(['dest_lat','dest_lng','dest_label']);

    foreach ($rides as $r) {
        $lat = (float)$r->dest_lat;
        $lng = (float)$r->dest_lng;

        if ($cityOrNull) {
            $dc = $this->haversineKm($lat, $lng, (float)$cityOrNull->center_lat, (float)$cityOrNull->center_lng);
            if ($dc > (float)$cityOrNull->radius_km) continue;
        }

        if ($this->isUsed($lat, $lng, $usedKeys)) continue;

        $this->markUsed($lat, $lng, $usedKeys);

        return [
            'id'      => null,
            'type'    => 'recent',
            'label'   => $r->dest_label ?: 'Reciente',
            'lat'     => $lat,
            'lng'     => $lng,
            'address' => null,
            'source'  => 'recent',
        ];
    }

    return null;
}

private function pickCityFallback(int $cityId, int $passengerId, array &$usedKeys): ?array
{
    $candidates = CityPlace::where('city_id', $cityId)
        ->where('is_active', 1)
        ->where('is_featured', 1)
        ->orderByDesc('priority')
        ->get(['label','lat','lng','address']);

    if ($candidates->isEmpty()) return null;

    $seed = crc32($passengerId . '|' . date('Y-m-d'));
    $idxStart = $seed % $candidates->count();

    for ($i=0; $i < $candidates->count(); $i++) {
        $idx = ($idxStart + $i) % $candidates->count();
        $p = $candidates[$idx];

        $lat = (float)$p->lat;
        $lng = (float)$p->lng;

        if ($this->isUsed($lat, $lng, $usedKeys)) continue;

        $this->markUsed($lat, $lng, $usedKeys);

        return [
            'id'      => null,
            'type'    => 'suggested',
            'label'   => $p->label,
            'lat'     => $lat,
            'lng'     => $lng,
            'address' => $p->address,
            'source'  => 'suggested',
        ];
    }

    return null;
}

private function usedKey(float $lat, float $lng): string
{
    return round($lat, 3) . ',' . round($lng, 3);
}
private function isUsed(float $lat, float $lng, array $usedKeys): bool
{
    return isset($usedKeys[$this->usedKey($lat, $lng)]);
}
private function markUsed(float $lat, float $lng, array &$usedKeys): void
{
    $usedKeys[$this->usedKey($lat, $lng)] = true;
}


    private function mapPlaceItem(string $type, string $label, float $lat, float $lng, ?string $address, string $source): array
    {
        return [
        	'id' => $id,
            'type' => $type,         // home|work|recent|city
            'label' => $label,
            'lat' => $lat,
            'lng' => $lng,
            'address' => $address,
            'source' => $source,     // saved|recent|city
        ];
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat/2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) ** 2;
        return 2 * $R * asin(min(1.0, sqrt($a)));
    }
}
