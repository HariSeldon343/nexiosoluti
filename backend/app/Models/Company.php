<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory, BelongsToTenant;

    /**
     * Attributi assegnabili in massa
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'vat_number',
        'tax_code',
        'email',
        'phone',
        'website',
        'address',
        'city',
        'postal_code',
        'province',
        'country',
        'custom_fields',
        'logo_path',
        'description',
        'is_active',
        'max_users',
    ];

    /**
     * Cast degli attributi
     */
    protected $casts = [
        'custom_fields' => 'array',
        'is_active' => 'boolean',
        'max_users' => 'integer',
    ];

    /**
     * Relazione con gli utenti
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_users')
                    ->withPivot(['role', 'is_primary', 'joined_at', 'left_at'])
                    ->withTimestamps();
    }

    /**
     * Ottiene i proprietari dell'azienda
     */
    public function owners()
    {
        return $this->users()->wherePivot('role', 'owner');
    }

    /**
     * Ottiene i manager dell'azienda
     */
    public function managers()
    {
        return $this->users()->wherePivot('role', 'manager');
    }

    /**
     * Ottiene i membri dell'azienda
     */
    public function members()
    {
        return $this->users()->wherePivot('role', 'member');
    }

    /**
     * Relazione con i calendari aziendali
     */
    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class);
    }

    /**
     * Relazione con gli eventi aziendali
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Relazione con i task aziendali
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Relazione con le cartelle aziendali
     */
    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    /**
     * Relazione con i file aziendali
     */
    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    /**
     * Relazione con le stanze chat aziendali
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Relazione con i gruppi aziendali
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    /**
     * Relazione con i flussi di approvazione aziendali
     */
    public function approvalFlows(): HasMany
    {
        return $this->hasMany(ApprovalFlow::class);
    }

    /**
     * Verifica se l'azienda può aggiungere un nuovo utente
     */
    public function canAddUser(): bool
    {
        if ($this->max_users === 0) {
            return true; // Utenti illimitati
        }

        $currentUsers = $this->users()->count();
        return $currentUsers < $this->max_users;
    }

    /**
     * Ottiene il numero di utenti attivi
     */
    public function getActiveUsersCountAttribute(): int
    {
        return $this->users()
                    ->where('users.is_active', true)
                    ->whereNull('company_users.left_at')
                    ->count();
    }

    /**
     * Verifica se un utente è membro dell'azienda
     */
    public function hasUser(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Verifica se un utente è proprietario dell'azienda
     */
    public function isOwner(User $user): bool
    {
        return $this->users()
                    ->where('user_id', $user->id)
                    ->wherePivot('role', 'owner')
                    ->exists();
    }

    /**
     * Verifica se un utente è manager dell'azienda
     */
    public function isManager(User $user): bool
    {
        return $this->users()
                    ->where('user_id', $user->id)
                    ->wherePivotIn('role', ['owner', 'manager'])
                    ->exists();
    }

    /**
     * Aggiunge un utente all'azienda
     */
    public function addUser(User $user, string $role = 'member', bool $isPrimary = false): void
    {
        if (!$this->canAddUser()) {
            throw new \Exception('Limite utenti raggiunto per questa azienda');
        }

        $this->users()->attach($user->id, [
            'tenant_id' => $this->tenant_id,
            'role' => $role,
            'is_primary' => $isPrimary,
            'joined_at' => now(),
        ]);
    }

    /**
     * Rimuove un utente dall'azienda
     */
    public function removeUser(User $user): void
    {
        $this->users()->updateExistingPivot($user->id, [
            'left_at' => now(),
        ]);
    }

    /**
     * Ottiene il campo personalizzato
     */
    public function getCustomField(string $key, mixed $default = null): mixed
    {
        return data_get($this->custom_fields, $key, $default);
    }

    /**
     * Imposta un campo personalizzato
     */
    public function setCustomField(string $key, mixed $value): void
    {
        $customFields = $this->custom_fields ?? [];
        data_set($customFields, $key, $value);
        $this->update(['custom_fields' => $customFields]);
    }
}