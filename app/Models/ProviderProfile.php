<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderProfile extends Model
{
    protected $fillable = [
        'active',
        'display_name','contact_name','phone','email_support','email_admin',
        'address_line1','address_line2','city','state','country','postal_code',
        'legal_name','rfc','tax_regime','fiscal_address','cfdi_use_default','tax_zip',
        'acc1_bank','acc1_beneficiary','acc1_account','acc1_clabe',
        'acc2_bank','acc2_beneficiary','acc2_account','acc2_clabe',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public static function activeOne(): ?self
    {
        return self::query()->where('active', 1)->orderByDesc('id')->first();
    }
}
