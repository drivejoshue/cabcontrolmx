<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\OfferBroadcaster;

class ExpireOffers extends Command
{
    protected $signature = 'offers:expire';
    protected $description = 'Marca como expiradas las ofertas vencidas y emite evento';

    public function handle()
    {
        $now = now();

        $rows = DB::table('ride_offers')
            ->where('status','offered')
            ->whereNotNull('expires_at')
            ->where('expires_at','<',$now)
            ->limit(500)
            ->get(['id','tenant_id','driver_id','ride_id']);

        foreach ($rows as $r) {
            DB::table('ride_offers')->where('id',$r->id)->update([
                'status'=>'expired','responded_at'=>$now,'updated_at'=>$now
            ]);
            OfferBroadcaster::emitStatus((int)$r->tenant_id,(int)$r->driver_id,(int)$r->ride_id,(int)$r->id,'expired');
        }
        $this->info('Expiradas: '.$rows->count());
        return 0;
    }
}
