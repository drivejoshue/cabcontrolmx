<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverDocument extends Model
{
    protected $table = 'driver_documents';

    protected $fillable = [
        'tenant_id',
        'driver_id',
        'type',
        'file_path',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'ocr_json',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'ocr_json'    => 'array',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
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
