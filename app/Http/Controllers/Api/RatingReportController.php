<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RatingReportController extends Controller
{
    /** Vista index del reporte general */
    public function index()
    {
        $generalSummary = $this->getGeneralSummary();
        $driverRatings = $this->getDriverRatingsSummary();
        $passengerRatings = $this->getPassengerRatingsSummary();
        $monthlyTrends = $this->getMonthlyTrends();
        $lowRatingsAlerts = $this->getLowRatingsAlerts();

        return view('ratings.index', compact(
            'generalSummary',
            'driverRatings',
            'passengerRatings', 
            'monthlyTrends',
            'lowRatingsAlerts'
        ));
    }

    /** Vista show para un driver específico */
    public function showDriver($driverId)
    {
        $driverInfo = DB::table('drivers as d')
            ->where('d.id', $driverId)
            ->select('d.name', 'd.phone', 'd.id')
            ->first();

        $driverSummary = $this->getDriverSummary($driverId);
        $ratings = $this->getDriverRatings($driverId);
        $monthlyDriverRatings = $this->getMonthlyDriverRatings($driverId);

        return view('ratings.show', compact(
            'driverInfo',
            'driverSummary',
            'ratings',
            'monthlyDriverRatings'
        ));
    }

    // Resumen general del sistema
    private function getGeneralSummary()
    {
        return DB::table('ratings')
            ->selectRaw('
                COUNT(*) as total_ratings,
                ROUND(AVG(rating), 2) as overall_avg_rating,
                COUNT(DISTINCT ride_id) as rated_rides,
                COUNT(DISTINCT CASE WHEN rated_type = "driver" THEN rated_id END) as rated_drivers,
                COUNT(DISTINCT CASE WHEN rated_type = "passenger" THEN rated_id END) as rated_passengers,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_stars,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_stars,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_stars,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_stars,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_stars
            ')
            ->first();
    }

    // Top drivers mejor calificados
    private function getDriverRatingsSummary()
    {
        return DB::table('ratings as r')
            ->join('drivers as d', 'r.rated_id', '=', 'd.id')
            ->where('r.rated_type', 'driver')
            ->selectRaw('
                d.id as driver_id,
                d.name as driver_name,
                d.phone as driver_phone,
                COUNT(r.id) as total_ratings,
                ROUND(AVG(r.rating), 2) as avg_rating,
                ROUND(AVG(r.punctuality), 2) as avg_punctuality,
                ROUND(AVG(r.courtesy), 2) as avg_courtesy,
                ROUND(AVG(r.vehicle_condition), 2) as avg_vehicle_condition,
                ROUND(AVG(r.driving_skills), 2) as avg_driving_skills,
                SUM(CASE WHEN r.rating = 5 THEN 1 ELSE 0 END) as five_stars,
                SUM(CASE WHEN r.rating <= 2 THEN 1 ELSE 0 END) as low_ratings
            ')
            ->groupBy('d.id', 'd.name', 'd.phone')
            ->having('total_ratings', '>=', 1)
            ->orderByDesc('avg_rating')
            ->limit(20)
            ->get();
    }

    // Calificaciones de passengers
    private function getPassengerRatingsSummary()
    {
        return DB::table('ratings as r')
            ->join('rides as ride', 'r.ride_id', '=', 'ride.id')
            ->where('r.rated_type', 'passenger')
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
    private function getMonthlyTrends()
    {
        return DB::table('ratings')
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

    // Alertas de calificaciones bajas
    private function getLowRatingsAlerts()
    {
        return DB::table('ratings as r')
            ->join('drivers as d', 'r.rated_id', '=', 'd.id')
            ->where('r.rated_type', 'driver')
            ->selectRaw('
                d.id as driver_id,
                d.name as driver_name,
                COUNT(r.id) as total_ratings,
                ROUND(AVG(r.rating), 2) as avg_rating,
                SUM(CASE WHEN r.rating <= 2 THEN 1 ELSE 0 END) as low_ratings
            ')
            ->groupBy('d.id', 'd.name')
            ->having('low_ratings', '>=', 3)
            ->having('avg_rating', '<', 3.0)
            ->orderBy('avg_rating')
            ->limit(10)
            ->get();
    }

    // Resumen específico de un driver
    private function getDriverSummary($driverId)
    {
        return DB::table('ratings')
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
    private function getDriverRatings($driverId)
    {
        return DB::table('ratings as r')
            ->join('rides', 'r.ride_id', '=', 'rides.id')
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
    private function getMonthlyDriverRatings($driverId)
    {
        return DB::table('ratings')
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