<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $tenantId  = (int) auth()->user()->tenant_id;
        $tenantTz  = $this->tenantTimezone($tenantId);

        $now       = Carbon::now($tenantTz);
        $today     = $now->toDateString();
        $last7Days = $now->copy()->subDays(6)->toDateString();
        $last30Days= $now->copy()->subDays(29)->toDateString();

        // Métricas principales
        $metrics = [
            'total_rides_today'      => $this->getTotalRidesToday($tenantId, $today),
            'active_drivers'         => $this->getActiveDrivers($tenantId, $tenantTz),
            'total_vehicles'         => $this->getTotalVehicles($tenantId),
            'total_passengers'       => $this->getTotalPassengers($tenantId),
            'total_revenue_today'    => $this->getRevenueToday($tenantId, $today),
            'average_rating'         => $this->getAverageRating($tenantId),
            'cancellation_rate'      => $this->getCancellationRate($tenantId, $today),
            'completion_rate'        => $this->getCompletionRate($tenantId, $today),
        ];

        // Datos para gráficas
        $charts = [
            'rides_by_status'            => $this->getRidesByStatus($tenantId),
            'rides_trend'                => $this->getRidesTrend($tenantId, $last7Days, $today),
            'revenue_trend'              => $this->getRevenueTrend($tenantId, $last30Days, $today),
            'top_drivers'                => $this->getTopDrivers($tenantId, $last30Days),
            'ride_hours_distribution'    => $this->getRideHoursDistribution($tenantId, $today),
            'payment_methods_distribution'=> $this->getPaymentMethodsDistribution($tenantId, $last30Days),
        ];

        // Tablas
        $scheduled_rides = $this->getScheduledRides($tenantId, $tenantTz);
        $recent_rides    = $this->getRecentRides($tenantId);

        return view('admin.dashboard', compact('metrics', 'charts', 'scheduled_rides', 'recent_rides'));
    }

    /**
     * Timezone del tenant (fallback México CDMX)
     */
    private function tenantTimezone(int $tenantId): string
    {
        return DB::table('tenants')
            ->where('id', $tenantId)
            ->value('timezone') ?: 'America/Mexico_City';
    }

    private function getTotalRidesToday(int $tenantId, string $date): int
    {
        return (int) DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', $date)
            ->count();
    }

    private function getActiveDrivers(int $tenantId, string $tenantTz): int
    {
        // Si last_seen_at lo guardas en hora local del tenant, este now() es correcto.
        // Si lo guardas en UTC, aquí habría que usar Carbon::now('UTC').
        $cutoff = Carbon::now($tenantTz)->subMinutes(15);

        return (int) DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['idle', 'busy', 'on_ride'])
            ->where('active', 1)
            ->where('last_seen_at', '>=', $cutoff->toDateTimeString())
            ->count();
    }

    private function getTotalVehicles(int $tenantId): int
    {
        return (int) DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('active', 1)
            ->where('verification_status', 'verified')
            ->count();
    }

    private function getTotalPassengers(int $tenantId): int
    {
        return (int) DB::table('passengers')
            ->where('tenant_id', $tenantId)
            ->count();
    }

    private function getRevenueToday(int $tenantId, string $date): float
    {
        // Si total_amount puede ser null, SUM devuelve null -> casteamos a 0.
        $sum = DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->whereDate('finished_at', $date)
            ->where('status', 'finished')
            ->sum('total_amount');

        return (float) ($sum ?: 0);
    }

    private function getAverageRating(int $tenantId): float
    {
        $avg = DB::table('ratings')
            ->where('tenant_id', $tenantId)
            ->where('rated_type', 'driver')
            ->avg('rating');

        return $avg ? round((float)$avg, 1) : 0.0;
    }

    private function getCancellationRate(int $tenantId, string $date): float
    {
        $total = (int) DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', $date)
            ->count();

        if ($total === 0) return 0.0;

        $cancelled = (int) DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', $date)
            ->where('status', 'canceled')
            ->count();

        return round(($cancelled / $total) * 100, 1);
    }

    private function getCompletionRate(int $tenantId, string $date): float
    {
        $total = (int) DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', $date)
            ->count();

        if ($total === 0) return 0.0;

        $completed = (int) DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', $date)
            ->where('status', 'finished')
            ->count();

        return round(($completed / $total) * 100, 1);
    }

    private function getRidesByStatus(int $tenantId)
    {
        return DB::table('rides')
            ->select('status', DB::raw('count(*) as count'))
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['requested', 'accepted', 'en_route', 'arrived', 'on_board', 'finished', 'canceled', 'scheduled'])
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');
    }

    private function getRidesTrend(int $tenantId, string $startDate, string $endDate)
    {
        return DB::table('rides')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();
    }

    private function getRevenueTrend(int $tenantId, string $startDate, string $endDate)
    {
        return DB::table('rides')
            ->select(DB::raw('DATE(finished_at) as date'), DB::raw('SUM(total_amount) as revenue'))
            ->where('tenant_id', $tenantId)
            ->where('status', 'finished')
            ->whereDate('finished_at', '>=', $startDate)
            ->whereDate('finished_at', '<=', $endDate)
            ->groupBy(DB::raw('DATE(finished_at)'))
            ->orderBy('date')
            ->get();
    }

    private function getTopDrivers(int $tenantId, string $startDate)
    {
        return DB::table('rides')
            ->join('drivers', 'rides.driver_id', '=', 'drivers.id')
            ->leftJoin('ratings', function ($join) {
                $join->on('rides.id', '=', 'ratings.ride_id')
                    ->where('ratings.rated_type', '=', 'driver');
            })
            ->select(
                'drivers.id',
                'drivers.name',
                DB::raw('count(rides.id) as total_rides'),
                DB::raw('SUM(rides.total_amount) as total_revenue'),
                DB::raw('AVG(ratings.rating) as avg_rating')
            )
            ->where('rides.tenant_id', $tenantId)
            ->where('rides.status', 'finished')
            ->whereDate('rides.finished_at', '>=', $startDate)
            ->whereNotNull('rides.driver_id')
            ->groupBy('drivers.id', 'drivers.name')
            ->orderByDesc('total_rides')
            ->limit(5)
            ->get();
    }

    private function getRideHoursDistribution(int $tenantId, string $date)
    {
        // Tolerante: si requested_at está null, usa created_at.
        return DB::table('rides')
            ->select(DB::raw('HOUR(COALESCE(requested_at, created_at)) as hour'), DB::raw('count(*) as count'))
            ->where('tenant_id', $tenantId)
            ->whereDate(DB::raw('COALESCE(requested_at, created_at)'), $date)
            ->groupBy(DB::raw('HOUR(COALESCE(requested_at, created_at))'))
            ->orderBy('hour')
            ->get();
    }

    private function getPaymentMethodsDistribution(int $tenantId, string $startDate)
    {
        return DB::table('rides')
            ->select('payment_method', DB::raw('count(*) as count'))
            ->where('tenant_id', $tenantId)
            ->where('status', 'finished')
            ->whereDate('finished_at', '>=', $startDate)
            ->whereNotNull('payment_method')
            ->groupBy('payment_method')
            ->get();
    }

    private function getScheduledRides(int $tenantId, string $tenantTz)
    {
        $now = Carbon::now($tenantTz);

        return DB::table('rides')
            ->leftJoin('passengers', 'rides.passenger_id', '=', 'passengers.id')
            ->select(
                'rides.id',
                DB::raw("COALESCE(passengers.name, rides.passenger_name, 'N/A') as passenger_name"),
                'rides.origin_label',
                'rides.dest_label',
                'rides.scheduled_for',
                'rides.status'
            )
            ->where('rides.tenant_id', $tenantId)
            ->where('rides.status', 'scheduled')
            ->where('rides.scheduled_for', '>=', $now->toDateTimeString())
            ->orderBy('rides.scheduled_for')
            ->limit(5)
            ->get();
    }

    private function getRecentRides(int $tenantId)
    {
        return DB::table('rides')
            ->leftJoin('drivers', 'rides.driver_id', '=', 'drivers.id')
            ->leftJoin('passengers', 'rides.passenger_id', '=', 'passengers.id')
            ->select(
                'rides.id',
                DB::raw("COALESCE(passengers.name, rides.passenger_name, 'N/A') as passenger_name"),
                'rides.origin_label',
                'rides.dest_label',
                'rides.status',
                'rides.total_amount',
                'rides.created_at',
                'drivers.name as driver_name'
            )
            ->where('rides.tenant_id', $tenantId)
            ->orderByDesc('rides.created_at')
            ->limit(10)
            ->get();
    }
}
