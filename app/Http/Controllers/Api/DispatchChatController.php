<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\DriverMessage;
use App\Models\Driver;

class DispatchChatController extends Controller
{
    /**
     * Resolver tenant desde:
     *  - query/body: tenant_id
     *  - JSON crudo
     *  - usuario autenticado (panel): user()->tenant_id
     */
    private function resolveTenantId(Request $request): int
    {
        // 1) query/body normal
        $tid = (int) $request->input('tenant_id', 0);

        // 2) JSON crudo
        if (!$tid) {
            $json = json_decode($request->getContent(), true);
            if (is_array($json) && isset($json['tenant_id'])) {
                $tid = (int) $json['tenant_id'];
            }
        }

        // 3) usuario del panel
        if (!$tid && $request->user() && $request->user()->tenant_id) {
            $tid = (int) $request->user()->tenant_id;
        }

        if ($tid > 0) {
            return $tid;
        }

        abort(403, 'Tenant no determinado');
    }

    /**
     * Lista de hilos (1 por driver) para el inbox del Dispatch.
     *
     * Respuesta:
     *  [
     *    {
     *      driver_id,
     *      driver_name,
     *      vehicle_label,
     *      plate,
     *      last_text,
     *      last_at,
     *      unread_count
     *    },
     *    ...
     *  ]
     */
    public function threads(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);

        $rows = DriverMessage::query()
            ->selectRaw('
                driver_id,
                MAX(id) AS last_message_id,
                MAX(created_at) AS last_at,
                SUM(
                    CASE
                        WHEN sender_type = "driver"
                             AND seen_by_dispatch_at IS NULL
                        THEN 1
                        ELSE 0
                    END
                ) AS unread_count
            ')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('driver_id')
            ->groupBy('driver_id')
            ->orderByDesc('last_at')
            ->limit(100)
            ->get();

        if ($rows->isEmpty()) {
            return response()->json(['threads' => []]);
        }

        // Buscar los últimos mensajes de cada driver
        $lastIds      = $rows->pluck('last_message_id')->all();
        $lastMessages = DriverMessage::whereIn('id', $lastIds)->get()->keyBy('id');

        // Traer nombres / placas
        $driverIds   = $rows->pluck('driver_id')->all();
        $driversById = Driver::whereIn('id', $driverIds)->get()->keyBy('id');

        $threads = $rows->map(function ($row) use ($lastMessages, $driversById) {
            $lastMsg = $lastMessages->get($row->last_message_id);
            $driver  = $driversById->get($row->driver_id);
// AJUSTA estos nombres a tu DB real:
    $econ = $driver?->economico
         ?? $driver?->econ
         ?? $driver?->vehicle_number
         ?? null;
            return [
                'driver_id'     => (int) $row->driver_id,
                'driver_name'   => $driver?->name
                    ?? $driver?->full_name
                    ?? ('Conductor #' . $row->driver_id),
                'vehicle_label' => $driver->vehicle_label ?? null,
                'plate'         => $driver->plate ?? null,
                 // ✅ nuevo
                'econ'          => $econ ? (string)$econ : null,
                'last_text'     => $lastMsg?->message ?? '',
                'last_at'       => $row->last_at,
                'unread_count'  => (int) ($row->unread_count ?? 0),
            ];
        })->values();

        return response()->json([
            'threads' => $threads,
        ]);
    }

    /**
     * Lista de mensajes con un driver.
     * GET /api/dispatch/chats/drivers/{driverId}/messages
     *
     * Query param opcional: ?after_id=123
     *
     * Respuesta:
     *  messages: [
     *    { id, text, created_at, sender_type },
     *    ...
     *  ]
     */
   public function messages(Request $request, int $driverId)
{
    $tenantId = $this->resolveTenantId($request);
    $afterId = (int) $request->query('after_id', 0);

    $query = DriverMessage::query()
        ->where('tenant_id', $tenantId)
        ->where('driver_id', $driverId)
        ->orderBy('id', 'asc');

    if ($afterId > 0) {
        $query->where('id', '>', $afterId);
    }

    $messages = $query
        ->limit(200)
        ->get()
        ->map(function (DriverMessage $m) {
            $meta = is_array($m->meta) ? $m->meta : (json_decode($m->meta ?? 'null', true) ?: null);

            return [
                'id'          => (int) $m->id,
                'driver_id'   => (int) $m->driver_id,            // <-- IMPORTANTE
                'text'        => (string) $m->message,
                'created_at'  => optional($m->created_at)->format('Y-m-d H:i:s'),
                'sender_type' => (string) ($m->sender_type ?? 'dispatch'),
                'kind'        => (string) ($m->kind ?? 'chat'),   // <-- IMPORTANTE
                'meta'        => $meta,
                'is_help_request' => ($m->kind === 'help') || (($meta['is_help_request'] ?? false) === true),
            ];
        });

    DriverMessage::where('tenant_id', $tenantId)
        ->where('driver_id', $driverId)
        ->where('sender_type', 'driver')
        ->whereNull('seen_by_dispatch_at')
        ->update(['seen_by_dispatch_at' => now()]);

    return response()->json(['messages' => $messages]);
}


    /**
     * Enviar mensaje desde Dispatch → driver.
     *
     * Body:
     *  - text (lo que manda el JS)
     *  - tenant_id
     */
    public function send(Request $request, int $driverId)
    {
        $tenantId = $this->resolveTenantId($request);

        // Leer payload combinando form + JSON crudo
        $payload = $request->all();
        if (empty($payload)) {
            $json = json_decode($request->getContent(), true);
            if (is_array($json)) {
                $payload = array_merge($payload, $json);
            }
        }

        $data = validator($payload, [
            'text'    => 'nullable|string|max:500',
            'message' => 'nullable|string|max:500',
        ])->validate();

        $text = $data['text'] ?? $data['message'] ?? null;

        if (!$text || trim($text) === '') {
            return response()->json([
                'error' => 'Mensaje vacío',
            ], 422);
        }

        // Opcional: colgamos del último ride del driver (puede ser null)
        $rideId = DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('driver_id', $driverId)
            ->orderByDesc('id')
            ->value('id');

        $msg = DriverMessage::create([
            'tenant_id'           => $tenantId,
            'ride_id'             => $rideId,
            'driver_id'           => $driverId,
            'sender_type'         => 'dispatch',
            'sender_user_id'      => optional($request->user())->id,
            'kind'                => 'chat',
            'message'             => $text,
            'seen_by_driver_at'   => null,
            'seen_by_dispatch_at' => now(),   // ya lo vio el dispatch
        ]);

        $payload = [
            'id'          => $msg->id,
            'ride_id'     => $msg->ride_id,
            'text'        => $msg->message,
            'created_at'  => $msg->created_at,
            'sender_type' => $msg->sender_type,
        ];

        // 🔔 Tiempo real hacia el driver (canal privado tenant.X.driver.Y)
        try {
            \App\Services\Realtime::toDriver($tenantId, $driverId)
                ->emit('driver.message.new', $payload);
        } catch (\Throwable $e) {
            \Log::warning('DispatchChat send → error enviando a driver', [
                'tenant_id' => $tenantId,
                'driver_id' => $driverId,
                'msg_id'    => $msg->id,
                'error'     => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => $payload,
        ]);
    }

    /**
     * Marcar mensajes del driver como leídos (desde el panel).
     */
    public function markRead(Request $request, int $driverId)
    {
        $tenantId = $this->resolveTenantId($request);

        DriverMessage::where('tenant_id', $tenantId)
            ->where('driver_id', $driverId)
            ->where('sender_type', 'driver')
            ->whereNull('seen_by_dispatch_at')
            ->update(['seen_by_dispatch_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
