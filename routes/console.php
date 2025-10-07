<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\Admin\RideAdminController;

Route::middleware(['auth'])->group(function () {
    Route::get ('/admin/rides/create', [RideAdminController::class,'create'])->name('rides.create');
    Route::post('/admin/rides',         [RideAdminController::class,'store'])->name('rides.store');
    // luego: index/show si quieres
});