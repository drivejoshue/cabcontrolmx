<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Driver;
use App\Events\DriverLocationUpdated;
use Illuminate\Support\Facades\Log;

class DriverLocationController extends Controller
{
   public function update(Request $request, Driver $driver)
{
    $data = $request->validate([
        'lat'     => 'required|numeric',
        'lng'     => 'required|numeric',
        'bearing' => 'nullable|numeric',
        'speed'   => 'nullable|numeric',
        'reported_at' => 'nullable|date',
    ]);

    $now = now();

    // histórico
    \DB::table('driver_locations')->insert([
        'tenant_id'   => $driver->tenant_id,
        'driver_id'   => $driver->id,
        'lat'         => $data['lat'],
        'lng'         => $data['lng'],
        'speed_kmh'   => $data['speed'] ?? 0,
        'heading_deg' => $data['bearing'] ?? 0,
        'reported_at' => $data['reported_at'] ?? $now,
        'created_at'  => $now,
    ]);

    // denormalizado
    $driver->fill([
        'last_lat'     => $data['lat'],
        'last_lng'     => $data['lng'],
        'last_bearing' => $data['bearing'] ?? null,
        'last_speed'   => $data['speed'] ?? null,
        'last_seen_at' => $now,
    ])->save();

   try {
    event(new DriverLocationUpdated($driver->tenant_id, [
        'driver_id'  => $driver->id,
        'lat'        => $data['lat'],
        'lng'        => $data['lng'],
        'bearing'    => $data['bearing'] ?? null,
        'speed'      => $data['speed'] ?? null,
        'updated_at' => $now->toISOString(),
    ]));
} catch (\Throwable $e) {
    \Log::warning('Broadcast failed: '.$e->getMessage());
}

    return response()->json(['ok' => true]);


    
}

}
