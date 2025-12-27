<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// Public
use App\Http\Controllers\Public\TenantSignupController;

// Admin (tenant panel)
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DispatchController;
use App\Http\Controllers\Admin\SectorController as AdminSectorController;
use App\Http\Controllers\Admin\TaxiStandController as AdminTaxiStandController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Admin\DriverController;
use App\Http\Controllers\Admin\DispatchSettingsController;
use App\Http\Controllers\Admin\TenantFarePolicyController;
use App\Http\Controllers\Admin\Reports\RidesReportController;
use App\Http\Controllers\Admin\Reports\RatingReportController;
use App\Http\Controllers\Admin\Reports\DriverActivityReportController;
use App\Http\Controllers\Admin\Reports\ClientsReportController;
use App\Http\Controllers\Admin\VehicleDocsController;
use App\Http\Controllers\Admin\DriverDocsController;
use App\Http\Controllers\Admin\TenantProfileController;
use App\Http\Controllers\Admin\TenantSettingsController;
use App\Http\Controllers\Admin\RideIssueController as AdminRideIssueController;
use App\Http\Controllers\Admin\OnboardingController;
use App\Http\Controllers\Admin\TenantBillingController as AdminTenantBillingController;
use App\Http\Controllers\Admin\AdminRideController;
use App\Http\Controllers\Admin\TenantWalletTopupController;
use App\Http\Controllers\Admin\TenantWalletController;
use App\Http\Controllers\Admin\StaffUserController;
use App\Http\Controllers\Admin\TenantQrPointController;

// API “panel” (web+auth) para JS (dentro de /admin)
use App\Http\Controllers\Api\SectorController as ApiSectorController;
use App\Http\Controllers\Api\TaxiStandController as ApiTaxiStandController;
use App\Http\Controllers\Admin\Billing\TaxiFeesController;
use App\Http\Controllers\Admin\Billing\TaxiChargesController;
// SysAdmin
use App\Http\Controllers\SysAdmin\TenantController as SysTenantController;
use App\Http\Controllers\SysAdmin\TenantBillingController;
use App\Http\Controllers\SysAdmin\TenantInvoiceController;
use App\Http\Controllers\SysAdmin\VehicleDocumentController;
use App\Http\Controllers\SysAdmin\TenantCommissionReportController;
use App\Http\Controllers\SysAdmin\DashboardController as SysAdminDashboardController;
use App\Http\Controllers\SysAdmin\VerificationQueueController;
use App\Http\Controllers\SysAdmin\SysDriverDocumentController;
use App\Http\Controllers\Admin\TenantTopupController;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Models\Tenant;
use App\Http\Controllers\Admin\BI\DemandHeatmapController;
// Debug/events
use App\Events\TestEvent;

use App\Http\Controllers\Webhooks\MercadoPagoWebhookController;


/*
|--------------------------------------------------------------------------
| Landing pública
|--------------------------------------------------------------------------
*/


Route::match(['GET', 'POST'], '/webhooks/mercadopago', [MercadoPagoWebhookController::class, 'handle'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhooks.mercadopago');

    
Route::get('/', [TenantSignupController::class, 'landing'])->name('public.landing');


Route::post('/logout', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    
    // Redirigir SIEMPRE a login después de logout
    return redirect('/login');
})->middleware('auth')->name('logout');



/*
|--------------------------------------------------------------------------
| Registro público
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/signup', [TenantSignupController::class, 'show'])->name('public.signup');

    Route::post('/signup', [TenantSignupController::class, 'store'])
        ->middleware('throttle:signup')
        ->name('public.signup.store');

    Route::get('/signup/success', [TenantSignupController::class, 'success'])->name('public.signup.success');
});



Route::get('/email/verify', function () {
    return view('public.verify-email');
})->middleware('auth')->name('verification.notice');




Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {

    // Marca email_verified_at y dispara Verified (si aplica)
    $request->fulfill();

    // Activa tenant al verificar correo
    $u = $request->user();
    if ($u && (int)$u->tenant_id > 0) {
        Tenant::where('id', $u->tenant_id)
            ->where('public_active', 0)
            ->update(['public_active' => 1]);
    }

    // Redirección a onboarding (si todavía no lo termina)
    $tenant = $u && $u->tenant_id ? Tenant::find($u->tenant_id) : null;
    if ($tenant && empty($tenant->onboarding_done_at)) {
        return redirect()->route('admin.onboarding')
            ->with('status', 'Correo verificado. Completa los primeros pasos para activar tu central.');
    }

    return redirect()->route('admin.dashboard');
})->middleware(['auth', 'signed', 'throttle:6,1'])->name('verification.verify');




Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return back()->with('status', 'verification-link-sent');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

Route::view('/pending', 'public.pending')->name('public.pending-tenant');

/*
|--------------------------------------------------------------------------
| Redirect inteligente según sesión (entrada rápida)
|--------------------------------------------------------------------------
*/
Route::get('/go', function () {
    if (!Auth::check()) return redirect('/login');

    $u = Auth::user();

    if (!empty($u->is_sysadmin)) return redirect()->route('sysadmin.dashboard');
    if (!empty($u->is_admin)) return redirect()->route('admin.dashboard');
    if (!empty($u->is_dispatcher)) return redirect()->route('dispatch');

    abort(403, 'Tu cuenta no tiene permisos asignados. Contacta a un administrador.');
})->name('go');

Route::get('/dashboard', function () {
    if (!Auth::check()) return redirect('/login');

    $u = Auth::user();

    if (!empty($u->is_sysadmin)) return redirect()->route('sysadmin.dashboard');
    if (!empty($u->is_admin)) return redirect()->route('admin.dashboard');
    if (!empty($u->is_dispatcher)) return redirect()->route('dispatch');

    abort(403, 'Tu cuenta no tiene permisos asignados. Contacta a un administrador.');
})->middleware('auth')->name('dashboard');


/*
|--------------------------------------------------------------------------
| Reportes ratings (si quieres, puedes meterlos dentro de /admin)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'dispatch', 'tenant.onboarded'])->group(function () {
    Route::get('/dispatch', [\App\Http\Controllers\Admin\DispatchController::class, 'index'])
        ->name('dispatch');
});



Route::middleware('auth')->group(function () {
    Route::get('/ratings/reports', [RatingReportController::class, 'index'])->name('ratings.index');
    Route::get('/ratings/driver/{driverId}', [RatingReportController::class, 'showDriver'])->name('ratings.show');
});



Route::prefix('admin')
    ->middleware(['auth','admin','tenant.ready'])
    ->group(function () {

        // ---------------------------
        // Onboarding / Mi central (sin tenant.onboarded)
        // ---------------------------
        Route::get('/onboarding', [OnboardingController::class, 'index'])->name('admin.onboarding');
        Route::get('/onboarding/cities', [OnboardingController::class, 'cities'])->name('admin.onboarding.cities');
        Route::post('/onboarding/location', [OnboardingController::class, 'saveLocation'])->name('admin.onboarding.location');
        Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('admin.onboarding.complete');

        Route::get('/mi-central', [TenantProfileController::class, 'edit'])->name('admin.tenant.edit');
        Route::post('/mi-central', [TenantProfileController::class, 'update'])->name('admin.tenant.update');

        // ============================================================
        // SOLO SI tenant.onboarded
        // ============================================================
        Route::middleware(['tenant.onboarded'])->group(function () {

            // ---------------------------
            // Billing + Wallet (SIEMPRE accesible, aunque no haya saldo)
            // ---------------------------
            Route::get('/billing', [AdminTenantBillingController::class, 'plan'])->name('admin.billing.plan');
            Route::post('/billing/accept-terms', [AdminTenantBillingController::class, 'acceptTerms'])->name('admin.billing.accept_terms');

            Route::get('/billing/invoices/{invoice}', [AdminTenantBillingController::class, 'invoiceShow'])->name('admin.billing.invoices.show');
            Route::get('/billing/invoices/{invoice}/csv', [AdminTenantBillingController::class, 'invoiceCsv'])->name('admin.billing.invoices.csv');
            Route::get('/billing/invoices/{invoice}/pdf', [AdminTenantBillingController::class, 'invoicePdf'])->name('admin.billing.invoice_pdf');

            Route::post('/billing/invoices/{invoice}/pay-wallet', [AdminTenantBillingController::class, 'payWithWallet'])
                ->name('billing.invoices.pay_wallet');

            Route::get('/wallet', [TenantWalletController::class, 'index'])->name('admin.wallet.index');
            Route::get('/wallet/movements', [TenantWalletController::class, 'movements'])->name('admin.wallet.movements');

            Route::post('/wallet/topup-manual', [TenantWalletController::class, 'topupManual'])->name('admin.wallet.topup.manual');
            // recargar wallet    


          Route::get('/wallet', [\App\Http\Controllers\Admin\TenantWalletController::class, 'index'])->name('admin.wallet.index');
    Route::get('/wallet/movements', [\App\Http\Controllers\Admin\TenantWalletController::class, 'movements'])->name('admin.wallet.movements');

    Route::get('/wallet/topup', [\App\Http\Controllers\Admin\TenantWalletTopupController::class, 'create'])->name('admin.wallet.topup.create');
    Route::post('/wallet/topup', [\App\Http\Controllers\Admin\TenantWalletTopupController::class, 'store'])->name('admin.wallet.topup.store');

    Route::post('/admin/wallet/transfer/notice', function () {
    return back()->with('warning', 'Transferencia: flujo pendiente (se implementa en SysAdmin).');
})->name('admin.wallet.transfer.notice.store');

Route::get('/admin/wallet/topup/{topup}/checkout', [\App\Http\Controllers\Admin\TenantWalletTopupController::class, 'checkout'])
    ->name('admin.wallet.topup.checkout');

Route::get('/admin/wallet/topup/{topup}/status', [\App\Http\Controllers\Admin\TenantWalletTopupController::class, 'status'])
    ->name('admin.wallet.topup.status');


            // ---------------------------
            // Operación (requiere saldo suficiente)
            // ---------------------------
            Route::middleware(['tenant.balance'])->group(function () {

                Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
                Route::get('/dispatch', [DispatchController::class, 'index'])->name('admin.dispatch');

               Route::get('/profile', [\App\Http\Controllers\Admin\ProfileController::class, 'edit'])->name('admin.profile.edit');
              Route::post('/profile', [\App\Http\Controllers\Admin\ProfileController::class, 'update'])->name('admin.profile.update');

                // Rides
                Route::get('/rides', [AdminRideController::class, 'index'])->name('admin.rides.index');
                Route::get('/rides/{ride}', [AdminRideController::class, 'show'])->name('admin.rides.show');

                // Drivers
                Route::get('/drivers', [DriverController::class,'index'])->name('drivers.index');
                Route::get('/drivers/create', [DriverController::class,'create'])->name('drivers.create');
                Route::post('/drivers', [DriverController::class,'store'])->name('drivers.store');
                Route::get('/drivers/{id}', [DriverController::class,'show'])->name('drivers.show');
                Route::get('/drivers/{id}/edit', [DriverController::class,'edit'])->name('drivers.edit');
                Route::put('/drivers/{id}', [DriverController::class,'update'])->name('drivers.update');
                Route::delete('/drivers/{id}', [DriverController::class,'destroy'])->name('drivers.destroy');
                Route::post('/drivers/{id}/assign-vehicle', [DriverController::class,'assignVehicle'])->name('drivers.assignVehicle');
                Route::put('/assignments/{id}/close', [DriverController::class,'closeAssignment'])->name('assignments.close');

                // Vehicles
                Route::get('/vehicles', [VehicleController::class,'index'])->name('vehicles.index');
                Route::get('/vehicles/create', [VehicleController::class,'create'])->name('vehicles.create');
                Route::post('/vehicles', [VehicleController::class,'store'])->name('vehicles.store');
                Route::get('/vehicles/{id}', [VehicleController::class,'show'])->name('vehicles.show');
                Route::get('/vehicles/{id}/edit', [VehicleController::class,'edit'])->name('vehicles.edit');
                Route::put('/vehicles/{id}', [VehicleController::class,'update'])->name('vehicles.update');
                Route::delete('/vehicles/{id}', [VehicleController::class,'destroy'])->name('vehicles.destroy');
                Route::post('/vehicles/{id}/assign-driver', [VehicleController::class,'assignDriver'])->name('vehicles.assignDriver');

                // Sectores
                Route::get('/sectores', [AdminSectorController::class,'index'])->name('sectores.index');
                Route::get('/sectores/create', [AdminSectorController::class,'create'])->name('sectores.create');
                Route::post('/sectores', [AdminSectorController::class,'store'])->name('sectores.store');
                Route::get('/sectores/{id}', [AdminSectorController::class,'show'])->name('sectores.show');
                Route::get('/sectores/{id}/edit', [AdminSectorController::class,'edit'])->name('sectores.edit');
                Route::put('/sectores/{id}', [AdminSectorController::class,'update'])->name('sectores.update');
                Route::delete('/sectores/{id}', [AdminSectorController::class,'destroy'])->name('sectores.destroy');
                Route::get('/sectores.geojson', [AdminSectorController::class, 'geojson'])->name('sectores.geojson');

                // TaxiStands
                Route::get('/taxistands', [AdminTaxiStandController::class, 'index'])->name('taxistands.index');
                Route::get('/taxistands/create', [AdminTaxiStandController::class, 'create'])->name('taxistands.create');
                Route::post('/taxistands', [AdminTaxiStandController::class, 'store'])->name('taxistands.store');
                Route::get('/taxistands/{id}', [AdminTaxiStandController::class, 'show'])->name('taxistands.show');
                Route::get('/taxistands/{id}/edit', [AdminTaxiStandController::class, 'edit'])->name('taxistands.edit');
                Route::put('/taxistands/{id}', [AdminTaxiStandController::class, 'update'])->name('taxistands.update');
                Route::delete('/taxistands/{id}', [AdminTaxiStandController::class, 'destroy'])->name('taxistands.destroy');
                Route::get('/taxistands/{id}/qr/refresh', [AdminTaxiStandController::class,'refreshQr'])->name('taxistands.qr.refresh');
                Route::get('/taxistands/{id}/qr.png', [AdminTaxiStandController::class,'qrPng'])->name('taxistands.qr.png');


                  Route::get('/qr-points',             [TenantQrPointController::class, 'index'])->name('admin.qr-points.index');
                    Route::get('/qr-points/create',      [TenantQrPointController::class, 'create'])->name('admin.qr-points.create');
                    Route::post('/qr-points',            [TenantQrPointController::class, 'store'])->name('admin.qr-points.store');
                    Route::get('/qr-points/{qrPoint}/edit', [TenantQrPointController::class, 'edit'])->name('admin.qr-points.edit');
                    Route::put('/qr-points/{qrPoint}',   [TenantQrPointController::class, 'update'])->name('admin.qr-points.update');
                    Route::delete('/qr-points/{qrPoint}',[TenantQrPointController::class, 'destroy'])->name('admin.qr-points.destroy');
                    Route::get('/qr-points/{qrPoint}', [TenantQrPointController::class, 'show'])->name('admin.qr-points.show');

                // cuotas taxis  

                Route::get('/cobros/cuotas-taxi', [TaxiFeesController::class, 'index'])->name('admin.taxi_fees');
                Route::post('/cobros/cuotas-taxi/{id}', [TaxiFeesController::class, 'update'])->name('admin.taxi_fees.update');

                Route::get('/cobros/taxi', [TaxiChargesController::class, 'index'])->name('admin.taxi_charges');
                Route::post('/cobros/taxi/generar', [TaxiChargesController::class, 'generate'])->name('admin.taxi_charges.generate');
                Route::post('/cobros/taxi/{charge}/pagar', [TaxiChargesController::class, 'markPaid'])->name('admin.taxi_charges.pay');
                Route::post('/cobros/taxi/{charge}/cancelar', [TaxiChargesController::class, 'cancel'])->name('admin.taxi_charges.cancel');

                Route::post('/cobros/taxi/{charge}/recibo', [TaxiChargesController::class, 'issueReceipt'])->name('admin.taxi_charges.receipt');
                Route::get('/cobros/taxi/recibos/{receipt}', [TaxiChargesController::class, 'receiptShow'])->name('admin.taxi_receipts.show');



                // Settings

                Route::get('/usuarios', [StaffUserController::class, 'index'])->name('admin.users.index');
                Route::get('/usuarios/create', [StaffUserController::class, 'create'])->name('admin.users.create');
                Route::post('/usuarios', [StaffUserController::class, 'store'])->name('admin.users.store');

                Route::get('/usuarios/{user}/edit', [StaffUserController::class, 'edit'])->name('admin.users.edit');
                Route::put('/usuarios/{user}', [StaffUserController::class, 'update'])->name('admin.users.update');

                // Acciones de password (sin mail, o con mail)
                Route::post('/usuarios/{user}/set-password', [StaffUserController::class, 'setPassword'])->name('admin.users.set_password');
                Route::post('/usuarios/{user}/send-reset', [StaffUserController::class, 'sendResetLink'])->name('admin.users.send_reset');



                  Route::get('/tenant-settings',  [TenantSettingsController::class, 'edit'])
                ->name('tenant_settings.edit');

            Route::put('/tenant-settings',  [TenantSettingsController::class, 'update'])
                ->name('tenant_settings.update');

                Route::get('/dispatch-settings', [DispatchSettingsController::class, 'edit'])->name('admin.dispatch_settings.edit');
                Route::put('/dispatch-settings', [DispatchSettingsController::class, 'update'])->name('admin.dispatch_settings.update');

                Route::get('/fare-policies', [TenantFarePolicyController::class, 'index'])->name('admin.fare_policies.index');
                Route::get('/fare-policies/edit', [TenantFarePolicyController::class, 'edit'])->name('admin.fare_policies.edit');
                Route::put('/fare-policies/update', [TenantFarePolicyController::class, 'update'])->name('admin.fare_policies.update');

                // Docs
                Route::get('/vehicles/{id}/documents', [VehicleDocsController::class,'index'])->name('vehicles.documents.index');
                Route::post('/vehicles/{id}/documents', [VehicleDocsController::class,'store'])->name('vehicles.documents.store');
                Route::get('/vehicle-documents/{doc}/download', [VehicleDocsController::class,'download'])->name('vehicles.documents.download');
                Route::post('/vehicle-documents/{doc}/delete', [VehicleDocsController::class,'destroy'])->name('vehicles.documents.delete');

                Route::get('/drivers/{id}/documents', [DriverDocsController::class,'index'])->name('drivers.documents.index');
                Route::post('/drivers/{id}/documents', [DriverDocsController::class,'store'])->name('drivers.documents.store');
                Route::get('/driver-documents/{doc}/download', [DriverDocsController::class,'download'])->name('drivers.documents.download');
                Route::post('/driver-documents/{doc}/delete', [DriverDocsController::class,'destroy'])->name('drivers.documents.delete');


Route::get('/reportes/clientes', [ClientsReportController::class, 'index'])
    ->name('admin.reports.clients');

Route::get('/reportes/clientes/{ref}', [ClientsReportController::class, 'show'])
    ->name('admin.reports.clients.show');

// (Opcional) Export CSV de clientes (si lo implementas después)
Route::get('/reportes/clientes.csv', [ClientsReportController::class, 'exportCsv'])
    ->name('admin.reports.clients.csv');



                Route::get('/reportes/viajes', [RidesReportController::class, 'index'])->name('admin.reports.rides');
                Route::get('/reportes/viajes/{ride}', [RidesReportController::class, 'show'])->name('admin.reports.rides.show');
                Route::get('/reportes/viajes.csv', [RidesReportController::class, 'exportCsv'])->name('admin.reports.rides.csv');

                Route::get('/ride-issues', [AdminRideIssueController::class, 'index'])->name('ride_issues.index');
                Route::get('/ride-issues/{issue}', [AdminRideIssueController::class, 'show'])->name('ride_issues.show');
                Route::post('/ride-issues/{issue}/status', [AdminRideIssueController::class, 'updateStatus'])->name('ride_issues.update_status');

                Route::prefix('reportes')->as('reports.')->group(function () {
                    Route::get('conductores', [DriverActivityReportController::class, 'index'])->name('drivers');
                    Route::get('conductores/actividad', [DriverActivityReportController::class, 'index'])->name('drivers.activity');
                });


                Route::get('/bi/mapa-demanda', [DemandHeatmapController::class, 'index'])
                  ->name('admin.bi.demand');

                Route::get('/bi/api/heat-origins', [DemandHeatmapController::class, 'heatOrigins'])
                  ->name('admin.bi.heat.origins');

                // API panel
                Route::prefix('api')->group(function () {
                    Route::get('/sectores', [ApiSectorController::class, 'index']);
                    Route::get('/taxistands', [ApiTaxiStandController::class, 'index']);
                });
            });
        });
    });




/*
|--------------------------------------------------------------------------
| PANEL SYSADMIN (/sysadmin/… ) – totalmente separado del tenant
|--------------------------------------------------------------------------
| Aquí SOLO entra quien tenga is_sysadmin = 1 → Gate::define('sysadmin')
| No hay ningún enlace a estas rutas desde el panel /admin.
*/
Route::prefix('sysadmin')
    ->middleware(['auth','sysadmin'])
    ->group(function () {

        // Dashboard SysAdmin
        Route::get('/', [SysAdminDashboardController::class, 'index'])
            ->name('sysadmin.dashboard');

      // Tenants (lista y alta)
        Route::get ('/tenants',               [SysTenantController::class, 'index'])->name('sysadmin.tenants.index');
        Route::get ('/tenants/create',        [SysTenantController::class, 'create'])->name('sysadmin.tenants.create');
        Route::post('/tenants',               [SysTenantController::class, 'store'])->name('sysadmin.tenants.store');
        Route::get ('/tenants/{tenant}/edit', [SysTenantController::class, 'edit'])->name('sysadmin.tenants.edit');
        Route::post('/tenants/{tenant}',      [SysTenantController::class, 'update'])->name('sysadmin.tenants.update');

        // Billing del tenant
        Route::get ('/tenants/{tenant}/billing', [TenantBillingController::class, 'show'])
            ->name('sysadmin.tenants.billing.show');
        Route::post('/tenants/{tenant}/billing', [TenantBillingController::class, 'update'])
            ->name('sysadmin.tenants.billing.update');

        // Facturas a tenants
        Route::get('/invoices',              [TenantInvoiceController::class, 'index'])
            ->name('sysadmin.invoices.index');
        Route::get('/invoices/{invoice}',    [TenantInvoiceController::class, 'show'])
            ->name('sysadmin.invoices.show');

        // Documentos de vehículos
        Route::get('/tenants/{tenant}/vehicles/{vehicle}/documents', [VehicleDocumentController::class, 'index'])
            ->name('sysadmin.vehicles.documents.index');
        Route::post('/tenants/{tenant}/vehicles/{vehicle}/documents', [VehicleDocumentController::class, 'store'])
            ->name('sysadmin.vehicles.documents.store');
        Route::post('/vehicle-documents/{document}/review', [VehicleDocumentController::class, 'review'])
            ->name('sysadmin.vehicle-documents.review');
        Route::get('/vehicle-documents/{document}/download', [VehicleDocumentController::class, 'download'])
            ->name('sysadmin.vehicle-documents.download');

        // Reporte de comisiones por tenant
        Route::get('/tenants/{tenant}/reports/commissions', [TenantCommissionReportController::class, 'index'])
            ->name('sysadmin.tenants.reports.commissions');


            // Cola de verificación (documentos, etc.)
        Route::get('/verifications', [VerificationQueueController::class, 'index'])
            ->name('sysadmin.verifications.index');

        Route::get('/verifications/vehicles/{document}', [VerificationQueueController::class, 'showVehicle'])
            ->name('sysadmin.verifications.vehicles.show');

        Route::get('/verifications/drivers/{document}', [VerificationQueueController::class, 'showDriver'])
            ->name('sysadmin.verifications.drivers.show');

        Route::post('/verifications/{document}/review', [VerificationQueueController::class, 'review'])
            ->name('sysadmin.verifications.review');

             Route::get('/leads', [ContactLeadController::class,'index'])
      ->name('sysadmin.leads.index');

    Route::get('/leads/{lead}', [ContactLeadController::class,'show'])
      ->name('sysadmin.leads.show');

    Route::post('/leads/{lead}/status', [ContactLeadController::class,'updateStatus'])
      ->name('sysadmin.leads.status');

      
    });

/*
|--------------------------------------------------------------------------
| Rutas de DEBUG / pruebas de broadcast (puedes ponerles auth si quieres)
|--------------------------------------------------------------------------
*/

Route::get('/dispatch/test-driver/{tenantId}/{driverId}', function ($tenantId, $driverId) {
    \Log::info("🧪 TEST EVENT - Specific driver", [
        'tenant_id' => $tenantId,
        'driver_id' => $driverId
    ]);

    broadcast(new \App\Events\DriverEvent(
        tenantId: $tenantId,
        driverId: $driverId,
        type: 'TestEvent',
        payload: [
            'message'   => 'Test específico desde Dispatch',
            'tenant_id' => $tenantId,
            'driver_id' => $driverId,
            'timestamp' => now()->toDateTimeString()
        ]
    ));

    return response()->json([
        'sent'      => true,
        'tenant_id' => $tenantId,
        'driver_id' => $driverId,
        'channel'   => "tenant.{$tenantId}.driver.{$driverId}",
        'message'   => 'Evento enviado correctamente'
    ]);
});

Route::get('/debug/test-event', function () {
    event(new TestEvent('Hola desde Laravel @ '.now()));
    return ['ok' => true];
});


/*
|--------------------------------------------------------------------------
| DEBUG (recomendado: protégelo para sysadmin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth','sysadmin'])->group(function () {
    Route::get('/debug/test-event', function () {
        event(new TestEvent('Hola desde Laravel @ '.now()));
        return ['ok' => true];
    });
});





/*
|--------------------------------------------------------------------------
| Auth scaffolding (Breeze) => incluye POST /logout
|--------------------------------------------------------------------------
*/
require __DIR__ . '/auth.php';
