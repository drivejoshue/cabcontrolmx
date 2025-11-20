<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RatingController extends Controller
{
    /** Helper tenant */
    private function tenantIdFrom(Request $req): int
    {
        return (int)($req->header('X-Tenant-ID') ?? optional($req->user())->tenant_id ?? 1);
    }

    /** POST /api/ratings - Crear una calificación */
    public function store(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'ride_id' => 'required|exists:rides,id',
            'rated_type' => 'required|in:passenger,driver',
            'rated_id' => 'required|integer',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
            'punctuality' => 'nullable|integer|min:1|max:5',
            'courtesy' => 'nullable|integer|min:1|max:5',
            'vehicle_condition' => 'nullable|integer|min:1|max|5',
            'driving_skills' => 'nullable|integer|min:1|max|5',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $tenantId = $this->tenantIdFrom($req);
        $user = $req->user();

        // Verificar que el ride existe y pertenece al tenant
        $ride = DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('id', $req->ride_id)
            ->first();

        if (!$ride) {
            return response()->json([
                'ok' => false,
                'message' => 'Ride no encontrado'
            ], 404);
        }

        // Determinar quién está calificando
        if ($req->rated_type === 'driver') {
            // Passenger calificando al driver
            $raterType = 'passenger';
            $raterId = $ride->passenger_id;
            
            // Verificar que el passenger sea quien califica
            if ($user->id !== $ride->created_by) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No autorizado para calificar este ride'
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
                    'ok' => false,
                    'message' => 'No autorizado para calificar este ride'
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
                'ok' => false,
                'message' => 'Ya has calificado este ' . $req->rated_type
            ], 409);
        }

        try {
            $rating = Rating::create([
                'tenant_id' => $tenantId,
                'ride_id' => $req->ride_id,
                'rater_type' => $raterType,
                'rater_id' => $raterId,
                'rated_type' => $req->rated_type,
                'rated_id' => $req->rated_id,
                'rating' => $req->rating,
                'comment' => $req->comment,
                'punctuality' => $req->punctuality,
                'courtesy' => $req->courtesy,
                'vehicle_condition' => $req->vehicle_condition,
                'driving_skills' => $req->driving_skills,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Calificación enviada exitosamente',
                'rating' => $rating
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Error al crear calificación', [
                'error' => $e->getMessage(),
                'ride_id' => $req->ride_id,
                'user_id' => $user->id
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Error al crear la calificación'
            ], 500);
        }
    }

    /** GET /api/ratings/driver/{driverId} - Obtener calificaciones de un driver */
    public function getDriverRatings(Request $req, $driverId)
    {
        $tenantId = $this->tenantIdFrom($req);

        $ratings = Rating::with(['ride' => function($query) {
                $query->select('id', 'passenger_name', 'passenger_phone', 'created_at');
            }])
            ->tenant($tenantId)
            ->where('rated_type', 'driver')
            ->where('rated_id', $driverId)
            ->orderByDesc('created_at')
            ->paginate(20);

        // Calcular promedio
        $average = Rating::tenant($tenantId)
            ->where('rated_type', 'driver')
            ->where('rated_id', $driverId)
            ->avg('rating');

        return response()->json([
            'ok' => true,
            'ratings' => $ratings,
            'average_rating' => round($average, 1),
            'total_ratings' => $ratings->total()
        ]);
    }

    /** GET /api/ratings/passenger/{passengerId} - Obtener calificaciones de un passenger */
    public function getPassengerRatings(Request $req, $passengerId)
    {
        $tenantId = $this->tenantIdFrom($req);

        $ratings = Rating::with(['ride' => function($query) {
                $query->select('id', 'driver_id', 'created_at');
            }])
            ->tenant($tenantId)
            ->where('rated_type', 'passenger')
            ->where('rated_id', $passengerId)
            ->orderByDesc('created_at')
            ->paginate(20);

        $average = Rating::tenant($tenantId)
            ->where('rated_type', 'passenger')
            ->where('rated_id', $passengerId)
            ->avg('rating');

        return response()->json([
            'ok' => true,
            'ratings' => $ratings,
            'average_rating' => round($average, 1),
            'total_ratings' => $ratings->total()
        ]);
    }

    /** GET /api/ratings/ride/{rideId} - Obtener calificaciones de un ride específico */
    public function getRideRatings(Request $req, $rideId)
    {
        $tenantId = $this->tenantIdFrom($req);

        $ratings = Rating::tenant($tenantId)
            ->where('ride_id', $rideId)
            ->get();

        return response()->json([
            'ok' => true,
            'ratings' => $ratings
        ]);
    }
}