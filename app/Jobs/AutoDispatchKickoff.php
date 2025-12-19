<?php  
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class AutoDispatchKickoff implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $tenantId,
        public int $rideId,
        public float $lat,
        public float $lng,
        public float $radiusKm,
        public int $offerExpiresSec,
        public int $waveSize,
        public bool $autoAssignIfSingle
    ) {}

    public function handle()
    {
        // 0) abortar si el operador ya tocó el ride
        $ride = DB::table('rides')->where('tenant_id',$this->tenantId)->where('id',$this->rideId)->first();
        if (!$ride) return;
        if (in_array(strtoupper($ride->status), ['ACCEPTED','EN_ROUTE','ARRIVED','ON_BOARD','FINISHED','CANCELED'])) return;

        // 1) candidatos cerca (SP)
        $cand = DB::select('CALL sp_nearby_drivers(?,?,?,?)', [
            $this->tenantId, $this->lat, $this->lng, $this->radiusKm
        ]);

        if (count($cand) === 0) return;

        if ($this->autoAssignIfSingle && count($cand) === 1) {
            // crea oferta + acepta (mantiene auditoría y coherencia)
            $driverId = (int)($cand[0]->driver_id ?? $cand[0]->id ?? 0);
            if ($driverId > 0) {
                DB::select('CALL sp_create_offer_v2(?,?,?,?)', [
                    $this->tenantId, $this->rideId, $driverId, $this->offerExpiresSec
                ]);
                DB::select('SELECT LAST_INSERT_ID() AS oid'); // depende del conector; si no, busca la última offered
                // acepta (siempre vía SP)
                // si no tienes el offer_id, puedes resolverlo por ride+driver con otra consulta
                $offerId = DB::table('ride_offers')
                    ->where('tenant_id',$this->tenantId)
                    ->where('ride_id',$this->rideId)
                    ->where('driver_id',$driverId)
                    ->orderByDesc('id')->value('id');
                if ($offerId) DB::select('CALL sp_accept_offer_v7(?)', [$offerId]);
            }
            return;
        }

        // 2) ola a N (SP)
        DB::select('CALL sp_offer_wave_prio_v3(?,?,?,?,?)', [
            $this->tenantId, $this->rideId, $this->radiusKm, $this->waveSize, $this->offerExpiresSec
        ]);

        // (opcional) segundo intento diferido:
        // dispatch(new self(...))->delay(now()->addSeconds($this->offerExpiresSec + 5));
        // y antes de repetir: CALL sp_expire_offers_v2(tenant, ride)
    }
}
