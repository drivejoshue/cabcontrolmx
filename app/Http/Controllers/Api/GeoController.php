<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Geo\GoogleMapsService;
use Illuminate\Http\Request;

class GeoController extends Controller
{
    public function __construct(private GoogleMapsService $geo) {}

    public function geocode(Request $r){
        $q = trim($r->get('q',''));
        if(!$q){ return response()->json(['ok'=>false,'error'=>'q required'],422); }
        return ['ok'=>true,'results'=>$this->geo->geocode($q)];
    }

    public function route(Request $r){
        $v = $r->validate([
            'origin.lat'      => 'required|numeric',
            'origin.lng'      => 'required|numeric',
            'destination.lat' => 'required|numeric',
            'destination.lng' => 'required|numeric',
        ]);
        $o=$v['origin']; $d=$v['destination'];
        return ['ok'=>true] + $this->geo->route($o['lat'],$o['lng'],$d['lat'],$d['lng']);
    }
}
