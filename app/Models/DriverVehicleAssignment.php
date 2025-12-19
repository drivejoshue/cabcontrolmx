<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class DriverVehicleAssignment extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'driver_vehicle_assignments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'driver_id',
        'vehicle_id',
        'start_at',
        'end_at',
        'note',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'start_at',
        'end_at'
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Get the tenant that owns the assignment.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Get the driver that owns the assignment.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    /**
     * Get the vehicle associated with the assignment.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    /**
     * Check if the assignment is currently active.
     */
    public function isActive(): bool
    {
        return is_null($this->end_at);
    }

    /**
     * Check if the assignment is inactive.
     */
    public function isInactive(): bool
    {
        return !is_null($this->end_at);
    }

    /**
     * Get the duration of the assignment in days.
     */
    public function durationInDays(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->start_at) {
                    return 0;
                }
                
                $endTime = $this->end_at ?? now();
                return $this->start_at->diffInDays($endTime);
            }
        );
    }

    /**
     * Scope a query to only include active assignments.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('end_at');
    }

    /**
     * Scope a query to only include inactive assignments.
     */
    public function scopeInactive($query)
    {
        return $query->whereNotNull('end_at');
    }

    /**
     * Scope a query to only include assignments for a specific driver.
     */
    public function scopeForDriver($query, $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    /**
     * Scope a query to only include assignments for a specific vehicle.
     */
    public function scopeForVehicle($query, $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    /**
     * End the assignment.
     */
    public function end(string $note = null): bool
    {
        $this->end_at = now();
        
        if ($note) {
            $this->note = $note;
        }

        return $this->save();
    }

    /**
     * Get the formatted start time.
     */
    public function formattedStartAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->start_at ? $this->start_at->format('d/m/Y H:i') : 'N/A'
        );
    }

    /**
     * Get the formatted end time.
     */
    public function formattedEndAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->end_at ? $this->end_at->format('d/m/Y H:i') : 'Activa'
        );
    }

    /**
     * Get the driver shifts associated with this assignment.
     */
    public function shifts()
    {
        return $this->hasMany(DriverShift::class, 'assignment_id');
    }

    /**
     * Get the current active shift for this assignment.
     */
    public function currentShift()
    {
        return $this->shifts()
            ->where('status', 'abierto')
            ->whereNull('ended_at')
            ->orderByDesc('started_at')
            ->first();
    }
}