<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'drivers:watchdog', description: 'Marca offline y autocierra turnos huérfanos')]
class DriverWatchdog extends Command
{
    public function handle(): int
    {
        $offlineAfterSeconds = (int)($this->option('offline-after-seconds') ?? 120);
        $autoCloseMinutes    = (int)($this->option('autoclose-after-minutes') ?? 60);

        $now            = now();
        $cutOffOffline  = now()->subSeconds($offlineAfterSeconds);
        $cutOffAuto     = now()->subMinutes($autoCloseMinutes);

        // 1) Drivers offline si no hay ping reciente
        $aff1 = DB::table('drivers')
            ->where(function($q){ $q->whereNull('last_seen_at')->orWhere('status','!=','offline'); })
            ->where(function($q) use ($cutOffOffline){
                $q->whereNull('last_seen_at')->orWhere('last_seen_at','<',$cutOffOffline);
            })
            ->update(['status'=>'offline','updated_at'=>$now]);

        // 2) Autocerrar turnos abiertos sin pings
        $open = DB::table('driver_shifts as ds')
            ->join('drivers as d','d.id','=','ds.driver_id')
            ->whereNull('ds.ended_at')
            ->where(function($q) use ($cutOffAuto){
                $q->whereNull('d.last_seen_at')->orWhere('d.last_seen_at','<',$cutOffAuto);
            })
            ->select('ds.id','d.last_seen_at')->get();

        $aff2 = 0;
        foreach ($open as $s) {
            DB::table('driver_shifts')->where('id',$s->id)->update([
                'ended_at'   => $s->last_seen_at ?? $now,
                'status'     => 'closed',
                'updated_at' => $now,
            ]);
            $aff2++;
        }

        $this->info("Watchdog -> offline:$aff1, shifts_autoclosed:$aff2");
        return self::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addOption('offline-after-seconds', null, null, '120');
        $this->addOption('autoclose-after-minutes', null, null, '60');
    }
}
