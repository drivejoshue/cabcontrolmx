<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\DispatchSettingsService;
use App\Services\OfferBroadcaster;
use App\Services\RideBroadcaster;

class AutoDispatchTickMinute extends Command
{
    protected $signature = 'orbanamx:autodispatch-tick {--tenant= : Forzar tenant_id} {--limit=50 : Máx rides por corrida}';
    protected $description = 'Tick de autodispatch + normalizador de bids (pending_passenger) vencidos.';

    public function handle(): int
    {
        $onlyTenant = $this->option('tenant') ? (int)$this->option('tenant') : null;

        $tenantsQ = DB::table('tenants')->select('id');
        if ($onlyTenant) $tenantsQ->where('id', $onlyTenant);

        $tenants = $tenantsQ->get();

        foreach ($tenants as $t) {
            $tenantId = (int) $t->id;

            // settings enabled
            $cfg = DispatchSettingsService::forTenant($tenantId);
            if (!($cfg->enabled ?? true)) {
                continue;
            }

            // 1) Tick principal (colas/waves)
            try {
                DB::statement('CALL sp_dispatch_track_tick_v1(?)', [$tenantId]);
            } catch (\Throwable $e) {
                Log::warning('autodispatch tick SP error', [
                    'tenant_id' => $tenantId,
                    'error'     => $e->getMessage(),
                ]);
                // seguimos con el siguiente tenant
                continue;
            }

            // 2) Normalizador de bids vencidos (throttle por tenant)
            //    Para no saturar si el tick corre muy seguido (p.ej. cada segundo).
            //    Ajusta 15 -> 10/30 según carga.
            $thKey = "tick:bid_expire:tenant:$tenantId";
            if (!Cache::add($thKey, 1, 2)) {
                continue;
            }

            $this->normalizeExpiredPassengerBids($tenantId);
        }

        $this->info('OK tick');
        return 0;
    }

    /**
     * Regresa offers pending_passenger -> offered cuando bid_expires_at ya venció.
     * Emite RT al driver para habilitar acciones (reofertar/aceptar).
     */
    private function normalizeExpiredPassengerBids(int $tenantId): void
    {
        $limit = 300;

        // Tomamos candidatos primero (para emitir RT después del update)
       $expired = DB::table('ride_offers')
  ->where('tenant_id', $tenantId)
  ->where('status', 'pending_passenger')
  ->whereNotNull('bid_expires_at')
  ->whereRaw('bid_expires_at < NOW()')   // ✅
  ->orderBy('bid_expires_at')
  ->limit($limit)
  ->get(['id', 'ride_id', 'driver_id', 'bid_seq']);


        if ($expired->isEmpty()) {
            return;
        }

        $ids = $expired->pluck('id')->all();

        // Update masivo
        DB::table('ride_offers')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $ids)
            ->update([
                'status'     => 'offered',
                 //'bid_expires_at' => null, 
                'updated_at' => now(),
            ]);

        // Emitir RT (fuera de TX; aquí ya está committed)
        foreach ($expired as $row) {
            try {
                // Driver: vuelve a offered => puede reofertar o aceptar
                OfferBroadcaster::emitStatus(
                    tenantId: $tenantId,
                    driverId: (int) $row->driver_id,
                    rideId:   (int) $row->ride_id,
                    offerId:  (int) $row->id,
                    status:   'offered'
                );

                // Opcional: Passenger/panel limpian UI si aún muestran el bid
                RideBroadcaster::update($tenantId, (int) $row->ride_id, 'requested', [
                    'phase'     => 'bidding_expired',
                    'offer_id'  => (int) $row->id,
                    'driver_id' => (int) $row->driver_id,
                    'bid_seq'   => (int) ($row->bid_seq ?? 0),
                ]);
            } catch (\Throwable $e) {
                Log::warning('normalizeExpiredPassengerBids emit error', [
                    'tenant_id' => $tenantId,
                    'offer_id'  => (int) $row->id,
                    'ride_id'   => (int) $row->ride_id,
                    'driver_id' => (int) $row->driver_id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        Log::info('normalizeExpiredPassengerBids OK', [
            'tenant_id' => $tenantId,
            'count'     => $expired->count(),
        ]);
    }
}
