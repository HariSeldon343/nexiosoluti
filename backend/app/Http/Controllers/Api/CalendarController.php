<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Services\TenantService;
use App\Services\CalendarService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CalendarController extends Controller
{
    protected TenantService $tenantService;
    protected CalendarService $calendarService;

    public function __construct(
        TenantService $tenantService,
        CalendarService $calendarService
    ) {
        $this->tenantService = $tenantService;
        $this->calendarService = $calendarService;
    }

    /**
     * Lista calendari disponibili
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            // Calendari personali
            $personalCalendars = [
                [
                    'id' => 'personal_' . $user->id,
                    'name' => 'Calendario Personale',
                    'type' => 'personal',
                    'color' => $user->calendar_color ?? '#3B82F6',
                    'is_visible' => true,
                    'can_edit' => true
                ]
            ];

            // Calendari aziendali
            $companyCalendars = $user->companies->map(function ($company) use ($user) {
                $canEdit = $company->pivot->role === 'admin' ||
                          $user->hasPermissionTo('manage-company-calendar');

                return [
                    'id' => 'company_' . $company->id,
                    'name' => 'Calendario ' . $company->name,
                    'type' => 'company',
                    'company_id' => $company->id,
                    'color' => $company->calendar_color ?? '#10B981',
                    'is_visible' => true,
                    'can_edit' => $canEdit
                ];
            });

            // Calendari condivisi
            $sharedCalendars = DB::table('calendar_shares')
                ->join('users', 'calendar_shares.owner_id', '=', 'users.id')
                ->where('calendar_shares.shared_with', $user->id)
                ->where('calendar_shares.tenant_id', $tenant->id)
                ->select([
                    'calendar_shares.id',
                    'calendar_shares.calendar_name',
                    'calendar_shares.permissions',
                    'users.name as owner_name',
                    'calendar_shares.color'
                ])
                ->get()
                ->map(function ($share) {
                    return [
                        'id' => 'shared_' . $share->id,
                        'name' => $share->calendar_name,
                        'type' => 'shared',
                        'owner' => $share->owner_name,
                        'color' => $share->color ?? '#F59E0B',
                        'is_visible' => true,
                        'can_edit' => $share->permissions === 'write'
                    ];
                });

            $calendars = collect()
                ->merge($personalCalendars)
                ->merge($companyCalendars)
                ->merge($sharedCalendars);

            Log::info('Calendars list retrieved', [
                'user_id' => $user->id,
                'count' => $calendars->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => $calendars
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching calendars', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel recupero dei calendari'
            ], 500);
        }
    }

    /**
     * Eventi con range date
     */
    public function events(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'calendar_ids' => 'array',
                'calendar_ids.*' => 'string'
            ]);

            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $startDate = Carbon::parse($request->input('start_date'));
            $endDate = Carbon::parse($request->input('end_date'));
            $calendarIds = $request->input('calendar_ids', []);

            // Query base
            $query = CalendarEvent::where('tenant_id', $tenant->id)
                ->where(function ($q) use ($startDate, $endDate) {
                    // Eventi nel range
                    $q->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      // Eventi che coprono tutto il range
                      ->orWhere(function ($q2) use ($startDate, $endDate) {
                          $q2->where('start_date', '<=', $startDate)
                             ->where('end_date', '>=', $endDate);
                      })
                      // Eventi ricorrenti
                      ->orWhere(function ($q3) use ($startDate, $endDate) {
                          $q3->whereNotNull('recurrence_rule')
                             ->where('start_date', '<=', $endDate);
                      });
                })
                ->with(['creator', 'attendees']);

            // Filtra per calendari selezionati
            if (!empty($calendarIds)) {
                $query->where(function ($q) use ($calendarIds, $user) {
                    foreach ($calendarIds as $calendarId) {
                        [$type, $id] = explode('_', $calendarId);

                        switch ($type) {
                            case 'personal':
                                $q->orWhere(function ($q2) use ($id) {
                                    $q2->where('calendar_type', 'personal')
                                       ->where('user_id', $id);
                                });
                                break;

                            case 'company':
                                $q->orWhere(function ($q2) use ($id) {
                                    $q2->where('calendar_type', 'company')
                                       ->where('company_id', $id);
                                });
                                break;

                            case 'shared':
                                // TODO: Implementare eventi calendari condivisi
                                break;
                        }
                    }
                });
            } else {
                // Se non specificato, mostra tutti gli eventi accessibili
                $companyIds = $user->companies->pluck('id');

                $query->where(function ($q) use ($user, $companyIds) {
                    $q->where(function ($q2) use ($user) {
                        $q2->where('calendar_type', 'personal')
                           ->where('user_id', $user->id);
                    })
                    ->orWhere(function ($q2) use ($companyIds) {
                        $q2->where('calendar_type', 'company')
                           ->whereIn('company_id', $companyIds);
                    })
                    ->orWhereHas('attendees', function ($q2) use ($user) {
                        $q2->where('user_id', $user->id);
                    });
                });
            }

            $events = $query->get();

            // Espandi eventi ricorrenti
            $expandedEvents = collect();
            foreach ($events as $event) {
                if ($event->recurrence_rule) {
                    $occurrences = $this->calendarService->expandRecurringEvent(
                        $event,
                        $startDate,
                        $endDate
                    );
                    $expandedEvents = $expandedEvents->merge($occurrences);
                } else {
                    $expandedEvents->push($event);
                }
            }

            Log::info('Calendar events retrieved', [
                'user_id' => $user->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'events_count' => $expandedEvents->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => $expandedEvents
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching calendar events', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel recupero degli eventi'
            ], 500);
        }
    }

    /**
     * Crea evento
     */
    public function createEvent(StoreEventRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            DB::beginTransaction();

            // Prepara dati evento
            $data = $request->validated();
            $data['tenant_id'] = $tenant->id;
            $data['created_by'] = $user->id;

            // Determina tipo calendario e owner
            [$type, $id] = explode('_', $data['calendar_id']);
            $data['calendar_type'] = ($type === 'personal') ? 'personal' : 'company';

            if ($type === 'personal') {
                $data['user_id'] = $user->id;
            } else if ($type === 'company') {
                $data['company_id'] = $id;

                // Verifica permessi per calendario aziendale
                $hasPermission = $user->companies()
                    ->where('companies.id', $id)
                    ->exists();

                if (!$hasPermission) {
                    throw new \Exception('Non autorizzato a creare eventi in questo calendario');
                }
            }

            // Crea evento
            $event = CalendarEvent::create($data);

            // Aggiungi partecipanti
            if ($request->has('attendees')) {
                foreach ($request->input('attendees') as $attendeeId) {
                    $event->attendees()->attach($attendeeId, [
                        'status' => 'pending',
                        'invited_at' => now()
                    ]);

                    // Invia notifica invito
                    // TODO: Implementare notifica
                }
            }

            // Gestione reminder
            if ($request->has('reminders')) {
                foreach ($request->input('reminders') as $reminder) {
                    $event->reminders()->create([
                        'type' => $reminder['type'] ?? 'email',
                        'minutes_before' => $reminder['minutes_before'],
                        'is_sent' => false
                    ]);
                }
            }

            // Log creazione
            Log::info('Calendar event created', [
                'event_id' => $event->id,
                'created_by' => $user->id
            ]);

            DB::commit();

            // Carica relazioni
            $event->load(['creator', 'attendees', 'reminders']);

            return response()->json([
                'success' => true,
                'message' => 'Evento creato con successo',
                'data' => $event
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error creating calendar event', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nella creazione dell\'evento'
            ], 500);
        }
    }

    /**
     * Aggiorna evento
     */
    public function updateEvent(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $event = CalendarEvent::where('tenant_id', $tenant->id)
                ->findOrFail($id);

            // Verifica permessi
            $canEdit = $event->created_by === $user->id ||
                      $user->hasPermissionTo('manage-all-calendars');

            if (!$canEdit && $event->calendar_type === 'company') {
                $canEdit = $user->companies()
                    ->where('companies.id', $event->company_id)
                    ->wherePivot('role', 'admin')
                    ->exists();
            }

            if (!$canEdit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato a modificare questo evento'
                ], 403);
            }

            DB::beginTransaction();

            // Aggiorna evento
            $event->fill($request->only([
                'title',
                'description',
                'location',
                'start_date',
                'end_date',
                'all_day',
                'color',
                'recurrence_rule',
                'status'
            ]));

            $event->updated_by = $user->id;
            $event->save();

            // Aggiorna partecipanti se forniti
            if ($request->has('attendees')) {
                $event->attendees()->sync($request->input('attendees'));

                // Notifica cambiamenti ai partecipanti
                // TODO: Implementare notifiche
            }

            // Log aggiornamento
            Log::info('Calendar event updated', [
                'event_id' => $id,
                'updated_by' => $user->id,
                'changes' => $event->getChanges()
            ]);

            DB::commit();

            // Ricarica relazioni
            $event->load(['creator', 'attendees']);

            return response()->json([
                'success' => true,
                'message' => 'Evento aggiornato con successo',
                'data' => $event
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error updating calendar event', [
                'event_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'aggiornamento dell\'evento'
            ], 500);
        }
    }

    /**
     * Elimina evento
     */
    public function deleteEvent($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $event = CalendarEvent::where('tenant_id', $tenant->id)
                ->findOrFail($id);

            // Verifica permessi
            $canDelete = $event->created_by === $user->id ||
                        $user->hasPermissionTo('manage-all-calendars');

            if (!$canDelete) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato a eliminare questo evento'
                ], 403);
            }

            DB::beginTransaction();

            // Notifica cancellazione ai partecipanti
            if ($event->attendees()->exists()) {
                // TODO: Implementare notifiche cancellazione
            }

            // Elimina evento
            $event->delete();

            // Log eliminazione
            Log::info('Calendar event deleted', [
                'event_id' => $id,
                'deleted_by' => $user->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Evento eliminato con successo'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error deleting calendar event', [
                'event_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'eliminazione dell\'evento'
            ], 500);
        }
    }

    /**
     * Invita partecipanti
     */
    public function inviteAttendees(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'attendees' => 'required|array',
                'attendees.*' => 'exists:users,id',
                'message' => 'string'
            ]);

            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $event = CalendarEvent::where('tenant_id', $tenant->id)
                ->findOrFail($id);

            // Verifica permessi
            if ($event->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo il creatore può invitare partecipanti'
                ], 403);
            }

            DB::beginTransaction();

            $invitedUsers = [];
            foreach ($request->input('attendees') as $attendeeId) {
                // Verifica che l'utente appartenga al tenant
                $attendee = User::where('tenant_id', $tenant->id)
                    ->findOrFail($attendeeId);

                // Aggiungi o aggiorna invito
                $event->attendees()->syncWithoutDetaching([
                    $attendeeId => [
                        'status' => 'pending',
                        'invited_at' => now()
                    ]
                ]);

                $invitedUsers[] = $attendee;

                // Invia notifica invito
                // TODO: Implementare invio notifiche/email
            }

            // Log inviti
            Log::info('Calendar event attendees invited', [
                'event_id' => $id,
                'invited_by' => $user->id,
                'attendees_count' => count($invitedUsers)
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inviti inviati con successo',
                'data' => [
                    'invited_count' => count($invitedUsers),
                    'attendees' => $invitedUsers
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error inviting attendees', [
                'event_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'invio degli inviti'
            ], 500);
        }
    }

    /**
     * Sincronizza con CalDAV
     */
    public function syncCalDAV(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'url' => 'required|url',
                'username' => 'required|string',
                'password' => 'required|string',
                'calendar_id' => 'required|string'
            ]);

            $user = Auth::user();

            // Verifica permessi
            if (!$user->hasPermissionTo('sync-external-calendars')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato a sincronizzare calendari esterni'
                ], 403);
            }

            // Esegui sincronizzazione
            $result = $this->calendarService->syncWithCalDAV(
                $request->input('url'),
                $request->input('username'),
                $request->input('password'),
                $request->input('calendar_id'),
                $user
            );

            // Log sincronizzazione
            Log::info('CalDAV sync completed', [
                'user_id' => $user->id,
                'calendar_id' => $request->input('calendar_id'),
                'events_synced' => $result['synced_count']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sincronizzazione completata',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error syncing CalDAV', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nella sincronizzazione CalDAV'
            ], 500);
        }
    }
}