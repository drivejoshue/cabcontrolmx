<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\TenantTaxiFee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;



class TaxiFeesController extends Controller
{
  private function tenantId(): int {
    $tid = Auth::user()->tenant_id ?? null;
    if (!$tid) abort(403,'Usuario sin tenant asignado');
    return (int)$tid;
  }

  public function index(Request $r)
  {
    $tenantId = $this->tenantId();

    $vehicles = DB::table('vehicles')
      ->where('tenant_id', $tenantId)
      ->orderByRaw("COALESCE(economico,'') ASC, COALESCE(plate,'') ASC")
      ->get();

    $drivers = DB::table('drivers')
      ->where('tenant_id', $tenantId)
      ->orderBy('name')
      ->get();

    $fees = DB::table('tenant_taxi_fees as f')
      ->leftJoin('vehicles as v','f.vehicle_id','=','v.id')
      ->leftJoin('drivers as d','f.driver_id','=','d.id')
      ->where('f.tenant_id', $tenantId)
      ->select(
        'f.*',
        'v.economico as vehicle_economico','v.plate as vehicle_plate','v.brand as vehicle_brand','v.model as vehicle_model',
        'd.name as driver_name'
      )
      ->orderByDesc('f.active')
      ->orderBy('f.period_type')
      ->orderByRaw("COALESCE(v.economico,'') ASC")
      ->paginate(25)
      ->withQueryString();

    return view('admin.billing.taxi_fees.index', compact('vehicles','drivers','fees','tenantId'));
  }

public function update(Request $r, int $id)
{
  $tenantId = $this->tenantId();

  $data = $r->validate([
    'vehicle_id'  => [
      'nullable','integer',
      Rule::exists('vehicles','id')->where(fn($q)=>$q->where('tenant_id',$tenantId)),
    ],
    'driver_id'   => [
      'nullable','integer',
      Rule::exists('drivers','id')->where(fn($q)=>$q->where('tenant_id',$tenantId)),
    ],
    'period_type' => ['required', Rule::in(['weekly','biweekly','monthly'])],
    'amount'      => ['required','numeric','min:0'],
    'active'      => ['nullable'],
  ]);

  // Regla simple: al menos taxi o conductor (evita cuotas “vacías”)
  if (empty($data['vehicle_id']) && empty($data['driver_id'])) {
    return back()->with('warn', 'Selecciona al menos un Taxi o un Conductor.');
  }

  $data['tenant_id'] = $tenantId;
  $data['active'] = !empty($data['active']);

  // Helper: match por llave considerando NULL
  $matchQuery = function($q) use ($tenantId, $data) {
    $q->where('tenant_id', $tenantId)
      ->where('period_type', $data['period_type'])
      ->when(
        array_key_exists('vehicle_id', $data) && $data['vehicle_id'] !== null,
        fn($qq) => $qq->where('vehicle_id', $data['vehicle_id']),
        fn($qq) => $qq->whereNull('vehicle_id')
      )
      ->when(
        array_key_exists('driver_id', $data) && $data['driver_id'] !== null,
        fn($qq) => $qq->where('driver_id', $data['driver_id']),
        fn($qq) => $qq->whereNull('driver_id')
      );
  };

  // CREAR (id=0) => si existe, actualizar (anti duplicado)
  if ($id === 0) {
    $existing = TenantTaxiFee::query()->where($matchQuery)->first();

    if ($existing) {
      $existing->update($data);
      return back()->with('ok', 'La cuota ya existía; se actualizó.');
    }

    TenantTaxiFee::create($data);
    return back()->with('ok','Cuota creada');
  }

  // EDITAR (id!=0)
  $fee = TenantTaxiFee::where('tenant_id',$tenantId)->findOrFail($id);

  // Si al editar choca con otra cuota, hacemos MERGE:
  $collision = TenantTaxiFee::query()
    ->where($matchQuery)
    ->where('id','<>',$fee->id)
    ->first();

  if ($collision) {
    DB::transaction(function() use ($collision, $fee, $data) {
      $collision->update($data);
      $fee->delete();
    });

    return back()->with('ok','Cuota combinada: se evitó duplicado.');
  }

  $fee->update($data);
  return back()->with('ok','Cuota actualizada');
}


public function export(Request $r): StreamedResponse
{
  $tenantId = $this->tenantId();

  $rows = DB::table('tenant_taxi_fees as f')
    ->leftJoin('vehicles as v','f.vehicle_id','=','v.id')
    ->leftJoin('drivers as d','f.driver_id','=','d.id')
    ->where('f.tenant_id', $tenantId)
    ->select(
      'f.period_type','f.amount','f.active',
      'v.economico as vehicle_economico','v.plate as vehicle_plate','v.brand as vehicle_brand','v.model as vehicle_model',
      'd.name as driver_name'
    )
    ->orderByDesc('f.active')
    ->orderBy('f.period_type')
    ->orderByRaw("COALESCE(v.economico,'') ASC")
    ->get();

  $filename = "taxi_fees_tenant_{$tenantId}_" . now()->format('Ymd_His') . ".csv";

  return response()->streamDownload(function() use ($rows) {
    $out = fopen('php://output', 'w');
    // BOM UTF-8 para Excel
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, [
      'id','periodo','monto','activa',
      'taxi_economico','placa','marca','modelo',
      'conductor'
    ]);

    foreach ($rows as $r) {
      fputcsv($out, [
        $r->id,
        $r->period_type,
        (float)$r->amount,
        $r->active ? 'SI' : 'NO',
        $r->vehicle_economico,
        $r->vehicle_plate,
        $r->vehicle_brand,
        $r->vehicle_model,
        $r->driver_name,
      ]);
    }
    fclose($out);
  }, $filename, [
    'Content-Type' => 'text/csv; charset=UTF-8',
  ]);
}

}
