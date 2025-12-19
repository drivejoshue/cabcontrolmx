<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Passenger;
use App\Models\Ride;
use App\Models\RideIssue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class RideIssueController extends Controller
{
    /**
     * Lista de issues de un viaje (para mostrar estado en la app si quisieras).
     */
    public function index(Request $request, int $rideId)
    {
        $request->validate([
            'tenant_id'    => 'required|integer|exists:tenants,id',
            'firebase_uid' => 'required|string|max:191',
        ]);

        $tenantId = (int) $request->input('tenant_id');

        $ride = Ride::query()
            ->where('id', $rideId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $issues = RideIssue::query()
            ->where('ride_id', $ride->id)
            ->where('tenant_id', $ride->tenant_id)
            ->orderByDesc('created_at')
            ->get([
                'id',
                'category',
                'title',
                'status',
                'severity',
                'created_at',
                'resolved_at',
            ]);

        return response()->json([
            'ok'    => true,
            'items' => $issues,
        ]);
    }

    /**
     * Crea un nuevo reporte de problema desde la app del pasajero.
     * Sólo permite viajes del mismo día / últimas 24 horas.
     */
    public function store(Request $request, int $rideId)
    {
        $data = $request->validate([
            'tenant_id'    => 'required|integer|exists:tenants,id',
            'firebase_uid' => 'required|string|max:191',

            'category'    => 'required|string|in:safety,overcharge,route,driver_behavior,vehicle,lost_item,payment,app_problem,other',
            'summary'     => 'required|string|max:160',
            'description' => 'nullable|string|max:5000',
        ]);

        $tenantId   = (int) $data['tenant_id'];
        $firebaseId = $data['firebase_uid'];

        // 1. Validar que el viaje existe y pertenece al tenant
        $ride = Ride::query()
            ->where('id', $rideId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        // 2. Ventana de tiempo (ej. 24h desde completado/cancelado)
        $maxHours = config('orbana.issues.passenger_window_hours', 24);

        // Puedes ajustar según tus columnas reales (finished_at / ended_at / canceled_at)
        $referenceAt = $ride->finished_at
            ?? $ride->canceled_at
            ?? $ride->updated_at
            ?? $ride->created_at;

        if ($referenceAt) {
            $diffHours = Carbon::parse($referenceAt)->diffInHours(now());
            if ($diffHours > $maxHours) {
                return response()->json([
                    'ok'   => false,
                    'code' => 'REPORT_WINDOW_EXPIRED',
                    'msg'  => 'Solo puedes reportar problemas de viajes del mismo día.',
                ], 422);
            }
        }

        // 3. Resolver pasajero por (tenant, firebase_uid) y, si no existe, crearlo
        $passenger = Passenger::firstOrCreate(
            [
                'tenant_id'    => $ride->tenant_id,
                'firebase_uid' => $firebaseId,
            ],
            [
                'name'  => $ride->passenger_name,
                'phone' => $ride->passenger_phone,
            ]
        );

        // Si el ride no tiene ligado passenger_id, amárralo por conveniencia
        if (!$ride->passenger_id) {
            $ride->passenger_id = $passenger->id;
            $ride->save();
        }

        // 4. Evitar más de un issue abierto por pasajero en el mismo viaje
        $existingOpen = RideIssue::query()
            ->where('tenant_id', $ride->tenant_id)
            ->where('ride_id', $ride->id)
            ->where('reporter_type', 'passenger')
            ->whereNull('resolved_at')
            ->count();

        if ($existingOpen > 0) {
            return response()->json([
                'ok'   => false,
                'code' => 'ISSUE_ALREADY_OPEN',
                'msg'  => 'Ya existe un reporte abierto para este viaje.',
            ], 422);
        }

        // 5. Determinar si debemos escalar a Orbana (plataforma)
        $forwardToPlatform = in_array($data['category'], ['safety', 'payment', 'overcharge'], true);

        $severity = $data['category'] === 'safety'
            ? 'high'
            : 'normal';

        // 6. Crear el issue
        $issue = RideIssue::create([
            'tenant_id'           => $ride->tenant_id,
            'ride_id'             => $ride->id,
            'passenger_id'        => $passenger->id,
            'driver_id'           => $ride->driver_id,
            'reporter_type'       => 'passenger',
            'reporter_user_id'    => null,
            'category'            => $data['category'],
            'title'               => $data['summary'],
            'description'         => $data['description'] ?? null,
            'status'              => 'open',
            'severity'            => $severity,
            'forward_to_platform' => $forwardToPlatform,
        ]);

        Log::info('RIDE_ISSUE_CREATED_FROM_PASSENGER', [
            'issue_id'  => $issue->id,
            'ride_id'   => $ride->id,
            'tenant_id' => $ride->tenant_id,
            'category'  => $issue->category,
        ]);

        return response()->json([
            'ok'       => true,
            'msg'      => 'Hemos recibido tu reporte. La central revisará tu caso.',
            'issue_id' => $issue->id,
        ]);
    }

        /**
     * Crea un reporte de problema desde la app del conductor.
     */
    public function storeFromDriver(Request $request, int $rideId)
    {
        $user = $request->user(); // guard del driver (sanctum/driver_api)
        if (!$user || !$user->driver) {
            return response()->json([
                'ok'   => false,
                'code' => 'UNAUTHENTICATED',
                'msg'  => 'Debes iniciar sesión como conductor.',
            ], 401);
        }

        $data = $request->validate([
            'tenant_id'   => 'required|integer|exists:tenants,id',
            'category'    => 'required|string|in:safety,passenger_behavior,route,vehicle,payment,app_problem,other',
            'summary'     => 'required|string|max:160',
            'description' => 'nullable|string|max:5000',
        ]);

        $tenantId = (int) $data['tenant_id'];
        $driver   = $user->driver;

        // 1. Validar que el viaje exista, sea del tenant y de este driver
        $ride = Ride::query()
            ->where('id', $rideId)
            ->where('tenant_id', $tenantId)
            ->where('driver_id', $driver->id)
            ->firstOrFail();

        // 2. Ventana de tiempo para el conductor (ej. 24 h)
        $maxHours = config('orbana.issues.driver_window_hours', 24);

        $referenceAt = $ride->finished_at
            ?? $ride->canceled_at
            ?? $ride->updated_at
            ?? $ride->created_at;

        if ($referenceAt) {
            $diffHours = Carbon::parse($referenceAt)->diffInHours(now());
            if ($diffHours > $maxHours) {
                return response()->json([
                    'ok'   => false,
                    'code' => 'REPORT_WINDOW_EXPIRED',
                    'msg'  => 'Solo puedes reportar problemas de viajes recientes.',
                ], 422);
            }
        }

        // 3. Evitar duplicar issues abiertos del mismo driver para este ride
        $existingOpen = RideIssue::query()
            ->where('tenant_id', $ride->tenant_id)
            ->where('ride_id', $ride->id)
            ->where('reporter_type', 'driver')
            ->whereNull('resolved_at')
            ->count();

        if ($existingOpen > 0) {
            return response()->json([
                'ok'   => false,
                'code' => 'ISSUE_ALREADY_OPEN',
                'msg'  => 'Ya existe un reporte abierto de este viaje.',
            ], 422);
        }

        $forwardToPlatform = in_array($data['category'], ['safety', 'payment'], true);

        $severity = $data['category'] === 'safety'
            ? 'high'
            : 'normal';

        $issue = RideIssue::create([
            'tenant_id'           => $ride->tenant_id,
            'ride_id'             => $ride->id,
            'passenger_id'        => $ride->passenger_id,
            'driver_id'           => $ride->driver_id,
            'reporter_type'       => 'driver',
            'reporter_user_id'    => null,
            'category'            => $data['category'],
            'title'               => $data['summary'],
            'description'         => $data['description'] ?? null,
            'status'              => 'open',
            'severity'            => $severity,
            'forward_to_platform' => $forwardToPlatform,
        ]);

        Log::info('RIDE_ISSUE_CREATED_FROM_DRIVER', [
            'issue_id'  => $issue->id,
            'ride_id'   => $ride->id,
            'tenant_id' => $ride->tenant_id,
            'driver_id' => $driver->id,
        ]);

        return response()->json([
            'ok'       => true,
            'msg'      => 'Hemos recibido tu reporte. La central revisará tu caso.',
            'issue_id' => $issue->id,
        ]);
    }
}


