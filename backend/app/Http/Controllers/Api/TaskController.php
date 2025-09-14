<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Models\Task;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TaskController extends Controller
{
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Lista task con filtri
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $query = Task::where('tenant_id', $tenant->id)
                ->with(['creator', 'assignees', 'company', 'project', 'tags']);

            // Filtra per visibilità utente
            if (!$user->hasPermissionTo('view-all-tasks')) {
                $query->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id)
                      ->orWhereHas('assignees', function ($q2) use ($user) {
                          $q2->where('user_id', $user->id);
                      })
                      ->orWhereHas('company', function ($q2) use ($user) {
                          $q2->whereHas('users', function ($q3) use ($user) {
                              $q3->where('user_id', $user->id);
                          });
                      });
                });
            }

            // Filtri
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            }

            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('priority')) {
                $query->where('priority', $request->input('priority'));
            }

            if ($request->has('assignee_id')) {
                $query->whereHas('assignees', function ($q) use ($request) {
                    $q->where('user_id', $request->input('assignee_id'));
                });
            }

            if ($request->has('company_id')) {
                $query->where('company_id', $request->input('company_id'));
            }

            if ($request->has('project_id')) {
                $query->where('project_id', $request->input('project_id'));
            }

            if ($request->has('tag')) {
                $query->whereHas('tags', function ($q) use ($request) {
                    $q->where('name', $request->input('tag'));
                });
            }

            // Filtri date
            if ($request->has('due_date_from')) {
                $query->where('due_date', '>=', $request->input('due_date_from'));
            }

            if ($request->has('due_date_to')) {
                $query->where('due_date', '<=', $request->input('due_date_to'));
            }

            // Solo task scaduti
            if ($request->boolean('overdue')) {
                $query->where('due_date', '<', now())
                      ->whereNotIn('status', ['completed', 'cancelled']);
            }

            // Ordinamento
            $sortBy = $request->input('sort_by', 'created_at');
            $sortDesc = $request->boolean('sort_desc', true);
            $query->orderBy($sortBy, $sortDesc ? 'desc' : 'asc');

            // Paginazione
            $perPage = $request->input('per_page', 15);
            $tasks = $query->paginate($perPage);

            // Aggiungi informazioni extra
            $tasks->getCollection()->transform(function ($task) {
                $task->is_overdue = $task->due_date &&
                    Carbon::parse($task->due_date)->isPast() &&
                    !in_array($task->status, ['completed', 'cancelled']);

                $task->assignees_count = $task->assignees()->count();
                $task->comments_count = $task->comments()->count();
                $task->attachments_count = $task->attachments()->count();

                // Calcola progresso per task con sotto-task
                if ($task->subtasks()->exists()) {
                    $totalSubtasks = $task->subtasks()->count();
                    $completedSubtasks = $task->subtasks()
                        ->where('status', 'completed')
                        ->count();
                    $task->calculated_progress = $totalSubtasks > 0
                        ? round(($completedSubtasks / $totalSubtasks) * 100)
                        : 0;
                }

                return $task;
            });

            Log::info('Tasks list retrieved', [
                'user_id' => $user->id,
                'count' => $tasks->total()
            ]);

            return response()->json([
                'success' => true,
                'data' => $tasks
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching tasks', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel recupero dei task'
            ], 500);
        }
    }

    /**
     * Crea task
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            DB::beginTransaction();

            // Prepara dati task
            $data = $request->validated();
            $data['tenant_id'] = $tenant->id;
            $data['created_by'] = $user->id;

            // Genera codice task
            if (empty($data['code'])) {
                $data['code'] = $this->generateTaskCode($tenant->id);
            }

            // Crea task
            $task = Task::create($data);

            // Assegna utenti
            if ($request->has('assignees')) {
                foreach ($request->input('assignees') as $assigneeId) {
                    $task->assignees()->attach($assigneeId, [
                        'assigned_at' => now(),
                        'assigned_by' => $user->id
                    ]);

                    // Notifica assegnazione
                    // TODO: Implementare notifica
                }
            }

            // Aggiungi tag
            if ($request->has('tags')) {
                foreach ($request->input('tags') as $tagName) {
                    $task->tags()->firstOrCreate(['name' => $tagName]);
                }
            }

            // Gestione date multiple (occorrenze non consecutive)
            if ($request->has('occurrences')) {
                foreach ($request->input('occurrences') as $occurrence) {
                    $task->occurrences()->create([
                        'date' => $occurrence['date'],
                        'notes' => $occurrence['notes'] ?? null
                    ]);
                }
            }

            // Crea sotto-task se forniti
            if ($request->has('subtasks')) {
                foreach ($request->input('subtasks') as $subtaskData) {
                    $task->subtasks()->create([
                        'title' => $subtaskData['title'],
                        'description' => $subtaskData['description'] ?? null,
                        'status' => 'pending',
                        'tenant_id' => $tenant->id,
                        'created_by' => $user->id
                    ]);
                }
            }

            // Log creazione
            Log::info('Task created', [
                'task_id' => $task->id,
                'created_by' => $user->id
            ]);

            DB::commit();

            // Carica relazioni
            $task->load(['creator', 'assignees', 'company', 'tags', 'subtasks']);

            return response()->json([
                'success' => true,
                'message' => 'Task creato con successo',
                'data' => $task
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error creating task', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nella creazione del task'
            ], 500);
        }
    }

    /**
     * Dettagli task
     */
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $task = Task::where('tenant_id', $tenant->id)
                ->with([
                    'creator',
                    'assignees',
                    'company',
                    'project',
                    'tags',
                    'subtasks',
                    'comments.user',
                    'attachments',
                    'occurrences'
                ])
                ->findOrFail($id);

            // Verifica permessi
            $hasAccess = $task->created_by === $user->id ||
                        $task->assignees()->where('user_id', $user->id)->exists() ||
                        $user->hasPermissionTo('view-all-tasks');

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato a visualizzare questo task'
                ], 403);
            }

            // Aggiungi statistiche
            $task->statistics = [
                'time_tracking' => [
                    'estimated_hours' => $task->estimated_hours,
                    'actual_hours' => $task->time_logs()->sum('hours'),
                    'remaining_hours' => max(0, $task->estimated_hours - $task->time_logs()->sum('hours'))
                ],
                'activity' => [
                    'comments_count' => $task->comments()->count(),
                    'attachments_count' => $task->attachments()->count(),
                    'updates_count' => $task->activity_logs()->count(),
                    'last_update' => $task->updated_at
                ],
                'subtasks' => [
                    'total' => $task->subtasks()->count(),
                    'completed' => $task->subtasks()->where('status', 'completed')->count(),
                    'pending' => $task->subtasks()->where('status', 'pending')->count()
                ]
            ];

            // Registra visualizzazione
            DB::table('task_views')->insert([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'viewed_at' => now()
            ]);

            Log::info('Task details retrieved', [
                'task_id' => $id,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $task
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching task details', [
                'task_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel recupero dei dettagli del task'
            ], 500);
        }
    }

    /**
     * Aggiorna task
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $task = Task::where('tenant_id', $tenant->id)->findOrFail($id);

            // Verifica permessi
            $canEdit = $task->created_by === $user->id ||
                      $task->assignees()->where('user_id', $user->id)->exists() ||
                      $user->hasPermissionTo('edit-all-tasks');

            if (!$canEdit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato a modificare questo task'
                ], 403);
            }

            DB::beginTransaction();

            // Traccia modifiche per log attività
            $originalData = $task->toArray();

            // Aggiorna task
            $task->fill($request->only([
                'title',
                'description',
                'status',
                'priority',
                'due_date',
                'start_date',
                'estimated_hours',
                'progress',
                'company_id',
                'project_id'
            ]));

            $task->updated_by = $user->id;
            $task->save();

            // Aggiorna assegnazioni se fornite
            if ($request->has('assignees')) {
                $syncData = [];
                foreach ($request->input('assignees') as $assigneeId) {
                    $syncData[$assigneeId] = [
                        'assigned_at' => now(),
                        'assigned_by' => $user->id
                    ];
                }
                $task->assignees()->sync($syncData);
            }

            // Aggiorna tag se forniti
            if ($request->has('tags')) {
                $task->tags()->detach();
                foreach ($request->input('tags') as $tagName) {
                    $task->tags()->firstOrCreate(['name' => $tagName]);
                }
            }

            // Registra attività
            $task->activity_logs()->create([
                'user_id' => $user->id,
                'action' => 'updated',
                'changes' => array_diff_assoc($task->toArray(), $originalData),
                'ip_address' => $request->ip()
            ]);

            // Log aggiornamento
            Log::info('Task updated', [
                'task_id' => $id,
                'updated_by' => $user->id,
                'changes' => $task->getChanges()
            ]);

            DB::commit();

            // Ricarica relazioni
            $task->load(['creator', 'assignees', 'company', 'tags']);

            return response()->json([
                'success' => true,
                'message' => 'Task aggiornato con successo',
                'data' => $task
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error updating task', [
                'task_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'aggiornamento del task'
            ], 500);
        }
    }

    /**
     * Elimina task
     */
    public function destroy($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $task = Task::where('tenant_id', $tenant->id)->findOrFail($id);

            // Verifica permessi
            $canDelete = $task->created_by === $user->id ||
                        $user->hasPermissionTo('delete-all-tasks');

            if (!$canDelete) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato a eliminare questo task'
                ], 403);
            }

            DB::beginTransaction();

            // Soft delete per mantenere storico
            $task->deleted_by = $user->id;
            $task->save();
            $task->delete();

            // Log eliminazione
            Log::warning('Task deleted', [
                'task_id' => $id,
                'deleted_by' => $user->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Task eliminato con successo'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error deleting task', [
                'task_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'eliminazione del task'
            ], 500);
        }
    }

    /**
     * Assegna utenti al task
     */
    public function assignUsers(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'user_ids' => 'required|array',
                'user_ids.*' => 'exists:users,id'
            ]);

            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $task = Task::where('tenant_id', $tenant->id)->findOrFail($id);

            // Verifica permessi
            $canAssign = $task->created_by === $user->id ||
                        $user->hasPermissionTo('manage-task-assignments');

            if (!$canAssign) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato ad assegnare utenti a questo task'
                ], 403);
            }

            DB::beginTransaction();

            $assignedUsers = [];
            foreach ($request->input('user_ids') as $userId) {
                // Verifica che l'utente appartenga al tenant
                $assignee = User::where('tenant_id', $tenant->id)
                    ->findOrFail($userId);

                // Aggiungi assegnazione
                $task->assignees()->syncWithoutDetaching([
                    $userId => [
                        'assigned_at' => now(),
                        'assigned_by' => $user->id
                    ]
                ]);

                $assignedUsers[] = $assignee;

                // Notifica assegnazione
                // TODO: Implementare notifica
            }

            // Log assegnazioni
            Log::info('Task users assigned', [
                'task_id' => $id,
                'assigned_by' => $user->id,
                'users_count' => count($assignedUsers)
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Utenti assegnati con successo',
                'data' => $assignedUsers
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error assigning users to task', [
                'task_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'assegnazione degli utenti'
            ], 500);
        }
    }

    /**
     * Imposta date non consecutive per il task
     */
    public function setOccurrences(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'occurrences' => 'required|array',
                'occurrences.*.date' => 'required|date',
                'occurrences.*.notes' => 'nullable|string'
            ]);

            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $task = Task::where('tenant_id', $tenant->id)->findOrFail($id);

            // Verifica permessi
            if ($task->created_by !== $user->id && !$user->hasPermissionTo('edit-all-tasks')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato'
                ], 403);
            }

            DB::beginTransaction();

            // Rimuovi occorrenze esistenti
            $task->occurrences()->delete();

            // Crea nuove occorrenze
            foreach ($request->input('occurrences') as $occurrence) {
                $task->occurrences()->create([
                    'date' => $occurrence['date'],
                    'notes' => $occurrence['notes'] ?? null,
                    'is_completed' => false
                ]);
            }

            // Log impostazione occorrenze
            Log::info('Task occurrences set', [
                'task_id' => $id,
                'occurrences_count' => count($request->input('occurrences')),
                'set_by' => $user->id
            ]);

            DB::commit();

            // Ricarica occorrenze
            $task->load('occurrences');

            return response()->json([
                'success' => true,
                'message' => 'Date impostate con successo',
                'data' => $task->occurrences
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error setting task occurrences', [
                'task_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'impostazione delle date'
            ], 500);
        }
    }

    /**
     * Aggiorna progresso task
     */
    public function updateProgress(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'progress' => 'required|integer|min:0|max:100',
                'notes' => 'nullable|string'
            ]);

            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $task = Task::where('tenant_id', $tenant->id)->findOrFail($id);

            // Verifica permessi
            $canUpdate = $task->assignees()->where('user_id', $user->id)->exists() ||
                        $task->created_by === $user->id ||
                        $user->hasPermissionTo('edit-all-tasks');

            if (!$canUpdate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato'
                ], 403);
            }

            DB::beginTransaction();

            $oldProgress = $task->progress;
            $task->progress = $request->input('progress');

            // Aggiorna stato automaticamente
            if ($task->progress === 100 && $task->status !== 'completed') {
                $task->status = 'completed';
                $task->completed_at = now();
                $task->completed_by = $user->id;
            } elseif ($task->progress < 100 && $task->status === 'completed') {
                $task->status = 'in_progress';
                $task->completed_at = null;
                $task->completed_by = null;
            }

            $task->save();

            // Registra aggiornamento progresso
            $task->progress_updates()->create([
                'user_id' => $user->id,
                'from_progress' => $oldProgress,
                'to_progress' => $task->progress,
                'notes' => $request->input('notes'),
                'updated_at' => now()
            ]);

            // Log aggiornamento
            Log::info('Task progress updated', [
                'task_id' => $id,
                'from' => $oldProgress,
                'to' => $task->progress,
                'updated_by' => $user->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Progresso aggiornato con successo',
                'data' => [
                    'progress' => $task->progress,
                    'status' => $task->status
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error updating task progress', [
                'task_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'aggiornamento del progresso'
            ], 500);
        }
    }

    /**
     * Genera codice task univoco
     */
    private function generateTaskCode($tenantId): string
    {
        $prefix = 'TSK';
        $year = date('y');
        $month = date('m');

        // Trova ultimo numero per questo mese
        $lastTask = Task::where('tenant_id', $tenantId)
            ->where('code', 'like', "{$prefix}{$year}{$month}%")
            ->orderBy('code', 'desc')
            ->first();

        if ($lastTask) {
            $lastNumber = (int) substr($lastTask->code, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf("%s%s%s%04d", $prefix, $year, $month, $newNumber);
    }
}