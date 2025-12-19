<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Casts\Attribute;

class DriverShift extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'driver_shifts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'driver_id',
        'vehicle_id',
        'started_at',
        'ended_at',
        'status',
        'notes',
        'stats',
        'assignment_id'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'stats' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'started_at',
        'ended_at'
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Get the tenant that owns the driver shift.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Get the driver that owns the shift.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    /**
     * Get the vehicle associated with the shift.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    /**
     * Get the assignment associated with the shift.
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(DriverVehicleAssignment::class, 'assignment_id');
    }

    /**
     * Get the rides for this shift.
     */
    public function rides()
    {
        return $this->hasMany(Ride::class, 'shift_id');
    }

    /**
     * Check if the shift is currently open.
     */
    public function isOpen(): bool
    {
        return $this->status === 'abierto' && is_null($this->ended_at);
    }

    /**
     * Check if the shift is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === 'cerrado' && !is_null($this->ended_at);
    }

    /**
     * Calculate the duration of the shift in minutes.
     */
    public function durationInMinutes(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->started_at) {
                    return 0;
                }
                
                $endTime = $this->ended_at ?? now();
                return $this->started_at->diffInMinutes($endTime);
            }
        );
    }

    /**
     * Calculate the duration of the shift in hours.
     */
    public function durationInHours(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->started_at) {
                    return 0;
                }
                
                $endTime = $this->ended_at ?? now();
                return round($this->started_at->diffInHours($endTime), 2);
            }
        );
    }

    /**
     * Get shift statistics.
     */
    public function statistics(): Attribute
    {
        return Attribute::make(
            get: function () {
                $defaultStats = [
                    'total_rides' => 0,
                    'total_revenue' => 0,
                    'cash_revenue' => 0,
                    'transfer_revenue' => 0,
                    'card_revenue' => 0,
                    'total_distance_km' => 0,
                    'average_rating' => 0,
                ];

                if (is_array($this->stats)) {
                    return array_merge($defaultStats, $this->stats);
                }

                // Calculate from rides if stats is empty
                $rides = $this->rides()->whereIn('status', ['finished'])->get();
                
                if ($rides->isEmpty()) {
                    return $defaultStats;
                }

                return [
                    'total_rides' => $rides->count(),
                    'total_revenue' => $rides->sum('total_amount'),
                    'cash_revenue' => $rides->where('payment_method', 'cash')->sum('total_amount'),
                    'transfer_revenue' => $rides->where('payment_method', 'transfer')->sum('total_amount'),
                    'card_revenue' => $rides->where('payment_method', 'card')->sum('total_amount'),
                    'total_distance_km' => round($rides->sum('distance_m') / 1000, 2),
                    'average_rating' => $this->getAverageRating(),
                ];
            }
        );
    }

    /**
     * Calculate average rating from rides in this shift.
     */
    private function getAverageRating(): float
    {
        $rideIds = $this->rides()->pluck('id');
        
        if ($rideIds->isEmpty()) {
            return 0.0;
        }

        $average = Rating::whereIn('ride_id', $rideIds)
            ->where('rated_type', 'driver')
            ->where('rated_id', $this->driver_id)
            ->avg('rating');

        return round($average ?? 0, 2);
    }

    /**
     * Scope a query to only include open shifts.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'abierto')
                     ->whereNull('ended_at');
    }

    /**
     * Scope a query to only include closed shifts.
     */
    public function scopeClosed($query)
    {
        return $query->where('status', 'cerrado')
                     ->whereNotNull('ended_at');
    }

    /**
     * Scope a query to only include shifts for a specific driver.
     */
    public function scopeForDriver($query, $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    /**
     * Scope a query to only include shifts for a specific vehicle.
     */
    public function scopeForVehicle($query, $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    /**
     * Scope a query to only include shifts within a date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate = null)
    {
        $endDate = $endDate ?? $startDate;
        
        return $query->whereDate('started_at', '>=', $startDate)
                     ->whereDate('started_at', '<=', $endDate);
    }

    /**
     * Close the shift.
     */
    public function close(string $notes = null): bool
    {
        $this->status = 'cerrado';
        $this->ended_at = now();
        
        if ($notes) {
            $this->notes = $notes;
        }

        // Calculate final statistics
        $this->calculateFinalStats();

        return $this->save();
    }

    /**
     * Calculate and update final statistics for the shift.
     */
    private function calculateFinalStats(): void
    {
        $rides = $this->rides()->whereIn('status', ['finished'])->get();
        
        $stats = [
            'total_rides' => $rides->count(),
            'total_revenue' => $rides->sum('total_amount'),
            'cash_revenue' => $rides->where('payment_method', 'cash')->sum('total_amount'),
            'transfer_revenue' => $rides->where('payment_method', 'transfer')->sum('total_amount'),
            'card_revenue' => $rides->where('payment_method', 'card')->sum('total_amount'),
            'corp_revenue' => $rides->where('payment_method', 'corp')->sum('total_amount'),
            'total_distance_km' => round($rides->sum('distance_m') / 1000, 2),
            'total_duration_min' => round($rides->sum('duration_s') / 60, 2),
        ];

        $this->stats = $stats;
    }

    /**
     * Get the formatted started time.
     */
    public function formattedStartedAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->started_at ? $this->started_at->format('d/m/Y H:i') : 'N/A'
        );
    }

    /**
     * Get the formatted ended time.
     */
    public function formattedEndedAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->ended_at ? $this->ended_at->format('d/m/Y H:i') : 'En curso'
        );
    }

    /**
     * Get the shift duration formatted.
     */
    public function formattedDuration(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->started_at) {
                    return 'N/A';
                }

                $endTime = $this->ended_at ?? now();
                $hours = $this->started_at->diffInHours($endTime);
                $minutes = $this->started_at->diffInMinutes($endTime) % 60;

                if ($hours > 0) {
                    return "{$hours}h {$minutes}m";
                }

                return "{$minutes}m";
            }
        );
    }

    /**
     * Get the revenue per hour for this shift.
     */
    public function revenuePerHour(): Attribute
    {
        return Attribute::make(
            get: function () {
                $stats = $this->statistics;
                $hours = $this->duration_in_hours;

                if ($hours <= 0 || $stats['total_revenue'] <= 0) {
                    return 0;
                }

                return round($stats['total_revenue'] / $hours, 2);
            }
        );
    }

    /**
     * Get the rides per hour for this shift.
     */
    public function ridesPerHour(): Attribute
    {
        return Attribute::make(
            get: function () {
                $stats = $this->statistics;
                $hours = $this->duration_in_hours;

                if ($hours <= 0 || $stats['total_rides'] <= 0) {
                    return 0;
                }

                return round($stats['total_rides'] / $hours, 2);
            }
        );
    }

    // En tu modelo DriverShift existente, asegúrate de que esta relación esté así:

/**
 * Get the assignment associated with the shift.
 */

}