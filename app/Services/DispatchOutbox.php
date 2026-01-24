<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DispatchOutbox
{
    /**
     * Encola un evento offer.new para ser procesado asíncronamente
     */
    public static function enqueueOfferNew(int $tenantId, int $offerId, int $rideId, int $driverId): void
    {
        try {
            // Generar dedupe_key único
            $dedupeKey = "offer_new:{$tenantId}:{$offerId}";
            
            // Verificar si ya existe en outbox (usando dedupe_key)
            $exists = DB::table('dispatch_outbox')
                ->where('dedupe_key', $dedupeKey)
                ->whereIn('status', ['PENDING', 'PROCESSING', 'FAILED'])
                ->exists();

            if ($exists) {
                Log::debug('DispatchOutbox::enqueueOfferNew - Already enqueued', [
                    'tenant_id' => $tenantId,
                    'offer_id'  => $offerId,
                    'dedupe_key' => $dedupeKey,
                ]);
                return;
            }

            DB::table('dispatch_outbox')->insert([
                'tenant_id'    => $tenantId,
                'topic'        => 'offer.new',
                'offer_id'     => $offerId,
                'ride_id'      => $rideId,
                'driver_id'    => $driverId,
                'dedupe_key'   => $dedupeKey,  // ← CAMPO CRÍTICO FALTANTE
                'status'       => 'PENDING',
                'attempts'     => 0,
                'last_error'   => null,
                'locked_at'    => null,
                'locked_by'    => null,
                'available_at' => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            
            Log::debug('DispatchOutbox::enqueueOfferNew', [
                'tenant_id' => $tenantId,
                'offer_id'  => $offerId,
                'ride_id'   => $rideId,
                'driver_id' => $driverId,
                'dedupe_key' => $dedupeKey,
            ]);
        } catch (\Throwable $e) {
            Log::error('DispatchOutbox::enqueueOfferNew failed', [
                'tenant_id' => $tenantId,
                'offer_id'  => $offerId,
                'error'     => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Encola otros tipos de eventos
     */
    public static function enqueue(string $topic, array $data): void
    {
        try {
            // Generar dedupe_key basado en topic y datos
            $dedupeKey = self::generateDedupeKey($topic, $data);
            
            DB::table('dispatch_outbox')->insert([
                'tenant_id'    => $data['tenant_id'] ?? null,
                'topic'        => $topic,
                'offer_id'     => $data['offer_id'] ?? null,
                'ride_id'      => $data['ride_id'] ?? null,
                'driver_id'    => $data['driver_id'] ?? null,
                'payload'      => json_encode($data),
                'dedupe_key'   => $dedupeKey,  // ← NO OLVIDAR
                'status'       => 'PENDING',
                'attempts'     => 0,
                'last_error'   => null,
                'locked_at'    => null,
                'locked_by'    => null,
                'available_at' => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('DispatchOutbox::enqueue failed', [
                'topic' => $topic,
                'data'  => $data,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Genera una dedupe_key única basada en topic y datos
     */
    private static function generateDedupeKey(string $topic, array $data): string
    {
        switch ($topic) {
            case 'offer.new':
                $tenantId = $data['tenant_id'] ?? 0;
                $offerId = $data['offer_id'] ?? 0;
                return "offer_new:{$tenantId}:{$offerId}";
                
            case 'offer.update':
                $tenantId = $data['tenant_id'] ?? 0;
                $offerId = $data['offer_id'] ?? 0;
                return "offer_update:{$tenantId}:{$offerId}:" . ($data['status'] ?? 'unknown');
                
            case 'ride.status':
                $tenantId = $data['tenant_id'] ?? 0;
                $rideId = $data['ride_id'] ?? 0;
                return "ride_status:{$tenantId}:{$rideId}:" . ($data['status'] ?? 'unknown');
                
            default:
                return $topic . ':' . md5(json_encode($data));
        }
    }
}