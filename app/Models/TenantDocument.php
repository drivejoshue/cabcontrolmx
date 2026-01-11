<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantDocument extends Model
{
    protected $fillable = [
        'tenant_id','type','status','disk','path','original_name','mime','size_bytes',
        'uploaded_by','uploaded_at','reviewed_by','reviewed_at','review_notes',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public const TYPE_ID_OFFICIAL     = 'id_official';
    public const TYPE_PROOF_ADDRESS   = 'proof_address';
    public const TYPE_TAX_CERTIFICATE = 'tax_certificate';

    public const REQUIRED_TYPES = [
        self::TYPE_ID_OFFICIAL,
        self::TYPE_PROOF_ADDRESS,
    ];
}
