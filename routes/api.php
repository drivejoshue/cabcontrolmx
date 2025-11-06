<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\RideController;
use App\Http\Controllers\Api\PassengerController;
use App\Http\Controllers\Api\SectorController as ApiSectorController;
use App\Http\Controllers\Api\TaxiStandController;
use App\Http\Controllers\Api\GeoController;
use App\Http\Controllers\Api\DriverAuthController;
use App\Http\Controllers\Api\DriverShiftController;
use App\Http\Controllers\Api\DriverLocationController;
use App\Http\Controllers\Api\DispatchController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\QueueController; 
use App\Http\Controllers\Admin\DispatchBoardsController;
use App\Http\Controllers\Api\DriverVehiclesController;
use App\Http\Controllers\Admin\DispatchSettingsController;
use Illuminate\Http\Request;
use App\Events\DriverEvent;


Route::post('/test-driver-event', function (Request $request) {
    // Autenticar como usuario de prueba
    $user = \App\Models\User::where('email', 'driver@test.com')->first();
    
    if (!$user) {
        return response()->json(['error' => 'User not found'], 404);
    }

    auth()->login($user);

    $driver = \App\Models\Driver::where('user_id', $user->id)->first();

    event(new DriverEvent(
        tenantId: 1,
        driverId: $driver->id,
        type: 'offers.new',
        payload: [
            'ride_id' => 789,
            'offer_id' => 999,
            'message' => 'Test from API endpoint',
            'from_api' => true
        ]
    ));

    return response()->json([
        'status' => 'success',
        'message' => 'Event sent',
        'user_id' => $user->id,
        'driver_id' => $driver->id
    ]);
});
/* =========== DISPATCH (panel) =========== */
Route::post('/dispatch/quote', [DispatchController::class, 'quote'])->name('api.dispatch.quote');
Route::post('/dispatch/tick',  [DispatchController::class, 'tick']);
Route::get ('/dispatch/boards',   [DispatchBoardsController::class, 'index']);
Route::get ('/dispatch/settings', [DispatchSettingsController::class, 'show']);
Route::get ('/dispatch/runtime',  [DispatchController::class, 'runtime']);
Route::get ('/dispatch/active',   [DispatchController::class, 'active']);
Route::get ('/dispatch/drivers',  [DispatchController::class, 'driversLive']);
Route::post('/dispatch/assign',   [DispatchController::class, 'assign']);
Route::post('/dispatch/rides/{ride}/cancel', [DispatchController::class,'cancel']);
Route::get ('/dispatch/nearby-drivers', [DispatchController::class,'nearbyDrivers']);

/* Geo panel */
Route::post('/geo/route', [GeoController::class, 'route'])->name('api.geo.route');

/* =========== CAPAS MAPA =========== */
Route::get('/sectores',   [ApiSectorController::class, 'index']);
Route::get('/taxistands', [TaxiStandController::class, 'index']);

/* =========== AUTH DRIVER (Maui) =========== */
Route::post('/auth/login',  [DriverAuthController::class, 'login']);
Route::post('/auth/logout', [DriverAuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get ('/auth/me',     [DriverAuthController::class, 'me'])->middleware('auth:sanctum');

/* =========== RIDES CRUD =========== */
Route::prefix('rides')->group(function () {
    Route::get('',   [RideController::class, 'index']);
    Route::post('',  [RideController::class, 'store']);

    Route::post('{ride}/route', [RideController::class, 'setRoute']);
    Route::post('{ride}/quote', [RideController::class, 'quote']);

    Route::get('{ride}',   [RideController::class, 'show']);
    Route::patch('{ride}', [RideController::class, 'update']);

    Route::get('lists/active',    [RideController::class, 'active']);
    Route::get('lists/queued',    [RideController::class, 'queued']);
    Route::get('lists/scheduled', [RideController::class, 'scheduled']);

    Route::post('{ride}/assign', [RideController::class, 'assign']);
    Route::post('{ride}/start',  [RideController::class, 'start']);
    Route::post('{ride}/pickup', [RideController::class, 'pickup']);
    Route::post('{ride}/drop',   [RideController::class, 'drop']);
    Route::post('{ride}/cancel', [RideController::class, 'cancel']);

    Route::post('{ride}/stops',  [RideController::class,'setStops'])->name('panel.rides.stops');
    Route::patch('{ride}/stops', [RideController::class,'updateStops']);
});

/* =========== PASAJEROS =========== */
Route::get('/passengers/last-ride', [PassengerController::class, 'lastRide']);
Route::get('/passengers/lookup',    [PassengerController::class, 'lookup']);

/* =========== DRIVER (Maui) - Sanctum =========== */
Route::middleware('auth:sanctum')->prefix('driver')->group(function () {

    Route::get('/vehicles', [DriverVehiclesController::class, 'index']);

    Route::post('/shifts/start',  [DriverShiftController::class, 'start']);
    Route::post('/shifts/finish', [DriverShiftController::class, 'finish']);

    Route::get ('/offers',                 [OfferController::class, 'index']);
        Route::get('/offers/{offer}', [OfferController::class,'show']);   // ← NUEVO

    Route::post('/offers/{offer}/accept',  [OfferController::class, 'accept']);
    Route::post('/offers/{offer}/reject',  [OfferController::class, 'reject']);

    Route::post('/rides/{ride}/arrived', [RideController::class,'arrive']);
    Route::post('/rides/{ride}/board',   [RideController::class,'board']);
    Route::post('/rides/{ride}/finish',  [RideController::class,'finish']);
    Route::post('/rides/{ride}/cancel',  [RideController::class,'cancelByDriver']);

    Route::get('/rides/active', [RideController::class, 'activeForDriver']);

    Route::get ('/geo/geocode', [GeoController::class,'geocode']);
    Route::post('/geo/route',   [GeoController::class,'route']);

    Route::post('/rides/{ride}/complete-stop', [RideController::class, 'completeStop']);

    Route::match(['put','post'], '/location', [DriverLocationController::class, 'update']);

    // ==== COLA DE OFERTAS DEL DRIVER ====
    Route::get   ('/queue',         [QueueController::class, 'index']);
    Route::post  ('/queue/promote', [QueueController::class, 'promote']);
    Route::delete('/queue/{offer}', [QueueController::class, 'drop']);
    Route::delete('/queue',         [QueueController::class, 'clearAll']);

    // ==== TAXI STAND (BASE) ====
    Route::post('/stands/join',  [TaxiStandController::class, 'join']);   // stand_id o codigo
    Route::post('/stands/leave', [TaxiStandController::class, 'leave']);
    Route::get ('/stands/status',[TaxiStandController::class, 'status']);

    // ==== GEO extra ====
    Route::get('/geo/locate-sector', [GeoController::class, 'locateSector']); // (si lo implementas)
});
