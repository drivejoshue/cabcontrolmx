<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = [
        'tenant_id',

        // partner scope
        'partner_id',
        'recruited_by_partner_id',
        'partner_assigned_at',
        'partner_left_at',
        'partner_notes',

        // link user + profile
        'user_id',
        'name',
        'phone',
        'email',
        'foto_path',
        'document_id',

        // payout
        'payout_bank',
        'payout_account_name',
        'payout_account_number',
        'payout_clabe',
        'payout_notes',

        'profile_bio',

        // runtime
        'status',
        'last_active_status',
        'active',

        // verification
        'verification_status',
        'verification_notes',
        'verified_by',
        'verified_at',

        // last coords
        'last_lat',
        'last_lng',
        'last_ping_at',
        'last_bearing',
        'last_speed',
        'last_seen_at',
        'last_active_at',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'partner_id' => 'integer',
        'recruited_by_partner_id' => 'integer',
        'user_id' => 'integer',
        'active' => 'boolean',

        'partner_assigned_at' => 'datetime',
        'partner_left_at' => 'datetime',
        'verified_at' => 'datetime',

        'last_lat' => 'float',
        'last_lng' => 'float',
        'last_bearing' => 'float',
        'last_speed' => 'float',
        'last_ping_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_active_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function recruitedByPartner()
    {
        return $this->belongsTo(Partner::class, 'recruited_by_partner_id');
    }

    public function verifiedByUser()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function documents()
    {
        return $this->hasMany(DriverDocument::class);
    }

    // resto de relaciones que ya tienes:
    public function wallet() { return $this->hasOne(DriverWallet::class); }
    public function rides() { return $this->hasMany(Ride::class); }
    public function shifts() { return $this->hasMany(DriverShift::class); }
    public function vehicleAssignments() { return $this->hasMany(DriverVehicleAssignment::class); }
    public function ratings() { return $this->hasMany(Rating::class, 'rated_id')->where('rated_type', 'driver'); }
    public function walletMovements() { return $this->hasMany(DriverWalletMovement::class); }
}
