<?php
namespace App\Services\Geo;

use Illuminate\Support\Facades\Http;

class GoogleMapsService
{
    public function geocode(string $q): array {
        $r = Http::timeout(8)
            ->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address'=>$q,
                'key'=>config('services.google.key'),
                'language'=>'es'
            ])->throw()->json();

        return collect($r['results'] ?? [])->map(function($it){
            return [
                'label' => $it['formatted_address'] ?? '',
                'lat'   => data_get($it,'geometry.location.lat'),
                'lng'   => data_get($it,'geometry.location.lng'),
                'place_id' => $it['place_id'] ?? null,
            ];
        })->values()->all();
    }

    public function route(float $olat,float $olng,float $dlat,float $dlng): array {
        $resp = Http::timeout(8)
            ->withHeaders([
                'X-Goog-Api-Key' => config('services.google.key'),
                'X-Goog-FieldMask' =>
                  'routes.distanceMeters,routes.duration,routes.polyline.encodedPolyline'
            ])->post('https://routes.googleapis.com/directions/v2:computeRoutes', [
                'origin'      => ['location'=>['latLng'=>['latitude'=>$olat,'longitude'=>$olng]]],
                'destination' => ['location'=>['latLng'=>['latitude'=>$dlat,'longitude'=>$dlng]]],
                'travelMode'  => 'DRIVE',
                'polylineEncoding' => 'ENCODED_POLYLINE'
            ])->throw()->json();

        $r = $resp['routes'][0] ?? [];
        return [
            'distance_m' => $r['distanceMeters'] ?? 0,
            'duration_s' => isset($r['duration']) ? (int) trim($r['duration'],'s') : 0,
            'polyline'   => data_get($r,'polyline.encodedPolyline'),
        ];
    }
}
