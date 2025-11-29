<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantBillingProfile extends Model
{
    protected $fillable = [
        'tenant_id', 'plan_code', 'billing_model', 'status',
        'trial_ends_at', 'trial_vehicles',
        'base_monthly_fee', 'included_vehicles', 'price_per_vehicle',
        'max_vehicles',
        'invoice_day', 'next_invoice_date', 'last_invoice_date',
        'commission_percent', 'commission_min_fee',
        'notes',
    ];

    protected $casts = [
        'trial_ends_at' => 'date',
        'next_invoice_date' => 'date',
        'last_invoice_date' => 'date',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
