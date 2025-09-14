<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\User;
use Carbon\Carbon;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\ArrayTransformerConfig;
use Sabre\VObject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CalendarService
{
    /**
     * Espande eventi ricorrenti in un range di date
     */
    public function expandRecurringEvent(CalendarEvent $event, Carbon $startDate, Carbon $endDate): Collection
    {
        if (!$event->recurrence_rule) {
            return collect([$event]);
        }

        try {
            // Usa libreria Recurr per gestire regole RRULE
            $rule = new Rule($event->recurrence_rule, $event->start_date);

            // Configura transformer
            $config = new ArrayTransformerConfig();
            $config->enableLastDayOfMonthFix();
            $transformer = new ArrayTransformer($config);

            // Imposta constraint per il range di date
            $constraint = new \Recurr\Transformer\Constraint\BetweenConstraint(
                new \DateTime($startDate->toDateTimeString()),
                new \DateTime($endDate->toDateTimeString()),
                true
            );

            // Genera occorrenze
            $occurrences = $transformer->transform($rule, $constraint);

            $expandedEvents = collect();

            foreach ($occurrences as $occurrence) {
                $clonedEvent = clone $event;

                // Calcola durata originale
                $duration = $event->start_date->diffInMinutes($event->end_date);

                // Imposta nuove date per l'occorrenza
                $clonedEvent->start_date = Carbon::instance($occurrence->getStart());
                $clonedEvent->end_date = Carbon::instance($occurrence->getStart())->addMinutes($duration);

                // Aggiungi flag per indicare che è un'occorrenza
                $clonedEvent->is_occurrence = true;
                $clonedEvent->occurrence_date = $clonedEvent->start_date;

                $expandedEvents->push($clonedEvent);
            }

            return $expandedEvents;
        } catch (\Exception $e) {
            Log::error('Error expanding recurring event', [
                'event_id' => $event->id,
                'error' => $e->getMessage()
            ]);

            // In caso di errore, restituisci solo l'evento originale
            return collect([$event]);
        }
    }

    /**
     * Sincronizza con CalDAV
     */
    public function syncWithCalDAV(string $url, string $username, string $password, string $calendarId, User $user): array
    {
        try {
            $results = [
                'synced_count' => 0,
                'errors' => [],
                'events' => []
            ];

            // Configura client CalDAV
            $response = Http::withBasicAuth($username, $password)
                ->withHeaders([
                    'Content-Type' => 'application/xml',
                    'Depth' => '1'
                ])
                ->send('PROPFIND', $url);

            if (!$response->successful()) {
                throw new \Exception('Impossibile connettersi al server CalDAV');
            }

            // Ottieni eventi dal server CalDAV
            $calendarData = $this->fetchCalDAVEvents($url, $username, $password);

            foreach ($calendarData as $eventData) {
                try {
                    $event = $this->parseICalEvent($eventData, $calendarId, $user);
                    if ($event) {
                        $results['events'][] = $event;
                        $results['synced_count']++;
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = $e->getMessage();
                }
            }

            return $results;
        } catch (\Exception $e) {
            Log::error('CalDAV sync failed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Recupera eventi da CalDAV
     */
    private function fetchCalDAVEvents(string $url, string $username, string $password): array
    {
        $reportXml = '<?xml version="1.0" encoding="utf-8" ?>
            <C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
                <D:prop>
                    <D:getetag/>
                    <C:calendar-data/>
                </D:prop>
                <C:filter>
                    <C:comp-filter name="VCALENDAR">
                        <C:comp-filter name="VEVENT"/>
                    </C:comp-filter>
                </C:filter>
            </C:calendar-query>';

        $response = Http::withBasicAuth($username, $password)
            ->withHeaders([
                'Content-Type' => 'application/xml',
                'Depth' => '1'
            ])
            ->withBody($reportXml, 'application/xml')
            ->send('REPORT', $url);

        if (!$response->successful()) {
            throw new \Exception('Impossibile recuperare eventi dal server CalDAV');
        }

        // Parsa la risposta XML
        $xml = simplexml_load_string($response->body());
        $xml->registerXPathNamespace('D', 'DAV:');
        $xml->registerXPathNamespace('C', 'urn:ietf:params:xml:ns:caldav');

        $events = [];
        foreach ($xml->xpath('//D:response') as $response) {
            $calendarData = (string) $response->xpath('.//C:calendar-data')[0];
            if ($calendarData) {
                $events[] = $calendarData;
            }
        }

        return $events;
    }

    /**
     * Parsa evento iCal
     */
    private function parseICalEvent(string $icalData, string $calendarId, User $user): ?CalendarEvent
    {
        try {
            $vcalendar = VObject\Reader::read($icalData);

            if (!isset($vcalendar->VEVENT)) {
                return null;
            }

            $vevent = $vcalendar->VEVENT;

            // Estrai dati evento
            $eventData = [
                'calendar_id' => $calendarId,
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'title' => (string) $vevent->SUMMARY,
                'description' => isset($vevent->DESCRIPTION) ? (string) $vevent->DESCRIPTION : null,
                'location' => isset($vevent->LOCATION) ? (string) $vevent->LOCATION : null,
                'start_date' => $this->parseICalDateTime($vevent->DTSTART),
                'end_date' => $this->parseICalDateTime($vevent->DTEND ?? $vevent->DTSTART),
                'all_day' => !$vevent->DTSTART->hasTime(),
                'external_id' => (string) $vevent->UID,
                'status' => $this->mapICalStatus($vevent->STATUS ?? 'CONFIRMED'),
                'created_by' => $user->id
            ];

            // Gestione ricorrenza
            if (isset($vevent->RRULE)) {
                $eventData['recurrence_rule'] = (string) $vevent->RRULE;
            }

            // Cerca evento esistente per evitare duplicati
            $existingEvent = CalendarEvent::where('external_id', $eventData['external_id'])
                ->where('user_id', $user->id)
                ->first();

            if ($existingEvent) {
                // Aggiorna evento esistente
                $existingEvent->update($eventData);
                return $existingEvent;
            } else {
                // Crea nuovo evento
                return CalendarEvent::create($eventData);
            }
        } catch (\Exception $e) {
            Log::error('Error parsing iCal event', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Parsa data/ora iCal
     */
    private function parseICalDateTime($dtProperty): Carbon
    {
        if (!$dtProperty) {
            return now();
        }

        $dt = $dtProperty->getDateTime();
        return Carbon::instance($dt);
    }

    /**
     * Mappa stato iCal a stato interno
     */
    private function mapICalStatus(?string $status): string
    {
        $statusMap = [
            'CONFIRMED' => 'confirmed',
            'TENTATIVE' => 'tentative',
            'CANCELLED' => 'cancelled'
        ];

        return $statusMap[$status] ?? 'confirmed';
    }

    /**
     * Genera file iCal per esportazione
     */
    public function generateICalendar(Collection $events): string
    {
        $vcalendar = new VObject\Component\VCalendar([
            'PRODID' => '-//NexioSolution//Calendar//IT',
            'VERSION' => '2.0',
            'CALSCALE' => 'GREGORIAN'
        ]);

        foreach ($events as $event) {
            $vevent = $vcalendar->add('VEVENT', [
                'UID' => $event->external_id ?? 'event-' . $event->id . '@nexiosolution.com',
                'SUMMARY' => $event->title,
                'DTSTART' => $event->start_date,
                'DTEND' => $event->end_date
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

            // Aggiungi partecipanti
            foreach ($event->attendees as $attendee) {
                $vevent->add('ATTENDEE', 'mailto:' . $attendee->email, [
                    'CN' => $attendee->name,
                    'PARTSTAT' => $this->mapAttendeeStatus($attendee->pivot->status)
                ]);
            }

            // Aggiungi promemoria
            foreach ($event->reminders as $reminder) {
                $valarm = $vevent->add('VALARM');
                $valarm->add('ACTION', 'DISPLAY');
                $valarm->add('TRIGGER', '-PT' . $reminder->minutes_before . 'M');
                $valarm->add('DESCRIPTION', 'Promemoria: ' . $event->title);
            }
        }

        return $vcalendar->serialize();
    }

    /**
     * Mappa stato partecipante per iCal
     */
    private function mapAttendeeStatus(string $status): string
    {
        $statusMap = [
            'pending' => 'NEEDS-ACTION',
            'accepted' => 'ACCEPTED',
            'declined' => 'DECLINED',
            'tentative' => 'TENTATIVE'
        ];

        return $statusMap[$status] ?? 'NEEDS-ACTION';
    }

    /**
     * Verifica conflitti di calendario
     */
    public function checkConflicts(User $user, Carbon $startDate, Carbon $endDate, ?int $excludeEventId = null): Collection
    {
        $query = CalendarEvent::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('attendees', function ($q2) use ($user) {
                      $q2->where('user_id', $user->id)
                         ->where('status', '!=', 'declined');
                  });
            })
            ->where(function ($q) use ($startDate, $endDate) {
                // Eventi che si sovrappongono
                $q->where(function ($q2) use ($startDate, $endDate) {
                    $q2->whereBetween('start_date', [$startDate, $endDate])
                       ->orWhereBetween('end_date', [$startDate, $endDate])
                       ->orWhere(function ($q3) use ($startDate, $endDate) {
                           $q3->where('start_date', '<=', $startDate)
                              ->where('end_date', '>=', $endDate);
                       });
                });
            })
            ->where('status', '!=', 'cancelled');

        if ($excludeEventId) {
            $query->where('id', '!=', $excludeEventId);
        }

        return $query->get();
    }

    /**
     * Suggerisce slot liberi per meeting
     */
    public function suggestFreeSlots(
        array $users,
        int $durationMinutes,
        Carbon $searchStart,
        Carbon $searchEnd,
        array $preferences = []
    ): Collection {
        $freeSlots = collect();

        // Parametri di ricerca
        $slotDuration = $durationMinutes;
        $workingHoursStart = $preferences['working_hours_start'] ?? '09:00';
        $workingHoursEnd = $preferences['working_hours_end'] ?? '18:00';
        $excludeWeekends = $preferences['exclude_weekends'] ?? true;

        $current = $searchStart->copy();

        while ($current->lessThan($searchEnd)) {
            // Salta weekend se richiesto
            if ($excludeWeekends && $current->isWeekend()) {
                $current->addDay()->setTimeFromTimeString($workingHoursStart);
                continue;
            }

            // Controlla solo orario lavorativo
            if ($current->format('H:i') < $workingHoursStart) {
                $current->setTimeFromTimeString($workingHoursStart);
            }

            if ($current->format('H:i') >= $workingHoursEnd) {
                $current->addDay()->setTimeFromTimeString($workingHoursStart);
                continue;
            }

            $slotEnd = $current->copy()->addMinutes($slotDuration);

            // Verifica disponibilità per tutti gli utenti
            $isAvailable = true;
            foreach ($users as $user) {
                $conflicts = $this->checkConflicts($user, $current, $slotEnd);
                if ($conflicts->isNotEmpty()) {
                    $isAvailable = false;
                    break;
                }
            }

            if ($isAvailable) {
                $freeSlots->push([
                    'start' => $current->copy(),
                    'end' => $slotEnd->copy(),
                    'duration' => $slotDuration
                ]);
            }

            // Avanza di 30 minuti
            $current->addMinutes(30);
        }

        return $freeSlots->take(10); // Restituisci massimo 10 suggerimenti
    }

    /**
     * Invia inviti calendario via email
     */
    public function sendCalendarInvite(CalendarEvent $event, array $attendees): bool
    {
        try {
            // Genera iCal per l'evento
            $icalContent = $this->generateICalendar(collect([$event]));

            foreach ($attendees as $attendee) {
                // TODO: Implementare invio email con allegato iCal
                // Mail::to($attendee->email)
                //     ->send(new CalendarInvite($event, $icalContent));
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error sending calendar invites', [
                'event_id' => $event->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}