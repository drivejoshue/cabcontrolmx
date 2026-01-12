<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantTopup extends Model
{
    protected $table = 'tenant_topups';

    protected $fillable = [
        'tenant_id',
        'provider',
        'method',
        'amount',
        'currency',
        'status',

        'mp_preference_id',
        'mp_payment_id',
        'mp_status',
        'mp_status_detail',
        'init_point',
        'external_reference',

        'bank_ref',
        'deposited_at',
        'payer_name',
        'payer_ref',
        'proof_path',

        'meta',
        'paid_at',
        'credited_at',

          'provider_account_slot',
  'review_status',
  'review_notes',
  'reviewed_by',
  'reviewed_at',
    ];

    protected $casts = [
        'meta'         => 'array',
        'paid_at'      => 'datetime',
        'credited_at'  => 'datetime',
        'deposited_at' => 'datetime',
        'amount'       => 'decimal:2',
          'reviewed_at' => 'datetime',
  'provider_account_slot' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    // Helpers de status (opcionales)
    public function isCredited(): bool
    {
        return !is_null($this->credited_at);
    }
}
