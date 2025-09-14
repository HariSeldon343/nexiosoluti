<?php

namespace App\Models\Scopes;

use App\Services\TenantService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Applica lo scope al query builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantService = app(TenantService::class);

        // Applica il filtro solo se c'è un tenant impostato
        if ($tenantService->hasTenant()) {
            $builder->where($model->getTable() . '.tenant_id', $tenantService->getTenantId());
        }
    }
}