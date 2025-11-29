<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverWallet extends Model
{
    protected $primaryKey = 'driver_id';
    public $incrementing = false;

    protected $fillable = [
        'driver_id', 'tenant_id', 'balance', 'status', 'min_balance',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function movements()
    {
        return $this->hasMany(DriverWalletMovement::class, 'driver_id');
    }
}
