<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Scopes\TenantScope;
use App\Models\Traits\HasTenant;

class Calendar extends Model
{
    use HasFactory, SoftDeletes, HasTenant;

    /**
     * Attributi assegnabili in massa
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'description',
        'color',
        'timezone',
        'is_public',
        'is_default',
        'caldav_uri',
        'caldav_ctag',
        'caldav_synctoken',
        'external_id',
        'external_type',
        'sync_enabled',
        'last_synced_at',
        'settings',
    ];

    /**
     * Attributi da castare
     */
    protected $casts = [
        'is_public' => 'boolean',
        'is_default' => 'boolean',
        'sync_enabled' => 'boolean',
        'last_synced_at' => 'datetime',
        'settings' => 'array',
    ];

    /**
     * Boot del model
     */
    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);

        // Genera URI CalDAV univoco
        static::creating(function ($calendar) {
            if (!$calendar->caldav_uri) {
                $calendar->caldav_uri = \Str::uuid()->toString();
            }
            if (!$calendar->caldav_ctag) {
                $calendar->caldav_ctag = md5(time() . rand());
            }
        });

        // Aggiorna ctag quando il calendario viene modificato
        static::updating(function ($calendar) {
            $calendar->caldav_ctag = md5(time() . rand());
        });
    }

    /**
     * Relazione con il tenant
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relazione con l'utente proprietario
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relazione con gli eventi
     */
    public function events()
    {
        return $this->hasMany(CalendarEvent::class);
    }

    /**
     * Relazione con le condivisioni
     */
    public function shares()
    {
        return $this->hasMany(CalendarShare::class);
    }

    /**
     * Relazione con le sottoscrizioni
     */
    public function subscriptions()
    {
        return $this->hasMany(CalendarSubscription::class);
    }

    /**
     * Scope per calendari pubblici
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope per calendari sincronizzabili
     */
    public function scopeSyncEnabled($query)
    {
        return $query->where('sync_enabled', true);
    }

    /**
     * Verifica se l'utente ha accesso al calendario
     */
    public function userHasAccess($userId, $permission = 'read')
    {
        // Proprietario ha sempre accesso completo
        if ($this->user_id === $userId) {
            return true;
        }

        // Calendario pubblico permette lettura
        if ($this->is_public && $permission === 'read') {
            return true;
        }

        // Verifica condivisioni
        return $this->shares()
            ->where('user_id', $userId)
            ->where(function ($query) use ($permission) {
                if ($permission === 'read') {
                    $query->whereIn('permission', ['read', 'write']);
                } else {
                    $query->where('permission', 'write');
                }
            })
            ->exists();
    }

    /**
     * Condividi il calendario con un utente
     */
    public function shareWith($userId, $permission = 'read')
    {
        return $this->shares()->updateOrCreate(
            ['user_id' => $userId],
            ['permission' => $permission]
        );
    }

    /**
     * Sincronizza con calendario esterno
     */
    public function syncWithExternal()
    {
        if (!$this->sync_enabled || !$this->external_id) {
            return false;
        }

        // La logica di sincronizzazione dipende dal tipo esterno
        // (Google Calendar, Outlook, etc.)

        $this->update(['last_synced_at' => now()]);

        return true;
    }

    /**
     * Genera feed iCal
     */
    public function generateICalFeed()
    {
        $vcalendar = new \Sabre\VObject\Component\VCalendar();

        $vcalendar->add('X-WR-CALNAME', $this->name);
        $vcalendar->add('X-WR-CALDESC', $this->description);
        $vcalendar->add('X-WR-TIMEZONE', $this->timezone ?? 'Europe/Rome');

        foreach ($this->events as $event) {
            $vevent = $vcalendar->add('VEVENT', [
                'UID' => $event->uid,
                'DTSTART' => $event->start_date,
                'DTEND' => $event->end_date,
                'SUMMARY' => $event->title,
            ]);

            if ($event->description) {
                $vevent->add('DESCRIPTION', $event->description);
            }

            if ($event->location) {
                $vevent->add('LOCATION', $event->location);
            }

            if ($event->recurrence_rule) {
                $vevent->add('RRULE', $event->recurrence_rule);
            }
        }

        return $vcalendar->serialize();
    }
}