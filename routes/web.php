<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

use App\Events\DriverLocationUpdated;

// Admin controllers
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DispatchController;
use App\Http\Controllers\Admin\SectorController as AdminSectorController;
use App\Http\Controllers\Admin\TaxiStandController as AdminTaxiStandController;

use App\Http\Controllers\API\SectorController as ApiSectorController;
use App\Http\Controllers\API\TaxiStandController as ApiTaxiStandController;



Route::redirect('/', '/login');

// Logout (Breeze/Fortify no crean esta POST por defecto)
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
    Route::get('/admin', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/dispatch', [DispatchController::class, 'index'])->name('admin.dispatch');

    // Perfil simple (placeholder)
    Route::get('/admin/perfil', fn () => view('admin.profile'))->name('profile.edit');

    // Sectores (CRUD de backoffice)
    // CRUD Sectores (admin)
    Route::get   ('/admin/sectores',            [AdminSectorController::class,'index'])->name('sectores.index');
    Route::get   ('/admin/sectores/create',     [AdminSectorController::class,'create'])->name('sectores.create');
    Route::post  ('/admin/sectores',            [AdminSectorController::class,'store'])->name('sectores.store');
    Route::get   ('/admin/sectores/{id}',       [AdminSectorController::class,'show'])->name('sectores.show');
    Route::get   ('/admin/sectores/{id}/edit',  [AdminSectorController::class,'edit'])->name('sectores.edit');
    Route::put   ('/admin/sectores/{id}',       [AdminSectorController::class,'update'])->name('sectores.update');
    Route::delete('/admin/sectores/{id}',       [AdminSectorController::class,'destroy'])->name('sectores.destroy');

    Route::get('/admin/sectores.geojson', [AdminSectorController::class, 'geojson'])
->name('sectores.geojson');


    // TaxiStands (paraderos/bases)
    Route::get   ('/admin/taxistands',            [AdminTaxiStandController::class, 'index'])->name('taxistands.index');
    Route::get   ('/admin/taxistands/create',     [AdminTaxiStandController::class, 'create'])->name('taxistands.create');
    Route::post  ('/admin/taxistands',            [AdminTaxiStandController::class, 'store'])->name('taxistands.store');
    Route::get   ('/admin/taxistands/{id}/edit',  [AdminTaxiStandController::class, 'edit'])->name('taxistands.edit');
    Route::put   ('/admin/taxistands/{id}',       [AdminTaxiStandController::class, 'update'])->name('taxistands.update');
    Route::delete('/admin/taxistands/{id}',       [AdminTaxiStandController::class, 'destroy'])->name('taxistands.destroy');

    Route::get('/admin/taxistands/{id}',        [AdminTaxiStand::class,'show'])->name('taxistands.show');
    Route::get('/admin/taxistands/{id}/qr/refresh', [AdminTaxiStand::class,'refreshQr'])->name('taxistands.qr.refresh');
    Route::get('/admin/taxistands/{id}/qr.png',     [AdminTaxiStand::class,'qrPng'])->name('taxistands.qr.png');


});

/*
|--------------------------------------------------------------------------
| API “de panel” (web + auth)  → usa la sesión del login
| Se consumen desde JS: fetch(`${BASE}/api/sectores`)…
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
| Alias para Breeze (/dashboard)
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
require __DIR__.'/auth.php';
