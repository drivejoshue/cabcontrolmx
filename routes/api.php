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
use App\Http\Controllers\Admin\DispatchSettingsController;
use App\Http\Controllers\Admin\DispatchBoardsController;
use App\Http\Controllers\Api\DriverVehiclesController;

/* =========================
 *  DISPATCH (panel)
 * ========================= */
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

/* Geo para el panel (¡OJO! path correcto: /api/geo/route) */
Route::post('/geo/route', [GeoController::class, 'route'])->name('api.geo.route');

/* =========================
 *  CAPAS MAPA
 * ========================= */
Route::get('/sectores',   [ApiSectorController::class, 'index']);
Route::get('/taxistands', [TaxiStandController::class, 'index']);

/* =========================
 *  AUTH DRIVER (Maui)
 * ========================= */
Route::post('/auth/login',  [DriverAuthController::class, 'login']);
Route::post('/auth/logout', [DriverAuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get ('/auth/me',     [DriverAuthController::class, 'me'])->middleware('auth:sanctum');

/* =========================
 *  RIDES CRUD
 * ========================= */
Route::prefix('rides')->group(function () {
    Route::get('',   [RideController::class, 'index']);
    Route::post('',  [RideController::class, 'store']);

    // ❌ ESTO ANTES TENÍA /rides/... y quedaba /api/rides/rides/...  (404)
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

    // Stops (corregido sin duplicar /rides)
    Route::post('{ride}/stops',  [RideController::class,'setStops'])->name('panel.rides.stops');
    Route::patch('{ride}/stops', [RideController::class,'updateStops']);
});

/* =========================
 *  PASAJEROS
 * ========================= */
Route::get('/passengers/last-ride', [PassengerController::class, 'lastRide']);
Route::get('/passengers/lookup',    [PassengerController::class, 'lookup']);

/* =========================
 *  DRIVER (Maui) - Sanctum
 * ========================= */
Route::middleware('auth:sanctum')->prefix('driver')->group(function () {

     Route::get('/vehicles', [DriverVehiclesController::class, 'index']);

    Route::post('/shifts/start',  [DriverShiftController::class, 'start']);
    Route::post('/shifts/finish', [DriverShiftController::class, 'finish']);

    Route::get ('/offers',                 [OfferController::class, 'index']);
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
});
