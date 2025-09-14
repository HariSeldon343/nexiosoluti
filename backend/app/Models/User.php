<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable, BelongsToTenant, HasRoles;

    /**
     * Attributi assegnabili in massa
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'role',
        'is_company_referent',
        'can_access_multiple_tenants',
        'avatar_path',
        'phone',
        'job_title',
        'preferences',
        'timezone',
        'locale',
        'is_active',
        'last_login_at',
        'last_login_ip',
        'failed_login_attempts',
        'locked_until',
    ];

    /**
     * Attributi nascosti
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Cast degli attributi
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'is_company_referent' => 'boolean',
        'can_access_multiple_tenants' => 'boolean',
        'preferences' => 'array',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'locked_until' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Ottiene l'identificatore JWT
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Ottiene i custom claims JWT
     */
    public function getJWTCustomClaims()
    {
        return [
            'tenant_id' => $this->tenant_id,
            'role' => $this->role,
            'can_access_multiple_tenants' => $this->can_access_multiple_tenants,
        ];
    }

    /**
     * Relazione con le aziende
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_users')
                    ->withPivot(['role', 'is_primary', 'joined_at', 'left_at'])
                    ->withTimestamps();
    }

    /**
     * Ottiene l'azienda primaria dell'utente
     */
    public function primaryCompany()
    {
        return $this->companies()
                    ->wherePivot('is_primary', true)
                    ->first();
    }

    /**
     * Relazione con i calendari
     */
    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class);
    }

    /**
     * Relazione con gli eventi creati
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Relazione con gli eventi a cui partecipa
     */
    public function attendingEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_attendees')
                    ->withPivot(['status', 'role', 'response_message', 'responded_at'])
                    ->withTimestamps();
    }

    /**
     * Relazione con i task creati
     */
    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Relazione con i task assegnati
     */
    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_assignees')
                    ->withPivot(['assigned_by', 'assigned_at', 'started_at', 'completed_at'])
                    ->withTimestamps();
    }

    /**
     * Relazione con i file caricati
     */
    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    /**
     * Relazione con le cartelle create
     */
    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    /**
     * Relazione con le stanze chat
     */
    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'room_users')
                    ->withPivot(['role', 'is_muted', 'last_read_at', 'unread_count', 'joined_at', 'left_at'])
                    ->withTimestamps();
    }

    /**
     * Relazione con i messaggi inviati
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Relazione con i gruppi
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_user')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    /**
     * Verifica se l'utente è admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Verifica se l'utente è un utente speciale
     */
    public function isSpecialUser(): bool
    {
        return $this->role === 'special_user';
    }

    /**
     * Verifica se l'utente è un referente aziendale
     */
    public function isCompanyReferent(): bool
    {
        return $this->is_company_referent;
    }

    /**
     * Verifica se l'utente ha 2FA abilitato
     */
    public function hasTwoFactorEnabled(): bool
    {
        return !is_null($this->two_factor_secret);
    }

    /**
     * Verifica se l'account è bloccato
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Incrementa i tentativi di login falliti
     */
    public function incrementFailedLoginAttempts(): void
    {
        $this->increment('failed_login_attempts');

        // Blocca l'account dopo 5 tentativi
        if ($this->failed_login_attempts >= 5) {
            $this->update([
                'locked_until' => now()->addMinutes(15)
            ]);
        }
    }

    /**
     * Reset dei tentativi di login falliti
     */
    public function resetFailedLoginAttempts(): void
    {
        $this->update([
            'failed_login_attempts' => 0,
            'locked_until' => null
        ]);
    }

    /**
     * Aggiorna le informazioni dell'ultimo login
     */
    public function updateLastLogin(string $ipAddress): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress
        ]);
    }
}