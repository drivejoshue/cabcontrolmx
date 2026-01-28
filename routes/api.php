<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Events\PublicTestEvent;

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
use App\Http\Controllers\Admin\DispatchBoardsController;
use App\Http\Controllers\Api\DriverVehiclesController;
use App\Http\Controllers\Admin\DispatchSettingsController;
use App\Http\Controllers\Api\PassengerAuthController;
use App\Http\Controllers\Api\PassengerRideController;
use App\Http\Controllers\Api\PassengerDeviceController;
use App\Http\Controllers\Api\PassengerTestPushController;
use App\Http\Controllers\Api\RideIssueController as PassengerRideIssueController;
use App\Http\Controllers\Api\RideMessageController;
use App\Http\Controllers\Api\DispatchChatController;
use App\Http\Controllers\Api\DriverChatController;
use App\Http\Controllers\Api\PassengerAppQuoteController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\DriverProfileController;
use App\Http\Controllers\Api\DriverWalletController;
use App\Http\Controllers\Api\PassengerSuggestionsController;
use App\Http\Controllers\Api\PassengerPlacesController;
use App\Http\Controllers\Api\PublicContactController;
use App\Events\DriverEvent;
use App\Http\Controllers\Webhooks\MercadoPagoWebhookController;
use App\Http\Controllers\SysAdmin\ContactLeadController;
use App\Http\Controllers\Public\RideShareController;
use App\Http\Controllers\Api\PassengerRideShareController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Todas estas rutas se publican bajo el prefijo /api
| (configurado en RouteServiceProvider).
|--------------------------------------------------------------------------
*/




/* ===================== DEBUG / TEST ===================== */

Route::match(['GET','POST'], '/webhooks/mercadopago', [MercadoPagoWebhookController::class, 'handle'])
    ->name('api.webhooks.mercadopago');
Route::post('/public/contact', [PublicContactController::class, 'store'])
  ->middleware(['throttle:10,1']);
Route::post('/test-driver-event', function (Request $request) {
    $user = \App\Models\User::where('email', 'driver@test.com')->first();

    if (!$user) {
        return response()->json(['error' => 'User not found'], 404);
    }

    auth()->login($user);

    $driver = \App\Models\Driver::where('user_id', $user->id)->first();

    event(new DriverEvent(
        tenantId: 1,
        driverId: $driver->id,
        type: 'offers.new',
        payload: [
            'ride_id'  => 789,
            'offer_id' => 999,
            'message'  => 'Test from API endpoint',
            'from_api' => true,
        ]
    ));

    return response()->json([
        'status'    => 'success',
        'message'   => 'Event sent',
        'user_id'   => $user->id,
        'driver_id' => $driver->id,
    ]);
});
Route::get('/debug/reverb', function () {
    broadcast(new PublicTestEvent(
        'Hola desde Laravel ' . now()->format('Y-m-d H:i:s'),
        [
            'rand' => random_int(1, 9999),
            'env'  => app()->environment(),
        ]
    ));

    return ['ok' => true];
});
/* ===================== APP PASAJERO ===================== */


Route::get('/public/ride-share/{token}/state', [RideShareController::class, 'state'])
    ->where('token', '[A-Za-z0-9\-_]+')
    ->middleware(['throttle:30,1']) // ajusta a gusto
    ->name('public.ride-share.state');

/**
 * Sincronización de identidad del pasajero desde Firebase
 * (se llama después de login por teléfono / Google en la app).
 */
Route::post('/passenger/auth-sync', [PassengerAuthController::class, 'syncFromFirebase']);

/**
 * Rutas de la app de pasajero (quote, crear ride, bidding, cancelar, relanzar).
 */

Route::middleware('throttle:public-contact')->group(function () {
    Route::post('/public/contact', [ContactLeadController::class, 'store'])
        ->name('public.contact.store');
});


Route::get('/public/app-config', [\App\Http\Controllers\Api\PublicAppConfigController::class, 'show']);


Route::prefix('passenger')->group(function () {
     Route::post('ping', [PassengerAuthController::class, 'ping']);
      Route::post('/devices/sync', [PassengerDeviceController::class, 'sync']);
    Route::post('/devices/delete', [PassengerDeviceController::class, 'deleteToken']); // o
    Route::post('profile', [PassengerAuthController::class, 'profile']);
  Route::post('devices/logout-all',  [PassengerDeviceController::class, 'logoutAll']);

  Route::post('account/deactivate',  [PassengerDeviceController::class, 'deactivateAccount']);
    
    Route::post('test-push', [PassengerTestPushController::class, 'sendTest']);
    // Calcular tarifa recomendada para el pasajero (quote)
    Route::post('/quote', [PassengerAppQuoteController::class, 'quote']);

    // Crear un ride desde la app de pasajero (oferta inicial del pasajero)
    Route::post('/rides', [PassengerRideController::class, 'store']);
    Route::get('/rides/current-any', [PassengerRideController::class, 'currentAny']);

    Route::get('/rides/{ride}/offers', [PassengerRideController::class, 'offers']);


    // Bidding: aceptar / rechazar oferta del driver
    Route::post('/rides/{ride}/accept-offer', [PassengerRideController::class, 'acceptOffer']);
    Route::post('/rides/{ride}/reject-offer', [PassengerRideController::class, 'rejectOffer']);

    // Cancelar un ride desde la app de pasajero
    Route::post('/rides/{ride}/cancel', [PassengerRideController::class, 'cancel']);

    // Relanzar ola de ofertas (otra “ola” de drivers)
    Route::post('/rides/{ride}/relaunch-offers', [PassengerRideController::class, 'relaunchOffers']);
    Route::get('/rides/{ride}/driver-card',[RideController::class, 'driverCardForPassenger']);

    // (opcional) Marcar “ya voy al punto” – lo tienes en el controller
    Route::post('/rides/{ride}/on-the-way', [PassengerRideController::class, 'onTheWay']);

     Route::post('/rides/{ride}/on-board',  [PassengerRideController::class, 'onBoard']);
    Route::post('/rides/{ride}/finished',  [PassengerRideController::class, 'finishByPassenger']);
    Route::get('/rides/current', [PassengerRideController::class, 'current']);

    Route::get('/rides/history', [PassengerRideController::class, 'history']);

       Route::get('rides/{ride}/issues', [PassengerRideIssueController::class, 'index']);
    Route::post('rides/{ride}/issues', [PassengerRideIssueController::class, 'store']);
     Route::post(
        '/rides/{ride}/rate-driver',
        [RatingController::class, 'rateDriverFromPassenger']
    );
    Route::get('suggestions', [PassengerSuggestionsController::class, 'suggestions']);
   Route::post('places/upsert', [PassengerPlacesController::class, 'upsert']);
    Route::post('places/fav/add', [PassengerPlacesController::class, 'addFavorite']);
   Route::get('places', [PassengerPlacesController::class, 'list']);

    Route::post('places/{id}/deactivate', [PassengerPlacesController::class, 'deactivate']);

    Route::get('nearby-drivers', [\App\Http\Controllers\Api\PassengerNearbyDriversController::class, 'nearby']);

    Route::post('/rides/{ride}/share', [PassengerRideShareController::class, 'create']);
    Route::post('/rides/{ride}/share/revoke', [PassengerRideShareController::class, 'revoke']);

});

/* ===================== CALIFICACIONES ===================== */

Route::post('/ratings', [RatingController::class, 'store']);
    Route::get('/ratings/driver/{driver}', [RatingController::class, 'getDriverRatings']);
    Route::get('/ratings/passenger/{passenger}', [RatingController::class, 'getPassengerRatings']);
    Route::get('/ratings/ride/{ride}', [RatingController::class, 'getRideRatings']);;

/* ===================== DISPATCH (PANEL WEB) ===================== */
Route::prefix('dispatch/chats')->group(function () {

    // 1) Lista de hilos (drivers con mensajes)
    Route::get('/threads', [DispatchChatController::class, 'threads'])
        ->name('api.dispatch.chats.threads');

    // 2) Obtener mensajes de un driver
    Route::get('/{driverId}/messages', [DispatchChatController::class, 'messages'])
        ->name('api.dispatch.chats.messages');

    // 3) Enviar mensaje desde Dispatch → Driver
    Route::post('/{driverId}/messages', [DispatchChatController::class, 'send'])
        ->name('api.dispatch.chats.send');

    // 4) Marcar mensajes como leídos (opcional)
    Route::post('/{driverId}/read', [DispatchChatController::class, 'markRead'])
        ->name('api.dispatch.chats.read');

});


Route::post('/dispatch/quote',   [DispatchController::class, 'quote'])->name('api.dispatch.quote');
Route::post('/dispatch/tick',    [DispatchController::class, 'tick']);
Route::get ('/dispatch/boards',  [DispatchBoardsController::class, 'index']);
Route::get ('/dispatch/settings',[DispatchSettingsController::class, 'show']);
Route::get ('/dispatch/runtime', [DispatchController::class, 'runtime']);
Route::get ('/dispatch/active',  [DispatchController::class, 'active']);
Route::get ('/dispatch/drivers', [DispatchController::class, 'driversLive']);
Route::post('/dispatch/assign',  [DispatchController::class, 'assign']);
Route::post('/dispatch/rides/{ride}/reassign',  [DispatchController::class, 'reassign']);

Route::post('/dispatch/rides/{ride}/cancel', [DispatchController::class,'cancel']);
Route::get ('/dispatch/nearby-drivers',      [DispatchController::class,'nearbyDrivers']);


/* ===================== GEO (PANEL / GENERIC) ===================== */

// Ruta de cálculo de ruta para el panel de despacho
Route::post('/geo/route', [GeoController::class, 'route'])->name('api.geo.route');




/* ===================== CAPAS PARA MAPA (panel/app) ===================== */

// Sectores / zonas operativas
Route::get('/sectores',   [ApiSectorController::class, 'index']);
Route::get('/taxistands', [TaxiStandController::class, 'index']);

/* ===================== AUTH DRIVER (APP ORBANA DRIVER) ===================== */

// Login / logout / datos del driver (Sanctum)
Route::post('auth/login',  [DriverAuthController::class, 'login']);
Route::post('auth/logout', [DriverAuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get ('auth/me',     [DriverAuthController::class, 'me'])->middleware('auth:sanctum');
Route::put('auth/bank', [DriverAuthController::class, 'updateBank'])
    ->middleware('auth:sanctum');

/* ===================== RIDES CRUD (PANEL) ===================== */

Route::prefix('rides')->group(function () {
    // Listar / crear rides desde el panel
    Route::get ('',  [RideController::class, 'index']);
    Route::post('',  [RideController::class, 'store']);

    // Definir ruta y quote desde el panel
    Route::post('{ride}/route', [RideController::class, 'setRoute']);
    Route::post('{ride}/quote', [RideController::class, 'quote']);

    // Show / update
    Route::get  ('{ride}',   [RideController::class, 'show']);
    Route::patch('{ride}',   [RideController::class, 'update']);

    // Listas por estado para panel
    Route::get('lists/active',    [RideController::class, 'active']);
    Route::get('lists/queued',    [RideController::class, 'queued']);
    Route::get('lists/scheduled', [RideController::class, 'scheduled']);

    // Acciones desde el panel
    Route::post('{ride}/assign', [RideController::class, 'assign']);

    Route::post('{ride}/start',  [RideController::class, 'start']);
    Route::post('{ride}/pickup', [RideController::class, 'pickup']);
    Route::post('{ride}/drop',   [RideController::class, 'drop']);
    Route::post('{ride}/cancel', [RideController::class, 'cancel']);

   



});




/* ===================== PASAJEROS (PANEL) ===================== */

Route::get('/passengers/last-ride', [PassengerController::class, 'lastRide']);
Route::get('/passengers/lookup',    [PassengerController::class, 'lookup']);






/* ===================== DRIVER (APP) – Sanctum ===================== */

     Route::middleware('auth:sanctum', 'tenant.billing_ok_api')->prefix('driver')->group(function () {

    // Vehículos asociados al driver
    Route::get('/vehicles', [DriverVehiclesController::class, 'index']);
   
    Route::patch('/status', [DriverAuthController::class, 'setStatus']);
    Route::get('driver/status', [DriverAuthController::class, 'show']);

    // Turnos
    Route::post('/shifts/start',  [DriverShiftController::class, 'start']);
    Route::post('/shifts/finish', [DriverShiftController::class, 'finish']);

    // Ofertas (cola de ofertas, detalle y debug)
    Route::get ('/offers',        [OfferController::class, 'index']);
    Route::get ('/offers/{offer}',[OfferController::class,'show']);
    Route::get ('/offers/debug',  [OfferController::class, 'debugServerTime']);

    // Bidding / aceptar / rechazar desde el driver
    Route::post('/offers/{offer}/bid',    [OfferController::class, 'bid']);
    Route::post('/offers/{offer}/accept', [OfferController::class, 'accept']);
    Route::post('/offers/{offer}/reject', [OfferController::class, 'reject']);

        Route::post('/offers/{offer}/expire', [OfferController::class, 'expire']);


    Route::post('/offers/{offer}/viewing', [OfferController::class, 'viewing']);

    // Ciclo de ride (para el driver app)
    Route::post('/rides/{ride}/arrived', [RideController::class,'arrive']);
    Route::post('/rides/{ride}/board',   [RideController::class,'board']);
    Route::post('/rides/{ride}/finish',  [RideController::class,'finish']);
    Route::post('/rides/{ride}/cancel',  [RideController::class,'cancelByDriver']);

     Route::post('rides/{ride}/issues', [PassengerRideIssueController::class, 'storeFromDriver']);
    Route::get('rides/{ride}/issues', [PassengerRideIssueController::class, 'index']); // opcional

    Route::get('/rides/active', [RideController::class, 'activeForDriver']);
     Route::post('/rides/{ride}/rate-passenger',[RatingController::class, 'ratePassengerFromDriver']);

     Route::get('/messages', [DriverChatController::class, 'index']);
    Route::post('/messages', [DriverChatController::class, 'store']);

    Route::post('/offers/{offer}/expire', [OfferController::class, 'expire']);

    // GEO para driver (geocode + rutas)
    Route::get ('/geo/geocode', [GeoController::class,'geocode']);
    Route::post('/geo/route',   [GeoController::class,'route']);

    // Completar parada (multi-stop)
    Route::post('/rides/{ride}/complete-stop', [RideController::class, 'completeStop']);

    // Ubicación en tiempo real del driver
    Route::match(['put','post'], '/location', [DriverLocationController::class, 'update']);

    // ==== COLA DE OFERTAS DEL DRIVER ====
    Route::get   ('/queue',         [QueueController::class, 'index']);
    Route::post  ('/queue/promote', [QueueController::class, 'promote']);
    Route::delete('/queue/{offer}', [QueueController::class, 'drop']);
    Route::delete('/queue',         [QueueController::class, 'clearAll']);

    // ==== TAXI STAND (BASE) ====
     Route::get('/taxistands',      [TaxiStandController::class, 'index']); // 👈 lista para driver
    Route::post('/stands/join',   [TaxiStandController::class, 'join']);   // stand_id o código
    Route::post('/stands/join-code', [TaxiStandController::class, 'joinByCode']); // 👈 NUEVA

    Route::post('/stands/leave',  [TaxiStandController::class, 'leave']);
    Route::get ('/stands/status', [TaxiStandController::class, 'status']);


    // ==== GEO extra ====
    Route::get('/geo/locate-sector', [GeoController::class, 'locateSector']); // si lo implementas



     // Perfil del driver (incluye payout y foto)
   
    Route::get('/profile', [DriverProfileController::class, 'show']);

    Route::post('/profile', [DriverProfileController::class, 'update']);
    Route::post('/profile/photo', [DriverProfileController::class, 'updatePhoto']);


    Route::get('/history', [RideController::class, 'historyForDriver']);

    // Wallet del driver
    Route::get('/wallet', [DriverWalletController::class, 'show']);
    Route::get('/wallet/movements', [DriverWalletController::class, 'movements']);

   


});


