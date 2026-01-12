<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingPlan extends Model
{
    protected $fillable = [
        'code','name','billing_model','currency',
        'base_monthly_fee','included_vehicles','price_per_vehicle',
        'active','effective_from',
    ];
}
