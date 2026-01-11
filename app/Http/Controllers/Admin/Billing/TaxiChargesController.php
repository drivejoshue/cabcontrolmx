<?php
namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantTaxiCharge;
use App\Models\TenantTaxiReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;


class TaxiChargesController extends Controller
{
  private function tenantId(): int {
    $tid = Auth::user()->tenant_id ?? null;
    if (!$tid) abort(403,'Usuario sin tenant asignado');
    return (int)$tid;
  }

  private function tenantTz(int $tenantId): string {
    $tz = DB::table('tenants')->where('id',$tenantId)->value('timezone');
    return $tz ?: config('app.timezone','America/Mexico_City');
  }

  private function periodBounds(string $periodType, ?string $anchorDate, string $tz): array
  {
    $anchor = $anchorDate ? Carbon::parse($anchorDate, $tz) : Carbon::now($tz);

    if ($periodType === 'weekly') {
      $start = $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
      $end   = $anchor->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
      return [$start, $end];
    }

    if ($periodType === 'biweekly') {
      // quincena simple: 1-15 / 16-fin
      $day = (int)$anchor->format('d');
      if ($day <= 15) {
        $start = $anchor->copy()->startOfMonth()->startOfDay();
        $end   = $anchor->copy()->startOfMonth()->addDays(14)->endOfDay();
      } else {
        $start = $anchor->copy()->startOfMonth()->addDays(15)->startOfDay();
        $end   = $anchor->copy()->endOfMonth()->endOfDay();
      }
      return [$start, $end];
    }

    // monthly
    $start = $anchor->copy()->startOfMonth()->startOfDay();
    $end   = $anchor->copy()->endOfMonth()->endOfDay();
    return [$start, $end];
  }

  public function index(Request $r)
  {
    $tenantId = $this->tenantId();
    $tz = $this->tenantTz($tenantId);

    $status = $r->input('status','');
    $periodType = $r->input('period_type','weekly');
    if (!in_array($periodType, ['weekly','biweekly','monthly'], true)) $periodType = 'weekly';

    [$pStartDT, $pEndDT] = $this->periodBounds($periodType, $r->input('anchor_date'), $tz);
    $pStart = $pStartDT->toDateString();
    $pEnd   = $pEndDT->toDateString();

    $chargesQ = DB::table('tenant_taxi_charges as c')
      ->leftJoin('vehicles as v','c.vehicle_id','=','v.id')
      ->leftJoin('drivers as d','c.driver_id','=','d.id')
      ->leftJoin('tenant_taxi_receipts as rc','rc.charge_id','=','c.id')
      ->where('c.tenant_id', $tenantId)
      ->where('c.period_type', $periodType)
      ->where('c.period_start', $pStart)
      ->where('c.period_end', $pEnd)
      ->when($status !== '', fn($q)=>$q->where('c.status',$status))
      ->select(
        'c.*',
        'v.economico as vehicle_economico','v.plate as vehicle_plate','v.brand as vehicle_brand','v.model as vehicle_model',
        'd.name as driver_name',
        'rc.id as receipt_id','rc.receipt_number'
      )
      ->orderByRaw("FIELD(c.status,'pending','paid','canceled')")
      ->orderByDesc('c.amount');

    $charges = $chargesQ->paginate(25)->withQueryString();

    // Simulación comisión: ingreso real del periodo por VEHICLE (tu cuota es por taxi)
    $grossByVehicle = DB::table('rides as r')
      ->where('r.tenant_id', $tenantId)
      ->where('r.status','finished')
      ->whereNotNull('r.vehicle_id')
      ->whereBetween(DB::raw("DATE(COALESCE(r.finished_at, r.requested_at, r.created_at))"), [$pStart, $pEnd])
      ->groupBy('r.vehicle_id')
      ->selectRaw("r.vehicle_id")
      ->selectRaw("SUM(COALESCE(r.agreed_amount, r.total_amount, r.quoted_amount, 0)) as gross")
      ->pluck('gross','vehicle_id');

    $commissionPercent = (float) DB::table('tenants')->where('id',$tenantId)->value('commission_percent');
    if ($commissionPercent <= 0) $commissionPercent = 10.0; // default informativo

    return view('admin.billing.taxi_charges.index', compact(
      'tenantId','tz','periodType','pStart','pEnd','charges','grossByVehicle','commissionPercent'
    ));
  }

  public function generate(Request $r)
  {
    $tenantId = $this->tenantId();
    $tz = $this->tenantTz($tenantId);

    $data = $r->validate([
      'period_type' => ['required', Rule::in(['weekly','biweekly','monthly'])],
      'anchor_date' => ['nullable','date'],
    ]);

    [$pStartDT, $pEndDT] = $this->periodBounds($data['period_type'], $data['anchor_date'] ?? null, $tz);
    $pStart = $pStartDT->toDateString();
    $pEnd   = $pEndDT->toDateString();

    $fees = DB::table('tenant_taxi_fees')
      ->where('tenant_id', $tenantId)
      ->where('active', 1)
      ->where('period_type', $data['period_type'])
      ->get();

    $created = 0;
    $skipped = 0;

    foreach ($fees as $f) {
      // Idempotente por unique index
      $exists = DB::table('tenant_taxi_charges')
        ->where('tenant_id',$tenantId)
        ->where('period_type',$data['period_type'])
        ->where('period_start',$pStart)
        ->where('period_end',$pEnd)
        ->where('vehicle_id',$f->vehicle_id)
        ->where('driver_id',$f->driver_id)
        ->exists();

      if ($exists) { $skipped++; continue; }

      DB::table('tenant_taxi_charges')->insert([
        'tenant_id'    => $tenantId,
        'fee_id'       => $f->id,
        'vehicle_id'   => $f->vehicle_id,
        'driver_id'    => $f->driver_id,
        'period_type'  => $data['period_type'],
        'period_start' => $pStart,
        'period_end'   => $pEnd,
        'amount'       => $f->amount,
        'status'       => 'pending',
        'generated_at' => Carbon::now($tz)->toDateTimeString(),
        'generated_by' => Auth::id(),
        'created_at'   => now(),
        'updated_at'   => now(),
      ]);
      $created++;
    }

    return back()->with('ok', "Cobros generados: {$created}. Omitidos (ya existían): {$skipped}.");
  }

  public function markPaid(Request $r, int $charge)
  {
    $tenantId = $this->tenantId();
    $tz = $this->tenantTz($tenantId);

    $data = $r->validate([
      'notes' => ['nullable','string','max:2000'],
    ]);

    $c = TenantTaxiCharge::where('tenant_id',$tenantId)->findOrFail($charge);

    if ($c->status === 'paid') {
      return back()->with('ok','Ya estaba marcado como pagado');
    }

    $c->status  = 'paid';
    $c->paid_at = Carbon::now($tz);
    $c->paid_by = Auth::id();
    if (isset($data['notes'])) $c->notes = $data['notes'];
    $c->save();

    return back()->with('ok','Cobro marcado como pagado');
  }

  public function cancel(Request $r, int $charge)
  {
    $tenantId = $this->tenantId();
    $c = TenantTaxiCharge::where('tenant_id',$tenantId)->findOrFail($charge);

    if ($c->status === 'canceled') {
      return back()->with('ok','Ya estaba cancelado');
    }

    $c->status = 'canceled';
    $c->save();

    return back()->with('ok','Cobro cancelado');
  }

  private function nextReceiptNumber(int $tenantId, string $tz): string
  {
    $year = Carbon::now($tz)->format('Y');
    $prefix = "RC-{$year}-";

    $last = DB::table('tenant_taxi_receipts')
      ->where('tenant_id',$tenantId)
      ->where('receipt_number','like',$prefix.'%')
      ->orderByDesc('id')
      ->value('receipt_number');

    $n = 0;
    if ($last && preg_match('/RC-\d{4}-(\d+)/', $last, $m)) {
      $n = (int)$m[1];
    }
    $n++;

    return $prefix . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
  }

  public function issueReceipt(Request $r, int $charge)
  {
    $tenantId = $this->tenantId();
    $tz = $this->tenantTz($tenantId);

    $c = TenantTaxiCharge::where('tenant_id',$tenantId)->findOrFail($charge);

    $existing = TenantTaxiReceipt::where('tenant_id',$tenantId)->where('charge_id',$c->id)->first();
    if ($existing) {
      return redirect()->route('admin.taxi_receipts.show', $existing->id);
    }

    $receipt = TenantTaxiReceipt::create([
      'tenant_id'       => $tenantId,
      'charge_id'       => $c->id,
      'receipt_number'  => $this->nextReceiptNumber($tenantId, $tz),
      'issued_at'       => Carbon::now($tz),
      'issued_by'       => Auth::id(),
    ]);

    return redirect()->route('admin.taxi_receipts.show', $receipt->id);
  }

  public function receiptShow(int $receipt)
  {
    $tenantId = $this->tenantId();

    $rc = DB::table('tenant_taxi_receipts as rc')
      ->join('tenant_taxi_charges as c','c.id','=','rc.charge_id')
      ->leftJoin('vehicles as v','c.vehicle_id','=','v.id')
      ->leftJoin('drivers as d','c.driver_id','=','d.id')
      ->join('tenants as t','t.id','=','rc.tenant_id')
      ->where('rc.tenant_id',$tenantId)
      ->where('rc.id',$receipt)
      ->select(
        'rc.*',
        't.name as tenant_name','t.public_phone as tenant_phone','t.public_city as tenant_city',
        'c.period_type','c.period_start','c.period_end','c.amount','c.status','c.paid_at','c.notes',
        'v.economico as vehicle_economico','v.plate as vehicle_plate','v.brand as vehicle_brand','v.model as vehicle_model',
        'd.name as driver_name','d.phone as driver_phone'
      )
      ->first();

    if (!$rc) abort(404);

    return view('admin.billing.taxi_receipts.show', compact('rc'));
  }

  public function export(Request $r): StreamedResponse
{
  $tenantId = $this->tenantId();
  $tz = $this->tenantTz($tenantId);

  $status = $r->input('status','');
  $periodType = $r->input('period_type','weekly');
  if (!in_array($periodType, ['weekly','biweekly','monthly'], true)) $periodType = 'weekly';

  [$pStartDT, $pEndDT] = $this->periodBounds($periodType, $r->input('anchor_date'), $tz);
  $pStart = $pStartDT->toDateString();
  $pEnd   = $pEndDT->toDateString();

  $rows = DB::table('tenant_taxi_charges as c')
    ->leftJoin('vehicles as v','c.vehicle_id','=','v.id')
    ->leftJoin('drivers as d','c.driver_id','=','d.id')
    ->leftJoin('tenant_taxi_receipts as rc','rc.charge_id','=','c.id')
    ->where('c.tenant_id', $tenantId)
    ->where('c.period_type', $periodType)
    ->where('c.period_start', $pStart)
    ->where('c.period_end', $pEnd)
    ->when($status !== '', fn($q)=>$q->where('c.status',$status))
    ->select(
      'c.id','c.status','c.amount','c.period_type','c.period_start','c.period_end','c.generated_at','c.paid_at',
      'v.economico as vehicle_economico','v.plate as vehicle_plate',
      'd.name as driver_name',
      'rc.receipt_number'
    )
    ->orderByRaw("FIELD(c.status,'pending','paid','canceled')")
    ->orderByDesc('c.amount')
    ->get();

  $filename = "taxi_charges_tenant_{$tenantId}_{$periodType}_{$pStart}_{$pEnd}.csv";

  return response()->streamDownload(function() use ($rows) {
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, [
      'estado','monto','periodo','inicio','fin',
      'taxi_economico','placa','conductor',
      'generado_en','pagado_en','recibo'
    ]);

    foreach ($rows as $r) {
      fputcsv($out, [
       // $r->id,
        $r->status,
        (float)$r->amount,
        $r->period_type,
        $r->period_start,
        $r->period_end,
        $r->vehicle_economico,
        $r->vehicle_plate,
        $r->driver_name,
        $r->generated_at,
        $r->paid_at,
        $r->receipt_number,
      ]);
    }
    fclose($out);
  }, $filename, [
    'Content-Type' => 'text/csv; charset=UTF-8',
  ]);
}

public function purge(Request $r)
{
  $tenantId = $this->tenantId();

  $data = $r->validate([
    'confirm_text'  => ['required','string','max:50'],
    'confirm_check' => ['nullable'], // checkbox opcional
  ]);

  // si quieres forzar checkbox, descomenta:
  // if (empty($data['confirm_check'])) {
  //   return back()->with('warn','Debes marcar la casilla de confirmación.');
  // }

  $txt = trim((string)$data['confirm_text']);
  if (mb_strtoupper($txt) !== 'ACEPTAR') {
    return back()->with('warn','Confirmación incorrecta. Escribe ACEPTAR para vaciar el historial.');
  }

  [$chargesDeleted, $receiptsDeleted] = DB::transaction(function() use ($tenantId) {
    // Recibos primero
    $receiptsDeleted = DB::table('tenant_taxi_receipts')
      ->where('tenant_id', $tenantId)
      ->delete();

    // Luego cobros
    $chargesDeleted = DB::table('tenant_taxi_charges')
      ->where('tenant_id', $tenantId)
      ->delete();

    return [$chargesDeleted, $receiptsDeleted];
  });

  if (($chargesDeleted + $receiptsDeleted) === 0) {
    return back()->with('warn','No había registros para borrar (o no correspondían a este tenant).');
  }

  return back()->with('ok',"Historial vaciado. Cobros: {$chargesDeleted}. Recibos: {$receiptsDeleted}.");
}



}
