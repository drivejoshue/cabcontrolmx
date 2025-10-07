<?php
namespace App\Services\Geo;

use Illuminate\Support\Facades\Http;

class GoogleMapsService {
    public function geocode(string $q): array {
        $r = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/geocode/json',[
            'address'=>$q,'key'=>config('services.google.key')
        ])->throw()->json();
        return collect($r['results'] ?? [])->map(fn($it)=>[
            'label'=>$it['formatted_address'] ?? '',
            'lat'=>$it['geometry']['location']['lat'] ?? null,
            'lng'=>$it['geometry']['location']['lng'] ?? null,
            'place_id'=>$it['place_id'] ?? null,
        ])->values()->all();
    }

    public function reverse(float $lat,float $lng): ?array {
        $r = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/geocode/json',[
            'latlng'=>"$lat,$lng",'key'=>config('services.google.key')
        ])->throw()->json();
        $it = $r['results'][0] ?? null;
        if(!$it) return null;
        return [
            'label'=>$it['formatted_address'] ?? '',
            'place_id'=>$it['place_id'] ?? null,
        ];
    }

    public function route(array $o,array $d): array {
        $r = Http::timeout(8)->post('https://routes.googleapis.com/directions/v2:computeRoutes',[
            'origin'=>['location'=>['latLng'=>['latitude'=>$o['lat'],'longitude'=>$o['lng']]]],
            'destination'=>['location'=>['latLng'=>['latitude'=>$d['lat'],'longitude'=>$d['lng']]]],
            'travelMode'=>'DRIVE',
            'polylineEncoding'=>'ENCODED_POLYLINE'
        ])->withHeaders([
            'X-Goog-Api-Key'=>config('services.google.key'),
            'X-Goog-FieldMask'=>'routes.distanceMeters,routes.duration,routes.polyline.encodedPolyline'
        ])->throw()->json();

        $route = $r['routes'][0] ?? [];
        return [
            'distance_m'=>$route['distanceMeters'] ?? 0,
            'duration_s'=> isset($route['duration']) ? (int)trim($route['duration'],'s') : 0,
            'polyline'  => data_get($route,'polyline.encodedPolyline'),
        ];
    }
}
