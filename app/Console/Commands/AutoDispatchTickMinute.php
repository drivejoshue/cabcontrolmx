<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\AutoDispatchService;
use App\Services\DispatchSettingsService;

class AutoDispatchTickMinute extends Command
{
    protected $signature = 'orbanamx:autodispatch-tick {--tenant= : Forzar tenant_id} {--limit=50 : Máx rides por corrida}';
    protected $description = 'Relanza waves cada minuto para rides pending/requested que estén sin driver y sin offers vivas.';

    public function handle(): int
    {
        $onlyTenant = $this->option('tenant') ? (int)$this->option('tenant') : null;
        $limit = max(1, (int)$this->option('limit'));

        $tenantsQ = DB::table('tenants')->select('id');
        if ($onlyTenant) $tenantsQ->where('id', $onlyTenant);

        $tenants = $tenantsQ->get();

        $total = 0;

        foreach ($tenants as $t) {
            $tenantId = (int)$t->id;

            // settings
            $cfg = DispatchSettingsService::forTenant($tenantId);
            if (!($cfg->enabled ?? true)) {
                continue;
            }

            // rides candidatos (ajusta statuses a tu canon)
            $rides = DB::table('rides as r')
                ->where('r.tenant_id', $tenantId)
                ->whereNull('r.driver_id')
                ->whereIn('r.status', ['offered']) // ajusta si usas otros
                ->whereNotExists(function($q) use ($tenantId){
                    $q->select(DB::raw(1))
                      ->from('ride_offers as o')
                      ->whereColumn('o.ride_id','r.id')
                      ->where('o.tenant_id',$tenantId)
                      ->whereIn('o.status', ['offered','pending_passenger','accepted']);
                })
                ->orderByDesc('r.id')
                ->limit($limit)
                ->get(['r.id','r.origin_lat','r.origin_lng','r.status']);

            if ($rides->isEmpty()) {
                continue;
            }

            Log::info('AutoDispatchTickMinute.tenant', [
                'tenant_id' => $tenantId,
                'rides'     => $rides->count(),
                'cfg'       => [
                    'radius_km' => $cfg->radius_km ?? null,
                    'limit_n'   => $cfg->limit_n ?? null,
                    'expires_s' => $cfg->expires_s ?? null,
                ],
            ]);

            foreach ($rides as $r) {
                try {
                    $res = AutoDispatchService::kickoff(
                        tenantId: $tenantId,
                        rideId:   (int)$r->id,
                        lat:      (float)$r->origin_lat,
                        lng:      (float)$r->origin_lng,
                        km:       (float)($cfg->radius_km ?? 3.0),
                        expires:  (int)  ($cfg->expires_s ?? 180),
                        limitN:   (int)  ($cfg->limit_n ?? 8),
                        autoAssignIfSingle: (bool)($cfg->auto_assign_if_single ?? false)
                    );

                    $total++;

                    Log::info('AutoDispatchTickMinute.kickoff', [
                        'tenant_id' => $tenantId,
                        'ride_id'   => (int)$r->id,
                        'res'       => $res,
                    ]);

                } catch (\Throwable $e) {
                    Log::error('AutoDispatchTickMinute.kickoff_failed', [
                        'tenant_id' => $tenantId,
                        'ride_id'   => (int)$r->id,
                        'err'       => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("OK. kickoffs=$total");
        return 0;
    }
}
