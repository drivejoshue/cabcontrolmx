<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Driver;
use App\Events\DriverLocationUpdated;

class DriverLocationController extends Controller
{
    public function update(Request $request, Driver $driver)
    {
        $data = $request->validate([
            'lat'     => 'required|numeric',
            'lng'     => 'required|numeric',
            'bearing' => 'nullable|numeric',
            'speed'   => 'nullable|numeric',
        ]);

        $driver->fill([
            'last_lat'     => $data['lat'],
            'last_lng'     => $data['lng'],
            'last_bearing' => $data['bearing'] ?? null,
            'last_speed'   => $data['speed'] ?? null,
            'last_seen_at' => now(),
        ])->save();

        event(new DriverLocationUpdated($driver->tenant_id, [
            'driver_id'  => $driver->id,
            'lat'        => $data['lat'],
            'lng'        => $data['lng'],
            'bearing'    => $data['bearing'] ?? null,
            'speed'      => $data['speed'] ?? null,
            'updated_at' => now()->toISOString(),
        ]));

        return response()->json(['ok' => true]);
    }
}
