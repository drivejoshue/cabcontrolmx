<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverShift;
use App\Models\Ride;
use App\Models\DriverWalletMovement;
use App\Models\Rating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class DriverActivityReportController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = (int) (auth()->user()->tenant_id);

        $drivers = Driver::where('tenant_id', $tenantId)
            ->with('user')
            ->orderBy('name')
            ->get();

        $driverId  = $request->input('driver_id');
        $startDate = $request->input('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate   = $request->input('end_date', now()->format('Y-m-d'));
        $groupBy   = $request->input('group_by', 'day');

        $reportData = $this->getDriverActivityReport($tenantId, $driverId, $startDate, $endDate, $groupBy);

        $driverDetails = $driverId
            ? Driver::with('vehicleAssignments.vehicle')->find($driverId)
            : null;

        return view('admin.reports.drivers.driver-activity', compact(
            'drivers',
            'driverId',
            'startDate',
            'endDate',
            'groupBy',
            'reportData',
            'driverDetails'
        ));
    }

    private function getDriverActivityReport($tenantId, $driverId = null, $startDate, $endDate, $groupBy = 'day')
    {
        $dateFormat = $this->getDateFormat($groupBy);

        $rangeStart = Carbon::parse($startDate)->startOfDay();
        $rangeEnd   = Carbon::parse($endDate)->endOfDay();

        $period = CarbonPeriod::create($rangeStart, $rangeEnd);

        // 1) Rides (terminados) por conductor
        $ridesQuery = Ride::where('tenant_id', $tenantId)
            ->whereIn('status', ['finished'])
            ->whereBetween('finished_at', [$rangeStart, $rangeEnd]);

        if (!empty($driverId)) {
            $ridesQuery->where('driver_id', $driverId);
        }

        $ridesByDriver = $ridesQuery
            ->select([
                'driver_id',
                DB::raw('COUNT(*) as total_rides'),
                DB::raw('COALESCE(SUM(total_amount),0) as total_revenue'),
                DB::raw("COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END),0) as cash_revenue"),
                DB::raw("COALESCE(SUM(CASE WHEN payment_method = 'transfer' THEN total_amount ELSE 0 END),0) as transfer_revenue"),
                DB::raw("COALESCE(SUM(CASE WHEN payment_method = 'card' THEN total_amount ELSE 0 END),0) as card_revenue"),
                DB::raw('COALESCE(AVG(distance_m) / 1000,0) as avg_distance_km'),
                DB::raw('COALESCE(AVG(duration_s) / 60,0) as avg_duration_min'),
            ])
            ->groupBy('driver_id')
            ->get()
            ->keyBy('driver_id');

        // 2) Turnos por conductor (traslape con rango)
        $shiftsQuery = DriverShift::where('tenant_id', $tenantId)
            ->where('started_at', '<=', $rangeEnd)
            ->where(function ($q) use ($rangeStart) {
                $q->whereNull('ended_at')
                  ->orWhere('ended_at', '>=', $rangeStart);
            });

        if (!empty($driverId)) {
            $shiftsQuery->where('driver_id', $driverId);
        }

        $shiftsByDriver = $shiftsQuery
            ->select([
                'driver_id',
                DB::raw('COUNT(*) as total_shifts'),
                DB::raw('ROUND(SUM(TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, NOW())))/3600, 2) as total_hours'),
                DB::raw('ROUND(AVG(TIMESTAMPDIFF(MINUTE, started_at, COALESCE(ended_at, NOW()))), 0) as avg_shift_minutes'),
            ])
            ->groupBy('driver_id')
            ->get()
            ->keyBy('driver_id');

        // 3) Ratings por conductor
        $ratingsQuery = Rating::where('tenant_id', $tenantId)
            ->where('rated_type', 'driver')
            ->whereBetween('created_at', [$rangeStart, $rangeEnd]);

        if (!empty($driverId)) {
            $ratingsQuery->where('rated_id', $driverId);
        }

        $ratingsByDriver = $ratingsQuery
            ->select([
                'rated_id as driver_id',
                DB::raw('COUNT(*) as total_ratings'),
                DB::raw('ROUND(AVG(rating), 2) as avg_rating'),
                DB::raw('ROUND(AVG(punctuality), 2) as avg_punctuality'),
                DB::raw('ROUND(AVG(courtesy), 2) as avg_courtesy'),
                DB::raw('ROUND(AVG(vehicle_condition), 2) as avg_vehicle_condition'),
                DB::raw('ROUND(AVG(driving_skills), 2) as avg_driving_skills'),
            ])
            ->groupBy('rated_id')
            ->get()
            ->keyBy('driver_id');

        // 4) Revenue por periodo (chart)
        $revenueByPeriodQ = Ride::where('tenant_id', $tenantId)
            ->whereIn('status', ['finished'])
            ->whereBetween('finished_at', [$rangeStart, $rangeEnd]);

        if (!empty($driverId)) {
            $revenueByPeriodQ->where('driver_id', $driverId);
        }

        $revenueByPeriod = $revenueByPeriodQ
            ->select([
                DB::raw("DATE_FORMAT(finished_at, '{$dateFormat}') as period"),
                DB::raw('COALESCE(SUM(total_amount),0) as total_revenue'),
                DB::raw('COUNT(*) as ride_count'),
            ])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // 5) Métodos de pago
        $paymentMethodsQ = Ride::where('tenant_id', $tenantId)
            ->whereIn('status', ['finished'])
            ->whereBetween('finished_at', [$rangeStart, $rangeEnd]);

        if (!empty($driverId)) {
            $paymentMethodsQ->where('driver_id', $driverId);
        }

        $paymentMethods = $paymentMethodsQ
            ->select([
                'payment_method',
                DB::raw('COUNT(*) as count'),
                DB::raw('COALESCE(SUM(total_amount),0) as amount'),
            ])
            ->groupBy('payment_method')
            ->get();

        // 6) Horas pico
        $peakHoursQ = Ride::where('tenant_id', $tenantId)
            ->whereIn('status', ['finished'])
            ->whereBetween('finished_at', [$rangeStart, $rangeEnd]);

        if (!empty($driverId)) {
            $peakHoursQ->where('driver_id', $driverId);
        }

        $peakHours = $peakHoursQ
            ->select([
                DB::raw('HOUR(finished_at) as hour'),
                DB::raw('COUNT(*) as ride_count'),
                DB::raw('COALESCE(SUM(total_amount),0) as revenue'),
            ])
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        // 7) Wallet movements (solo si hay conductor)
        $walletMovements = [];
        if (!empty($driverId)) {
            $walletMovements = DriverWalletMovement::where('tenant_id', $tenantId)
                ->where('driver_id', $driverId)
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                ->with('ride')
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();
        }

        // 8) Consolidación final
        $driversQ = Driver::where('tenant_id', $tenantId);
        if (!empty($driverId)) {
            $driversQ->where('id', $driverId);
        }

        $driverList = $driversQ->get();

        $driverStats = $driverList->map(function ($driver) use ($ridesByDriver, $ratingsByDriver, $shiftsByDriver) {
            $rideStats   = $ridesByDriver->get($driver->id);
            $ratingStats = $ratingsByDriver->get($driver->id);
            $shiftStats  = $shiftsByDriver->get($driver->id);

            $ridesTotal   = $rideStats ? (int)$rideStats->total_rides : 0;
            $revenueTotal = $rideStats ? (float)$rideStats->total_revenue : 0.0;

            $totalHours = $shiftStats ? (float)$shiftStats->total_hours : 0.0;

            $ridesPerHour   = ($totalHours > 0) ? round($ridesTotal / $totalHours, 2) : null;
            $revenuePerHour = ($totalHours > 0) ? round($revenueTotal / $totalHours, 2) : null;

            return [
                'driver' => $driver,

                'rides' => $rideStats ? [
                    'total'        => (int)$rideStats->total_rides,
                    'revenue'      => (float)$rideStats->total_revenue,
                    'cash'         => (float)$rideStats->cash_revenue,
                    'transfer'     => (float)$rideStats->transfer_revenue,
                    'card'         => (float)$rideStats->card_revenue,
                    'avg_distance' => round((float)$rideStats->avg_distance_km, 2),
                    'avg_duration' => round((float)$rideStats->avg_duration_min, 2),
                ] : [
                    'total' => 0, 'revenue' => 0, 'cash' => 0, 'transfer' => 0, 'card' => 0,
                    'avg_distance' => 0, 'avg_duration' => 0,
                ],

                'shifts' => $shiftStats ? [
                    'total'             => (int)$shiftStats->total_shifts,
                    'total_hours'       => (float)$shiftStats->total_hours,
                    'avg_shift_minutes' => (int)$shiftStats->avg_shift_minutes,
                ] : [
                    'total' => 0, 'total_hours' => 0, 'avg_shift_minutes' => 0,
                ],

                'ratings' => $ratingStats ? [
                    'total'           => (int)$ratingStats->total_ratings,
                    'avg_rating'      => (float)$ratingStats->avg_rating,
                    'avg_punctuality' => (float)$ratingStats->avg_punctuality,
                    'avg_courtesy'    => (float)$ratingStats->avg_courtesy,
                    'avg_vehicle'     => (float)$ratingStats->avg_vehicle_condition,
                    'avg_driving'     => (float)$ratingStats->avg_driving_skills,
                ] : null,

                'efficiency' => [
                    'rides_per_hour'   => $ridesPerHour,
                    'revenue_per_hour' => $revenuePerHour,
                ],
            ];
        });

        return [
            'driver_stats'       => $driverStats,
            'revenue_by_period'  => $revenueByPeriod,
            'payment_methods'    => $paymentMethods,
            'peak_hours'         => $peakHours,
            'wallet_movements'   => $walletMovements,
            'period_labels'      => $this->generatePeriodLabels($period, $groupBy),
            'summary' => [
                'total_drivers' => $driverList->count(),
                'total_rides'   => (int) $ridesByDriver->sum('total_rides'),
                'total_revenue' => (float) $ridesByDriver->sum('total_revenue'),
                'total_shifts'  => (int) $shiftsByDriver->sum('total_shifts'),
                'total_hours'   => (float) $shiftsByDriver->sum('total_hours'),
                'avg_rating'    => (float) $ratingsByDriver->avg('avg_rating'),
            ]
        ];
    }

    private function getDateFormat($groupBy)
    {
        return match($groupBy) {
            'hour'  => '%Y-%m-%d %H:00',
            'day'   => '%Y-%m-%d',
            'week'  => '%Y-%U',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };
    }

    private function generatePeriodLabels($period, $groupBy)
    {
        $labels = [];
        foreach ($period as $date) {
            $labels[] = match($groupBy) {
                'hour'  => $date->format('d M H:00'),
                'day'   => $date->format('d M'),
                'week'  => 'Sem ' . $date->weekOfYear . ' ' . $date->format('Y'),
                'month' => $date->format('M Y'),
                default => $date->format('d M'),
            };
        }
        return $labels;
    }
}
