<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class TenantService
{
    protected ?Tenant $tenant = null;

    /**
     * Imposta il tenant corrente
     */
    public function setTenant(?Tenant $tenant): void
    {
        $this->tenant = $tenant;

        // Imposta il tenant_id nella sessione per riferimento globale
        if ($tenant) {
            session(['tenant_id' => $tenant->id]);

            // Imposta il database connection per il tenant se necessario
            $this->configureTenantDatabase($tenant);
        }
    }

    /**
     * Ottiene il tenant corrente
     */
    public function getTenant(): ?Tenant
    {
        if (!$this->tenant && session('tenant_id')) {
            $this->tenant = Cache::remember(
                'tenant_' . session('tenant_id'),
                3600, // Cache per 1 ora
                fn() => Tenant::find(session('tenant_id'))
            );
        }

        return $this->tenant;
    }

    /**
     * Ottiene l'ID del tenant corrente
     */
    public function getTenantId(): ?int
    {
        return $this->tenant?->id ?? session('tenant_id');
    }

    /**
     * Verifica se un tenant è impostato
     */
    public function hasTenant(): bool
    {
        return $this->tenant !== null || session()->has('tenant_id');
    }

    /**
     * Resetta il tenant corrente
     */
    public function resetTenant(): void
    {
        $this->tenant = null;
        session()->forget('tenant_id');
    }

    /**
     * Configura il database per il tenant (se usi database separati)
     */
    protected function configureTenantDatabase(Tenant $tenant): void
    {
        // In questo caso usiamo single database con tenant_id
        // ma qui potremmo configurare connessioni separate se necessario

        // Esempio per database separati:
        // config(['database.connections.tenant.database' => 'tenant_' . $tenant->id]);
        // DB::purge('tenant');
        // DB::reconnect('tenant');
    }

    /**
     * Ottiene le impostazioni del tenant
     */
    public function getSettings(): array
    {
        if (!$this->tenant) {
            return [];
        }

        return $this->tenant->settings ?? [];
    }

    /**
     * Ottiene un'impostazione specifica del tenant
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        $settings = $this->getSettings();
        return data_get($settings, $key, $default);
    }

    /**
     * Aggiorna un'impostazione del tenant
     */
    public function updateSetting(string $key, mixed $value): void
    {
        if (!$this->tenant) {
            return;
        }

        $settings = $this->tenant->settings ?? [];
        data_set($settings, $key, $value);

        $this->tenant->update(['settings' => $settings]);

        // Invalida la cache
        Cache::forget('tenant_' . $this->tenant->id);
    }

    /**
     * Ottiene il branding del tenant
     */
    public function getBranding(): array
    {
        if (!$this->tenant) {
            return $this->getDefaultBranding();
        }

        return [
            'name' => $this->tenant->name,
            'logo' => $this->tenant->logo_path ? asset('storage/' . $this->tenant->logo_path) : null,
            'favicon' => $this->tenant->favicon_path ? asset('storage/' . $this->tenant->favicon_path) : null,
            'primary_color' => $this->tenant->primary_color,
            'secondary_color' => $this->tenant->secondary_color,
            'contact_email' => $this->tenant->contact_email,
            'contact_phone' => $this->tenant->contact_phone,
        ];
    }

    /**
     * Ottiene il branding predefinito
     */
    protected function getDefaultBranding(): array
    {
        return [
            'name' => config('app.name'),
            'logo' => null,
            'favicon' => null,
            'primary_color' => '#3B82F6',
            'secondary_color' => '#1E40AF',
            'contact_email' => null,
            'contact_phone' => null,
        ];
    }

    /**
     * Verifica se il tenant ha raggiunto il limite di utenti
     */
    public function hasReachedUserLimit(): bool
    {
        if (!$this->tenant || $this->tenant->max_users === 0) {
            return false; // 0 = illimitato
        }

        $currentUsers = $this->tenant->users()->count();
        return $currentUsers >= $this->tenant->max_users;
    }

    /**
     * Verifica se il tenant ha raggiunto il limite di storage
     */
    public function hasReachedStorageLimit(): bool
    {
        if (!$this->tenant || $this->tenant->max_storage_mb === 0) {
            return false; // 0 = illimitato
        }

        $currentStorageMb = $this->calculateCurrentStorage();
        return $currentStorageMb >= $this->tenant->max_storage_mb;
    }

    /**
     * Calcola lo storage corrente utilizzato in MB
     */
    protected function calculateCurrentStorage(): float
    {
        if (!$this->tenant) {
            return 0;
        }

        // Calcola la somma delle dimensioni dei file
        $totalBytes = $this->tenant->files()->sum('size');

        return $totalBytes / (1024 * 1024); // Converti in MB
    }

    /**
     * Verifica se l'abbonamento del tenant è valido
     */
    public function hasValidSubscription(): bool
    {
        if (!$this->tenant) {
            return false;
        }

        if (!$this->tenant->subscription_expires_at) {
            return true; // Nessuna scadenza impostata
        }

        return $this->tenant->subscription_expires_at->isFuture();
    }
}