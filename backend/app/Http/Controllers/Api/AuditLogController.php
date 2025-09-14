<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use League\Csv\Writer;

class AuditLogController extends Controller
{
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Lista log con filtri
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            // Verifica permessi
            if (!$user->hasPermissionTo('view-audit-logs')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato a visualizzare i log di audit'
                ], 403);
            }

            $query = AuditLog::where('tenant_id', $tenant->id)
                ->with(['user', 'company']);

            // Filtri
            if ($request->has('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }

            if ($request->has('company_id')) {
                $query->where('company_id', $request->input('company_id'));
            }

            if ($request->has('action')) {
                $query->where('action', $request->input('action'));
            }

            if ($request->has('model_type')) {
                $query->where('model_type', $request->input('model_type'));
            }

            if ($request->has('model_id')) {
                $query->where('model_id', $request->input('model_id'));
            }

            if ($request->has('ip_address')) {
                $query->where('ip_address', $request->input('ip_address'));
            }

            // Filtri data
            if ($request->has('date_from')) {
                $query->where('created_at', '>=', Carbon::parse($request->input('date_from')));
            }

            if ($request->has('date_to')) {
                $query->where('created_at', '<=', Carbon::parse($request->input('date_to'))->endOfDay());
            }

            // Ricerca testuale
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                      ->orWhere('old_values', 'like', "%{$search}%")
                      ->orWhere('new_values', 'like', "%{$search}%");
                });
            }

            // Ordinamento
            $sortBy = $request->input('sort_by', 'created_at');
            $sortDesc = $request->boolean('sort_desc', true);
            $query->orderBy($sortBy, $sortDesc ? 'desc' : 'asc');

            // Paginazione
            $perPage = $request->input('per_page', 50);
            $logs = $query->paginate($perPage);

            // Aggiungi informazioni extra
            $logs->getCollection()->transform(function ($log) {
                // Formatta azione in modo leggibile
                $log->action_label = $this->formatAction($log->action);

                // Colore per tipo di azione
                $log->action_color = $this->getActionColor($log->action);

                // Formatta modifiche
                if ($log->old_values && $log->new_values) {
                    $log->changes = $this->formatChanges(
                        json_decode($log->old_values, true),
                        json_decode($log->new_values, true)
                    );
                }

                // Tempo relativo
                $log->time_ago = $log->created_at->diffForHumans();

                return $log;
            });

            // Statistiche generali
            $statistics = [
                'total_logs' => $query->count(),
                'unique_users' => $query->distinct('user_id')->count('user_id'),
                'actions_today' => $query->whereDate('created_at', today())->count(),
                'most_common_action' => $query->select('action', DB::raw('count(*) as count'))
                    ->groupBy('action')
                    ->orderBy('count', 'desc')
                    ->first()
            ];

            Log::info('Audit logs retrieved', [
                'user_id' => $user->id,
                'filters' => $request->all(),
                'count' => $logs->total()
            ]);

            return response()->json([
                'success' => true,
                'data' => $logs,
                'statistics' => $statistics
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching audit logs', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel recupero dei log di audit'
            ], 500);
        }
    }

    /**
     * Esporta log
     */
    public function export(Request $request)
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            // Verifica permessi
            if (!$user->hasPermissionTo('export-audit-logs')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato a esportare i log di audit'
                ], 403);
            }

            $request->validate([
                'format' => 'required|in:csv,json,pdf',
                'date_from' => 'required|date',
                'date_to' => 'required|date|after_or_equal:date_from'
            ]);

            $query = AuditLog::where('tenant_id', $tenant->id)
                ->with(['user', 'company'])
                ->whereBetween('created_at', [
                    Carbon::parse($request->input('date_from')),
                    Carbon::parse($request->input('date_to'))->endOfDay()
                ]);

            // Applica altri filtri se presenti
            if ($request->has('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }

            if ($request->has('action')) {
                $query->where('action', $request->input('action'));
            }

            $logs = $query->get();

            // Log esportazione
            Log::info('Audit logs exported', [
                'user_id' => $user->id,
                'format' => $request->input('format'),
                'count' => $logs->count(),
                'date_range' => [
                    'from' => $request->input('date_from'),
                    'to' => $request->input('date_to')
                ]
            ]);

            switch ($request->input('format')) {
                case 'csv':
                    return $this->exportToCsv($logs);

                case 'json':
                    return response()->json([
                        'success' => true,
                        'data' => $logs,
                        'exported_at' => now(),
                        'exported_by' => $user->name
                    ])
                    ->header('Content-Disposition', 'attachment; filename="audit_logs_' . now()->format('Y-m-d') . '.json"');

                case 'pdf':
                    // TODO: Implementare esportazione PDF
                    return response()->json([
                        'success' => false,
                        'message' => 'Esportazione PDF non ancora implementata'
                    ], 501);

                default:
                    throw new \Exception('Formato non supportato');
            }
        } catch (\Exception $e) {
            Log::error('Error exporting audit logs', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'esportazione dei log'
            ], 500);
        }
    }

    /**
     * Elimina log vecchi (con creazione summary)
     */
    public function deleteLogs(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            // Solo super-admin può eliminare log
            if (!$user->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo super-admin può eliminare i log di audit'
                ], 403);
            }

            $request->validate([
                'older_than_days' => 'required|integer|min:30',
                'create_summary' => 'boolean'
            ]);

            $cutoffDate = now()->subDays($request->input('older_than_days'));

            DB::beginTransaction();

            // Query per i log da eliminare
            $logsToDelete = AuditLog::where('tenant_id', $tenant->id)
                ->where('created_at', '<', $cutoffDate);

            // Crea summary se richiesto
            if ($request->boolean('create_summary', true)) {
                $summary = $this->createAuditSummary($logsToDelete->get(), $cutoffDate);

                // Salva summary
                DB::table('audit_summaries')->insert([
                    'tenant_id' => $tenant->id,
                    'period_start' => $logsToDelete->min('created_at'),
                    'period_end' => $cutoffDate,
                    'total_logs' => $summary['total_logs'],
                    'summary_data' => json_encode($summary),
                    'created_by' => $user->id,
                    'created_at' => now()
                ]);
            }

            // Conta log da eliminare
            $deleteCount = $logsToDelete->count();

            // Elimina log
            $logsToDelete->delete();

            // Log eliminazione
            Log::warning('Audit logs deleted', [
                'deleted_by' => $user->id,
                'count' => $deleteCount,
                'older_than' => $cutoffDate,
                'summary_created' => $request->boolean('create_summary', true)
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$deleteCount} log eliminati con successo",
                'data' => [
                    'deleted_count' => $deleteCount,
                    'summary_created' => $request->boolean('create_summary', true)
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error deleting audit logs', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'eliminazione dei log'
            ], 500);
        }
    }

    /**
     * Statistiche accessi e attività
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            // Verifica permessi
            if (!$user->hasPermissionTo('view-audit-statistics')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato a visualizzare le statistiche'
                ], 403);
            }

            $request->validate([
                'period' => 'in:today,week,month,year',
                'group_by' => 'in:hour,day,week,month'
            ]);

            $period = $request->input('period', 'month');
            $groupBy = $request->input('group_by', 'day');

            // Determina range date
            $startDate = $this->getPeriodStartDate($period);
            $endDate = now();

            $baseQuery = AuditLog::where('tenant_id', $tenant->id)
                ->whereBetween('created_at', [$startDate, $endDate]);

            // Statistiche generali
            $statistics = [
                'period' => [
                    'from' => $startDate->format('Y-m-d'),
                    'to' => $endDate->format('Y-m-d')
                ],
                'total_actions' => (clone $baseQuery)->count(),
                'unique_users' => (clone $baseQuery)->distinct('user_id')->count('user_id'),
                'unique_ips' => (clone $baseQuery)->distinct('ip_address')->count('ip_address'),
            ];

            // Azioni per tipo
            $statistics['actions_by_type'] = (clone $baseQuery)
                ->select('action', DB::raw('count(*) as count'))
                ->groupBy('action')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();

            // Utenti più attivi
            $statistics['most_active_users'] = (clone $baseQuery)
                ->select('user_id', DB::raw('count(*) as action_count'))
                ->with('user:id,name,email')
                ->groupBy('user_id')
                ->orderBy('action_count', 'desc')
                ->limit(10)
                ->get();

            // Modelli più modificati
            $statistics['most_modified_models'] = (clone $baseQuery)
                ->select('model_type', DB::raw('count(*) as count'))
                ->whereNotNull('model_type')
                ->groupBy('model_type')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();

            // Timeline attività
            $statistics['activity_timeline'] = $this->getActivityTimeline(
                $baseQuery,
                $groupBy,
                $startDate,
                $endDate
            );

            // Orari di punta
            $statistics['peak_hours'] = (clone $baseQuery)
                ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('count(*) as count'))
                ->groupBy('hour')
                ->orderBy('hour')
                ->get()
                ->map(function ($item) {
                    return [
                        'hour' => sprintf('%02d:00', $item->hour),
                        'count' => $item->count
                    ];
                });

            // Pattern di accesso sospetti
            $statistics['suspicious_patterns'] = $this->detectSuspiciousPatterns($baseQuery);

            Log::info('Audit statistics generated', [
                'user_id' => $user->id,
                'period' => $period
            ]);

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating audit statistics', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nella generazione delle statistiche'
            ], 500);
        }
    }

    /**
     * Formatta azione in modo leggibile
     */
    private function formatAction(string $action): string
    {
        $actions = [
            'create' => 'Creazione',
            'update' => 'Modifica',
            'delete' => 'Eliminazione',
            'login' => 'Accesso',
            'logout' => 'Disconnessione',
            'view' => 'Visualizzazione',
            'download' => 'Download',
            'upload' => 'Upload',
            'share' => 'Condivisione',
            'approve' => 'Approvazione',
            'reject' => 'Rifiuto',
            'assign' => 'Assegnazione',
            'export' => 'Esportazione'
        ];

        return $actions[$action] ?? ucfirst($action);
    }

    /**
     * Ottiene colore per tipo di azione
     */
    private function getActionColor(string $action): string
    {
        $colors = [
            'create' => 'green',
            'update' => 'blue',
            'delete' => 'red',
            'login' => 'indigo',
            'logout' => 'gray',
            'view' => 'cyan',
            'download' => 'purple',
            'upload' => 'teal',
            'share' => 'orange',
            'approve' => 'emerald',
            'reject' => 'rose',
            'assign' => 'violet'
        ];

        return $colors[$action] ?? 'gray';
    }

    /**
     * Formatta le modifiche in modo leggibile
     */
    private function formatChanges(?array $oldValues, ?array $newValues): array
    {
        if (!$oldValues || !$newValues) {
            return [];
        }

        $changes = [];
        $allKeys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

        foreach ($allKeys as $key) {
            $oldValue = $oldValues[$key] ?? null;
            $newValue = $newValues[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changes[] = [
                    'field' => $key,
                    'old_value' => $oldValue,
                    'new_value' => $newValue
                ];
            }
        }

        return $changes;
    }

    /**
     * Esporta log in formato CSV
     */
    private function exportToCsv($logs)
    {
        $csv = Writer::createFromString();

        // Header
        $csv->insertOne([
            'Data/Ora',
            'Utente',
            'Azione',
            'Modello',
            'ID Modello',
            'Descrizione',
            'IP',
            'User Agent'
        ]);

        // Righe
        foreach ($logs as $log) {
            $csv->insertOne([
                $log->created_at->format('Y-m-d H:i:s'),
                $log->user ? $log->user->name : 'Sistema',
                $this->formatAction($log->action),
                $log->model_type,
                $log->model_id,
                $log->description,
                $log->ip_address,
                $log->user_agent
            ]);
        }

        return response($csv->toString(), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit_logs_' . now()->format('Y-m-d') . '.csv"'
        ]);
    }

    /**
     * Crea summary dei log da eliminare
     */
    private function createAuditSummary($logs, $cutoffDate): array
    {
        return [
            'total_logs' => $logs->count(),
            'date_range' => [
                'from' => $logs->min('created_at'),
                'to' => $cutoffDate
            ],
            'actions_count' => $logs->groupBy('action')
                ->map->count()
                ->toArray(),
            'users_count' => $logs->groupBy('user_id')
                ->map->count()
                ->toArray(),
            'models_count' => $logs->groupBy('model_type')
                ->map->count()
                ->toArray()
        ];
    }

    /**
     * Ottiene data inizio periodo
     */
    private function getPeriodStartDate(string $period): Carbon
    {
        switch ($period) {
            case 'today':
                return now()->startOfDay();
            case 'week':
                return now()->startOfWeek();
            case 'month':
                return now()->startOfMonth();
            case 'year':
                return now()->startOfYear();
            default:
                return now()->subMonth();
        }
    }

    /**
     * Genera timeline attività
     */
    private function getActivityTimeline($query, $groupBy, $startDate, $endDate): array
    {
        $format = match($groupBy) {
            'hour' => 'Y-m-d H:00',
            'day' => 'Y-m-d',
            'week' => 'Y-W',
            'month' => 'Y-m',
            default => 'Y-m-d'
        };

        return (clone $query)
            ->select(
                DB::raw("DATE_FORMAT(created_at, '{$format}') as period"),
                DB::raw('count(*) as count')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();
    }

    /**
     * Rileva pattern sospetti
     */
    private function detectSuspiciousPatterns($query): array
    {
        $suspicious = [];

        // Accessi fuori orario
        $afterHours = (clone $query)
            ->where('action', 'login')
            ->where(function ($q) {
                $q->whereTime('created_at', '<', '07:00:00')
                  ->orWhereTime('created_at', '>', '20:00:00');
            })
            ->count();

        if ($afterHours > 0) {
            $suspicious[] = [
                'type' => 'after_hours_access',
                'count' => $afterHours,
                'severity' => 'low'
            ];
        }

        // Molte eliminazioni
        $deletions = (clone $query)
            ->where('action', 'delete')
            ->where('created_at', '>', now()->subHour())
            ->count();

        if ($deletions > 10) {
            $suspicious[] = [
                'type' => 'excessive_deletions',
                'count' => $deletions,
                'severity' => 'medium'
            ];
        }

        // Accessi da IP multipli
        $multipleIps = (clone $query)
            ->select('user_id', DB::raw('count(DISTINCT ip_address) as ip_count'))
            ->where('action', 'login')
            ->where('created_at', '>', now()->subDay())
            ->groupBy('user_id')
            ->having('ip_count', '>', 3)
            ->get();

        if ($multipleIps->isNotEmpty()) {
            $suspicious[] = [
                'type' => 'multiple_ip_access',
                'users' => $multipleIps->pluck('user_id'),
                'severity' => 'high'
            ];
        }

        return $suspicious;
    }
}