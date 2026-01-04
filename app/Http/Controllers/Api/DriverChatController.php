<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DriverMessage;
use App\Events\DriverEvent;
use Illuminate\Support\Facades\DB;


class DriverChatController extends Controller
{
    /**
     * GET /api/driver/messages
     */
    public function index(Request $request)
    { $user = $request->user();

        $driver = DB::table('drivers')
    ->where('user_id', $user->id)
    ->first(['id','tenant_id']);

if (!$driver) {
    return response()->json([
        'ok' => false,
        'error' => 'Driver no encontrado para el usuario autenticado.',
        'messages' => [],
    ], 401);
}


    \Log::info('CHAT_INDEX_AUTH', [
        'auth_user_id' => $user?->id,
        'auth_email'   => $user?->email,
        'auth_tenant'  => $user?->tenant_id,
        'has_driver_rel' => (bool) ($user?->driver),
        'driver_rel_id'  => $user?->driver?->id,
        'driver_rel_tenant' => $user?->driver?->tenant_id,
        'header_tenant' => $request->header('X-Tenant-ID'),
    ]);



        if (!$user || !$user->driver) {
            return response()->json([
                'ok'       => false,
                'error'    => 'Driver no encontrado para el usuario autenticado.',
                'messages' => [],
            ], 401);
        }

       $driver = DB::table('drivers')
    ->where('user_id', $user->id)
    ->first(['id','tenant_id']);

if (!$driver) {
    return response()->json([
        'ok' => false,
        'error' => 'Driver no encontrado para el usuario autenticado.',
        'messages' => [],
    ], 401);
}




       $tenantId = (int) $driver->tenant_id; 
        $afterId  = $request->query('after_id');

        $q = DriverMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('driver_id', $driver->id);

        if (!empty($afterId)) {
            $q->where('id', '>', (int) $afterId);
        }

        \Log::info('CHAT_INDEX_SCOPE', [
    'using_driver_id' => $driver->id,
    'using_tenant_id' => $driver->tenant_id,
    'after_id' => $afterId,
]);

        $messages = $q
            ->orderBy('id', 'asc')
            ->limit(200)
            ->get();

        // IMPORTANTE: mapear EXACTAMENTE los nombres que espera el DTO
        $payload = $messages->map(function (DriverMessage $m) {
            return [
                'id'           => (int) $m->id,
                'ride_id'      => $m->ride_id ? (int) $m->ride_id : null,
                'driver_id'    => (int) $m->driver_id,
                'passenger_id' => $m->passenger_id ? (int) $m->passenger_id : null,
                'text'         => (string) $m->message,                    // 👈 message → text
                'sender_type'  => $m->sender_type ?? 'dispatch',           // 👈 AQUÍ EL VALOR
                'kind'         => $m->kind ?? 'chat',
                'created_at'   => optional($m->created_at)->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'ok'       => true,
            'messages' => $payload,
        ]);
    }

    /**
     * POST /api/driver/messages
     */
  public function store(Request $request)
{
    $user = $request->user();

    if (!$user || !$user->driver) {
        return response()->json([
            'ok'    => false,
            'error' => 'Driver no encontrado para el usuario autenticado.',
        ], 401);
    }

    $driver = $user->driver;

    $data = $request->validate([
        'text'         => 'required|string|max:500',
        'kind'         => 'nullable|string|in:chat,help,warning,info',
        'ride_id'      => 'nullable|integer|exists:rides,id',
        'template_key' => 'nullable|string|max:64',
    ]);

   $tenantId = (int) $driver->tenant_id; 

    // ==========================
    //  KIND + META (ayuda)
    // ==========================
    $kind = $data['kind'] ?? 'chat';

    $meta = [];

    if ($kind === 'help') {
        // Bandera clara para el panel Dispatch
        $meta['is_help_request'] = true;
        $meta['requested_at']    = now()->format('Y-m-d H:i:s');

        // (Opcional) última ubicación del driver, si tienes la tabla driver_locations
        try {
            /** @var \App\Models\DriverLocation|null $lastLoc */
            $lastLoc = \App\Models\DriverLocation::where('driver_id', $driver->id)
                ->latest('id')
                ->first();

            if ($lastLoc) {
                $meta['location'] = [
                    'lat'     => $lastLoc->lat ?? null,
                    'lng'     => $lastLoc->lng ?? null,
                    'label'   => $lastLoc->label ?? null,
                    'captured_at' => optional($lastLoc->created_at)->format('Y-m-d H:i:s'),
                ];
            }
        } catch (\Throwable $e) {
            // No rompemos el flujo si no existe la tabla o el modelo
            \Log::warning('No se pudo adjuntar ubicación a help_request: '.$e->getMessage());
        }
    }

    $msg = new DriverMessage();
    $msg->tenant_id       = $tenantId;
    $msg->ride_id         = $data['ride_id'] ?? null;
    $msg->driver_id       = $driver->id;
    $msg->passenger_id    = null;
    $msg->sender_type     = 'driver';                        // 👈 SIEMPRE driver aquí
    $msg->sender_user_id  = $user->id;
    $msg->kind            = $kind;                           // 👈 ya con default
    $msg->template_key    = $data['template_key'] ?? null;
    $msg->message         = $data['text'];                   // se guarda en "message"
    $msg->meta            = $meta ?: null;                   // 👈 guardamos meta (incluye flag de ayuda)
    $msg->save();

    $dto = [
        'id'              => (int) $msg->id,
        'ride_id'         => $msg->ride_id ? (int) $msg->ride_id : null,
        'driver_id'       => (int) $msg->driver_id,
        'passenger_id'    => $msg->passenger_id ? (int) $msg->passenger_id : null,
        'text'            => (string) $msg->message,
        'sender_type'     => $msg->sender_type,                  // "driver"
        'kind'            => $msg->kind ?? 'chat',
        'created_at'      => optional($msg->created_at)->format('Y-m-d H:i:s'),

        // 👇 meta + bandera plana para que el JS no tenga que meterse al array
        'meta'            => $msg->meta ?: null,
        'is_help_request' => $msg->kind === 'help' ||
                             (($msg->meta['is_help_request'] ?? false) === true),
    ];

    event(new DriverEvent(
        tenantId: $tenantId,
        driverId: $driver->id,
        type: 'driver.message.new',
        payload: $dto
    ));

    return response()->json([
        'ok'      => true,
        'message' => $dto,
    ], 201);
}

}
