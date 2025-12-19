<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RatingController extends Controller
{
    /** Helper tenant (header, body o user) */
    private function tenantIdFrom(Request $req): int
    {
        $user       = $req->user();
        $userTenant = $user->tenant_id ?? null;

        $fromHeader = $req->header('X-Tenant-ID');
        $fromBody   = $req->input('tenant_id');

        // 1) Priorizar header, luego body
        $candidate = $fromHeader ?? $fromBody;

        if ($candidate !== null && $candidate !== '') {
            $tid = (int) $candidate;

            // Si el usuario tiene tenant fijo y no es sysadmin, validar que coincida
            if ($userTenant && $userTenant != $tid) {
                abort(403, 'Tenant inválido para este usuario');
            }

            return $tid;
        }

        // 2) Fallback al tenant del usuario
        if ($userTenant) {
            return (int) $userTenant;
        }

        // 3) Nada → error
        abort(403, 'Tenant no determinado');
    }

    /**
     * POST /api/ratings
     * Endpoint genérico (si quieres usarlo desde panel o API directa)
     */
    public function store(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'ride_id'            => 'required|exists:rides,id',
            'rated_type'         => 'required|in:passenger,driver',
            'rated_id'           => 'required|integer',
            'rating'             => 'required|integer|min:1|max:5',
            'comment'            => 'nullable|string|max:500',
            'punctuality'        => 'nullable|integer|min:1|max:5',
            'courtesy'           => 'nullable|integer|min:1|max:5',
            'vehicle_condition'  => 'nullable|integer|min:1|max:5',
            'driving_skills'     => 'nullable|integer|min:1|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Datos inválidos',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $tenantId = $this->tenantIdFrom($req);
        $user     = $req->user();

        // Verificar que el ride existe y pertenece al tenant
        $ride = DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('id', $req->ride_id)
            ->first();

        if (!$ride) {
            return response()->json([
                'ok'      => false,
                'message' => 'Ride no encontrado',
            ], 404);
        }

        // Determinar quién está calificando
        if ($req->rated_type === 'driver') {
            // Passenger calificando al driver
            $raterType = 'passenger';
            $raterId   = $ride->passenger_id;

            // Verificar que el passenger sea quien califica
            if (!$user || $user->id !== $ride->created_by) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'No autorizado para calificar este ride',
                ], 403);
            }
        } else {
            // Driver calificando al passenger
            $raterType = 'driver';

            // Obtener driver_id del usuario
            $driverId = DB::table('drivers')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->value('id');

            if (!$driverId || $driverId != $ride->driver_id) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'No autorizado para calificar este ride',
                ], 403);
            }

            $raterId = $driverId;
        }

        // Verificar que no exista ya una calificación para este ride y combinación
        $existingRating = Rating::where('tenant_id', $tenantId)
            ->where('ride_id', $req->ride_id)
            ->where('rater_type', $raterType)
            ->where('rated_type', $req->rated_type)
            ->first();

        if ($existingRating) {
            return response()->json([
                'ok'      => false,
                'message' => 'Ya has calificado este ' . $req->rated_type,
            ], 409);
        }

        try {
            $rating = Rating::create([
                'tenant_id'          => $tenantId,
                'ride_id'            => $req->ride_id,
                'rater_type'         => $raterType,
                'rater_id'           => $raterId,
                'rated_type'         => $req->rated_type,
                'rated_id'           => $req->rated_id,
                'rating'             => $req->rating,
                'comment'            => $req->comment,
                'punctuality'        => $req->punctuality,
                'courtesy'           => $req->courtesy,
                'vehicle_condition'  => $req->vehicle_condition,
                'driving_skills'     => $req->driving_skills,
            ]);

            return response()->json([
                'ok'      => true,
                'message' => 'Calificación enviada exitosamente',
                'rating'  => $rating,
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Error al crear calificación', [
                'error'   => $e->getMessage(),
                'ride_id' => $req->ride_id,
                'user_id' => optional($user)->id,
            ]);

            return response()->json([
                'ok'      => false,
                'message' => 'Error al crear la calificación',
            ], 500);
        }
    }

    /**
     * POST /api/passenger/rides/{ride}/rate-driver
     * Passenger → califica a Driver (para Orbana Passenger)
     */
    public function rateDriverFromPassenger(Request $req, int $rideId)
    {
        $validator = Validator::make($req->all(), [
            'rating'            => 'required|integer|min:1|max:5',
            'comment'           => 'nullable|string|max:500',
            'punctuality'       => 'nullable|integer|min:1|max:5',
            'courtesy'          => 'nullable|integer|min:1|max:5',
            'vehicle_condition' => 'nullable|integer|min:1|max:5',
            'driving_skills'    => 'nullable|integer|min:1|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Datos inválidos',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // 1) Resolver firebase_uid (query, body o header)
        $firebaseUid = $req->input('firebase_uid')
            ?? $req->query('firebase_uid')
            ?? $req->header('X-Firebase-UID');

        if (!$firebaseUid) {
            return response()->json([
                'ok'      => false,
                'message' => 'firebase_uid requerido',
            ], 401);
        }

        // 2) Buscar passenger por firebase_uid (global, sin tenant)
        $passenger = DB::table('passengers')
            ->where('firebase_uid', $firebaseUid)
            ->first();

        if (!$passenger) {
            return response()->json([
                'ok'      => false,
                'message' => 'Pasajero no encontrado para ese firebase_uid',
            ], 404);
        }

        // 3) Cargar ride
        $ride = DB::table('rides')
            ->where('id', $rideId)
            ->first();

        if (!$ride) {
            return response()->json([
                'ok'      => false,
                'message' => 'Ride no encontrado',
            ], 404);
        }

        // 4) Verificar que este passenger sea el dueño del ride
        if ((int)$ride->passenger_id !== (int)$passenger->id) {
            return response()->json([
                'ok'      => false,
                'message' => 'No autorizado para calificar este ride',
            ], 403);
        }

        if (!$ride->driver_id) {
            return response()->json([
                'ok'      => false,
                'message' => 'Ride sin driver asignado',
            ], 422);
        }

        // 5) Evitar calificación duplicada passenger → driver
        $existing = Rating::where('tenant_id', $ride->tenant_id)
            ->where('ride_id', $ride->id)
            ->where('rater_type', 'passenger')
            ->where('rated_type', 'driver')
            ->first();

        if ($existing) {
            return response()->json([
                'ok'      => false,
                'message' => 'Ya has calificado a este conductor',
            ], 409);
        }

        // 6) Crear rating usando tenant_id del ride
        $rating = Rating::create([
            'tenant_id'          => $ride->tenant_id,
            'ride_id'            => $ride->id,
            'rater_type'         => 'passenger',
            'rater_id'           => $passenger->id,
            'rated_type'         => 'driver',
            'rated_id'           => $ride->driver_id,
            'rating'             => $req->input('rating'),
            'comment'            => $req->input('comment'),
            'punctuality'        => $req->input('punctuality'),
            'courtesy'           => $req->input('courtesy'),
            'vehicle_condition'  => $req->input('vehicle_condition'),
            'driving_skills'     => $req->input('driving_skills'),
        ]);

        return response()->json([
            'ok'      => true,
            'message' => 'Gracias por calificar a tu conductor',
            'rating'  => $rating,
        ], 201);
    }

    /**
     * POST /api/driver/rides/{ride}/rate-passenger
     * Driver → califica a Passenger (para Orbana Driver)
     */
    public function ratePassengerFromDriver(Request $req, int $rideId)
    {
        $validator = Validator::make($req->all(), [
            'rating'            => 'required|integer|min:1|max:5',
            'comment'           => 'nullable|string|max:500',
            'punctuality'       => 'nullable|integer|min:1|max:5',
            'courtesy'          => 'nullable|integer|min:1|max:5',
            'vehicle_condition' => 'nullable|integer|min:1|max:5',
            'driving_skills'    => 'nullable|integer|min:1|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Datos inválidos',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Driver viene autenticado por Sanctum (guard web/api de driver)
        $user = $req->user();
        if (!$user) {
            return response()->json([
                'ok'      => false,
                'message' => 'No autenticado',
            ], 401);
        }

        // Resolver driver_id desde users -> drivers
        $driver = DB::table('drivers')
            ->where('user_id', $user->id)
            ->first();

        if (!$driver) {
            return response()->json([
                'ok'      => false,
                'message' => 'No se encontró el registro de conductor para este usuario',
            ], 403);
        }

        // Cargar ride
        $ride = DB::table('rides')
            ->where('id', $rideId)
            ->first();

        if (!$ride) {
            return response()->json([
                'ok'      => false,
                'message' => 'Ride no encontrado',
            ], 404);
        }

        // Verificar que este driver sea el del ride
        if ((int)$ride->driver_id !== (int)$driver->id) {
            return response()->json([
                'ok'      => false,
                'message' => 'No autorizado para calificar este ride',
            ], 403);
        }

        if (!$ride->passenger_id) {
            return response()->json([
                'ok'      => false,
                'message' => 'Ride sin passenger asociado',
            ], 422);
        }

        // Evitar rating duplicado driver → passenger para este ride
        $existing = Rating::where('tenant_id', $ride->tenant_id)
            ->where('ride_id', $ride->id)
            ->where('rater_type', 'driver')
            ->where('rated_type', 'passenger')
            ->first();

        if ($existing) {
            return response()->json([
                'ok'      => false,
                'message' => 'Ya has calificado a este pasajero',
            ], 409);
        }

        // Crear rating
        $rating = Rating::create([
            'tenant_id'          => $ride->tenant_id,
            'ride_id'            => $ride->id,
            'rater_type'         => 'driver',
            'rater_id'           => $driver->id,
            'rated_type'         => 'passenger',
            'rated_id'           => $ride->passenger_id,
            'rating'             => $req->input('rating'),
            'comment'            => $req->input('comment'),
            'punctuality'        => $req->input('punctuality'),
            'courtesy'           => $req->input('courtesy'),
            'vehicle_condition'  => $req->input('vehicle_condition'),
            'driving_skills'     => $req->input('driving_skills'),
        ]);

        return response()->json([
            'ok'      => true,
            'message' => 'Gracias por calificar a tu pasajero',
            'rating'  => $rating,
        ], 201);
    }

    /** GET /api/ratings/driver/{driverId} - Obtener calificaciones de un driver */
    public function getDriverRatings(Request $req, $driverId)
    {
        $tenantId = $this->tenantIdFrom($req);

        $ratings = Rating::with(['ride' => function ($query) {
                $query->select('id', 'passenger_name', 'passenger_phone', 'created_at');
            }])
            ->tenant($tenantId)
            ->where('rated_type', 'driver')
            ->where('rated_id', $driverId)
            ->orderByDesc('created_at')
            ->paginate(20);

        // Obtener información del conductor incluyendo la foto
        $driver = DB::table('drivers')
            ->where('id', $driverId)
            ->where('tenant_id', $tenantId)
            ->select('id', 'name', 'foto_path', 'phone', 'email')
            ->first();

        $average = Rating::tenant($tenantId)
            ->where('rated_type', 'driver')
            ->where('rated_id', $driverId)
            ->avg('rating');

        return response()->json([
            'ok'             => true,
            'driver'         => $driver, // Incluir información del conductor
            'ratings'        => $ratings,
            'average_rating' => round((float) $average, 1),
            'total_ratings'  => $ratings->total(),
        ]);
    }

    /** GET /api/ratings/passenger/{passengerId} - Obtener calificaciones de un passenger */
    public function getPassengerRatings(Request $req, $passengerId)
    {
        $tenantId = $this->tenantIdFrom($req);

        $ratings = Rating::with(['ride' => function ($query) {
                $query->select('id', 'driver_id', 'created_at');
            }])
            ->tenant($tenantId)
            ->where('rated_type', 'passenger')
            ->where('rated_id', $passengerId)
            ->orderByDesc('created_at')
            ->paginate(20);

        // Obtener información del pasajero
        $passenger = DB::table('passengers')
            ->where('id', $passengerId)
            ->where('tenant_id', $tenantId)
            ->select('id', 'name', 'phone', 'email')
            ->first();

        $average = Rating::tenant($tenantId)
            ->where('rated_type', 'passenger')
            ->where('rated_id', $passengerId)
            ->avg('rating');

        return response()->json([
            'ok'             => true,
            'passenger'      => $passenger, // Incluir información del pasajero
            'ratings'        => $ratings,
            'average_rating' => round((float) $average, 1),
            'total_ratings'  => $ratings->total(),
        ]);
    }

    /** GET /api/ratings/ride/{rideId} - Obtener calificaciones de un ride específico */
    public function getRideRatings(Request $req, $rideId)
    {
        $tenantId = $this->tenantIdFrom($req);

        $ratings = Rating::tenant($tenantId)
            ->where('ride_id', $rideId)
            ->get();

        // Obtener información del ride con datos del conductor (incluyendo foto)
        $ride = DB::table('rides')
            ->leftJoin('drivers', 'rides.driver_id', '=', 'drivers.id')
            ->where('rides.id', $rideId)
            ->where('rides.tenant_id', $tenantId)
            ->select(
                'rides.*',
                'drivers.name as driver_name',
                'drivers.foto_path as driver_foto_path',
                'drivers.phone as driver_phone'
            )
            ->first();

        return response()->json([
            'ok'       => true,
            'ride'     => $ride, // Incluir información del ride con datos del conductor
            'ratings'  => $ratings,
        ]);
    }

    /** GET /api/drivers/{driverId} - Obtener información completa de un conductor incluyendo foto */
    public function getDriverInfo(Request $req, $driverId)
    {
        $tenantId = $this->tenantIdFrom($req);

        $driver = DB::table('drivers')
            ->where('id', $driverId)
            ->where('tenant_id', $tenantId)
            ->select(
                'id',
                'name',
                'phone',
                'foto_path',
                'email',
                'profile_bio',
                'status',
                'active',
                'last_lat',
                'last_lng',
                'last_seen_at',
                'created_at',
                'updated_at'
            )
            ->first();

        if (!$driver) {
            return response()->json([
                'ok'      => false,
                'message' => 'Conductor no encontrado',
            ], 404);
        }

        // Obtener calificación promedio
        $averageRating = Rating::tenant($tenantId)
            ->where('rated_type', 'driver')
            ->where('rated_id', $driverId)
            ->avg('rating');

        // Obtener total de calificaciones
        $totalRatings = Rating::tenant($tenantId)
            ->where('rated_type', 'driver')
            ->where('rated_id', $driverId)
            ->count();

        return response()->json([
            'ok'             => true,
            'driver'         => $driver,
            'average_rating' => round((float) $averageRating, 1),
            'total_ratings'  => $totalRatings,
        ]);
    }
}