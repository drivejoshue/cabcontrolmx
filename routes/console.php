<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\Admin\RideAdminController;
use Illuminate\Support\Facades\Schedule;

// Corre cada minuto
Schedule::call(function () {
    $now = now();
    $cutOffOffline = now()->subSeconds(120);
    $cutOffAuto    = now()->subMinutes(60);

    DB::table('drivers')
      ->where(function($q){ $q->whereNull('last_seen_at')->orWhere('status','!=','offline'); })
      ->where(function($q) use ($cutOffOffline){ $q->whereNull('last_seen_at')->orWhere('last_seen_at','<',$cutOffOffline); })
      ->update(['status'=>'offline','updated_at'=>$now]);

    $open = DB::table('driver_shifts as ds')
      ->join('drivers as d','d.id','=','ds.driver_id')
      ->whereNull('ds.ended_at')
      ->where(function($q) use ($cutOffAuto){ $q->whereNull('d.last_seen_at')->orWhere('d.last_seen_at','<',$cutOffAuto); })
      ->select('ds.id','d.last_seen_at')->get();

    foreach ($open as $s) {
        DB::table('driver_shifts')->where('id',$s->id)->update([
            'ended_at'=> $s->last_seen_at ?? $now, 'status'=>'closed', 'updated_at'=>$now
        ]);
    }
})->everyMinute();
