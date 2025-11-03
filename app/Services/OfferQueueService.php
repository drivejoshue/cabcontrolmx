<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class OfferQueueService
{
    /** Intenta poner en cola una oferta para un driver respetando max_queue. */
    public static function enqueueDriver(int $tenantId, int $rideId, int $driverId, ?string $reason = null): bool
    {
        $cfg = \App\Services\AutoDispatchService::settings($tenantId);
        $max = max(0, (int)$cfg->max_queue);
        if ($max === 0) return false;

        // cupo actual
        $count = DB::table('ride_offers')
            ->where('driver_id',$driverId)
            ->where('status','queued')
            ->count();

        if ($count >= $max) return false;

        // ¿existe offered viva para este ride/driver? → muévela a queued
        $off = DB::table('ride_offers')
            ->where('tenant_id',$tenantId)
            ->where('ride_id',$rideId)
            ->where('driver_id',$driverId)
            ->where('status','offered')
            ->orderByDesc('id')
            ->first();

        if (!$off) return false;

        $pos = ($count + 1);
        DB::table('ride_offers')
            ->where('id',$off->id)
            ->update([
                'status'          => 'queued',
                'queued_at'       => now(),
                'queued_position' => $pos,
                'queued_reason'   => $reason ? substr($reason,0,32) : null,
                'updated_at'      => now(),
            ]);

        return true;
    }
}