<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    /**
     * Attributi assegnabili in massa
     */
    protected $fillable = [
        'name',
        'domain',
        'subdomain',
        'logo_path',
        'favicon_path',
        'primary_color',
        'secondary_color',
        'settings',
        'is_active',
        'contact_email',
        'contact_phone',
        'address',
        'vat_number',
        'max_users',
        'max_storage_mb',
        'subscription_expires_at',
        'subscription_plan',
    ];

    /**
     * Cast degli attributi
     */
    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'max_users' => 'integer',
        'max_storage_mb' => 'integer',
        'subscription_expires_at' => 'date',
    ];

    /**
     * Relazione con gli utenti
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Relazione con le aziende
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    /**
     * Relazione con i calendari
     */
    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class);
    }

    /**
     * Relazione con gli eventi
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Relazione con i task
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Relazione con le cartelle
     */
    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    /**
     * Relazione con i file
     */
    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    /**
     * Relazione con le stanze chat
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Relazione con i messaggi
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Relazione con i log di audit
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Ottiene il numero di utenti attivi
     */
    public function getActiveUsersCountAttribute(): int
    {
        return $this->users()->where('is_active', true)->count();
    }

    /**
     * Ottiene lo storage utilizzato in MB
     */
    public function getUsedStorageMbAttribute(): float
    {
        $bytes = $this->files()->sum('size');
        return round($bytes / (1024 * 1024), 2);
    }

    /**
     * Verifica se il tenant ha spazio disponibile
     */
    public function hasStorageSpace(int $sizeInBytes): bool
    {
        if ($this->max_storage_mb === 0) {
            return true; // Storage illimitato
        }

        $currentMb = $this->used_storage_mb;
        $additionalMb = $sizeInBytes / (1024 * 1024);

        return ($currentMb + $additionalMb) <= $this->max_storage_mb;
    }

    /**
     * Verifica se il tenant può aggiungere un nuovo utente
     */
    public function canAddUser(): bool
    {
        if ($this->max_users === 0) {
            return true; // Utenti illimitati
        }

        return $this->active_users_count < $this->max_users;
    }

    /**
     * Ottiene l'URL completo del tenant
     */
    public function getUrlAttribute(): string
    {
        if ($this->domain) {
            return 'https://' . $this->domain;
        }

        $baseDomain = config('tenant.default_domain', 'localhost');
        return 'https://' . $this->subdomain . '.' . $baseDomain;
    }
}