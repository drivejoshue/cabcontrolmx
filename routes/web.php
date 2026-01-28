<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Http\Controllers\Auth\EmailVerificationResendController;

use App\Models\Tenant;

// Public
use App\Http\Controllers\Public\TenantSignupController;

// Admin (tenant panel)
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DispatchController;
use App\Http\Controllers\Admin\OnboardingController;

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
use App\Http\Controllers\Admin\AdminRideController;

use App\Http\Controllers\Admin\TenantWalletController;
use App\Http\Controllers\Admin\TenantWalletTopupController;


use App\Http\Controllers\Admin\StaffUserController;
use App\Http\Controllers\Admin\TenantQrPointController;

use App\Http\Controllers\Admin\Billing\TaxiFeesController;
use App\Http\Controllers\Admin\Billing\TaxiChargesController;

use App\Http\Controllers\Admin\BI\DemandHeatmapController;

// API “panel” (web+auth) para JS (dentro de /admin)
use App\Http\Controllers\Api\SectorController as ApiSectorController;
use App\Http\Controllers\Api\TaxiStandController as ApiTaxiStandController;

// SysAdmin controllers
use App\Http\Controllers\SysAdmin\DashboardController as SysAdminDashboardController;
use App\Http\Controllers\SysAdmin\TenantController as SysTenantController;
use App\Http\Controllers\SysAdmin\TenantBillingController;
use App\Http\Controllers\SysAdmin\TenantInvoiceController;
use App\Http\Controllers\SysAdmin\VehicleDocumentController;
use App\Http\Controllers\SysAdmin\SysDriverDocumentController;
use App\Http\Controllers\SysAdmin\TenantCommissionReportController;
use App\Http\Controllers\SysAdmin\VerificationQueueController;
use App\Http\Controllers\SysAdmin\ContactLeadController;
use App\Http\Controllers\SysAdmin\CityController;
use App\Http\Controllers\SysAdmin\CityPlaceController;

use App\Http\Controllers\SysAdmin\TenantConsoleController;
use App\Http\Controllers\SysAdmin\TenantConsoleWalletController;
use App\Http\Controllers\SysAdmin\TenantConsoleInvoiceController;
use App\Http\Controllers\SysAdmin\TenantConsoleUserController;
use App\Http\Controllers\SysAdmin\TenantConsoleVehicleController;

use App\Http\Controllers\SysAdmin\SysRidesGenerationReportController;

use App\Http\Controllers\SysAdmin\TenantDocumentsReviewController;

use App\Http\Controllers\SysAdmin\TenantPartnerBillingController;
use App\Http\Controllers\SysAdmin\PartnerTransferTopupReviewController;
use App\Http\Controllers\SysAdmin\PartnerManualTopupController;
// Debug/events
use App\Events\TestEvent;

// Webhooks
use App\Http\Controllers\Webhooks\MercadoPagoWebhookController;

use App\Http\Controllers\Public\RideShareController;
use App\Http\Controllers\SysAdmin\BillingPlanController;


use App\Http\Middleware\VerifyCsrfToken;

//partners
use App\Http\Controllers\Partner\PartnerDashboardController;
use App\Http\Controllers\Partner\PartnerVehicleController;
use App\Http\Controllers\Partner\PartnerDriverController;
use App\Http\Controllers\Partner\PartnerContextController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Partner\PartnerWalletController;
use App\Http\Controllers\Partner\PartnerTopupController;
use App\Http\Controllers\Admin\PartnerTopupAdminController;
use App\Http\Controllers\Partner\VehicleDocsController as PartnerVehicleDocsController;
use App\Http\Controllers\Partner\DriverDocsController as PartnerDriverDocsController;
use App\Http\Controllers\Partner\PartnerRideController;
use App\Http\Controllers\Partner\Reports\PartnerRidesReportController;
use App\Http\Controllers\Partner\Reports\PartnerDriverQualityReportController;
use App\Http\Controllers\Partner\Reports\PartnerVehiclesReportController;




/*
|--------------------------------------------------------------------------
| Webhooks (sin CSRF)
|--------------------------------------------------------------------------
*/
Route::match(['GET', 'POST'], '/webhooks/mercadopago', [MercadoPagoWebhookController::class, 'handle'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhooks.mercadopago');

/*
|--------------------------------------------------------------------------
| Landing pública
|--------------------------------------------------------------------------
*/
Route::get('/', [TenantSignupController::class, 'landing'])->name('public.landing');

/*
|--------------------------------------------------------------------------
| Logout (tu override para siempre mandar a /login)
|--------------------------------------------------------------------------
*/
Route::post('/logout', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/login');
})
->middleware('auth')
->withoutMiddleware([VerifyCsrfToken::class])
->name('logout');

/*
|--------------------------------------------------------------------------
| Registro público (guest)
|--------------------------------------------------------------------------
*/


Route::get('/s/{token}', [RideShareController::class, 'show'])
    ->where('token', '[A-Za-z0-9\-_]+')
    ->name('public.ride-share.show');

Route::get('/ride_share/{token}', [RideShareController::class, 'show'])
    ->name('ride_share.show');

Route::get('/ride_share/{token}/state', [RideShareController::class, 'state'])
    ->name('ride_share.state');


    

Route::middleware('guest')->group(function () {
    Route::get('/signup', [TenantSignupController::class, 'show'])->name('public.signup');

    Route::post('/signup', [TenantSignupController::class, 'store'])
        ->middleware('throttle:signup')
        ->name('public.signup.store');

    Route::get('/signup/success', [TenantSignupController::class, 'success'])->name('public.signup.success');
});

Route::view('/pending', 'public.pending')->name('public.pending-tenant');



/*
|--------------------------------------------------------------------------
| Email verification (Breeze-like)
|--------------------------------------------------------------------------
*/
Route::get('/email/verify', function () {
    return view('public.verify-email');
})->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {

    $request->fulfill();

    $u = $request->user();
    if ($u && (int) $u->tenant_id > 0) {
        Tenant::where('id', $u->tenant_id)
            ->where('public_active', 0)
            ->update(['public_active' => 1]);
    }

    $tenant = $u && $u->tenant_id ? Tenant::find($u->tenant_id) : null;
    if ($tenant && empty($tenant->onboarding_done_at)) {
        return redirect()->route('admin.onboarding')
            ->with('status', 'Correo verificado. Completa los primeros pasos para activar tu central.');
    }

    return redirect()->route('admin.dashboard');
})->middleware(['auth', 'signed', 'throttle:6,1'])->name('verification.verify');

Route::post('/email/verification-notification', EmailVerificationResendController::class)
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.send');

/*
|--------------------------------------------------------------------------
| Redirect inteligente según sesión
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
| Dispatch (usuario is_dispatcher, fuera de /admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'dispatch', 'tenant.onboarded'])->group(function () {
    Route::get('/dispatch', [DispatchController::class, 'index'])->name('dispatch');
});

/*
|--------------------------------------------------------------------------
| Reportes ratings (fuera de /admin, como lo tenías)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/ratings/reports', [RatingReportController::class, 'index'])->name('ratings.index');
    Route::get('/ratings/driver/{driverId}', [RatingReportController::class, 'showDriver'])->name('ratings.show');
});

/*
|--------------------------------------------------------------------------
| PANEL TENANT (/admin/…)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'admin', 'tenant.ready'])
    ->group(function () {

        // Onboarding / Mi central (sin tenant.onboarded)
        Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding');
        Route::get('/onboarding/cities', [OnboardingController::class, 'cities'])->name('onboarding.cities');
        Route::post('/onboarding/location', [OnboardingController::class, 'saveLocation'])->name('onboarding.location');
        Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');

        Route::get('/mi-central', [TenantProfileController::class, 'edit'])->name('tenant.edit');
        Route::post('/mi-central', [TenantProfileController::class, 'update'])->name('tenant.update');

        // SOLO SI tenant.onboarded
        Route::middleware(['tenant.onboarded'])->group(function () {

            // Billing + Wallet (accesible aunque no haya saldo)
            Route::get('/billing', [\App\Http\Controllers\Admin\TenantBillingController::class, 'plan'])->name('billing.plan');
            Route::post('/billing/accept-terms', [\App\Http\Controllers\Admin\TenantBillingController::class, 'acceptTerms'])->name('billing.accept_terms');

            Route::get('/billing/invoices/{invoice}', [\App\Http\Controllers\Admin\TenantBillingController::class, 'invoiceShow'])->name('billing.invoices.show');
            Route::get('/billing/invoices/{invoice}/csv', [\App\Http\Controllers\Admin\TenantBillingController::class, 'invoiceCsv'])->name('billing.invoices.csv');
            Route::get('/billing/invoices/{invoice}/pdf', [\App\Http\Controllers\Admin\TenantBillingController::class, 'invoicePdf'])->name('billing.invoice_pdf');

            Route::post('/billing/invoices/{invoice}/pay-wallet', [\App\Http\Controllers\Admin\TenantBillingController::class, 'payWithWallet'])
                ->name('billing.invoices.pay_wallet');

            Route::get('/wallet', [TenantWalletController::class, 'index'])->name('wallet.index');
            Route::get('/wallet/movements', [TenantWalletController::class, 'movements'])->name('wallet.movements');

            Route::post('/wallet/topup-manual', [TenantWalletController::class, 'topupManual'])->name('wallet.topup.manual');

            Route::get('/wallet/topup', [TenantWalletTopupController::class, 'create'])->name('wallet.topup.create');
            Route::post('/wallet/topup', [TenantWalletTopupController::class, 'store'])->name('wallet.topup.store');

           Route::post('/wallet/transfer/notice', [TenantWalletTopupController::class, 'storeTransferNotice'])
  ->name('wallet.transfer.notice.store');

            Route::get('/wallet/topup/{topup}/checkout', [TenantWalletTopupController::class, 'checkout'])->name('wallet.topup.checkout');
            Route::get('/wallet/topup/{topup}/status', [TenantWalletTopupController::class, 'status'])->name('wallet.topup.status');


             Route::get('tenant', [\App\Http\Controllers\Admin\TenantProfileController::class, 'edit'])
               ->name('tenant.edit');
                Route::post('tenant', [\App\Http\Controllers\Admin\TenantProfileController::class, 'update'])
                    ->name('tenant.update');

                Route::post('tenant/documents', [\App\Http\Controllers\Admin\TenantDocumentsController::class, 'store'])
                    ->name('tenant.documents.store');

                Route::get('tenant/documents/{doc}', [\App\Http\Controllers\Admin\TenantDocumentsController::class, 'download'])
                    ->name('tenant.documents.download');


             Route::resource('partners', PartnerController::class);


        Route::get('/partner-topups', [PartnerTopupAdminController::class, 'index'])->name('partner_topups.index');
        Route::get('/partner-topups/{id}', [PartnerTopupAdminController::class, 'show'])->name('partner_topups.show');
        Route::post('/partner-topups/{id}/approve', [PartnerTopupAdminController::class, 'approve'])->name('partner_topups.approve');
        Route::post('/partner-topups/{id}/reject', [PartnerTopupAdminController::class, 'reject'])->name('partner_topups.reject');




                    // -----------------------------------------------------
        // Partner Support Inbox (Tenant Admin)
        // Tablas: partner_threads / partner_thread_messages
        // -----------------------------------------------------
        Route::prefix('partner-support')->name('partner_support.')->group(function () {

            // Inbox / listado
            Route::get('/', [\App\Http\Controllers\Admin\Support\TenantPartnerSupportController::class, 'index'])
                ->name('index');

            // Crear NO aplica en tenant (solo partner). Tenant solo ve/contesta.
            // Ver thread
            Route::get('/{thread}', [\App\Http\Controllers\Admin\Support\TenantPartnerSupportController::class, 'show'])
                ->whereNumber('thread')->name('show');

            // Responder thread
            Route::post('/{thread}/reply', [\App\Http\Controllers\Admin\Support\TenantPartnerSupportController::class, 'reply'])
                ->whereNumber('thread')->name('reply');

            // Cambiar estado (open|in_review|closed)
            Route::post('/{thread}/status', [\App\Http\Controllers\Admin\Support\TenantPartnerSupportController::class, 'setStatus'])
                ->whereNumber('thread')->name('status');

            // (Opcional) Marcar “leído por tenant” (usa last_tenant_read_at)
            Route::post('/{thread}/read', [\App\Http\Controllers\Admin\Support\TenantPartnerSupportController::class, 'markRead'])
                ->whereNumber('thread')->name('read');
        });







            // Operación (requiere saldo suficiente)
            Route::middleware(['tenant.balance'])->group(function () {

                Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
                Route::get('/dispatch', [DispatchController::class, 'index'])->name('dispatch');

                Route::get('/profile', [\App\Http\Controllers\Admin\ProfileController::class, 'edit'])->name('profile.edit');
                Route::post('/profile', [\App\Http\Controllers\Admin\ProfileController::class, 'update'])->name('profile.update');

                // Rides
                Route::get('/rides', [AdminRideController::class, 'index'])->name('rides.index');
                Route::get('/rides/{ride}', [AdminRideController::class, 'show'])->name('rides.show');

                // Drivers
                Route::get('/drivers', [DriverController::class, 'index'])->name('drivers.index');
                Route::get('/drivers/create', [DriverController::class, 'create'])->name('drivers.create');
                Route::post('/drivers', [DriverController::class, 'store'])->name('drivers.store');
                Route::get('/drivers/{id}', [DriverController::class, 'show'])->name('drivers.show');
                Route::get('/drivers/{id}/edit', [DriverController::class, 'edit'])->name('drivers.edit');
                Route::put('/drivers/{id}', [DriverController::class, 'update'])->name('drivers.update');
                Route::delete('/drivers/{id}', [DriverController::class, 'destroy'])->name('drivers.destroy');
                Route::post('/drivers/{id}/assign-vehicle', [DriverController::class, 'assignVehicle'])->name('drivers.assignVehicle');
                Route::put('/assignments/{id}/close', [DriverController::class, 'closeAssignment'])->name('assignments.close');
                Route::post('/drivers/{id}/reset-password', [DriverController::class, 'resetPassword'])->name('drivers.resetPassword');



                Route::get('/reportes/ingresos-taxis', [\App\Http\Controllers\Admin\Reports\TaxiIncomeReportController::class, 'index'])
                ->name('reports.incomes.taxi_income');

                 Route::get('/reportes/ingresos-taxis.csv', [\App\Http\Controllers\Admin\Reports\TaxiIncomeReportController::class, 'exportCsv'])
                ->name('reports.incomes.taxi_income.csv');




                // Vehicles
                Route::get('/vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
                Route::get('/vehicles/create', [VehicleController::class, 'create'])->name('vehicles.create');
                Route::post('/vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
                Route::get('/vehicles/{id}', [VehicleController::class, 'show'])->name('vehicles.show');
                Route::get('/vehicles/{id}/edit', [VehicleController::class, 'edit'])->name('vehicles.edit');
                Route::put('/vehicles/{id}', [VehicleController::class, 'update'])->name('vehicles.update');
                Route::delete('/vehicles/{id}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');
                Route::post('/vehicles/{id}/assign-driver', [VehicleController::class, 'assignDriver'])->name('vehicles.assignDriver');

                // Sectores
                Route::get('/sectores', [AdminSectorController::class, 'index'])->name('sectores.index');
                Route::get('/sectores/create', [AdminSectorController::class, 'create'])->name('sectores.create');
                Route::post('/sectores', [AdminSectorController::class, 'store'])->name('sectores.store');
                Route::get('/sectores/{id}', [AdminSectorController::class, 'show'])->name('sectores.show');
                Route::get('/sectores/{id}/edit', [AdminSectorController::class, 'edit'])->name('sectores.edit');
                Route::put('/sectores/{id}', [AdminSectorController::class, 'update'])->name('sectores.update');
                Route::delete('/sectores/{id}', [AdminSectorController::class, 'destroy'])->name('sectores.destroy');
                Route::get('/sectores.geojson', [AdminSectorController::class, 'geojson'])->name('sectores.geojson');

                // TaxiStands
                Route::get('/taxistands', [AdminTaxiStandController::class, 'index'])->name('taxistands.index');
                Route::get('/taxistands/create', [AdminTaxiStandController::class, 'create'])->name('taxistands.create');
                Route::post('/taxistands', [AdminTaxiStandController::class, 'store'])->name('taxistands.store');
                Route::get('/taxistands/{id}', [AdminTaxiStandController::class, 'show'])->name('taxistands.show');
                Route::get('/taxistands/{id}/edit', [AdminTaxiStandController::class, 'edit'])->name('taxistands.edit');
                Route::put('/taxistands/{id}', [AdminTaxiStandController::class, 'update'])->name('taxistands.update');
                Route::delete('/taxistands/{id}', [AdminTaxiStandController::class, 'destroy'])->name('taxistands.destroy');
                Route::get('/taxistands/{id}/qr/refresh', [AdminTaxiStandController::class, 'refreshQr'])->name('taxistands.qr.refresh');
                Route::get('/taxistands/{id}/qr.png', [AdminTaxiStandController::class, 'qrPng'])->name('taxistands.qr.png');

                // QR Points (tenant)
                Route::get('/qr-points', [TenantQrPointController::class, 'index'])->name('qr-points.index');
                Route::get('/qr-points/create', [TenantQrPointController::class, 'create'])->name('qr-points.create');
                Route::post('/qr-points', [TenantQrPointController::class, 'store'])->name('qr-points.store');
                Route::get('/qr-points/{qrPoint}', [TenantQrPointController::class, 'show'])->name('qr-points.show');
                Route::get('/qr-points/{qrPoint}/edit', [TenantQrPointController::class, 'edit'])->name('qr-points.edit');
                Route::put('/qr-points/{qrPoint}', [TenantQrPointController::class, 'update'])->name('qr-points.update');
                Route::delete('/qr-points/{qrPoint}', [TenantQrPointController::class, 'destroy'])->name('qr-points.destroy');

                // Cobros / cuotas taxis (ya existentes)
                Route::get('/cobros/cuotas-taxi', [TaxiFeesController::class, 'index'])->name('taxi_fees');
                Route::post('/cobros/cuotas-taxi/{id}', [TaxiFeesController::class, 'update'])->name('taxi_fees.update');

                Route::get('/cobros/taxi', [TaxiChargesController::class, 'index'])->name('taxi_charges');
                Route::post('/cobros/taxi/generar', [TaxiChargesController::class, 'generate'])->name('taxi_charges.generate');
                Route::post('/cobros/taxi/{charge}/pagar', [TaxiChargesController::class, 'markPaid'])->name('taxi_charges.pay');
                Route::post('/cobros/taxi/{charge}/cancelar', [TaxiChargesController::class, 'cancel'])->name('taxi_charges.cancel');

                Route::post('/cobros/taxi/{charge}/recibo', [TaxiChargesController::class, 'issueReceipt'])->name('taxi_charges.receipt');
                Route::get('/cobros/taxi/recibos/{receipt}', [TaxiChargesController::class, 'receiptShow'])->name('taxi_receipts.show');


                // =====================
                // FALTANTES (agregar)
                // =====================

                // Export cuotas (CSV compatible con Excel)
                Route::get('/cobros/cuotas-taxi/exportar', [TaxiFeesController::class, 'export'])
                  ->name('taxi_fees.export');

                // Export cobros del periodo (CSV compatible con Excel; respeta filtros)
                Route::get('/cobros/taxi/exportar', [TaxiChargesController::class, 'export'])
                  ->name('taxi_charges.export');

                // Vaciar historial (cobros + recibos) con doble confirmación
                Route::post('/cobros/taxi/vaciar', [TaxiChargesController::class, 'purge'])
                  ->name('taxi_charges.purge');


                

                // Usuarios staff
                Route::get('/usuarios', [StaffUserController::class, 'index'])->name('users.index');
                Route::get('/usuarios/create', [StaffUserController::class, 'create'])->name('users.create');
                Route::post('/usuarios', [StaffUserController::class, 'store'])->name('users.store');
                Route::get('/usuarios/{user}/edit', [StaffUserController::class, 'edit'])->name('users.edit');
                Route::put('/usuarios/{user}', [StaffUserController::class, 'update'])->name('users.update');
                Route::post('/usuarios/{user}/set-password', [StaffUserController::class, 'setPassword'])->name('users.set_password');
                Route::post('/usuarios/{user}/send-reset', [StaffUserController::class, 'sendResetLink'])->name('users.send_reset');

                Route::post('/usuarios/{user}/deactivate', [StaffUserController::class, 'deactivate'])->name('users.deactivate');
                Route::post('/usuarios/{user}/reactivate', [StaffUserController::class, 'reactivate'])->name('users.reactivate');

                // Tenant settings
                Route::get('/tenant-settings', [TenantSettingsController::class, 'edit'])->name('tenant_settings.edit');
                Route::put('/tenant-settings', [TenantSettingsController::class, 'update'])->name('tenant_settings.update');



             
                Route::get('/dispatch-settings', [DispatchSettingsController::class, 'edit'])->name('dispatch_settings.edit');
                Route::put('/dispatch-settings', [DispatchSettingsController::class, 'update'])->name('dispatch_settings.update');
           


                Route::get('/fare-policies', [TenantFarePolicyController::class, 'index'])->name('fare_policies.index');
                Route::get('/fare-policies/edit', [TenantFarePolicyController::class, 'edit'])->name('fare_policies.edit');
                Route::put('/fare-policies/update', [TenantFarePolicyController::class, 'update'])->name('fare_policies.update');

                // Docs
                Route::get('/vehicles/{id}/documents', [VehicleDocsController::class, 'index'])->name('vehicles.documents.index');
                Route::post('/vehicles/{id}/documents', [VehicleDocsController::class, 'store'])->name('vehicles.documents.store');
                Route::get('/vehicle-documents/{doc}/download', [VehicleDocsController::class, 'download'])->name('vehicles.documents.download');
                Route::post('/vehicle-documents/{doc}/delete', [VehicleDocsController::class, 'destroy'])->name('vehicles.documents.delete');

                Route::get('/drivers/{id}/documents', [DriverDocsController::class, 'index'])->name('drivers.documents.index');
                Route::post('/drivers/{id}/documents', [DriverDocsController::class, 'store'])->name('drivers.documents.store');
                Route::get('/driver-documents/{doc}/download', [DriverDocsController::class, 'download'])->name('drivers.documents.download');
                Route::post('/driver-documents/{doc}/delete', [DriverDocsController::class, 'destroy'])->name('drivers.documents.delete');

                // Reportes: clientes
                Route::get('/reportes/clientes', [ClientsReportController::class, 'index'])->name('reports.clients');
                Route::get('/reportes/clientes/{ref}', [ClientsReportController::class, 'show'])->name('reports.clients.show');
                Route::get('/reportes/clientes.csv', [ClientsReportController::class, 'exportCsv'])->name('reports.clients.csv');

                // Reportes: viajes
                Route::get('/reportes/viajes', [RidesReportController::class, 'index'])->name('reports.rides');
                Route::get('/reportes/viajes/{ride}', [RidesReportController::class, 'show'])->name('reports.rides.show');
                Route::get('/reportes/viajes.csv', [RidesReportController::class, 'exportCsv'])->name('reports.rides.csv');

                // Ride issues (TENANT)
                Route::get('/ride-issues', [\App\Http\Controllers\Admin\RideIssueController::class, 'index'])->name('ride_issues.index');
                Route::get('/ride-issues/{issue}', [\App\Http\Controllers\Admin\RideIssueController::class, 'show'])->name('ride_issues.show');
                Route::post('/ride-issues/{issue}/status', [\App\Http\Controllers\Admin\RideIssueController::class, 'updateStatus'])->name('ride_issues.update_status');
                Route::post('/ride-issues/{issue}/notes', [\App\Http\Controllers\Admin\RideIssueController::class, 'storeNote'])->name('ride_issues.notes.store');

                // Reportes: conductores (alias) => quedan como admin.reports.drivers / admin.reports.drivers.activity
                Route::prefix('reportes')->as('reports.')->group(function () {
                    Route::get('conductores', [DriverActivityReportController::class, 'index'])->name('drivers');
                    Route::get('conductores/actividad', [DriverActivityReportController::class, 'index'])->name('drivers.activity');
                });

                // BI
                Route::get('/bi/mapa-demanda', [DemandHeatmapController::class, 'index'])->name('bi.demand');
                Route::get('/bi/api/heat-origins', [DemandHeatmapController::class, 'heatOrigins'])->name('bi.heat.origins');

                // API panel (para JS dentro de /admin) => si quieres nombres, agrégalos; si no, déjalas anónimas
                Route::prefix('api')->group(function () {
                    Route::get('/sectores', [ApiSectorController::class, 'index']);
                    Route::get('/taxistands', [ApiTaxiStandController::class, 'index']);






                });
            });
        });
    });


/*
|--------------------------------------------------------------------------
| PANEL SYSADMIN (/sysadmin/… ) – separado del tenant
|--------------------------------------------------------------------------
*/        // routes/web.php (dentro del mismo group)


Route::prefix('sysadmin')->middleware(['auth','sysadmin'])->group(function () {

    // Dashboard
    Route::get('/', [SysAdminDashboardController::class,'index'])
        ->name('sysadmin.dashboard');

        Route::resource('provider-profiles', \App\Http\Controllers\SysAdmin\ProviderProfileController::class)
    ->except(['show'])
    ->names('sysadmin.provider-profiles');

    // Tenants (CRUD básico)
    Route::get('/tenants', [SysTenantController::class, 'index'])->name('sysadmin.tenants.index');
    Route::get('/tenants/create', [SysTenantController::class, 'create'])->name('sysadmin.tenants.create');
    Route::post('/tenants', [SysTenantController::class, 'store'])->name('sysadmin.tenants.store');
    Route::get('/tenants/{tenant}/edit', [SysTenantController::class, 'edit'])->name('sysadmin.tenants.edit');
   Route::put('/tenants/{tenant}', [SysTenantController::class, 'update'])->name('sysadmin.tenants.update');


    // Billing del tenant (show/update) + generar factura mensual de prueba
    Route::get('/tenants/{tenant}/billing', [TenantBillingController::class,'show'])
        ->name('sysadmin.tenants.billing.show');
    Route::post('/tenants/{tenant}/billing', [TenantBillingController::class,'update'])
        ->name('sysadmin.tenants.billing.update');
    Route::post('/tenants/{tenant}/billing/generate-monthly', [TenantBillingController::class,'generateMonthly'])
        ->name('sysadmin.tenants.billing.generate-monthly');

        Route::post('/tenants/{tenant}/billing/run-monthly', [\App\Http\Controllers\SysAdmin\TenantBillingController::class, 'runMonthly'])
    ->name('sysadmin.tenants.billing.runMonthly');

    // Facturas (listar/ver). NO se define export_csv para evitar "route not defined".
    Route::get('/invoices', [TenantInvoiceController::class, 'index'])->name('sysadmin.invoices.index');
    Route::get('/invoices/{invoice}', [TenantInvoiceController::class, 'show'])->name('sysadmin.invoices.show');

    Route::get('/invoices/export/csv', [TenantInvoiceController::class, 'exportCsv'])
    ->name('sysadmin.invoices.export_csv');

     Route::get('/invoices/export/pdf', [TenantInvoiceController::class, 'downloadPdf'])
    ->name('sysadmin.invoices.pdf');



    // Documentos de VEHÍCULO
    Route::get('/tenants/{tenant}/vehicles/{vehicle}/documents', [VehicleDocumentController::class, 'index'])
        ->name('sysadmin.vehicles.documents.index');
    Route::post('/tenants/{tenant}/vehicles/{vehicle}/documents', [VehicleDocumentController::class, 'store'])
        ->name('sysadmin.vehicles.documents.store');
    Route::post('/vehicle-documents/{document}/review', [VehicleDocumentController::class, 'review'])
        ->name('sysadmin.vehicle-documents.review');
    Route::get('/vehicle-documents/{document}/download', [VehicleDocumentController::class, 'download'])
        ->name('sysadmin.vehicle-documents.download');
    Route::get('/vehicle-documents/{document}/view', [VehicleDocumentController::class, 'view'])
        ->name('sysadmin.vehicle-documents.view');

    // Documentos de DRIVER
    Route::get('/tenants/{tenant}/drivers/{driver}/documents', [SysDriverDocumentController::class, 'index'])
        ->name('sysadmin.drivers.documents.index');
    Route::post('/tenants/{tenant}/drivers/{driver}/documents', [SysDriverDocumentController::class, 'store'])
        ->name('sysadmin.drivers.documents.store');
    Route::post('/driver-documents/{document}/review', [SysDriverDocumentController::class, 'review'])
        ->name('sysadmin.driver-documents.review');
    Route::get('/driver-documents/{document}/download', [SysDriverDocumentController::class, 'download'])
        ->name('sysadmin.driver-documents.download');
    Route::get('/driver-documents/{document}/view', [SysDriverDocumentController::class, 'view'])
        ->name('sysadmin.driver-documents.view');



        //docs  tenannt   

     Route::get('/tenants/{tenant}/documents', [TenantDocumentsReviewController::class, 'index'])
      ->name('sysadmin.tenants.documents.index');

    Route::get('/tenant-documents/{doc}/download', [TenantDocumentsReviewController::class, 'download'])
      ->name('sysadmin.tenant-documents.download');

    Route::post('/tenant-documents/{doc}/approve', [TenantDocumentsReviewController::class, 'approve'])
      ->name('sysadmin.tenant-documents.approve');

    Route::post('/tenant-documents/{doc}/reject', [TenantDocumentsReviewController::class, 'reject'])
      ->name('sysadmin.tenant-documents.reject');

    Route::post('/tenant-documents/{doc}/reopen', [TenantDocumentsReviewController::class, 'reopen'])
      ->name('sysadmin.tenant-documents.reopen');




    // Cola de verificación + acciones específicas
    Route::get('/verifications', [VerificationQueueController::class, 'index'])
        ->name('sysadmin.verifications.index');
    Route::get('/verifications/vehicles/{vehicle}', [VerificationQueueController::class, 'showVehicle'])
        ->name('sysadmin.verifications.vehicles.show');
    Route::get('/verifications/drivers/{driver}', [VerificationQueueController::class, 'showDriver'])
        ->name('sysadmin.verifications.drivers.show');
    Route::post('/verifications/vehicle-docs/{document}/review', [VerificationQueueController::class, 'reviewVehicleDoc'])
        ->name('sysadmin.verifications.vehicle_docs.review');
    Route::post('/verifications/driver-docs/{document}/review', [VerificationQueueController::class, 'reviewDriverDoc'])
        ->name('sysadmin.verifications.driver_docs.review');

    // Leads
    Route::get('/leads', [ContactLeadController::class,'index'])->name('sysadmin.leads.index');
    Route::get('/leads/{lead}', [ContactLeadController::class,'show'])->name('sysadmin.leads.show');
    Route::post('/leads/{lead}/status', [ContactLeadController::class,'updateStatus'])->name('sysadmin.leads.status');

    // Reporte (placeholder habilitado si ya existe controller)
    Route::get('/tenants/{tenant}/reports/commissions', [TenantCommissionReportController::class, 'index'])
        ->name('sysadmin.tenants.reports.commissions');

    // =====================================
    // Cities (SysAdmin)
    // =====================================
    Route::get('/cities', [CityController::class, 'index'])->name('sysadmin.cities.index');
    Route::get('/cities/create', [CityController::class, 'create'])->name('sysadmin.cities.create');
    Route::post('/cities', [CityController::class, 'store'])->name('sysadmin.cities.store');
    Route::get('/cities/{city}', [CityController::class, 'show'])->name('sysadmin.cities.show');
    Route::get('/cities/{city}/edit', [CityController::class, 'edit'])->name('sysadmin.cities.edit');
    Route::put('/cities/{city}', [CityController::class, 'update'])->name('sysadmin.cities.update');
    Route::delete('/cities/{city}', [CityController::class, 'destroy'])->name('sysadmin.cities.destroy');


    // =====================================
    // City Places (SysAdmin)
    // =====================================
    Route::get('/city-places', [CityPlaceController::class, 'index'])->name('sysadmin.city-places.index');
    Route::get('/city-places/create', [CityPlaceController::class, 'create'])->name('sysadmin.city-places.create');
    Route::post('/city-places', [CityPlaceController::class, 'store'])->name('sysadmin.city-places.store');
    Route::get('/city-places/{city_place}', [CityPlaceController::class, 'show'])->name('sysadmin.city-places.show');
    Route::get('/city-places/{city_place}/edit', [CityPlaceController::class, 'edit'])->name('sysadmin.city-places.edit');
    Route::put('/city-places/{city_place}', [CityPlaceController::class, 'update'])->name('sysadmin.city-places.update');
    Route::delete('/city-places/{city_place}', [CityPlaceController::class, 'destroy'])->name('sysadmin.city-places.destroy');


      Route::get('ride-issues', [\App\Http\Controllers\SysAdmin\RideIssueSysAdminController::class, 'index'])->name('ride_issues.index');
        Route::get('ride-issues/{issue}', [\App\Http\Controllers\SysAdmin\RideIssueSysAdminController::class, 'show'])->name('ride_issues.show');
        Route::patch('ride-issues/{issue}', [\App\Http\Controllers\SysAdmin\RideIssueSysAdminController::class, 'update'])->name('ride_issues.update');
        Route::post('ride-issues/{issue}/notes', [\App\Http\Controllers\SysAdmin\RideIssueSysAdminController::class, 'storeNote'])->name('ride_issues.notes.store');
    
    

// =====================================================
// Tenant Console (misma pantalla de billing, acciones extra)
// =====================================================


        
        Route::get('/generacion', [SysRidesGenerationReportController::class, 'index'])
            ->name('sysadmin.rides.generation.index');

        Route::get('/generacion.csv', [SysRidesGenerationReportController::class, 'exportCsv'])
            ->name('sysadmin.rides.generation.csv');
   


Route::post('/tenants/{tenant}/billing/actions/recheck', [TenantConsoleController::class, 'recheck'])
    ->name('sysadmin.tenants.billing.actions.recheck');

Route::post('/tenants/{tenant}/billing/actions/pause', [TenantConsoleController::class, 'pause'])
    ->name('sysadmin.tenants.billing.actions.pause');

Route::post('/tenants/{tenant}/billing/actions/activate', [TenantConsoleController::class, 'activate'])
    ->name('sysadmin.tenants.billing.actions.activate');

Route::post('/tenants/{tenant}/billing/actions/cancel', [TenantConsoleController::class, 'cancel'])
    ->name('sysadmin.tenants.billing.actions.cancel');

// Cerrar turnos abiertos (emergencia)
Route::post('/tenants/{tenant}/billing/actions/close-open-shifts', [TenantConsoleController::class, 'closeOpenShifts'])
    ->name('sysadmin.tenants.billing.actions.close_open_shifts');


// =====================================================
// Wallet / Transferencias (crédito manual y ajustes)
// =====================================================
Route::post('/tenants/{tenant}/billing/wallet/credit', [TenantConsoleWalletController::class, 'credit'])
    ->name('sysadmin.tenants.billing.wallet.credit');

Route::post('/tenants/{tenant}/billing/wallet/debit', [TenantConsoleWalletController::class, 'debit'])
    ->name('sysadmin.tenants.billing.wallet.debit');

Route::post('/tenants/{tenant}/billing/wallet/adjust', [TenantConsoleWalletController::class, 'adjust'])
    ->name('sysadmin.tenants.billing.wallet.adjust');


// =====================================================
// Facturas (acciones manuales)
// =====================================================
// Importante: en controller valida que $invoice->tenant_id === $tenant->id
Route::post('/tenants/{tenant}/billing/invoices/{invoice}/mark-paid', [TenantConsoleInvoiceController::class, 'markPaid'])
    ->name('sysadmin.tenants.billing.invoices.mark_paid');

Route::post('/tenants/{tenant}/billing/invoices/{invoice}/mark-pending', [TenantConsoleInvoiceController::class, 'markPending'])
    ->name('sysadmin.tenants.billing.invoices.mark_pending');

Route::post('/tenants/{tenant}/billing/invoices/{invoice}/void', [TenantConsoleInvoiceController::class, 'void'])
    ->name('sysadmin.tenants.billing.invoices.void');


// =====================================================
// Usuarios del tenant (filtrados por tenant_id)
// =====================================================
Route::get('/tenants/{tenant}/billing/users', [TenantConsoleUserController::class, 'index'])
    ->name('sysadmin.tenants.billing.users.index');

Route::get('/tenants/{tenant}/billing/users/{user}', [TenantConsoleUserController::class, 'show'])
    ->name('sysadmin.tenants.billing.users.show');

Route::post('/tenants/{tenant}/billing/users/{user}/verify-email', [TenantConsoleUserController::class, 'verifyEmail'])
    ->name('sysadmin.tenants.billing.users.verify_email');

Route::post('/tenants/{tenant}/billing/users/{user}/unverify-email', [TenantConsoleUserController::class, 'unverifyEmail'])
    ->name('sysadmin.tenants.billing.users.unverify_email');

Route::post('/tenants/{tenant}/billing/users/{user}/set-password', [TenantConsoleUserController::class, 'setPassword'])
    ->name('sysadmin.tenants.billing.users.set_password');

Route::post('/tenants/{tenant}/billing/users/{user}/send-reset-link', [TenantConsoleUserController::class, 'sendResetLink'])
    ->name('sysadmin.tenants.billing.users.send_reset_link');

Route::post('/tenants/{tenant}/billing/users/{user}/revoke-tokens', [TenantConsoleUserController::class, 'revokeTokens'])
    ->name('sysadmin.tenants.billing.users.revoke_tokens');


// =====================================================
// Billing Plans (SysAdmin)
// =====================================================

Route::get('/billing-plans', [BillingPlanController::class, 'index'])
    ->name('sysadmin.billing-plans.index');

Route::get('/billing-plans/create', [BillingPlanController::class, 'create'])
    ->name('sysadmin.billing-plans.create');

Route::post('/billing-plans', [BillingPlanController::class, 'store'])
    ->name('sysadmin.billing-plans.store');

Route::get('/billing-plans/{billing_plan}/edit', [BillingPlanController::class, 'edit'])
    ->name('sysadmin.billing-plans.edit');

Route::put('/billing-plans/{billing_plan}', [BillingPlanController::class, 'update'])
    ->name('sysadmin.billing-plans.update');

Route::delete('/billing-plans/{billing_plan}', [BillingPlanController::class, 'destroy'])
    ->name('sysadmin.billing-plans.destroy');



//tenant  topup
Route::get('/topups/transfer', [\App\Http\Controllers\SysAdmin\TransferTopupReviewController::class, 'index'])
  ->name('sysadmin.topups.transfer.index');

Route::get('/topups/transfer/{topup}', [\App\Http\Controllers\SysAdmin\TransferTopupReviewController::class, 'show'])
  ->name('sysadmin.topups.transfer.show');

Route::post('/topups/transfer/{topup}/approve', [\App\Http\Controllers\SysAdmin\TransferTopupReviewController::class, 'approve'])
  ->name('sysadmin.topups.transfer.approve');

Route::post('/topups/transfer/{topup}/reject', [\App\Http\Controllers\SysAdmin\TransferTopupReviewController::class, 'reject'])
  ->name('sysadmin.topups.transfer.reject');

//partner topup  

  Route::get('/topups/partner-transfer', [\App\Http\Controllers\SysAdmin\PartnerTransferTopupReviewController::class, 'index'])
  ->name('sysadmin.topups.partner_transfer.index');

Route::get('/topups/partner-transfer/{topup}', [\App\Http\Controllers\SysAdmin\PartnerTransferTopupReviewController::class, 'show'])
  ->name('sysadmin.topups.partner_transfer.show');

Route::post('/topups/partner-transfer/{topup}/approve', [\App\Http\Controllers\SysAdmin\PartnerTransferTopupReviewController::class, 'approve'])
  ->name('sysadmin.topups.partner_transfer.approve');

Route::post('/topups/partner-transfer/{topup}/reject', [\App\Http\Controllers\SysAdmin\PartnerTransferTopupReviewController::class, 'reject'])
  ->name('sysadmin.topups.partner_transfer.reject');

Route::prefix('tenants/{tenant}')->group(function () {

   Route::get('partners/{partner}/billing', [TenantPartnerBillingController::class, 'show'])
          ->name('sysadmin.partners.billing.show');
 });

Route::post('partners/{partner}/topups/manual', [PartnerManualTopupController::class, 'store'])
    ->name('partners.topups.manual.store');

//partner topup manual 

 Route::prefix('topups/manual')->name('topups.manual.')->group(function () {
            Route::get('create', [PartnerManualTopupController::class, 'create'])->name('create');
            Route::post('/',       [PartnerManualTopupController::class, 'store'])->name('store');
            Route::get('{topup}',  [PartnerManualTopupController::class, 'show'])->name('show');
        });
 

// =====================================================
// Shifts (control manual total)
// =====================================================
// Lista rápida (abiertos + últimos cerrados)


Route::post('/tenants/{tenant}/billing/vehicles/{vehicle}/toggle-active', [TenantConsoleVehicleController::class, 'toggleActive'])
    ->name('sysadmin.tenants.billing.vehicles.toggle_active');
});





// =====================================================
// Partner Portal
// =====================================================
// Requiere sesión web, usuario autenticado/verificado y contexto de partner activo.
Route::middleware(['web', 'auth', 'verified', 'partner.ctx', 'partner.billing.gate'])

  ->prefix('partner')
  ->name('partner.')
  ->group(function () {

    // -------------------------------------------------
    // Dashboard / Contexto
    // -------------------------------------------------
    Route::get('/', [PartnerDashboardController::class, 'index'])
      ->name('dashboard');

    // Cambiar partner activo (si un user pertenece a varios partners)
    Route::post('/switch', [PartnerContextController::class, 'switch'])
      ->name('switch');

    // -------------------------------------------------
    // Flota: Vehículos
    // -------------------------------------------------
    Route::get('/vehicles', [PartnerVehicleController::class, 'index'])->name('vehicles.index');
    Route::get('/vehicles/create', [PartnerVehicleController::class, 'create'])->name('vehicles.create');
    Route::post('/vehicles', [PartnerVehicleController::class, 'store'])->name('vehicles.store');
    Route::get('/vehicles/{id}', [PartnerVehicleController::class, 'show'])->name('vehicles.show');
    Route::get('/vehicles/{id}/edit', [PartnerVehicleController::class, 'edit'])->name('vehicles.edit');
    Route::put('/vehicles/{id}', [PartnerVehicleController::class, 'update'])->name('vehicles.update');

    // Activación / Suspensión (ya tienes botón activar/desactivar)
    Route::post('/vehicles/{id}/suspend', [PartnerVehicleController::class, 'suspend'])->name('vehicles.suspend');
    Route::post('/vehicles/{id}/activate', [PartnerVehicleController::class, 'activate'])->name('vehicles.activate');

    // Documentos de vehículo
    Route::get('/vehicles/{id}/documents', [PartnerVehicleDocsController::class, 'index'])->name('vehicles.documents.index');
    Route::post('/vehicles/{id}/documents', [PartnerVehicleDocsController::class, 'store'])->name('vehicles.documents.store');
    Route::get('/vehicle-documents/{doc}/download', [PartnerVehicleDocsController::class, 'download'])->name('vehicles.documents.download');
    Route::post('/vehicle-documents/{doc}/delete', [PartnerVehicleDocsController::class, 'destroy'])->name('vehicles.documents.delete');

    // -------------------------------------------------
    // Flota: Conductores
    // -------------------------------------------------
    Route::get('/drivers', [PartnerDriverController::class, 'index'])->name('drivers.index');
    Route::get('/drivers/create', [PartnerDriverController::class, 'create'])->name('drivers.create');
    Route::post('/drivers', [PartnerDriverController::class, 'store'])->name('drivers.store');
    Route::get('/drivers/{id}', [PartnerDriverController::class, 'show'])->name('drivers.show');
    Route::get('/drivers/{id}/edit', [PartnerDriverController::class, 'edit'])->name('drivers.edit');
    Route::put('/drivers/{id}', [PartnerDriverController::class, 'update'])->name('drivers.update');
    Route::delete('/drivers/{id}', [PartnerDriverController::class, 'destroy'])->name('drivers.destroy');

    // Activación / Suspensión
    Route::post('/drivers/{id}/suspend', [PartnerDriverController::class, 'suspend'])->name('drivers.suspend');
    Route::post('/drivers/{id}/activate', [PartnerDriverController::class, 'activate'])->name('drivers.activate');

    // Reset password (nota: ya estabas usando FQCN distinto; lo dejé consistente)
    Route::post('/drivers/{id}/reset-password', [PartnerDriverController::class, 'resetPassword'])
      ->name('drivers.resetPassword');

    // Documentos de conductor
    Route::get('/drivers/{id}/documents', [PartnerDriverDocsController::class, 'index'])->name('drivers.documents.index');
    Route::post('/drivers/{id}/documents', [PartnerDriverDocsController::class, 'store'])->name('drivers.documents.store');
    Route::get('/driver-documents/{doc}/download', [PartnerDriverDocsController::class, 'download'])->name('drivers.documents.download');
    Route::post('/driver-documents/{doc}/delete', [PartnerDriverDocsController::class, 'destroy'])->name('drivers.documents.delete');

    // -------------------------------------------------
    // Asignaciones (driver -> vehicle)
    // -------------------------------------------------
    Route::post('/drivers/{id}/assign-vehicle', [PartnerDriverController::class, 'assignVehicle'])
      ->name('drivers.assignVehicle');

    Route::post('/assignments/{assignmentId}/close', [PartnerDriverController::class, 'closeAssignment'])
      ->name('assignments.close');

    // -------------------------------------------------
    // Finanzas: Wallet + Recargas
    // -------------------------------------------------
    Route::get('/wallet', [PartnerWalletController::class, 'index'])->name('wallet.index');

    Route::get('/topups', [PartnerTopupController::class, 'index'])->name('topups.index');
    Route::get('/topups/create', [PartnerTopupController::class, 'create'])->name('topups.create');
    Route::post('/topups', [PartnerTopupController::class, 'store'])->name('topups.store');
    Route::get('/topups/{topup}', [PartnerTopupController::class, 'show'])->name('topups.show');
    Route::post('/topups/{topup}/resubmit', [PartnerTopupController::class, 'resubmit'])->name('topups.resubmit');

    // -------------------------------------------------
    // Reportes (solo lectura)
    // -------------------------------------------------
    Route::prefix('reports')->name('reports.')->group(function () {

      // Rides
      Route::get('/rides', [PartnerRidesReportController::class, 'index'])->name('rides.index');
      Route::get('/rides/export-csv', [PartnerRidesReportController::class, 'exportCsv'])->name('rides.exportCsv');
      Route::get('/rides/{ride}', [PartnerRidesReportController::class, 'show'])
        ->whereNumber('ride')->name('rides.show');

      // Driver Quality
      Route::get('/driver-quality', [PartnerDriverQualityReportController::class, 'index'])->name('driver_quality.index');
      Route::get('/driver-quality/export.csv', [PartnerDriverQualityReportController::class, 'exportCsv'])->name('driver_quality.export');
      Route::get('/driver-quality/{driver}', [PartnerDriverQualityReportController::class, 'show'])->name('driver_quality.show');

      // Vehicles
      Route::get('/vehicles', [PartnerVehiclesReportController::class, 'index'])->name('vehicles.index');
      Route::get('/vehicles/export-csv', [PartnerVehiclesReportController::class, 'exportCsv'])->name('vehicles.exportCsv');
      Route::get('/vehicles/{vehicle}', [PartnerVehiclesReportController::class, 'show'])
        ->whereNumber('vehicle')->name('vehicles.show');
    });

    // -------------------------------------------------
    // Ride detail (vista completa 1 ride)
    // Nota: esta ruta chocaba con partner.reports.rides.show si usabas el mismo name.
    // La dejo como partner.rides.show (fuera de reports) porque es la que usan enlaces desde tablas.
    // -------------------------------------------------
    Route::get('/rides/{rideId}', [\App\Http\Controllers\Partner\PartnerRideController::class, 'show'])
      ->name('rides.show');

    // -------------------------------------------------
    // API interna para Partner (polling)
    // -------------------------------------------------
    Route::prefix('api')->name('api.')->group(function () {

      Route::get('/dashboard', [\App\Http\Controllers\Partner\Api\PartnerApiController::class, 'dashboard'])
        ->name('dashboard');

      Route::get('/reports/rides-summary', [\App\Http\Controllers\Partner\Api\PartnerReportsApiController::class, 'ridesSummary'])
        ->name('reports.ridesSummary');
    });

    // -------------------------------------------------
    // Monitor (mapa solo lectura)
    // -------------------------------------------------
    Route::get('/monitor', [\App\Http\Controllers\Partner\PartnerMonitorController::class, 'index'])
      ->name('monitor.index');

    Route::get('/monitor/bootstrap', [\App\Http\Controllers\Partner\PartnerMonitorApiController::class, 'bootstrap'])
      ->name('monitor.bootstrap');

    Route::get('/monitor/active-rides', [\App\Http\Controllers\Partner\PartnerMonitorApiController::class, 'activeRides'])
      ->name('monitor.activeRides');

    Route::get('/monitor/rides/{ride}', [\App\Http\Controllers\Partner\PartnerMonitorApiController::class, 'rideShow'])
      ->whereNumber('ride')
      ->name('monitor.rideShow');

    // -------------------------------------------------
    // Inbox (partner_notifications)
    // -------------------------------------------------
    Route::get('/inbox', [\App\Http\Controllers\Partner\Inbox\PartnerInboxController::class, 'index'])
      ->name('inbox.index');

    Route::post('/inbox/{id}/read', [\App\Http\Controllers\Partner\Inbox\PartnerInboxController::class, 'markRead'])
      ->whereNumber('id')->name('inbox.read');

    Route::post('/inbox/read-all', [\App\Http\Controllers\Partner\Inbox\PartnerInboxController::class, 'markAllRead'])
      ->name('inbox.readAll');

    // Deep-link “Ver relacionado”
    Route::get('/inbox/{id}/go', [\App\Http\Controllers\Partner\Inbox\PartnerInboxController::class, 'go'])
      ->whereNumber('id')->name('inbox.go');

    // -------------------------------------------------
    // Soporte / Solicitudes (tickets)
    // -------------------------------------------------
    Route::get('/support', [\App\Http\Controllers\Partner\Support\PartnerSupportController::class, 'index'])
      ->name('support.index');

    Route::get('/support/create', [\App\Http\Controllers\Partner\Support\PartnerSupportController::class, 'create'])
      ->name('support.create');

    Route::post('/support', [\App\Http\Controllers\Partner\Support\PartnerSupportController::class, 'store'])
      ->name('support.store');

    Route::get('/support/{thread}', [\App\Http\Controllers\Partner\Support\PartnerSupportController::class, 'show'])
      ->whereNumber('thread')->name('support.show');

    Route::post('/support/{thread}/reply', [\App\Http\Controllers\Partner\Support\PartnerSupportController::class, 'reply'])
      ->whereNumber('thread')->name('support.reply');

    Route::post('/support/{thread}/close', [\App\Http\Controllers\Partner\Support\PartnerSupportController::class, 'close'])
      ->whereNumber('thread')->name('support.close');
  });


/*
|--------------------------------------------------------------------------
| Auth scaffolding (Breeze) => incluye POST /logout
|--------------------------------------------------------------------------
*/
require __DIR__ . '/auth.php';
