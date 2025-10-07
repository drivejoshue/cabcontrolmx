<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\DriverLocationController;
// API controllers (para el fetch del mapa)
use App\Http\Controllers\API\SectorController as ApiSectorController;
use App\Http\Controllers\API\TaxiStandController;

use App\Http\Controllers\API\DriverAuthController;
use App\Http\Controllers\API\DriverShiftController;
use App\Http\Controllers\Api\RideController;
use App\Http\Controllers\Api\RideOfferController;

// Si después quieres auth, le agregamos middleware('auth:sanctum') al grupo.


// Para pruebas sin auth:
Route::post('/drivers/{driver}/location', [DriverLocationController::class, 'update']);

// Protegidas (Sanctum token del chofer)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/driver/shifts/start', [DriverShiftController::class, 'start']);
    Route::post('/driver/shifts/finish', [DriverShiftController::class, 'finish']);
    Route::post('/driver/location', [DriverLocationController::class, 'store']);

    // Ofertas (aceptar/rechazar) — listo para auto-dispatch v1
    Route::post('/driver/offers/{offer}/accept', [RideOfferController::class,'accept']);
    Route::post('/driver/offers/{offer}/reject', [RideOfferController::class,'reject']);

    // Flujo de viaje básico
    Route::post('/driver/rides/{ride}/arrived', [RideController::class,'arrived']);
    Route::post('/driver/rides/{ride}/boarded', [RideController::class,'boarded']);
    Route::post('/driver/rides/{ride}/finish',  [RideController::class,'finish']);
});
