<?php

use Illuminate\Support\Facades\Route;

// ===== Controllers =====
use App\Http\Controllers\Api\RideController;
use App\Http\Controllers\Api\PassengerController;
use App\Http\Controllers\Api\RideOfferController;

use App\Http\Controllers\API\SectorController as ApiSectorController;
use App\Http\Controllers\API\TaxiStandController;
use App\Http\Controllers\API\GeoController;

use App\Http\Controllers\API\DriverAuthController;
use App\Http\Controllers\API\DriverShiftController;
use App\Http\Controllers\API\DriverLocationController;

use App\Http\Controllers\API\DispatchController;

// ===== MAPA: capas estáticas =====


Route::post('/drivers/{driver}/location', [DriverLocationController::class, 'update']); // ← fuera de Sanctum, solo pruebas


Route::get('/sectores',   [ApiSectorController::class, 'index']);
Route::get('/taxistands', [TaxiStandController::class, 'index']);

// ===== RIDES CRUD =====
Route::prefix('rides')->group(function () {
    Route::get('',          [RideController::class, 'index']);
    Route::post('',         [RideController::class, 'store']);
    Route::get('{ride}',    [RideController::class, 'show']);
    Route::patch('{ride}',  [RideController::class, 'update']);

    // listas si las usas luego
    Route::get('lists/active',    [RideController::class, 'active']);
    Route::get('lists/queued',    [RideController::class, 'queued']);
    Route::get('lists/scheduled', [RideController::class, 'scheduled']);

    // transiciones
    Route::post('{ride}/assign', [RideController::class, 'assign']);
    Route::post('{ride}/start',  [RideController::class, 'start']);
    Route::post('{ride}/pickup', [RideController::class, 'pickup']);
    Route::post('{ride}/drop',   [RideController::class, 'drop']);
    Route::post('{ride}/cancel', [RideController::class, 'cancel']);
});

// ===== PASAJEROS util =====
Route::get('/passengers/last-ride', [PassengerController::class, 'lastRide']);
Route::get('/passengers/lookup',    [PassengerController::class, 'lookup']);

// ===== DISPATCH (panel derecho) =====
Route::get ('/dispatch/active',         [DispatchController::class,'active']);
Route::get ('/dispatch/drivers',        [DispatchController::class,'driversLive']);
Route::post('/dispatch/assign',         [DispatchController::class,'assign']);
Route::post('/dispatch/cancel',         [DispatchController::class,'cancel']);
Route::get ('/dispatch/nearby-drivers', [DispatchController::class,'nearbyDrivers']);

// ===== DRIVER (móvil) con Sanctum =====
Route::middleware('auth:sanctum')->prefix('driver')->group(function () {
    Route::post('/shifts/start',  [DriverShiftController::class, 'start']);
    Route::post('/shifts/finish', [DriverShiftController::class, 'finish']);
    Route::post('/location',      [DriverLocationController::class, 'store']);

    Route::post('/offers/{offer}/accept', [RideOfferController::class,'accept']);
    Route::post('/offers/{offer}/reject', [RideOfferController::class,'reject']);

    Route::post('/rides/{ride}/arrived', [RideController::class,'start']);
    Route::post('/rides/{ride}/boarded', [RideController::class,'pickup']);
    Route::post('/rides/{ride}/finish',  [RideController::class,'drop']);

    Route::get ('/geo/geocode', [GeoController::class,'geocode']);
    Route::post('/geo/route',   [GeoController::class,'route']);
});
