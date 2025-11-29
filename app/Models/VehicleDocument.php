<?php 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleDocument extends Model
{
    protected $fillable = [
        'vehicle_id', 'tenant_id', 'type', 'document_no',
        'issuer', 'issue_date', 'expiry_date',
        'file_path', 'status', 'review_notes',
        'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
