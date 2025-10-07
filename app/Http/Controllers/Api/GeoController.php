<?php
// app/Http/Controllers/API/GeoController.php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Geo\GoogleMapsService;
use Illuminate\Http\Request;

class GeoController extends Controller {
    public function __construct(private GoogleMapsService $geo) {}

    public function geocode(Request $r){
        $q = trim($r->get('q',''));
        if(!$q) return response()->json(['ok'=>false,'error'=>'q required'],422);
        return ['ok'=>true,'results'=>$this->geo->geocode($q)];
    }
    public function reverse(Request $r){
        $lat=$r->float('lat'); $lng=$r->float('lng');
        if($lat===null||$lng===null) return response()->json(['ok'=>false,'error'=>'lat/lng required'],422);
        return ['ok'=>true,'result'=>$this->geo->reverse($lat,$lng)];
    }
    public function route(Request $r){
        $data = $r->validate([
            'origin.lat'=>'required|numeric','origin.lng'=>'required|numeric',
            'destination.lat'=>'required|numeric','destination.lng'=>'required|numeric',
        ]);
        $o=$data['origin']; $d=$data['destination'];
        return ['ok'=>true] + $this->geo->route($o,$d);
    }
}
