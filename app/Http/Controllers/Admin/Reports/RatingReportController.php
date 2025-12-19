<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RatingReportController extends Controller
{
    /** Vista index del reporte general */
    public function index()
    {
        $tenantId = auth()->user()->tenant_id ?? null;
        if (!$tenantId) {
            abort(403, 'Usuario sin tenant asignado');
        }

        $generalSummary   = $this->getGeneralSummary($tenantId);
        $driverRatings    = $this->getDriverRatingsSummary($tenantId);
        $passengerRatings = $this->getPassengerRatingsSummary($tenantId);
        $monthlyTrends    = $this->getMonthlyTrends($tenantId);
        $lowRatingsAlerts = $this->getLowRatingsAlerts($tenantId);
        $ridesStats       = $this->getRidesStats($tenantId);

        return view('ratings.index', compact(
            'generalSummary',
            'driverRatings',
            'passengerRatings',
            'monthlyTrends',
            'lowRatingsAlerts',
            'ridesStats'
        ));
    }

    /** Vista show para un driver específico */
    public function showDriver($driverId)
    {
        $tenantId = auth()->user()->tenant_id ?? null;
        if (!$tenantId) {
            abort(403, 'Usuario sin tenant asignado');
        }

        // MODIFICADO: Incluir foto_path en la consulta del driver
        $driverInfo = DB::table('drivers as d')
            ->where('d.id', $driverId)
            ->where('d.tenant_id', $tenantId)
            ->select('d.id', 'd.name', 'd.phone', 'd.status', 'd.last_seen_at', 'd.foto_path')
            ->first();

        if (!$driverInfo) {
            abort(404);
        }

        $driverSummary        = $this->getDriverSummary($tenantId, $driverId);
        $ratings              = $this->getDriverRatings($tenantId, $driverId);
        $monthlyDriverRatings = $this->getMonthlyDriverRatings($tenantId, $driverId);
        $driverRidesStats     = $this->getDriverRidesStats($tenantId, $driverId);

        return view('ratings.show', compact(
            'driverInfo',
            'driverSummary',
            'ratings',
            'monthlyDriverRatings',
            'driverRidesStats'
        ));
    }

    // 🔹 Estadísticas generales de viajes
    private function getRidesStats(int $tenantId)
    {
        return DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->selectRaw('
                COUNT(*) as total_rides,
                COUNT(CASE WHEN status = "finished" THEN 1 END) as completed_rides,
                COUNT(CASE WHEN status = "canceled" THEN 1 END) as canceled_rides,
                COUNT(CASE WHEN status IN ("accepted", "en_route", "arrived", "on_board") THEN 1 END) as active_rides,
                ROUND(COUNT(CASE WHEN status = "finished" THEN 1 END) * 100.0 / COUNT(*), 2) as completion_rate
            ')
            ->first();
    }

    // 🔹 Estadísticas de viajes por driver
    private function getDriverRidesStats(int $tenantId, $driverId)
    {
        return DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('driver_id', $driverId)
            ->selectRaw('
                COUNT(*) as total_rides,
                COUNT(CASE WHEN status = "finished" THEN 1 END) as completed_rides,
                COUNT(CASE WHEN status = "canceled" THEN 1 END) as canceled_rides,
                COUNT(CASE WHEN driver_id IS NOT NULL THEN 1 END) as assigned_rides,
                ROUND(COUNT(CASE WHEN status = "finished" THEN 1 END) * 100.0 / COUNT(*), 2) as completion_rate,
                AVG(distance_m) as avg_distance,
                AVG(duration_s) as avg_duration
            ')
            ->first();
    }

    // 🔹 Resumen general del sistema por tenant (actualizado con viajes)
    private function getGeneralSummary(int $tenantId)
    {
        return DB::table('ratings as r')
            ->join('rides as ride', 'r.ride_id', '=', 'ride.id')
            ->where('ride.tenant_id', $tenantId)
            ->selectRaw('
                COUNT(*) as total_ratings,
                ROUND(AVG(r.rating), 2) as overall_avg_rating,
                COUNT(DISTINCT r.ride_id) as rated_rides,
                COUNT(DISTINCT CASE WHEN r.rated_type = "driver" THEN r.rated_id END) as rated_drivers,
                COUNT(DISTINCT CASE WHEN r.rated_type = "passenger" THEN r.rated_id END) as rated_passengers,
                SUM(CASE WHEN r.rating = 5 THEN 1 ELSE 0 END) as five_stars,
                SUM(CASE WHEN r.rating = 4 THEN 1 ELSE 0 END) as four_stars,
                SUM(CASE WHEN r.rating = 3 THEN 1 ELSE 0 END) as three_stars,
                SUM(CASE WHEN r.rating = 2 THEN 1 ELSE 0 END) as two_stars,
                SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END) as one_stars,
                (SELECT COUNT(*) FROM rides WHERE tenant_id = ? AND status = "finished") as total_completed_rides
            ', [$tenantId])
            ->first();
    }

    // 🔹 Top drivers mejor calificados por tenant (ACTUALIZADO: incluir foto_path)
    private function getDriverRatingsSummary(int $tenantId)
    {
        return DB::table('ratings as r')
            ->join('drivers as d', 'r.rated_id', '=', 'd.id')
            ->join('rides as ride', 'r.ride_id', '=', 'ride.id')
            ->where('r.rated_type', 'driver')
            ->where('ride.tenant_id', $tenantId)
            ->where('d.tenant_id', $tenantId)
            ->selectRaw('
                d.id as driver_id,
                d.name as driver_name,
                d.phone as driver_phone,
                d.status as driver_status,
                d.foto_path as driver_foto_path,
                COUNT(r.id) as total_ratings,
                ROUND(AVG(r.rating), 2) as avg_rating,
                ROUND(AVG(r.punctuality), 2) as avg_punctuality,
                ROUND(AVG(r.courtesy), 2) as avg_courtesy,
                ROUND(AVG(r.vehicle_condition), 2) as avg_vehicle_condition,
                ROUND(AVG(r.driving_skills), 2) as avg_driving_skills,
                SUM(CASE WHEN r.rating = 5 THEN 1 ELSE 0 END) as five_stars,
                SUM(CASE WHEN r.rating <= 2 THEN 1 ELSE 0 END) as low_ratings,
                (SELECT COUNT(*) FROM rides WHERE driver_id = d.id AND tenant_id = ? AND status = "finished") as completed_rides,
                (SELECT COUNT(*) FROM rides WHERE driver_id = d.id AND tenant_id = ?) as total_rides
            ', [$tenantId, $tenantId])
            ->groupBy('d.id', 'd.name', 'd.phone', 'd.status', 'd.foto_path')
            ->having('total_ratings', '>=', 1)
            ->orderByDesc('avg_rating')
            ->limit(20)
            ->get();
    }

    // 🔹 Alertas de calificaciones bajas (ACTUALIZADO: incluir foto_path)
    private function getLowRatingsAlerts(int $tenantId)
    {
        return DB::table('ratings as r')
            ->join('drivers as d', 'r.rated_id', '=', 'd.id')
            ->where('r.rated_type', 'driver')
            ->where('r.tenant_id', $tenantId)
            ->where('d.tenant_id', $tenantId)
            ->selectRaw('
                d.id as driver_id,
                d.name as driver_name,
                d.phone as driver_phone,
                d.foto_path as driver_foto_path,
                COUNT(r.id) as total_ratings,
                ROUND(AVG(r.rating), 2) as avg_rating,
                SUM(CASE WHEN r.rating <= 2 THEN 1 ELSE 0 END) as low_ratings
            ')
            ->groupBy('d.id', 'd.name', 'd.phone', 'd.foto_path')
            ->having('low_ratings', '>=', 3)
            ->having('avg_rating', '<', 3.0)
            ->orderBy('avg_rating')
            ->limit(10)
            ->get();
    }

    // Calificaciones de passengers
    private function getPassengerRatingsSummary(int $tenantId)
    {
        return DB::table('ratings as r')
            ->join('rides as ride', 'r.ride_id', '=', 'ride.id')
            ->where('r.rated_type', 'passenger')
            ->where('r.tenant_id', $tenantId)
            ->where('ride.tenant_id', $tenantId)
            ->selectRaw('
                r.rated_id as passenger_id,
                ride.passenger_name,
                ride.passenger_phone,
                COUNT(r.id) as total_ratings,
                ROUND(AVG(r.rating), 2) as avg_rating,
                ROUND(AVG(r.punctuality), 2) as avg_punctuality,
                ROUND(AVG(r.courtesy), 2) as avg_courtesy,
                SUM(CASE WHEN r.rating = 5 THEN 1 ELSE 0 END) as five_stars,
                SUM(CASE WHEN r.rating <= 2 THEN 1 ELSE 0 END) as low_ratings
            ')
            ->groupBy('r.rated_id', 'ride.passenger_name', 'ride.passenger_phone')
            ->having('total_ratings', '>=', 1)
            ->orderByDesc('avg_rating')
            ->limit(15)
            ->get();
    }

    // Tendencias mensuales
    private function getMonthlyTrends(int $tenantId)
    {
        return DB::table('ratings')
            ->where('tenant_id', $tenantId)
            ->selectRaw('
                DATE_FORMAT(created_at, "%Y-%m") as month,
                COUNT(*) as total_ratings,
                ROUND(AVG(rating), 2) as avg_rating,
                COUNT(CASE WHEN rated_type = "driver" THEN 1 END) as driver_ratings,
                COUNT(CASE WHEN rated_type = "passenger" THEN 1 END) as passenger_ratings
            ')
            ->groupBy('month')
            ->orderBy('month')
            ->limit(12)
            ->get();
    }

    // Resumen específico de un driver
    private function getDriverSummary(int $tenantId, $driverId)
    {
        return DB::table('ratings')
            ->where('tenant_id', $tenantId)
            ->where('rated_type', 'driver')
            ->where('rated_id', $driverId)
            ->selectRaw('
                COUNT(*) as total_ratings,
                ROUND(AVG(rating), 2) as avg_rating,
                ROUND(AVG(punctuality), 2) as avg_punctuality,
                ROUND(AVG(courtesy), 2) as avg_courtesy,
                ROUND(AVG(vehicle_condition), 2) as avg_vehicle_condition,
                ROUND(AVG(driving_skills), 2) as avg_driving_skills,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_stars,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_stars,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_stars,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_stars,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_stars
            ')
            ->first();
    }

    // Calificaciones detalladas de un driver
    private function getDriverRatings(int $tenantId, $driverId)
    {
        return DB::table('ratings as r')
            ->join('rides', 'r.ride_id', '=', 'rides.id')
            ->where('r.tenant_id', $tenantId)
            ->where('rides.tenant_id', $tenantId)
            ->where('r.rated_type', 'driver')
            ->where('r.rated_id', $driverId)
            ->select(
                'r.*',
                'rides.passenger_name',
                'rides.passenger_phone',
                'rides.created_at as ride_date'
            )
            ->orderBy('r.created_at', 'desc')
            ->paginate(15);
    }

    // Tendencias mensuales de un driver específico
    private function getMonthlyDriverRatings(int $tenantId, $driverId)
    {
        return DB::table('ratings')
            ->where('tenant_id', $tenantId)
            ->where('rated_type', 'driver')
            ->where('rated_id', $driverId)
            ->selectRaw('
                DATE_FORMAT(created_at, "%Y-%m") as month,
                COUNT(*) as total_ratings,
                ROUND(AVG(rating), 2) as avg_rating,
                ROUND(AVG(punctuality), 2) as avg_punctuality,
                ROUND(AVG(courtesy), 2) as avg_courtesy
            ')
            ->groupBy('month')
            ->orderBy('month')
            ->limit(6)
            ->get();
    }
}