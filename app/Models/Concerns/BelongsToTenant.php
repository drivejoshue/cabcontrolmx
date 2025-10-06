<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant()
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = app('currentTenantId'); // lo setearemos en un middleware
            if ($tenantId) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);
            }
        });
    }

    // opcional: fill automático
    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id) && app('currentTenantId')) {
                $model->tenant_id = app('currentTenantId');
            }
        });
    }
}
