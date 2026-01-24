<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerThread extends Model
{
    protected $table = 'partner_threads';

    protected $fillable = [
        'tenant_id','partner_id',
        'category','status','priority',
        'subject',
        'entity_type','entity_id',
        'last_partner_read_at','last_tenant_read_at',
        'last_message_id','last_message_at',
    ];

    protected $casts = [
        'last_partner_read_at' => 'datetime',
        'last_tenant_read_at' => 'datetime',
        'last_message_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(PartnerThreadMessage::class, 'thread_id');
    }

    public function scopeForContext($q, int $tenantId, int $partnerId)
    {
        return $q->where('tenant_id', $tenantId)->where('partner_id', $partnerId);
    }

    public function touchLastMessage(int $messageId): void
    {
        $this->last_message_id = $messageId;
        $this->last_message_at = now();
        $this->save();
    }
}
