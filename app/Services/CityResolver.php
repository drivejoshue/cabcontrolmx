<?php  
namespace App\Services;

use App\Models\City;

class CityResolver
{
    public function resolve(float $lat, float $lng): ?array
    {
        $cities = City::where('is_active', 1)->get([
            'id','name','slug','timezone','center_lat','center_lng','radius_km'
        ]);

        if ($cities->isEmpty()) return null;

        $bestInside = null;   // dentro del radio
        $bestAny    = null;   // más cercana (fallback)

        foreach ($cities as $city) {
            $d = $this->haversineKm($lat, $lng, (float)$city->center_lat, (float)$city->center_lng);

            if ($bestAny === null || $d < $bestAny['distance_km']) {
                $bestAny = ['city' => $city, 'distance_km' => $d, 'inside' => ($d <= (float)$city->radius_km)];
            }
            if ($d <= (float)$city->radius_km) {
                if ($bestInside === null || $d < $bestInside['distance_km']) {
                    $bestInside = ['city' => $city, 'distance_km' => $d, 'inside' => true];
                }
            }
        }

        return $bestInside ?? $bestAny;
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
