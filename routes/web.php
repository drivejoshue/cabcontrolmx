<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

// Admin controllers (web)
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DispatchController;
use App\Http\Controllers\Admin\SectorController as AdminSectorController;
use App\Http\Controllers\Admin\TaxiStandController as AdminTaxiStandController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Admin\DriverController;

// API controllers (panel con sesión)
use App\Http\Controllers\API\SectorController as ApiSectorController;
use App\Http\Controllers\API\TaxiStandController as ApiTaxiStandController;

Route::redirect('/', '/login');

/**
 * Logout (POST) – Breeze/Fortify no la generan por defecto
 */
Route::post('/logout', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/login');
})->name('logout');

/*
|--------------------------------------------------------------------------
| Admin (web + auth)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    // Dashboard & Dispatch
    Route::get('/admin',           [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/dispatch',  [DispatchController::class, 'index'])->name('admin.dispatch');

    // Perfil (placeholder)
    Route::get('/admin/perfil', fn () => view('admin.profile'))->name('profile.edit');

    // Sectores (CRUD)
    Route::get   ('/admin/sectores',            [AdminSectorController::class,'index'])->name('sectores.index');
    Route::get   ('/admin/sectores/create',     [AdminSectorController::class,'create'])->name('sectores.create');
    Route::post  ('/admin/sectores',            [AdminSectorController::class,'store'])->name('sectores.store');
    Route::get   ('/admin/sectores/{id}',       [AdminSectorController::class,'show'])->name('sectores.show');
    Route::get   ('/admin/sectores/{id}/edit',  [AdminSectorController::class,'edit'])->name('sectores.edit');
    Route::put   ('/admin/sectores/{id}',       [AdminSectorController::class,'update'])->name('sectores.update');
    Route::delete('/admin/sectores/{id}',       [AdminSectorController::class,'destroy'])->name('sectores.destroy');

    // GeoJSON de todos los sectores (para mapas de edición/consulta)
    Route::get('/admin/sectores.geojson', [AdminSectorController::class, 'geojson'])->name('sectores.geojson');

    // Paraderos / TaxiStands (CRUD)
    Route::get   ('/admin/taxistands',             [AdminTaxiStandController::class, 'index'])->name('taxistands.index');
    Route::get   ('/admin/taxistands/create',      [AdminTaxiStandController::class, 'create'])->name('taxistands.create');
    Route::post  ('/admin/taxistands',             [AdminTaxiStandController::class, 'store'])->name('taxistands.store');
    Route::get   ('/admin/taxistands/{id}',        [AdminTaxiStandController::class, 'show'])->name('taxistands.show');
    Route::get   ('/admin/taxistands/{id}/edit',   [AdminTaxiStandController::class, 'edit'])->name('taxistands.edit');
    Route::put   ('/admin/taxistands/{id}',        [AdminTaxiStandController::class, 'update'])->name('taxistands.update');
    Route::delete('/admin/taxistands/{id}',        [AdminTaxiStandController::class, 'destroy'])->name('taxistands.destroy');

    // (Opcional) QR del paradero – solo si tu controller tiene estos métodos
    Route::get('/admin/taxistands/{id}/qr/refresh', [AdminTaxiStandController::class,'refreshQr'])->name('taxistands.qr.refresh');
    Route::get('/admin/taxistands/{id}/qr.png',     [AdminTaxiStandController::class,'qrPng'])->name('taxistands.qr.png');

    // Vehículos (CRUD)
  // Drivers
Route::get   ('/admin/drivers',           [DriverController::class,'index'])->name('drivers.index');
Route::get   ('/admin/drivers/create',    [DriverController::class,'create'])->name('drivers.create');
Route::post  ('/admin/drivers',           [DriverController::class,'store'])->name('drivers.store');
Route::get   ('/admin/drivers/{id}',      [DriverController::class,'show'])->name('drivers.show');
Route::get   ('/admin/drivers/{id}/edit', [DriverController::class,'edit'])->name('drivers.edit');
Route::put   ('/admin/drivers/{id}',      [DriverController::class,'update'])->name('drivers.update');
Route::delete('/admin/drivers/{id}',      [DriverController::class,'destroy'])->name('drivers.destroy');

// Vehicles
Route::get   ('/admin/vehicles',           [VehicleController::class,'index'])->name('vehicles.index');
Route::get   ('/admin/vehicles/create',    [VehicleController::class,'create'])->name('vehicles.create');
Route::post  ('/admin/vehicles',           [VehicleController::class,'store'])->name('vehicles.store');
Route::get   ('/admin/vehicles/{id}',      [VehicleController::class,'show'])->name('vehicles.show');
Route::get   ('/admin/vehicles/{id}/edit', [VehicleController::class,'edit'])->name('vehicles.edit');
Route::put   ('/admin/vehicles/{id}',      [VehicleController::class,'update'])->name('vehicles.update');
Route::delete('/admin/vehicles/{id}',      [VehicleController::class,'destroy'])->name('vehicles.destroy');

 // 👉 Asignar vehículo a driver (POST del modal en show del driver)
    Route::post  ('/admin/drivers/{id}/assign-vehicle', [DriverController::class,'assignVehicle'])->name('drivers.assignVehicle');

    // 👉 Cerrar una asignación (usado en driver y vehicle)
    Route::put   ('/admin/assignments/{id}/close',       [DriverController::class,'closeAssignment'])->name('assignments.close');

});

/*
|--------------------------------------------------------------------------
| API “de panel” (web + auth) – usa la sesión del login
| Se consumen desde JS: fetch(`${BASE}/api/sectores`) …
|--------------------------------------------------------------------------
*/
Route::prefix('api')
    ->middleware(['web', 'auth'])
    ->group(function () {
        Route::get('/sectores',   [ApiSectorController::class, 'index']);
        Route::get('/taxistands', [ApiTaxiStandController::class, 'index']);
    });

/*
|--------------------------------------------------------------------------
| Alias Breeze (/dashboard)
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', fn () => redirect()->route('admin.dashboard'))
    ->middleware('auth')
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| Auth scaffolding (Breeze)
|--------------------------------------------------------------------------
*/
require __DIR__ . '/auth.php';
