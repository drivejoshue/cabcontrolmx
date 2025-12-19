<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleDocument extends Model
{
    protected $table = 'vehicle_documents';

    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'type',
        'document_no',
        'issuer',
        'issue_date',
        'expiry_date',
        'file_path',
        'original_name',
        'mime',
        'size_bytes',
        'status',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
        'ocr_json',
    ];

    protected $casts = [
        'issue_date'   => 'date',
        'expiry_date'  => 'date',
        'reviewed_at'  => 'datetime',
        'ocr_json'     => 'array',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
