<?php

namespace App\Services;

use Sabre\DAV;
use Sabre\CalDAV;
use Sabre\DAVACL;
use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Server CalDAV per sincronizzazione calendari
 * Supporta sincronizzazione bidirezionale con client esterni
 */
class CalDAVServer
{
    protected $server;
    protected $principalBackend;
    protected $calendarBackend;
    protected $authBackend;

    public function __construct()
    {
        // Inizializza i backend
        $this->setupBackends();

        // Crea l'albero dei nodi DAV
        $tree = $this->createNodeTree();

        // Inizializza il server DAV
        $this->server = new DAV\Server($tree);
        $this->server->setBaseUri('/dav/');

        // Aggiungi plugins
        $this->setupPlugins();
    }

    /**
     * Configura i backend per CalDAV
     */
    protected function setupBackends()
    {
        // Backend per la gestione dei principal (utenti)
        $this->principalBackend = new CalDAVPrincipalBackend();

        // Backend per la gestione dei calendari
        $this->calendarBackend = new CalDAVCalendarBackend();

        // Backend per l'autenticazione
        $this->authBackend = new CalDAVAuthBackend();
    }

    /**
     * Crea l'albero dei nodi DAV
     */
    protected function createNodeTree()
    {
        return [
            // Nodo principale per i principals
            new CalDAV\Principal\Collection($this->principalBackend),

            // Nodo principale per i calendari
            new CalDAV\CalendarRoot($this->principalBackend, $this->calendarBackend),
        ];
    }

    /**
     * Configura i plugin del server
     */
    protected function setupPlugins()
    {
        // Plugin per autenticazione Basic/Bearer
        $authPlugin = new DAV\Auth\Plugin($this->authBackend);
        $this->server->addPlugin($authPlugin);

        // Plugin per ACL (Access Control List)
        $aclPlugin = new DAVACL\Plugin();
        $this->server->addPlugin($aclPlugin);

        // Plugin per CalDAV
        $caldavPlugin = new CalDAV\Plugin();
        $this->server->addPlugin($caldavPlugin);

        // Plugin per sync-collection (sincronizzazione incrementale)
        $syncPlugin = new DAV\Sync\Plugin();
        $this->server->addPlugin($syncPlugin);

        // Plugin per browser HTML (debug)
        if (config('app.debug')) {
            $browserPlugin = new DAV\Browser\Plugin();
            $this->server->addPlugin($browserPlugin);
        }

        // Plugin per CORS
        $corsPlugin = new CORSPlugin();
        $this->server->addPlugin($corsPlugin);
    }

    /**
     * Gestisce la richiesta CalDAV
     */
    public function handleRequest()
    {
        $this->server->exec();
    }

    /**
     * Genera un UID univoco per un evento
     */
    public static function generateUID(): string
    {
        return Str::uuid()->toString() . '@nexiosolution.local';
    }

    /**
     * Genera un ETag per la sincronizzazione
     */
    public static function generateETag($data): string
    {
        return '"' . md5(serialize($data)) . '"';
    }

    /**
     * Converte un evento del database in formato iCalendar
     */
    public static function eventToVCalendar(CalendarEvent $event): string
    {
        $vcalendar = new \Sabre\VObject\Component\VCalendar([
            'PRODID' => '-//NexioSolution//CalDAV Server//IT',
        ]);

        $vevent = $vcalendar->add('VEVENT', [
            'UID' => $event->uid ?? self::generateUID(),
            'SUMMARY' => $event->title,
            'DTSTART' => $event->start_date,
            'DTEND' => $event->end_date,
        ]);

        // Aggiungi descrizione se presente
        if ($event->description) {
            $vevent->add('DESCRIPTION', $event->description);
        }

        // Aggiungi location se presente
        if ($event->location) {
            $vevent->add('LOCATION', $event->location);
        }

        // Aggiungi categoria/tipo
        if ($event->type) {
            $vevent->add('CATEGORIES', $event->type);
        }

        // Aggiungi reminder/allarme
        if ($event->reminder_minutes) {
            $valarm = $vevent->add('VALARM', [
                'ACTION' => 'DISPLAY',
                'TRIGGER' => '-PT' . $event->reminder_minutes . 'M',
            ]);
            $valarm->add('DESCRIPTION', 'Promemoria: ' . $event->title);
        }

        // Aggiungi partecipanti
        if ($event->attendees && is_array($event->attendees)) {
            foreach ($event->attendees as $attendee) {
                $vevent->add('ATTENDEE', 'mailto:' . $attendee['email'], [
                    'CN' => $attendee['name'] ?? '',
                    'PARTSTAT' => $attendee['status'] ?? 'NEEDS-ACTION',
                ]);
            }
        }

        // Aggiungi ricorrenza se presente
        if ($event->recurrence_rule) {
            $vevent->add('RRULE', $event->recurrence_rule);
        }

        // Timestamp di modifica
        $vevent->add('LAST-MODIFIED', $event->updated_at);
        $vevent->add('CREATED', $event->created_at);

        return $vcalendar->serialize();
    }

    /**
     * Converte un task del database in formato iCalendar VTODO
     */
    public static function taskToVTodo($task): string
    {
        $vcalendar = new \Sabre\VObject\Component\VCalendar([
            'PRODID' => '-//NexioSolution//CalDAV Server//IT',
        ]);

        $vtodo = $vcalendar->add('VTODO', [
            'UID' => $task->uid ?? self::generateUID(),
            'SUMMARY' => $task->title,
            'STATUS' => $task->status === 'completed' ? 'COMPLETED' : 'IN-PROCESS',
        ]);

        // Aggiungi descrizione
        if ($task->description) {
            $vtodo->add('DESCRIPTION', $task->description);
        }

        // Aggiungi data di scadenza
        if ($task->due_date) {
            $vtodo->add('DUE', $task->due_date);
        }

        // Aggiungi priorità
        if ($task->priority) {
            $priority = match($task->priority) {
                'high' => 1,
                'medium' => 5,
                'low' => 9,
                default => 0,
            };
            $vtodo->add('PRIORITY', $priority);
        }

        // Aggiungi percentuale completamento
        if ($task->percent_complete !== null) {
            $vtodo->add('PERCENT-COMPLETE', $task->percent_complete);
        }

        // Timestamp
        $vtodo->add('LAST-MODIFIED', $task->updated_at);
        $vtodo->add('CREATED', $task->created_at);

        return $vcalendar->serialize();
    }

    /**
     * Genera feed ICS privato per un utente
     */
    public function generatePrivateFeed(User $user, $calendarId = null): string
    {
        $vcalendar = new \Sabre\VObject\Component\VCalendar([
            'PRODID' => '-//NexioSolution//Private Feed//IT',
            'VERSION' => '2.0',
            'CALSCALE' => 'GREGORIAN',
            'METHOD' => 'PUBLISH',
            'X-WR-CALNAME' => $user->name . ' - NexioSolution',
            'X-WR-TIMEZONE' => config('app.timezone'),
        ]);

        // Query eventi
        $query = CalendarEvent::where('user_id', $user->id);

        if ($calendarId) {
            $query->where('calendar_id', $calendarId);
        }

        $events = $query->get();

        // Aggiungi eventi al calendario
        foreach ($events as $event) {
            $vevent = $vcalendar->add('VEVENT', [
                'UID' => $event->uid ?? self::generateUID(),
                'SUMMARY' => $event->title,
                'DTSTART' => new \DateTime($event->start_date),
                'DTEND' => new \DateTime($event->end_date),
                'DTSTAMP' => new \DateTime(),
            ]);

            if ($event->description) {
                $vevent->add('DESCRIPTION', $event->description);
            }

            if ($event->location) {
                $vevent->add('LOCATION', $event->location);
            }

            // Colore categoria
            if ($event->color) {
                $vevent->add('X-APPLE-CALENDAR-COLOR', $event->color);
                $vevent->add('X-OUTLOOK-COLOR', $event->color);
            }
        }

        return $vcalendar->serialize();
    }

    /**
     * Importa eventi da un file ICS
     */
    public function importICS(string $icsData, int $calendarId, int $userId): array
    {
        $vcalendar = \Sabre\VObject\Reader::read($icsData);
        $imported = [];
        $errors = [];

        foreach ($vcalendar->VEVENT as $vevent) {
            try {
                $event = new CalendarEvent([
                    'calendar_id' => $calendarId,
                    'user_id' => $userId,
                    'uid' => (string) $vevent->UID ?? self::generateUID(),
                    'title' => (string) $vevent->SUMMARY,
                    'description' => isset($vevent->DESCRIPTION) ? (string) $vevent->DESCRIPTION : null,
                    'location' => isset($vevent->LOCATION) ? (string) $vevent->LOCATION : null,
                    'start_date' => $vevent->DTSTART->getDateTime(),
                    'end_date' => $vevent->DTEND->getDateTime(),
                    'all_day' => !$vevent->DTSTART->hasTime(),
                ]);

                // Gestisci ricorrenza
                if (isset($vevent->RRULE)) {
                    $event->recurrence_rule = (string) $vevent->RRULE;
                }

                // Gestisci reminder
                if (isset($vevent->VALARM)) {
                    foreach ($vevent->VALARM as $valarm) {
                        if (isset($valarm->TRIGGER)) {
                            $trigger = (string) $valarm->TRIGGER;
                            // Estrai minuti dal trigger (es. -PT15M)
                            if (preg_match('/PT(\d+)M/', $trigger, $matches)) {
                                $event->reminder_minutes = (int) $matches[1];
                            }
                        }
                    }
                }

                $event->save();
                $imported[] = $event;

            } catch (\Exception $e) {
                $errors[] = [
                    'event' => (string) $vevent->SUMMARY,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'imported' => count($imported),
            'errors' => $errors,
            'events' => $imported,
        ];
    }
}

/**
 * Backend per gestione Principal (utenti)
 */
class CalDAVPrincipalBackend extends \Sabre\DAVACL\PrincipalBackend\AbstractBackend
{
    /**
     * Restituisce la lista dei principals
     */
    public function getPrincipalsByPrefix($prefixPath): array
    {
        $principals = [];

        if ($prefixPath === 'principals') {
            $users = User::all();

            foreach ($users as $user) {
                $principals[] = [
                    'uri' => 'principals/' . $user->email,
                    '{DAV:}displayname' => $user->name,
                    '{http://sabredav.org/ns}email-address' => $user->email,
                ];
            }
        }

        return $principals;
    }

    /**
     * Restituisce un principal specifico
     */
    public function getPrincipalByPath($path): ?array
    {
        $parts = explode('/', $path);

        if (count($parts) !== 2 || $parts[0] !== 'principals') {
            return null;
        }

        $user = User::where('email', $parts[1])->first();

        if (!$user) {
            return null;
        }

        return [
            'uri' => $path,
            '{DAV:}displayname' => $user->name,
            '{http://sabredav.org/ns}email-address' => $user->email,
        ];
    }

    /**
     * Aggiorna un principal
     */
    public function updatePrincipal($path, \Sabre\DAV\PropPatch $propPatch): void
    {
        // Implementare se necessario l'aggiornamento dei dati utente
    }

    /**
     * Cerca principals
     */
    public function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof'): array
    {
        $results = [];

        foreach ($searchProperties as $property => $value) {
            if ($property === '{DAV:}displayname') {
                $users = User::where('name', 'like', '%' . $value . '%')->get();
                foreach ($users as $user) {
                    $results[] = 'principals/' . $user->email;
                }
            }
        }

        return array_unique($results);
    }

    /**
     * Restituisce i gruppi di un principal
     */
    public function getGroupMemberSet($principal): array
    {
        return [];
    }

    /**
     * Restituisce i membri di un gruppo
     */
    public function getGroupMembership($principal): array
    {
        return [];
    }

    /**
     * Imposta i membri di un gruppo
     */
    public function setGroupMemberSet($principal, array $members): void
    {
        // Non implementato
    }
}

/**
 * Backend per gestione Calendari
 */
class CalDAVCalendarBackend extends \Sabre\CalDAV\Backend\AbstractBackend
{
    /**
     * Restituisce i calendari di un utente
     */
    public function getCalendarsForUser($principalUri): array
    {
        $parts = explode('/', $principalUri);
        $email = $parts[1] ?? null;

        if (!$email) {
            return [];
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            return [];
        }

        $calendars = Calendar::where('user_id', $user->id)->get();
        $result = [];

        foreach ($calendars as $calendar) {
            $result[] = [
                'id' => $calendar->id,
                'uri' => $calendar->slug ?? 'calendar-' . $calendar->id,
                'principaluri' => $principalUri,
                '{DAV:}displayname' => $calendar->name,
                '{' . CalDAV\Plugin::NS_CALDAV . '}calendar-description' => $calendar->description ?? '',
                '{' . CalDAV\Plugin::NS_CALDAV . '}calendar-timezone' => config('app.timezone'),
                '{http://apple.com/ns/ical/}calendar-color' => $calendar->color ?? '#0066CC',
                '{' . CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set' => new CalDAV\Xml\Property\SupportedCalendarComponentSet(['VEVENT', 'VTODO']),
                '{DAV:}sync-token' => $calendar->sync_token ?? '1',
            ];
        }

        return $result;
    }

    /**
     * Crea un nuovo calendario
     */
    public function createCalendar($principalUri, $calendarUri, array $properties): void
    {
        $parts = explode('/', $principalUri);
        $email = $parts[1] ?? null;

        $user = User::where('email', $email)->first();

        if (!$user) {
            throw new \Exception('Utente non trovato');
        }

        $calendar = new Calendar([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'slug' => $calendarUri,
            'name' => $properties['{DAV:}displayname'] ?? 'Nuovo Calendario',
            'description' => $properties['{' . CalDAV\Plugin::NS_CALDAV . '}calendar-description'] ?? '',
            'color' => $properties['{http://apple.com/ns/ical/}calendar-color'] ?? '#0066CC',
            'sync_token' => '1',
        ]);

        $calendar->save();
    }

    /**
     * Aggiorna un calendario
     */
    public function updateCalendar($calendarId, \Sabre\DAV\PropPatch $propPatch): void
    {
        $calendar = Calendar::find($calendarId);

        if (!$calendar) {
            return;
        }

        $propPatch->handle([
            '{DAV:}displayname',
            '{' . CalDAV\Plugin::NS_CALDAV . '}calendar-description',
            '{http://apple.com/ns/ical/}calendar-color',
        ], function($properties) use ($calendar) {
            foreach ($properties as $key => $value) {
                switch ($key) {
                    case '{DAV:}displayname':
                        $calendar->name = $value;
                        break;
                    case '{' . CalDAV\Plugin::NS_CALDAV . '}calendar-description':
                        $calendar->description = $value;
                        break;
                    case '{http://apple.com/ns/ical/}calendar-color':
                        $calendar->color = $value;
                        break;
                }
            }

            $calendar->sync_token = (string) (intval($calendar->sync_token) + 1);
            $calendar->save();

            return true;
        });
    }

    /**
     * Elimina un calendario
     */
    public function deleteCalendar($calendarId): void
    {
        $calendar = Calendar::find($calendarId);

        if ($calendar) {
            // Elimina anche tutti gli eventi associati
            CalendarEvent::where('calendar_id', $calendarId)->delete();
            $calendar->delete();
        }
    }

    /**
     * Restituisce gli oggetti del calendario (eventi/task)
     */
    public function getCalendarObjects($calendarId): array
    {
        $events = CalendarEvent::where('calendar_id', $calendarId)->get();
        $objects = [];

        foreach ($events as $event) {
            $objects[] = [
                'id' => $event->id,
                'uri' => $event->uid . '.ics',
                'lastmodified' => $event->updated_at->timestamp,
                'etag' => CalDAVServer::generateETag($event),
                'size' => strlen(CalDAVServer::eventToVCalendar($event)),
                'contenttype' => 'text/calendar',
            ];
        }

        return $objects;
    }

    /**
     * Restituisce un oggetto specifico del calendario
     */
    public function getCalendarObject($calendarId, $objectUri): ?array
    {
        $uid = str_replace('.ics', '', $objectUri);
        $event = CalendarEvent::where('calendar_id', $calendarId)
            ->where('uid', $uid)
            ->first();

        if (!$event) {
            return null;
        }

        return [
            'id' => $event->id,
            'uri' => $objectUri,
            'lastmodified' => $event->updated_at->timestamp,
            'etag' => CalDAVServer::generateETag($event),
            'calendardata' => CalDAVServer::eventToVCalendar($event),
            'size' => strlen(CalDAVServer::eventToVCalendar($event)),
            'contenttype' => 'text/calendar',
        ];
    }

    /**
     * Crea un nuovo oggetto nel calendario
     */
    public function createCalendarObject($calendarId, $objectUri, $calendarData): ?string
    {
        $vcalendar = \Sabre\VObject\Reader::read($calendarData);

        if (isset($vcalendar->VEVENT)) {
            $vevent = $vcalendar->VEVENT;

            $event = new CalendarEvent([
                'calendar_id' => $calendarId,
                'uid' => (string) $vevent->UID ?? CalDAVServer::generateUID(),
                'title' => (string) $vevent->SUMMARY,
                'description' => isset($vevent->DESCRIPTION) ? (string) $vevent->DESCRIPTION : null,
                'location' => isset($vevent->LOCATION) ? (string) $vevent->LOCATION : null,
                'start_date' => $vevent->DTSTART->getDateTime(),
                'end_date' => $vevent->DTEND->getDateTime(),
                'all_day' => !$vevent->DTSTART->hasTime(),
            ]);

            // Gestisci ricorrenza
            if (isset($vevent->RRULE)) {
                $event->recurrence_rule = (string) $vevent->RRULE;
            }

            $event->save();

            // Aggiorna sync token del calendario
            $calendar = Calendar::find($calendarId);
            if ($calendar) {
                $calendar->sync_token = (string) (intval($calendar->sync_token) + 1);
                $calendar->save();
            }

            return CalDAVServer::generateETag($event);
        }

        return null;
    }

    /**
     * Aggiorna un oggetto del calendario
     */
    public function updateCalendarObject($calendarId, $objectUri, $calendarData): ?string
    {
        $uid = str_replace('.ics', '', $objectUri);
        $event = CalendarEvent::where('calendar_id', $calendarId)
            ->where('uid', $uid)
            ->first();

        if (!$event) {
            return $this->createCalendarObject($calendarId, $objectUri, $calendarData);
        }

        $vcalendar = \Sabre\VObject\Reader::read($calendarData);

        if (isset($vcalendar->VEVENT)) {
            $vevent = $vcalendar->VEVENT;

            $event->title = (string) $vevent->SUMMARY;
            $event->description = isset($vevent->DESCRIPTION) ? (string) $vevent->DESCRIPTION : null;
            $event->location = isset($vevent->LOCATION) ? (string) $vevent->LOCATION : null;
            $event->start_date = $vevent->DTSTART->getDateTime();
            $event->end_date = $vevent->DTEND->getDateTime();
            $event->all_day = !$vevent->DTSTART->hasTime();

            if (isset($vevent->RRULE)) {
                $event->recurrence_rule = (string) $vevent->RRULE;
            }

            $event->save();

            // Aggiorna sync token del calendario
            $calendar = Calendar::find($calendarId);
            if ($calendar) {
                $calendar->sync_token = (string) (intval($calendar->sync_token) + 1);
                $calendar->save();
            }

            return CalDAVServer::generateETag($event);
        }

        return null;
    }

    /**
     * Elimina un oggetto dal calendario
     */
    public function deleteCalendarObject($calendarId, $objectUri): void
    {
        $uid = str_replace('.ics', '', $objectUri);

        CalendarEvent::where('calendar_id', $calendarId)
            ->where('uid', $uid)
            ->delete();

        // Aggiorna sync token del calendario
        $calendar = Calendar::find($calendarId);
        if ($calendar) {
            $calendar->sync_token = (string) (intval($calendar->sync_token) + 1);
            $calendar->save();
        }
    }

    /**
     * Restituisce i cambiamenti per la sincronizzazione
     */
    public function getChangesForCalendar($calendarId, $syncToken, $syncLevel, $limit = null): array
    {
        $calendar = Calendar::find($calendarId);

        if (!$calendar) {
            return null;
        }

        $currentToken = (int) $calendar->sync_token;
        $requestedToken = (int) $syncToken;

        // Se il token richiesto è uguale a quello corrente, non ci sono cambiamenti
        if ($requestedToken >= $currentToken) {
            return [
                'syncToken' => $currentToken,
                'added' => [],
                'modified' => [],
                'deleted' => [],
            ];
        }

        // Recupera eventi modificati dopo il syncToken richiesto
        $events = CalendarEvent::where('calendar_id', $calendarId)
            ->where('updated_at', '>', Carbon::createFromTimestamp($requestedToken))
            ->get();

        $added = [];
        $modified = [];

        foreach ($events as $event) {
            $uri = $event->uid . '.ics';
            if ($event->created_at->timestamp > $requestedToken) {
                $added[] = $uri;
            } else {
                $modified[] = $uri;
            }
        }

        return [
            'syncToken' => $currentToken,
            'added' => $added,
            'modified' => $modified,
            'deleted' => [], // TODO: implementare tracking delle eliminazioni
        ];
    }
}

/**
 * Backend per autenticazione CalDAV
 */
class CalDAVAuthBackend extends \Sabre\DAV\Auth\Backend\AbstractBasic
{
    /**
     * Valida le credenziali dell'utente
     */
    protected function validateUserPass($username, $password): bool
    {
        // Supporto per autenticazione Bearer token
        if (str_starts_with($password, 'Bearer ')) {
            $token = substr($password, 7);
            // Valida il token Bearer (implementare logica personalizzata)
            return $this->validateBearerToken($username, $token);
        }

        // Autenticazione Basic standard
        $user = User::where('email', $username)->first();

        if (!$user) {
            return false;
        }

        return \Hash::check($password, $user->password);
    }

    /**
     * Valida un Bearer token
     */
    protected function validateBearerToken($username, $token): bool
    {
        // Implementare validazione del token Bearer
        // Per esempio usando Laravel Sanctum o Passport
        $user = User::where('email', $username)
            ->whereHas('tokens', function($query) use ($token) {
                $query->where('token', hash('sha256', $token));
            })
            ->first();

        return $user !== null;
    }
}

/**
 * Plugin per gestione CORS
 */
class CORSPlugin extends DAV\ServerPlugin
{
    protected $server;

    public function initialize(DAV\Server $server): void
    {
        $this->server = $server;
        $server->on('beforeMethod:*', [$this, 'handleCORS']);
    }

    public function handleCORS($request, $response): bool
    {
        $response->setHeader('Access-Control-Allow-Origin', '*');
        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PROPFIND, PROPPATCH, REPORT');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, Depth, If-Match, If-None-Match, Prefer');
        $response->setHeader('Access-Control-Allow-Credentials', 'true');
        $response->setHeader('Access-Control-Max-Age', '86400');

        if ($request->getMethod() === 'OPTIONS') {
            $response->setStatus(200);
            return false;
        }

        return true;
    }
}