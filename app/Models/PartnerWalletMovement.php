<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerWalletMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id','partner_id',
        'type','direction',
        'amount','balance_after','currency',
        'ref_type','ref_id','external_ref',
        'notes','meta','created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}
