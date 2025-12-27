<?php
// app/Http/Controllers/Admin/Billing/TaxiFeesController.php
namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\TenantTaxiFee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
      'vehicle_id'  => ['nullable','integer'],
      'driver_id'   => ['nullable','integer'],
      'period_type' => ['required', Rule::in(['weekly','biweekly','monthly'])],
      'amount'      => ['required','numeric','min:0'],
      'active'      => ['nullable'],
    ]);

    $data['tenant_id'] = $tenantId;
    $data['active'] = !empty($data['active']);

    // si id=0 => crear
    if ($id === 0) {
      TenantTaxiFee::create($data);
      return back()->with('ok','Cuota creada');
    }

    $fee = TenantTaxiFee::where('tenant_id',$tenantId)->findOrFail($id);
    $fee->update($data);

    return back()->with('ok','Cuota actualizada');
  }
}
