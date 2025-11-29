<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantInvoice extends Model
{
    protected $fillable = [
        'tenant_id',
        'billing_profile_id',
        'period_start',
        'period_end',
        'issue_date',
        'due_date',
        'status',
        'vehicles_count',
        'base_fee',
        'vehicles_fee',
        'total',
        'currency',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'issue_date'   => 'date',
        'due_date'     => 'date',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function billingProfile()
    {
        return $this->belongsTo(TenantBillingProfile::class, 'billing_profile_id');
    }
}
