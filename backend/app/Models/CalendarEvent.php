<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Scopes\TenantScope;
use App\Models\Traits\HasTenant;

class CalendarEvent extends Model
{
    use HasFactory, SoftDeletes, HasTenant;

    /**
     * Attributi assegnabili in massa
     */
    protected $fillable = [
        'tenant_id',
        'calendar_id',
        'uid',
        'title',
        'description',
        'location',
        'start_date',
        'end_date',
        'all_day',
        'recurrence_rule',
        'recurrence_id',
        'recurrence_exceptions',
        'status',
        'visibility',
        'busy_status',
        'priority',
        'url',
        'organizer_id',
        'attendees',
        'reminders',
        'categories',
        'attachments',
        'custom_fields',
        'caldav_data',
        'caldav_etag',
        'external_id',
        'external_type',
        'color',
        'conference_data',
    ];

    /**
     * Attributi da castare
     */
    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'all_day' => 'boolean',
        'recurrence_exceptions' => 'array',
        'attendees' => 'array',
        'reminders' => 'array',
        'categories' => 'array',
        'attachments' => 'array',
        'custom_fields' => 'array',
        'conference_data' => 'array',
        'priority' => 'integer',
    ];

    /**
     * Valori di default
     */
    protected $attributes = [
        'status' => 'confirmed',
        'visibility' => 'default',
        'busy_status' => 'busy',
        'priority' => 0,
    ];

    /**
     * Boot del model
     */
    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($event) {
            // Genera UID univoco se non presente
            if (!$event->uid) {
                $event->uid = \Str::uuid()->toString() . '@nexiosolution.com';
            }

            // Genera etag per CalDAV
            if (!$event->caldav_etag) {
                $event->caldav_etag = md5($event->uid . time());
            }
        });

        static::updating(function ($event) {
            // Aggiorna etag quando l'evento viene modificato
            $event->caldav_etag = md5($event->uid . time());
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
     * Relazione con il calendario
     */
    public function calendar()
    {
        return $this->belongsTo(Calendar::class);
    }

    /**
     * Relazione con l'organizzatore
     */
    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * Relazione con gli inviti
     */
    public function invitations()
    {
        return $this->hasMany(EventInvitation::class);
    }

    /**
     * Relazione con i commenti
     */
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Scope per eventi futuri
     */
    public function scopeFuture($query)
    {
        return $query->where('start_date', '>=', now());
    }

    /**
     * Scope per eventi passati
     */
    public function scopePast($query)
    {
        return $query->where('end_date', '<', now());
    }

    /**
     * Scope per eventi in corso
     */
    public function scopeOngoing($query)
    {
        $now = now();
        return $query->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now);
    }

    /**
     * Scope per eventi ricorrenti
     */
    public function scopeRecurring($query)
    {
        return $query->whereNotNull('recurrence_rule');
    }

    /**
     * Verifica se l'evento è ricorrente
     */
    public function isRecurring()
    {
        return !empty($this->recurrence_rule);
    }

    /**
     * Verifica se l'evento è tutto il giorno
     */
    public function isAllDay()
    {
        return $this->all_day;
    }

    /**
     * Ottieni le occorrenze dell'evento ricorrente
     */
    public function getOccurrences($startDate, $endDate)
    {
        if (!$this->isRecurring()) {
            return collect([$this]);
        }

        // Usa la libreria Sabre per calcolare le occorrenze
        $rrule = new \Sabre\VObject\Recur\RRuleIterator(
            $this->recurrence_rule,
            $this->start_date
        );

        $occurrences = collect();

        while ($rrule->valid() && $rrule->current() <= $endDate) {
            if ($rrule->current() >= $startDate) {
                // Salta le eccezioni
                if (!in_array($rrule->current()->format('Y-m-d'), $this->recurrence_exceptions ?? [])) {
                    $occurrences->push($rrule->current());
                }
            }
            $rrule->next();
        }

        return $occurrences;
    }

    /**
     * Invia inviti agli attendees
     */
    public function sendInvitations()
    {
        $attendees = $this->attendees ?? [];

        foreach ($attendees as $attendee) {
            $invitation = $this->invitations()->create([
                'tenant_id' => $this->tenant_id,
                'user_id' => $attendee['user_id'] ?? null,
                'email' => $attendee['email'],
                'name' => $attendee['name'] ?? null,
                'status' => 'pending',
                'token' => \Str::random(32),
            ]);

            // Invia email di invito
            \Mail::to($attendee['email'])->queue(new \App\Mail\EventInvitation($invitation));
        }
    }

    /**
     * Aggiungi un reminder
     */
    public function addReminder($minutes, $method = 'email')
    {
        $reminders = $this->reminders ?? [];
        $reminders[] = [
            'minutes' => $minutes,
            'method' => $method,
            'sent' => false,
        ];

        $this->update(['reminders' => $reminders]);
    }

    /**
     * Genera dati per conferenza online
     */
    public function generateConferenceData($provider = 'internal')
    {
        $conferenceData = [
            'provider' => $provider,
            'url' => null,
            'access_code' => null,
            'password' => null,
        ];

        switch ($provider) {
            case 'internal':
                $conferenceData['url'] = route('conference.join', \Str::uuid());
                $conferenceData['access_code'] = rand(100000, 999999);
                break;
            case 'zoom':
                // Integrazione con Zoom API
                break;
            case 'teams':
                // Integrazione con Microsoft Teams
                break;
            case 'meet':
                // Integrazione con Google Meet
                break;
        }

        $this->update(['conference_data' => $conferenceData]);

        return $conferenceData;
    }

    /**
     * Converti in formato iCal
     */
    public function toICal()
    {
        $vcalendar = new \Sabre\VObject\Component\VCalendar();
        $vevent = $vcalendar->add('VEVENT');

        $vevent->add('UID', $this->uid);
        $vevent->add('DTSTART', $this->start_date);
        $vevent->add('DTEND', $this->end_date);
        $vevent->add('SUMMARY', $this->title);

        if ($this->description) {
            $vevent->add('DESCRIPTION', $this->description);
        }

        if ($this->location) {
            $vevent->add('LOCATION', $this->location);
        }

        if ($this->recurrence_rule) {
            $vevent->add('RRULE', $this->recurrence_rule);
        }

        if ($this->organizer) {
            $vevent->add('ORGANIZER', 'mailto:' . $this->organizer->email);
        }

        foreach ($this->attendees ?? [] as $attendee) {
            $vevent->add('ATTENDEE', 'mailto:' . $attendee['email']);
        }

        return $vcalendar->serialize();
    }
}