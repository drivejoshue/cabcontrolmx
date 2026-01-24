<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerTopup extends Model
{
    protected $fillable = [
        'tenant_id','partner_id',
        'provider','method','provider_account_slot',
        'amount','currency','status',
        'mp_preference_id','mp_payment_id','mp_status','mp_status_detail',
        'init_point','external_reference','bank_ref',
        'deposited_at','payer_name','payer_ref',
        'proof_path',
        'reviewed_by','reviewed_at','review_status','review_notes',
        'meta','paid_at','credited_at','apply_wallet_movement_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'array',
        'deposited_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'paid_at' => 'datetime',
        'credited_at' => 'datetime',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}
