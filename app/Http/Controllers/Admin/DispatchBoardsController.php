<?php 
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class DispatchBoardsController extends Controller
{
    public function index(Request $req)
    {
      $tenantId = (int)($req->header('X-Tenant-ID') ?? null);

      // Rides “de tablero”: últimos N en estados de interés
      $rides = DB::table('rides')
        ->where('tenant_id',$tenantId)
        ->whereIn('status',['requested','offered','accepted']) // activos de la parte superior
        ->orWhereNotNull('scheduled_for')                       // programados
        ->orderByDesc('id')
        ->limit(80)
        ->get();

      // Activos “de abajo” (en curso)
      $active = DB::table('rides')
        ->where('tenant_id',$tenantId)
        ->whereIn('status',['accepted','en_route','arrived','on_board'])
        ->orderByDesc('id')
        ->limit(40)
        ->get();

      // Colas / Bases (ajusta a tu estructura real)
      $queues = DB::table('stands as s')
        ->leftJoin('stand_queue as q', 'q.stand_id', '=', 's.id') // o la vista que uses
        ->where('s.tenant_id',$tenantId)
        ->selectRaw('s.id, s.name as stand_name, COUNT(q.driver_id) as count')
        ->groupBy('s.id','s.name')
        ->get();

      return response()->json([
        'rides'  => $rides,
        'active' => $active,
        'queues' => $queues,
      ]);
    }
}
