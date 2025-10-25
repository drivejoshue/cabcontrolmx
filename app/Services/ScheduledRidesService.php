<?php // app/Services/ScheduledRidesService.php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ScheduledRidesService {
    public static function fireDue(int $tenantId, int $windowAheadSec = 0): int {
        $tenantTz = DB::table('tenants')->where('id',$tenantId)->value('timezone')
                 ?: config('app.timezone','UTC');

        $now   = Carbon::now($tenantTz);
        $limit = $windowAheadSec > 0 ? $now->copy()->addSeconds($windowAheadSec) : $now;

        $due = DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', $limit->toDateTimeString())
            ->orderBy('id')
            ->limit(100)
            ->get();

        $count = 0;
        foreach ($due as $r) {
            if ($r->driver_id) {
                DB::table('rides')
                  ->where('tenant_id',$tenantId)->where('id',$r->id)
                  ->update(['status'=>'accepted', 'accepted_at'=>$now, 'updated_at'=>$now]);
            } else {
                AutoDispatchService::kickoff(
                    tenantId: $tenantId,
                    rideId:   (int)$r->id,
                    lat:      (float)$r->origin_lat,
                    lng:      (float)$r->origin_lng,
                    km:       AutoDispatchService::settings($tenantId)->radius_km ?? 5,
                    expires:  AutoDispatchService::settings($tenantId)->expires_s ?? 45,
                    limitN:   AutoDispatchService::settings($tenantId)->limit_n  ?? 8,
                    autoAssignIfSingle: (bool)(AutoDispatchService::settings($tenantId)->auto_assign_if_single ?? false)
                );
                DB::table('rides')
                  ->where('tenant_id',$tenantId)->where('id',$r->id)
                  ->update(['updated_at'=>$now]); // marca actividad
            }
            $count++;
        }
        return $count;
    }
}
