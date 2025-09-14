<?php

namespace App\Models\Traits;

use App\Models\Tenant;
use App\Models\Scopes\TenantScope;
use App\Services\TenantService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot del trait per aggiungere automaticamente il global scope
     */
    protected static function bootBelongsToTenant(): void
    {
        // Aggiunge il global scope per filtrare automaticamente per tenant
        static::addGlobalScope(new TenantScope());

        // Quando si crea un nuovo record, imposta automaticamente il tenant_id
        static::creating(function (Model $model) {
            $tenantService = app(TenantService::class);

            if ($tenantService->hasTenant() && !$model->tenant_id) {
                $model->tenant_id = $tenantService->getTenantId();
            }
        });

        // Validazione prima del salvataggio per assicurarsi che il tenant_id sia presente
        static::saving(function (Model $model) {
            if (!$model->tenant_id) {
                throw new \Exception('Impossibile salvare il record senza tenant_id');
            }
        });
    }

    /**
     * Relazione con il tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope per ottenere record di un tenant specifico
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope per rimuovere il filtro tenant (solo per admin)
     */
    public function scopeWithoutTenant($query)
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Verifica se il modello appartiene al tenant corrente
     */
    public function belongsToCurrentTenant(): bool
    {
        $tenantService = app(TenantService::class);

        if (!$tenantService->hasTenant()) {
            return false;
        }

        return $this->tenant_id === $tenantService->getTenantId();
    }

    /**
     * Ottiene solo i record del tenant corrente (metodo statico)
     */
    public static function currentTenant()
    {
        $tenantService = app(TenantService::class);

        if (!$tenantService->hasTenant()) {
            throw new \Exception('Nessun tenant impostato');
        }

        return static::where('tenant_id', $tenantService->getTenantId());
    }
}