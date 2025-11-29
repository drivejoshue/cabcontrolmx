<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantCommissionReportController extends Controller
{
    /**
     * Reporte de comisiones sugeridas para un tenant
     * - Filtra por rango de fechas
     * - Calcula comisión a partir de agreed_amount / total_amount
     */
    public function index(Request $request, Tenant $tenant)
    {
        // Rango de fechas (por defecto últimos 7 días)
        $defaultFrom = Carbon::now()->subDays(7)->toDateString();
        $defaultTo   = Carbon::now()->toDateString();

        $from = $request->input('from', $defaultFrom);
        $to   = $request->input('to', $defaultTo);

        // Porcentaje de comisión:
        //  1) request
        //  2) commission_percent del billingProfile
        //  3) fallback 15%
        $profile = $tenant->billingProfile;
        $percent = $request->filled('percent')
            ? (float) $request->input('percent')
            : ($profile && $profile->commission_percent !== null
                ? (float) $profile->commission_percent
                : 15.0);

        // Aseguramos que from/to sean fechas válidas
        $fromDateTime = Carbon::parse($from)->startOfDay();
        $toDateTime   = Carbon::parse($to)->endOfDay();

        // Query base
        $query = DB::table('rides')
            ->leftJoin('drivers', 'rides.driver_id', '=', 'drivers.id')
            ->leftJoin('vehicles', 'rides.vehicle_id', '=', 'vehicles.id')
            ->select(
                'rides.id as ride_id',
                'rides.finished_at',
                'rides.total_amount',
                'rides.agreed_amount',
                'rides.quoted_amount',
                'rides.passenger_offer',
                'rides.driver_offer',
                'rides.currency',
                'drivers.id as driver_id',
                'drivers.name as driver_name',
                'vehicles.id as vehicle_id',
                'vehicles.economico',
                'vehicles.plate'
            )
            ->where('rides.tenant_id', $tenant->id)
            ->where('rides.status', 'finished')
            ->whereBetween('rides.finished_at', [
                $fromDateTime->toDateTimeString(),
                $toDateTime->toDateTimeString(),
            ])
            ->orderBy('rides.finished_at', 'desc');

        $rows = collect($query->get());

        // Transformamos filas calculando base_amount y commission
        $reportRows = $rows->map(function ($row) use ($percent) {
            // Regla de monto base:
            // 1) agreed_amount (bid pactado)
            // 2) total_amount
            // 3) quoted_amount
            // 4) passenger_offer
            $base = null;

            if (!is_null($row->agreed_amount)) {
                $base = (float) $row->agreed_amount;
            } elseif (!is_null($row->total_amount)) {
                $base = (float) $row->total_amount;
            } elseif (!is_null($row->quoted_amount)) {
                $base = (float) $row->quoted_amount;
            } elseif (!is_null($row->passenger_offer)) {
                $base = (float) $row->passenger_offer;
            } else {
                $base = 0.0;
            }

            $commission = round($base * $percent / 100, 2);

            return (object) [
                'ride_id'       => $row->ride_id,
                'finished_at'   => $row->finished_at,
                'driver_id'     => $row->driver_id,
                'driver_name'   => $row->driver_name,
                'vehicle_id'    => $row->vehicle_id,
                'economico'     => $row->economico,
                'plate'         => $row->plate,
                'base_amount'   => $base,
                'commission'    => $commission,
                'currency'      => $row->currency ?? 'MXN',
            ];
        });

        $totalBase       = $reportRows->sum('base_amount');
        $totalCommission = $reportRows->sum('commission');
        $totalRides      = $reportRows->count();

        // Totales por driver (para que la central vea cuánto pagar a cada uno)
        $totalsByDriver = $reportRows
            ->groupBy('driver_id')
            ->map(function ($items, $driverId) {
                /** @var \Illuminate\Support\Collection $items */
                $first = $items->first();

                return (object) [
                    'driver_id'       => $driverId,
                    'driver_name'     => $first->driver_name,
                    'rides_count'     => $items->count(),
                    'base_sum'        => $items->sum('base_amount'),
                    'commission_sum'  => $items->sum('commission'),
                ];
            })
            ->values();

        return view('sysadmin.reports.tenant_commissions', [
            'tenant'          => $tenant,
            'rows'            => $reportRows,
            'totalsByDriver'  => $totalsByDriver,
            'totalBase'       => $totalBase,
            'totalCommission' => $totalCommission,
            'totalRides'      => $totalRides,
            'filters'         => [
                'from'    => $fromDateTime->toDateString(),
                'to'      => $toDateTime->toDateString(),
                'percent' => $percent,
            ],
        ]);
    }
}
