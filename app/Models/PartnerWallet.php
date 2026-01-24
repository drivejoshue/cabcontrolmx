<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerWallet extends Model
{
    protected $fillable = [
        'tenant_id','partner_id','balance','currency','last_topup_at',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'last_topup_at' => 'datetime',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}
