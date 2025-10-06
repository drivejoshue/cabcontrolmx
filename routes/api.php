<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\DriverLocationController;
// API controllers (para el fetch del mapa)
use App\Http\Controllers\API\SectorController as ApiSectorController;
use App\Http\Controllers\API\TaxiStandController;


// Si después quieres auth, le agregamos middleware('auth:sanctum') al grupo.


// Para pruebas sin auth:
Route::post('/drivers/{driver}/location', [DriverLocationController::class, 'update']);


Route::middleware(['web','auth'])->group(function () {
    Route::get('/taxistands', [TaxiStandController::class, 'index']);
});

