<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerThreadMessage extends Model
{
    protected $table = 'partner_thread_messages';

    protected $fillable = [
        'tenant_id','partner_id','thread_id',
        'author_role','author_id',
        'message','meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(PartnerThread::class, 'thread_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}
