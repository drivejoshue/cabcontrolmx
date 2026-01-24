<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\UniqueConstraintViolationException;

class TaxiStandService
{
    /**
     * Lista de bases para el driver:
     *  - sector_nombre
     *  - queue_count (# de carros en cola)
     */
    public static function listForDriver(int $tenantId)
    {
        return DB::table('taxi_stands as ts')
            ->leftJoin('sectores as s', function ($q) use ($tenantId) {
                $q->on('s.id', '=', 'ts.sector_id')
                  ->where('s.tenant_id', '=', $tenantId);
            })
         ->leftJoin('taxi_stand_queue as q', function ($join) use ($tenantId) {
    $join->on('q.stand_id', '=', 'ts.id')
         ->where('q.tenant_id', '=', $tenantId)
         ->whereIn('q.status', ['en_cola','saltado']); // si quieres contar ambos


            })
            ->where('ts.tenant_id', $tenantId)
            ->where('ts.activo', 1)
            ->groupBy(
                'ts.id', 'ts.tenant_id', 'ts.sector_id',
                'ts.nombre', 'ts.latitud', 'ts.longitud',
                'ts.codigo', 'ts.capacidad',
                's.nombre'
            )
            ->orderBy('ts.nombre')
            ->get([
                'ts.id',
                'ts.nombre',
                'ts.latitud',
                'ts.longitud',
                'ts.capacidad',
                'ts.codigo',
                DB::raw('s.nombre as sector_nombre'),
                DB::raw('COUNT(q.id) as queue_count'),
            ]);
    }

    /**
     * Reglas básicas para poder unirse a una base.
     * Aquí puedes meter:
     *  - no tener ride activo
     *  - no tener oferta aceptada, etc.
     */
    public static function ensureCanJoin(int $tenantId, int $driverId): void
    {
        $driver = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('id', $driverId)
            ->first();

        if (!$driver) {
            abort(403, 'Driver no encontrado');
        }

        if ($driver->status !== 'idle') {
            abort(422, 'Solo puedes unirte a una base estando Disponible.');
        }


            // $inQueue = DB::table('taxi_stand_queue')
            //   ->where('tenant_id', $tenantId)
            //   ->where('driver_id', $driverId)
            //   ->where('active_key', 1)              // ya implica en_cola|saltado
            //   ->exists();

            // if ($inQueue) {
            //   abort(422, 'Ya estás en una base. Sal primero para cambiar.');
            // }

        // Aquí luego se puede validar:
        //  - rides activos
        //  - offers en estado offered/accepted
    }

    /**
     * Join por stand_id (ya validado / resuelto).
     * Incluye validación por distancia.
     */
 public static function joinStandById(int $tenantId, int $driverId, int $standId): array
{
    self::ensureCanJoin($tenantId, $driverId);

    $stand = DB::table('taxi_stands')
        ->where('tenant_id', $tenantId)
        ->where('id', $standId)
        ->where('activo', 1)
        ->first();

    if (!$stand) {
        return ['ok' => false, 'message' => 'Base no encontrada o inactiva'];
    }

    $driver = DB::table('drivers')
        ->where('tenant_id', $tenantId)
        ->where('id', $driverId)
        ->select('last_lat', 'last_lng')
        ->first();

  $freshSec = 120;
$loc = self::getFreshLocation($tenantId, $driverId, $freshSec);

if (!$loc) {
    return [
        'ok' => false,
        'message' => "Ubicación no disponible o desactualizada. Mantén la app enviando ping y reintenta.",
    ];
}

// settings → radius
$settings = DB::table('dispatch_settings')->where('tenant_id', $tenantId)->first();
$radiusKm = 0.2;
if ($settings) {
    $radiusKm = (float)($settings->stand_radius_km ?? $settings->auto_dispatch_radius_km ?? 0.2);
}

// distancia con la ubicación fresca (NO con drivers.last_lat/last_lng)
$distKm = self::haversineKm(
    (float)$stand->latitud,
    (float)$stand->longitud,
    (float)$loc['lat'],
    (float)$loc['lng']
);

if ($distKm > $radiusKm) {
    return [
        'ok' => false,
        'message' => 'No estás dentro de la base. Acércate al paradero para unirte.',
        'dist_km' => $distKm,
        'radius_km' => $radiusKm,
        'age_sec' => $loc['age_sec'] ?? null,
    ];
}

try {
    DB::statement('CALL sp_queue_join_stand_v1(?, ?, ?)', [$tenantId, $standId, $driverId]);
    return ['ok' => true, 'message' => 'Te uniste a la base.'];
} catch (UniqueConstraintViolationException $e) {
    return ['ok' => true, 'message' => 'Ya estabas en una base.'];
}

}

    /**
     * Leave: si no envían stand_id, detectamos la base actual.
     */
public static function leaveStand(int $tenantId, int $driverId, int $standId, string $statusTo = 'salio'): array
{
    try {
        // Primero, eliminar cualquier registro 'salio' existente para evitar violación de unicidad
      $active = DB::table('taxi_stand_queue')
  ->where('tenant_id', $tenantId)
  ->where('stand_id', $standId)
  ->where('driver_id', $driverId)
  ->where('active_key', 1)              // en_cola|saltado
  ->exists();

        if (!$active) {
          return ['ok'=>true,'message'=>'Ya estabas fuera de la base.'];
        }

        DB::statement('CALL sp_queue_leave_stand_v1(?, ?, ?, ?)', [$tenantId,$standId,$driverId,$statusTo]);


        return [
            'ok'      => true,
            'message' => 'Saliste de la base.',
        ];
        
    } catch (UniqueConstraintViolationException $e) {
        // Si aún hay error, manejar como idempotente
        Log::warning('TAXI_STAND_LEAVE_DUPLICATE', [
            'tenant_id' => $tenantId,
            'driver_id' => $driverId,
            'stand_id'  => $standId,
            'error'     => $e->getMessage(),
        ]);
        
        return [
            'ok'      => true,
            'message' => 'Ya estabas fuera de la base.',
        ];
    } catch (\Throwable $e) {
        Log::error('TAXI_STAND_LEAVE_ERROR', [
            'tenant_id' => $tenantId,
            'driver_id' => $driverId,
            'stand_id'  => $standId,
            'error'     => $e->getMessage(),
        ]);
        
        return [
            'ok'      => false,
            'message' => 'No se pudo salir de la base.',
        ];
    }
}



    /**
     * FORCE LEAVE automático si el driver se alejó del radio permitido.
     * Devuelve null si NO se salió; o un arreglo con datos si lo sacamos.
     */
    public static function autoLeaveIfFar(
        int $tenantId,
        int $driverId,
        ?int $standId
    ): ?array {
        // Detectar stand actual si no viene
        if (!$standId) {
          $standId = DB::table('taxi_stand_queue')
    ->where('tenant_id', $tenantId)
    ->where('driver_id', $driverId)
    ->where('active_key', 1)
    ->whereIn('status', ['en_cola','saltado'])
    ->orderByDesc('id')
    ->value('stand_id');

        }

        if (!$standId) {
            return null;
        }

        $stand = DB::table('taxi_stands')
            ->where('tenant_id', $tenantId)
            ->where('id', $standId)
            ->where('activo', 1)
            ->first();

        if (!$stand) {
            return null;
        }

        $driver = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('id', $driverId)
            ->select('last_lat', 'last_lng')
            ->first();

       $freshSec = 120; // o desde settings si quieres
    $loc = self::getFreshLocation($tenantId, $driverId, $freshSec);

    if (!$loc) {
        return [
            'ok' => false,
            'message' => "Ubicación no disponible o desactualizada. Mantén la app enviando ping y reintenta.",
        ];
    }

    $distKm = self::haversineKm(
        (float)$stand->latitud,
        (float)$stand->longitud,
        $loc['lat'],
        $loc['lng']
    );

        $settings = DB::table('dispatch_settings')
            ->where('tenant_id', $tenantId)
            ->first();

        $radiusKm = 0.2;
        if ($settings) {
            $radiusKm = $settings->stand_radius_km
                ?? $settings->auto_dispatch_radius_km
                ?? 0.2;
        }

        $distKm = self::haversineKm(
            (float) $stand->latitud,
            (float) $stand->longitud,
            (float) $driver->last_lat,
            (float) $driver->last_lng
        );

        // Sigue dentro del radio → no hacemos nada
        if ($distKm <= $radiusKm) {
            return null;
        }

        // Se alejó → lo sacamos de la cola con el SP estándar
        DB::statement(
            'CALL sp_queue_leave_stand_v1(?, ?, ?, ?)',
            [$tenantId, $standId, $driverId, 'salio']
        );

        return [
            'stand_id'   => (int) $stand->id,
            'stand_name' => $stand->nombre,
            'dist_km'    => $distKm,
            'radius_km'  => $radiusKm,
            'reason'     => 'out_of_radius',
        ];
    }

    /**
     * Status de cola:
     *  - si NO envías stand_id → detecta la base actual del driver.
     *  - aplica autoLeaveIfFar (force-leave) si ya se alejó del paradero.
     *  - saca plate/economico desde driver_shifts + vehicles.
     */
    public static function status(
        int $tenantId,
        int $driverId,
        ?int $standId
    ): array {
        // 1) FORCE LEAVE si el driver se alejó pero sigue en cola
        $autoLeave = self::autoLeaveIfFar($tenantId, $driverId, $standId);

        if ($autoLeave && $autoLeave['reason'] === 'out_of_radius') {
           $queueCount = DB::table('taxi_stand_queue')
    ->where('tenant_id', $tenantId)
    ->where('stand_id', $autoLeave['stand_id'])
    ->whereIn('status', ['en_cola','saltado'])   // <-- aquí
    ->count();
            return [
                'ok'          => true,
                'auto_left'   => true,
                'reason'      => $autoLeave['reason'],
                'in_queue'    => false,
                'stand_id'    => $autoLeave['stand_id'],
                'stand_name'  => $autoLeave['stand_name'],
                'my_position' => null,
                'ahead_count' => null,
                'queue_count' => $queueCount,
                'queue'       => [],
                'dist_km'     => $autoLeave['dist_km'],
                'radius_km'   => $autoLeave['radius_km'],
            ];
        }

        // 2) Si no nos salimos, usamos standId detectado (puede venir de la cola)
        if (!$standId) {
           $standId = DB::table('taxi_stand_queue')
    ->where('tenant_id', $tenantId)
    ->where('driver_id', $driverId)
    ->whereIn('status', ['en_cola','saltado'])   // <-- aquí
    ->orderByDesc('id')
    ->value('stand_id');

        }

        if (!$standId) {
            return [
                'ok'          => true,
                'in_queue'    => false,
                'stand_id'    => null,
                'stand_name'  => null,
                'my_position' => null,
                'ahead_count' => null,
                'queue_count' => 0,
                'queue'       => [],
            ];
        }

        $stand = DB::table('taxi_stands')
            ->where('tenant_id', $tenantId)
            ->where('id', $standId)
            ->first();

        if (!$stand) {
            return [
                'ok'          => false,
                'in_queue'    => false,
                'stand_id'    => $standId,
                'stand_name'  => null,
                'my_position' => null,
                'ahead_count' => null,
                'queue_count' => 0,
                'queue'       => [],
            ];
        }

        // Cola + vehículo:
        // taxi_stand_queue → drivers → driver_shifts (abierto) → vehicles
      $queue = DB::table('taxi_stand_queue as q')
    ->join('drivers as d', function ($j) use ($tenantId) {
        $j->on('d.id', '=', 'q.driver_id')
          ->where('d.tenant_id', '=', $tenantId);
    })
    ->leftJoin('driver_shifts as s', function ($j) use ($tenantId) {
        $j->on('s.driver_id', '=', 'd.id')
          ->where('s.tenant_id', '=', $tenantId)
          ->where('s.status', '=', 'abierto');
    })
    ->leftJoin('vehicles as v', function ($j) use ($tenantId) {
        $j->on('v.id', '=', 's.vehicle_id')
          ->where('v.tenant_id', '=', $tenantId);
    })
    ->where('q.tenant_id', $tenantId)
    ->where('q.stand_id', $standId)
    ->where('q.active_key', 1)
    ->whereIn('q.status', ['en_cola','saltado'])
    ->orderBy('q.position')
    ->get([
        'q.driver_id',
        'q.position',
        'q.status as driver_status',
        'd.last_lat',
        'd.last_lng',
        'v.economico',
        'v.plate',
    ]);


        $myPos = null;
        foreach ($queue as $item) {
            if ((int) $item->driver_id === $driverId) {
                $myPos = (int) $item->position;
                break;
            }
        }

        $ahead = $myPos
            ? $queue->where('position', '<', $myPos)->count()
            : null;

        return [
            'ok'          => true,
            'in_queue'    => $myPos !== null,
            'stand_id'    => $standId,
            'stand_name'  => $stand->nombre,
            'my_position' => $myPos,
            'ahead_count' => $ahead,
            'queue_count' => $queue->count(),
            'queue'       => $queue->map(function ($item) {
                return [
                    'driver_id'     => (int) $item->driver_id,
                    'position'      => (int) $item->position,
                    'driver_status' => $item->driver_status, // 'en_cola'
                    'last_lat'      => $item->last_lat,
                    'last_lng'      => $item->last_lng,
                    'economico'     => $item->economico,     // viene de vehicles (shift abierto)
                    'plate'         => $item->plate,         // viene de vehicles (shift abierto)
                ];
            })->values(),
        ];
    }

    private static function haversineKm(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }


    private static function getFreshLocation(int $tenantId, int $driverId, int $freshSec = 120): ?array
{
    $loc = DB::table('driver_locations')   // <-- cambia a 'locations' si así se llama
        ->where('tenant_id', $tenantId)
        ->where('driver_id', $driverId)
        ->orderByDesc('id')
        ->first(['lat','lng','created_at']);

    if (!$loc || $loc->lat === null || $loc->lng === null) {
        return null;
    }

    $age = now()->diffInSeconds($loc->created_at);
    if ($age > $freshSec) {
        return null; // ubicación vieja = no permitimos join
    }

    return [
        'lat' => (float)$loc->lat,
        'lng' => (float)$loc->lng,
        'age_sec' => (int)$age,
    ];
}




}
