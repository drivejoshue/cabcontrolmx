<?php
// app/Http/Controllers/API/DispatchController.php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Geo\GoogleMapsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DispatchController extends Controller
{
    public function __construct(private GoogleMapsService $geo) {}

    public function quote(Request $r){
        $v = $r->validate([
            'origin.label'=>'nullable|string','origin.lat'=>'required|numeric','origin.lng'=>'required|numeric',
            'destination.label'=>'nullable|string','destination.lat'=>'required|numeric','destination.lng'=>'required|numeric',
            'pax'=>'nullable|integer|min:1|max:6'
        ]);
        $rt = $this->geo->route($v['origin'],$v['destination']);
        $price = $this->tarifar($rt['distance_m'],$rt['duration_s']);
        return ['ok'=>true,'distance_m'=>$rt['distance_m'],'duration_s'=>$rt['duration_s'],'price'=>$price];
    }

    public function store(Request $r){
        $tenantId = auth()->user()->tenant_id ?? 1;
        $v = $r->validate([
            'origin.label'=>'required|string','origin.lat'=>'required|numeric','origin.lng'=>'required|numeric',
            'destination.label'=>'required|string','destination.lat'=>'required|numeric','destination.lng'=>'required|numeric',
            'pax'=>'nullable|integer|min:1|max:6',
            'notes'=>'nullable|string|max:255',
            'scheduled_at'=>'nullable|date',
            'assign.driver_id'=>'nullable|integer',
        ]);

        $rt = $this->geo->route($v['origin'],$v['destination']);
        $price = $this->tarifar($rt['distance_m'],$rt['duration_s']);

        $id = DB::table('services')->insertGetId([
            'tenant_id'=>$tenantId,
            'status'=> empty($v['assign']['driver_id']) ? 'offered' : 'accepted',
            'origin_label'=>$v['origin']['label'],'origin_lat'=>$v['origin']['lat'],'origin_lng'=>$v['origin']['lng'],
            'dest_label'=>$v['destination']['label'],'dest_lat'=>$v['destination']['lat'],'dest_lng'=>$v['destination']['lng'],
            'distance_m'=>$rt['distance_m'],'eta_s'=>$rt['duration_s'],'price_total'=>$price,
            'driver_id'=> $v['assign']['driver_id'] ?? null,
            'requested_at'=> now(),'scheduled_at'=>$v['scheduled_at'] ?? null,
            'created_at'=>now(),'updated_at'=>now()
        ]);

        // si es offered → crear ofertas a drivers cercanos (pendiente: algoritmo)
        // si es accepted directo → emitir ServiceUpdated al panel y al driver.

        return ['ok'=>true,'service_id'=>$id,'status'=> empty($v['assign']['driver_id'])?'offered':'accepted'];
    }

    private function tarifar(int $m, int $s): float {
        $base = 25; $km = 8;    // valores de prueba
        return round($base + ($m/1000.0)*$km, 2);
    }
}
