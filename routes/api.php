<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
// ===== Controllers =====
use App\Http\Controllers\Api\RideController;
use App\Http\Controllers\Api\PassengerController;

use App\Http\Controllers\Api\SectorController as ApiSectorController;
use App\Http\Controllers\Api\TaxiStandController;
use App\Http\Controllers\Api\GeoController;

use App\Http\Controllers\DriverController;
use App\Http\Controllers\Api\DriverAuthController;
use App\Http\Controllers\Api\DriverShiftController;
use App\Http\Controllers\Api\DriverLocationController;

use App\Http\Controllers\Api\DispatchController;

use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\QueueController;

// ===== MAPA: capas estáticas =====

  
   Route::post('/dispatch/quote', [DispatchController::class, 'quote'])->name('api.dispatch.quote');
    Route::post('/dispatch/tick', [DispatchController::class, 'tick']); // para pruebas

    Route::get('/sectores',   [ApiSectorController::class, 'index']);
    Route::get('/taxistands', [TaxiStandController::class, 'index']);


    Route::post('/auth/login',  [DriverAuthController::class, 'login']);
    Route::post('/auth/logout', [DriverAuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/auth/me',      [DriverAuthController::class, 'me'])->middleware('auth:sanctum'); // ← AQUÍ


    // ===== RIDES CRUD =====
    Route::prefix('rides')->group(function () {
    Route::get('',          [RideController::class, 'index']);
    Route::post('',         [RideController::class, 'store']);

    Route::post('/rides/{ride}/route',   [RideController::class,'setRoute']);
    Route::post('/rides/{ride}/quote',   [RideController::class,'quote']);


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

    Route::post('/dispatch/rides/{ride}/cancel', [DispatchController::class,'cancel']);

    Route::get('/cancel-reasons', [\App\Http\Controllers\Api\RideController::class,'cancelReasons']);
    Route::get ('/dispatch/nearby-drivers', [DispatchController::class,'nearbyDrivers']);



   // ===== DRIVER (móvil) con Sanctum =====
        Route::middleware('auth:sanctum')->prefix('driver')->group(function () {
        Route::post('/shifts/start',  [DriverShiftController::class, 'start']);
        Route::post('/shifts/finish', [DriverShiftController::class, 'finish']);
        
        Route::get('/offers', [OfferController::class, 'index']); // GETconductor autenticado
        Route::post('/offers/{offer}/accept', [OfferController::class,'accept']);
        Route::post('/offers/{offer}/reject', [OfferController::class,'reject']);

        Route::post('/rides/{ride}/arrived', [\App\Http\Controllers\Api\RideController::class,'arrive']);
        Route::post('/rides/{ride}/board',  [\App\Http\Controllers\Api\RideController::class,'board']);
        Route::post('/rides/{ride}/finish', [\App\Http\Controllers\Api\RideController::class,'finish']);

           Route::post('/rides/{ride}/cancel', [RideController::class,'cancelByDriver']);



         Route::get('/rides/active', [\App\Http\Controllers\Api\RideController::class, 'activeForDriver']);

        Route::get ('/geo/geocode', [GeoController::class,'geocode']);
        Route::post('/geo/route',   [GeoController::class,'route']);

        Route::match(['put','post'], '/location',[DriverLocationController::class, 'update']
); 


    });
