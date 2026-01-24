<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerNotification extends Model
{
    protected $table = 'partner_notifications';

    protected $fillable = [
        'tenant_id','partner_id',
        'type','level','title','body',
        'entity_type','entity_id',
        'data','read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function scopeForContext($q, int $tenantId, int $partnerId)
    {
        return $q->where('tenant_id', $tenantId)->where('partner_id', $partnerId);
    }

    public function getIsReadAttribute(): bool
    {
        return !is_null($this->read_at);
    }
}
