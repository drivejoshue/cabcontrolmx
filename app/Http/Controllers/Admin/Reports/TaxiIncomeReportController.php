<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TaxiIncomeReportController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = (int) auth()->user()->tenant_id;

        // Defaults: mes actual (hora local app)
        $from = $request->input('from');
        $to   = $request->input('to');

        $fromDt = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth()->startOfDay();
        $toDt   = $to ? Carbon::parse($to)->addDay()->startOfDay() : now()->addDay()->startOfDay(); // to inclusivo

        $periodType = $request->input('period_type'); // weekly|biweekly|monthly|null
        $vehicleId  = $request->input('vehicle_id');
        $driverId   = $request->input('driver_id');

        // Base para KPIs/series: cargos del tenant
        $base = DB::table('tenant_taxi_charges as c')
            ->where('c.tenant_id', $tenantId);

        if ($periodType) $base->where('c.period_type', $periodType);
        if ($vehicleId)  $base->where('c.vehicle_id', (int) $vehicleId);
        if ($driverId)   $base->where('c.driver_id', (int) $driverId);

        // ========= KPIs =========
        $paidQ = (clone $base)
            ->where('c.status', 'paid')
            ->whereNotNull('c.paid_at')
            ->where('c.paid_at', '>=', $fromDt)
            ->where('c.paid_at', '<',  $toDt);

        $kpiPaidTotal = (float) $paidQ->sum('c.amount');
        $kpiPaidCount = (int) (clone $paidQ)->count();

        $pendingTotal = (float) (clone $base)->where('c.status', 'pending')->sum('c.amount');
        $canceledTotal = (float) (clone $base)->where('c.status', 'canceled')->sum('c.amount');

        // ========= Serie mensual (paid_at) =========
        $seriesMonthly = (clone $base)
            ->selectRaw("DATE_FORMAT(c.paid_at, '%Y-%m') as ym, SUM(c.amount) as total")
            ->where('c.status', 'paid')
            ->whereNotNull('c.paid_at')
            ->where('c.paid_at', '>=', $fromDt)
            ->where('c.paid_at', '<',  $toDt)
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        // ========= Selects enriquecidos (solo los que aparecen en charges del tenant) =========
        $vehiclesForSelect = DB::table('tenant_taxi_charges as c')
            ->join('vehicles as v', 'v.id', '=', 'c.vehicle_id')
            ->where('c.tenant_id', $tenantId)
            ->whereNotNull('c.vehicle_id')
            ->select([
                'v.id',
                'v.economico',
                'v.plate',
                'v.brand',
                'v.model',
                'v.type',
            ])
            ->distinct()
            ->orderByRaw("COALESCE(NULLIF(v.economico,''), v.plate, v.id)")
            ->get();

        $driversForSelect = DB::table('tenant_taxi_charges as c')
            ->join('drivers as d', 'd.id', '=', 'c.driver_id')
            ->where('c.tenant_id', $tenantId)
            ->whereNotNull('c.driver_id')
            ->select(['d.id', 'd.name', 'd.phone'])
            ->distinct()
            ->orderBy('d.name')
            ->get();

        // ========= Tabla detalle =========
        $rows = DB::table('tenant_taxi_charges as c')
            ->leftJoin('tenant_taxi_receipts as r', 'r.charge_id', '=', 'c.id')
            ->leftJoin('vehicles as v', 'v.id', '=', 'c.vehicle_id')
            ->leftJoin('drivers as d', 'd.id', '=', 'c.driver_id')
            ->where('c.tenant_id', $tenantId)
            ->when($periodType, fn($q) => $q->where('c.period_type', $periodType))
            ->when($vehicleId,  fn($q) => $q->where('c.vehicle_id', (int)$vehicleId))
            ->when($driverId,   fn($q) => $q->where('c.driver_id', (int)$driverId))
            ->where('c.status', 'paid')
            ->whereNotNull('c.paid_at')
            ->where('c.paid_at', '>=', $fromDt)
            ->where('c.paid_at', '<',  $toDt)
            ->select([
                'c.id',
                'c.period_type', 'c.period_start', 'c.period_end',
                'c.amount', 'c.paid_at',
                'c.vehicle_id', 'c.driver_id',
                'r.receipt_number', 'r.issued_at',

                // vehículo
                'v.economico as vehicle_economico',
                'v.plate as vehicle_plate',
                'v.brand as vehicle_brand',
                'v.model as vehicle_model',
                'v.type as vehicle_type',

                // driver
                'd.name as driver_name',
                'd.phone as driver_phone',
            ])
            ->orderByDesc('c.paid_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.reports.incomes.taxi_income', [
            'from' => $fromDt->toDateString(),
            'to'   => $toDt->copy()->subDay()->toDateString(),

            'kpiPaidTotal'   => $kpiPaidTotal,
            'kpiPaidCount'   => $kpiPaidCount,
            'pendingTotal'   => $pendingTotal,
            'canceledTotal'  => $canceledTotal,

            'seriesMonthly'  => $seriesMonthly,
            'rows'           => $rows,

            'periodType'     => $periodType,
            'vehicleId'      => $vehicleId,
            'driverId'       => $driverId,

            'vehiclesForSelect' => $vehiclesForSelect,
            'driversForSelect'  => $driversForSelect,
        ]);
    }

    public function exportCsv(Request $request)
    {
        $tenantId = (int) auth()->user()->tenant_id;

        $from = $request->input('from') ?: now()->startOfMonth()->toDateString();
        $to   = $request->input('to') ?: now()->toDateString();

        $fromDt = Carbon::parse($from)->startOfDay();
        $toDt   = Carbon::parse($to)->addDay()->startOfDay();

        $periodType = $request->input('period_type');
        $vehicleId  = $request->input('vehicle_id');
        $driverId   = $request->input('driver_id');

        $q = DB::table('tenant_taxi_charges as c')
            ->leftJoin('tenant_taxi_receipts as r', 'r.charge_id', '=', 'c.id')
            ->leftJoin('vehicles as v', 'v.id', '=', 'c.vehicle_id')
            ->leftJoin('drivers as d', 'd.id', '=', 'c.driver_id')
            ->where('c.tenant_id', $tenantId)
            ->where('c.status', 'paid')
            ->whereNotNull('c.paid_at')
            ->where('c.paid_at', '>=', $fromDt)
            ->where('c.paid_at', '<',  $toDt)
            ->when($periodType, fn($qq) => $qq->where('c.period_type', $periodType))
            ->when($vehicleId,  fn($qq) => $qq->where('c.vehicle_id', (int)$vehicleId))
            ->when($driverId,   fn($qq) => $qq->where('c.driver_id', (int)$driverId))
            ->orderBy('c.paid_at', 'asc')
            ->select([
                'c.id',
                'c.period_type',
                'c.period_start',
                'c.period_end',
                'c.amount',
                'c.paid_at',

                'c.vehicle_id',
                'v.economico as vehicle_economico',
                'v.plate as vehicle_plate',
                'v.brand as vehicle_brand',
                'v.model as vehicle_model',

                'c.driver_id',
                'd.name as driver_name',
                'd.phone as driver_phone',

                'r.receipt_number',
                'r.issued_at',
            ]);

        $filename = "ingresos_taxis_{$from}_{$to}.csv";

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'charge_id',
                'period_type','period_start','period_end',
                'amount','paid_at',
                'vehicle_id','vehicle_economico','vehicle_plate','vehicle_brand','vehicle_model',
                'driver_id','driver_name','driver_phone',
                'receipt_number','receipt_issued_at'
            ]);

            $q->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->id,
                        $r->period_type,
                        $r->period_start,
                        $r->period_end,
                        number_format((float)$r->amount, 2, '.', ''),
                        $r->paid_at,

                        $r->vehicle_id,
                        $r->vehicle_economico,
                        $r->vehicle_plate,
                        $r->vehicle_brand,
                        $r->vehicle_model,

                        $r->driver_id,
                        $r->driver_name,
                        $r->driver_phone,

                        $r->receipt_number,
                        $r->issued_at,
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
