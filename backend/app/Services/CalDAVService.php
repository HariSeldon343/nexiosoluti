<?php

namespace App\Services;

use App\Models\Calendar;
use App\Models\Event;
use Sabre\DAV;
use Sabre\CalDAV;
use Sabre\DAVACL;

class CalDAVService
{
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Inizializza il server CalDAV
     */
    public function initializeServer(): DAV\Server
    {
        // Crea il backend per i calendari
        $calendarBackend = new CalDAV\Backend\Laravel(
            $this->tenantService
        );

        // Crea il backend per i principals (utenti)
        $principalBackend = new DAVACL\PrincipalBackend\Laravel(
            $this->tenantService
        );

        // Crea l'albero dei nodi DAV
        $tree = [
            new CalDAV\Principal\Collection($principalBackend),
            new CalDAV\CalendarRoot($principalBackend, $calendarBackend),
        ];

        // Crea il server DAV
        $server = new DAV\Server($tree);
        $server->setBaseUri('/caldav/');

        // Aggiungi i plugin necessari
        $server->addPlugin(new DAV\Auth\Plugin(
            new DAV\Auth\Backend\Laravel()
        ));
        $server->addPlugin(new DAVACL\Plugin());
        $server->addPlugin(new CalDAV\Plugin());
        $server->addPlugin(new DAV\Browser\Plugin());
        $server->addPlugin(new DAV\Sync\Plugin());

        // Plugin per scheduling (inviti)
        $server->addPlugin(new CalDAV\Schedule\Plugin());

        // Plugin per le sottoscrizioni
        $server->addPlugin(new CalDAV\Subscriptions\Plugin());

        // Plugin per la condivisione
        $server->addPlugin(new CalDAV\SharingPlugin());

        return $server;
    }

    /**
     * Sincronizza eventi dal database a CalDAV
     */
    public function syncEventsToCalDAV(Calendar $calendar): void
    {
        $events = $calendar->events()->get();

        foreach ($events as $event) {
            $this->createOrUpdateCalDAVEvent($event);
        }
    }

    /**
     * Crea o aggiorna un evento CalDAV
     */
    public function createOrUpdateCalDAVEvent(Event $event): void
    {
        // Genera l'UID CalDAV se non esiste
        if (!$event->caldav_uid) {
            $event->caldav_uid = $this->generateCalDAVUID();
            $event->save();
        }

        // Crea il componente VEVENT
        $vcalendar = new \Sabre\VObject\Component\VCalendar();
        $vevent = $vcalendar->add('VEVENT', [
            'UID' => $event->caldav_uid,
            'SUMMARY' => $event->title,
            'DTSTART' => $event->start_at,
            'DTEND' => $event->end_at,
            'DESCRIPTION' => $event->description,
            'LOCATION' => $event->location,
            'STATUS' => strtoupper($event->status),
            'SEQUENCE' => $event->sequence,
        ]);

        // Aggiungi ricorrenza se presente
        if ($event->is_recurring && $event->recurrence_rule) {
            $vevent->add('RRULE', $event->recurrence_rule['rrule']);
        }

        // Aggiungi partecipanti
        foreach ($event->attendees as $attendee) {
            $vevent->add('ATTENDEE', 'mailto:' . $attendee->user->email, [
                'CN' => $attendee->user->name,
                'PARTSTAT' => strtoupper($attendee->status),
                'ROLE' => strtoupper($attendee->role),
            ]);
        }

        // Aggiungi promemoria
        if ($event->reminders) {
            foreach ($event->reminders as $reminder) {
                $valarm = $vevent->add('VALARM');
                $valarm->add('ACTION', strtoupper($reminder['type'] ?? 'DISPLAY'));
                $valarm->add('TRIGGER', '-PT' . $reminder['minutes'] . 'M');

                if ($reminder['type'] === 'email') {
                    $valarm->add('DESCRIPTION', $event->title);
                }
            }
        }

        // Salva nel backend CalDAV
        $this->saveToCalDAVBackend($event->calendar_id, $event->caldav_uid, $vcalendar->serialize());
    }

    /**
     * Importa eventi da un file ICS
     */
    public function importICS(Calendar $calendar, string $icsContent): array
    {
        $vcalendar = \Sabre\VObject\Reader::read($icsContent);
        $imported = [];
        $errors = [];

        foreach ($vcalendar->VEVENT as $vevent) {
            try {
                $event = $this->createEventFromVEvent($calendar, $vevent);
                $imported[] = $event;
            } catch (\Exception $e) {
                $errors[] = [
                    'uid' => (string) $vevent->UID,
                    'summary' => (string) $vevent->SUMMARY,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
            'total' => count($vcalendar->VEVENT),
        ];
    }

    /**
     * Esporta calendario in formato ICS
     */
    public function exportICS(Calendar $calendar): string
    {
        $vcalendar = new \Sabre\VObject\Component\VCalendar([
            'PRODID' => '-//NexioSolution//CalDAV//IT',
            'VERSION' => '2.0',
            'CALSCALE' => 'GREGORIAN',
            'X-WR-CALNAME' => $calendar->name,
            'X-WR-CALDESC' => $calendar->description,
            'X-WR-TIMEZONE' => $calendar->timezone,
        ]);

        // Aggiungi tutti gli eventi
        foreach ($calendar->events as $event) {
            $vevent = $vcalendar->add('VEVENT', [
                'UID' => $event->caldav_uid ?? $this->generateCalDAVUID(),
                'SUMMARY' => $event->title,
                'DTSTART' => $event->start_at,
                'DTEND' => $event->end_at,
                'DESCRIPTION' => $event->description,
                'LOCATION' => $event->location,
                'STATUS' => strtoupper($event->status),
                'CREATED' => $event->created_at,
                'LAST-MODIFIED' => $event->updated_at,
            ]);

            // Aggiungi ricorrenza
            if ($event->is_recurring && $event->recurrence_rule) {
                $vevent->add('RRULE', $event->recurrence_rule['rrule']);
            }
        }

        return $vcalendar->serialize();
    }

    /**
     * Crea un evento dal componente VEVENT
     */
    protected function createEventFromVEvent(Calendar $calendar, $vevent): Event
    {
        $data = [
            'calendar_id' => $calendar->id,
            'tenant_id' => $calendar->tenant_id,
            'user_id' => auth()->id(),
            'title' => (string) $vevent->SUMMARY,
            'description' => (string) $vevent->DESCRIPTION,
            'location' => (string) $vevent->LOCATION,
            'start_at' => $vevent->DTSTART->getDateTime(),
            'end_at' => $vevent->DTEND->getDateTime(),
            'caldav_uid' => (string) $vevent->UID,
            'status' => strtolower((string) ($vevent->STATUS ?? 'confirmed')),
        ];

        // Gestisci eventi tutto il giorno
        if ($vevent->DTSTART->hasTime() === false) {
            $data['all_day'] = true;
        }

        // Gestisci ricorrenza
        if (isset($vevent->RRULE)) {
            $data['is_recurring'] = true;
            $data['recurrence_rule'] = ['rrule' => (string) $vevent->RRULE];
        }

        return Event::create($data);
    }

    /**
     * Genera un UID univoco per CalDAV
     */
    protected function generateCalDAVUID(): string
    {
        return uniqid('', true) . '@' . config('app.url');
    }

    /**
     * Salva nel backend CalDAV
     */
    protected function saveToCalDAVBackend(int $calendarId, string $uid, string $calendarData): void
    {
        // Implementazione specifica per salvare nel backend CalDAV
        // Questo dipende dall'implementazione del backend CalDAV personalizzato
    }

    /**
     * Ottiene l'URL CalDAV per un calendario
     */
    public function getCalDAVUrl(Calendar $calendar, User $user): string
    {
        $baseUrl = config('app.url');
        $principalUri = 'principals/' . $user->email;
        $calendarUri = $calendar->caldav_uri ?? $calendar->id;

        return "{$baseUrl}/caldav/{$principalUri}/calendars/{$calendarUri}/";
    }

    /**
     * Configura la sottoscrizione CalDAV per un client
     */
    public function getClientConfiguration(User $user): array
    {
        return [
            'server_url' => config('app.url') . '/caldav/',
            'principal_url' => config('app.url') . '/caldav/principals/' . $user->email . '/',
            'calendar_home_set' => config('app.url') . '/caldav/calendars/' . $user->email . '/',
            'username' => $user->email,
            'auth_method' => 'Bearer Token o Basic Auth',
            'supported_components' => ['VEVENT', 'VTODO'],
            'supported_reports' => [
                'calendar-multiget',
                'calendar-query',
                'free-busy-query',
                'sync-collection',
            ],
        ];
    }
}