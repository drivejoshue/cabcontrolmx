<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Partner extends Model
{
    use SoftDeletes;

    protected $table = 'partners';

    protected $fillable = [
        'tenant_id',

        'code','slug','name','kind','status','is_active',

        'contact_name','contact_phone','contact_email',

        'address_line1','address_line2','city','state','country','postal_code',

        'legal_name','rfc','tax_regime','fiscal_address','cfdi_use_default','tax_zip',

        'payout_bank','payout_beneficiary','payout_account','payout_clabe','payout_notes',

        'commission_percent','commission_fixed','settlement_schedule','settlement_day','risk_level',

        'notes','meta',

        'created_by','updated_by',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'is_active' => 'boolean',
        'meta' => 'array',
        'commission_percent' => 'float',
        'commission_fixed' => 'float',
        'settlement_day' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function members()
    {
        return $this->hasMany(PartnerUser::class, 'partner_id');
    }

    public function activeMembers()
    {
        return $this->members()->whereNull('revoked_at');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'partner_users', 'partner_id', 'user_id')
            ->withPivot(['role','is_primary','invited_by','invited_at','accepted_at','revoked_at','permissions'])
            ->withTimestamps();
    }
}
