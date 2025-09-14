<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Lista aziende del tenant
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $query = Company::where('tenant_id', $tenant->id)
                ->with(['users', 'parent']);

            // Se l'utente non è admin, mostra solo le sue aziende
            if (!$user->hasRole(['super-admin', 'tenant-admin', 'company-admin'])) {
                $query->whereHas('users', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            }

            // Filtri
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('vat_number', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('parent_id')) {
                $query->where('parent_id', $request->input('parent_id'));
            }

            // Solo aziende root
            if ($request->boolean('only_root')) {
                $query->whereNull('parent_id');
            }

            // Ordinamento
            $sortBy = $request->input('sort_by', 'name');
            $sortDesc = $request->boolean('sort_desc', false);
            $query->orderBy($sortBy, $sortDesc ? 'desc' : 'asc');

            // Paginazione
            $perPage = $request->input('per_page', 15);
            $companies = $query->paginate($perPage);

            // Aggiungi conteggio utenti
            $companies->getCollection()->transform(function ($company) {
                $company->users_count = $company->users()->count();
                $company->has_children = $company->children()->exists();
                return $company;
            });

            Log::info('Companies list retrieved', [
                'user_id' => Auth::id(),
                'tenant_id' => $tenant->id,
                'count' => $companies->total()
            ]);

            return response()->json([
                'success' => true,
                'data' => $companies
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching companies', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel recupero delle aziende'
            ], 500);
        }
    }

    /**
     * Crea nuova azienda
     */
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Verifica permessi
            if (!$user->hasRole(['super-admin', 'tenant-admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato a creare aziende'
                ], 403);
            }

            $tenant = $this->tenantService->getCurrentTenant();

            DB::beginTransaction();

            // Crea azienda
            $data = $request->validated();
            $data['tenant_id'] = $tenant->id;
            $data['created_by'] = $user->id;

            // Genera codice azienda se non fornito
            if (empty($data['code'])) {
                $data['code'] = $this->generateCompanyCode($data['name']);
            }

            $company = Company::create($data);

            // Assegna l'utente creatore come admin dell'azienda
            $company->users()->attach($user->id, [
                'role' => 'admin',
                'joined_at' => now()
            ]);

            // Gestione campi personalizzati
            if ($request->has('custom_fields')) {
                $company->custom_fields = $request->input('custom_fields');
                $company->save();
            }

            // Log creazione
            Log::info('Company created', [
                'company_id' => $company->id,
                'tenant_id' => $tenant->id,
                'created_by' => $user->id
            ]);

            DB::commit();

            // Carica relazioni
            $company->load(['users', 'parent']);

            return response()->json([
                'success' => true,
                'message' => 'Azienda creata con successo',
                'data' => $company
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error creating company', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nella creazione dell\'azienda'
            ], 500);
        }
    }

    /**
     * Dettagli azienda
     */
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $company = Company::where('tenant_id', $tenant->id)
                ->with(['users.roles', 'parent', 'children'])
                ->findOrFail($id);

            // Verifica permessi
            if (!$user->hasRole(['super-admin', 'tenant-admin', 'company-admin'])) {
                $hasAccess = $company->users()->where('user_id', $user->id)->exists();
                if (!$hasAccess) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Non autorizzato'
                    ], 403);
                }
            }

            // Aggiungi statistiche
            $company->statistics = [
                'users_count' => $company->users()->count(),
                'active_users' => $company->users()
                    ->where('is_active', true)
                    ->count(),
                'tasks_count' => $company->tasks()->count(),
                'open_tasks' => $company->tasks()
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->count(),
                'files_count' => $company->files()->count(),
                'storage_used' => $this->calculateCompanyStorage($company->id)
            ];

            Log::info('Company details retrieved', [
                'company_id' => $id,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $company
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching company details', [
                'company_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel recupero dei dettagli dell\'azienda'
            ], 500);
        }
    }

    /**
     * Aggiorna dati azienda
     */
    public function update(UpdateCompanyRequest $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $company = Company::where('tenant_id', $tenant->id)->findOrFail($id);

            // Verifica permessi
            if (!$user->hasRole(['super-admin', 'tenant-admin'])) {
                $isCompanyAdmin = $company->users()
                    ->wherePivot('user_id', $user->id)
                    ->wherePivot('role', 'admin')
                    ->exists();

                if (!$isCompanyAdmin) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Non autorizzato a modificare questa azienda'
                    ], 403);
                }
            }

            DB::beginTransaction();

            // Aggiorna dati
            $company->fill($request->validated());

            // Gestione campi personalizzati
            if ($request->has('custom_fields')) {
                $customFields = $company->custom_fields ?? [];
                $customFields = array_merge($customFields, $request->input('custom_fields'));
                $company->custom_fields = $customFields;
            }

            $company->updated_by = $user->id;
            $company->save();

            // Log aggiornamento
            Log::info('Company updated', [
                'company_id' => $id,
                'updated_by' => $user->id,
                'changes' => $company->getChanges()
            ]);

            DB::commit();

            // Ricarica relazioni
            $company->load(['users', 'parent']);

            return response()->json([
                'success' => true,
                'message' => 'Azienda aggiornata con successo',
                'data' => $company
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error updating company', [
                'company_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'aggiornamento dell\'azienda'
            ], 500);
        }
    }

    /**
     * Elimina azienda
     */
    public function destroy($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            // Verifica permessi
            if (!$user->hasRole(['super-admin', 'tenant-admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato a eliminare aziende'
                ], 403);
            }

            $company = Company::where('tenant_id', $tenant->id)->findOrFail($id);

            // Verifica che non ci siano aziende figlie
            if ($company->children()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossibile eliminare un\'azienda con sotto-aziende'
                ], 422);
            }

            // Verifica che non ci siano utenti attivi
            if ($company->users()->where('is_active', true)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossibile eliminare un\'azienda con utenti attivi'
                ], 422);
            }

            DB::beginTransaction();

            // Soft delete per mantenere storico
            $company->deleted_by = $user->id;
            $company->save();
            $company->delete();

            // Log eliminazione
            Log::warning('Company deleted', [
                'company_id' => $id,
                'deleted_by' => $user->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Azienda eliminata con successo'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error deleting company', [
                'company_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'eliminazione dell\'azienda'
            ], 500);
        }
    }

    /**
     * Assegna utenti ad azienda
     */
    public function assignUsers(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'users' => 'required|array',
                'users.*.user_id' => 'required|exists:users,id',
                'users.*.role' => 'required|in:admin,manager,user'
            ]);

            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $company = Company::where('tenant_id', $tenant->id)->findOrFail($id);

            // Verifica permessi
            if (!$user->hasRole(['super-admin', 'tenant-admin'])) {
                $isCompanyAdmin = $company->users()
                    ->wherePivot('user_id', $user->id)
                    ->wherePivot('role', 'admin')
                    ->exists();

                if (!$isCompanyAdmin) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Non autorizzato a gestire gli utenti di questa azienda'
                    ], 403);
                }
            }

            DB::beginTransaction();

            foreach ($request->input('users') as $userData) {
                // Verifica che l'utente appartenga al tenant
                $targetUser = User::where('tenant_id', $tenant->id)
                    ->findOrFail($userData['user_id']);

                // Aggiorna o crea associazione
                $company->users()->syncWithoutDetaching([
                    $userData['user_id'] => [
                        'role' => $userData['role'],
                        'joined_at' => now()
                    ]
                ]);

                // Log assegnazione
                Log::info('User assigned to company', [
                    'company_id' => $id,
                    'user_id' => $userData['user_id'],
                    'role' => $userData['role'],
                    'assigned_by' => $user->id
                ]);
            }

            DB::commit();

            // Ricarica utenti
            $company->load('users');

            return response()->json([
                'success' => true,
                'message' => 'Utenti assegnati con successo',
                'data' => $company->users
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error assigning users to company', [
                'company_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'assegnazione degli utenti'
            ], 500);
        }
    }

    /**
     * Gestione campi personalizzati
     */
    public function customFields(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'fields' => 'required|array',
                'fields.*.name' => 'required|string',
                'fields.*.type' => 'required|in:text,number,date,select,checkbox',
                'fields.*.required' => 'boolean',
                'fields.*.options' => 'array'
            ]);

            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            // Solo admin possono gestire campi personalizzati
            if (!$user->hasRole(['super-admin', 'tenant-admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato'
                ], 403);
            }

            $company = Company::where('tenant_id', $tenant->id)->findOrFail($id);

            // Aggiorna definizione campi personalizzati
            $customFieldsDefinition = $company->custom_fields_definition ?? [];
            $customFieldsDefinition = array_merge($customFieldsDefinition, [
                'fields' => $request->input('fields'),
                'updated_at' => now(),
                'updated_by' => $user->id
            ]);

            $company->custom_fields_definition = $customFieldsDefinition;
            $company->save();

            Log::info('Company custom fields updated', [
                'company_id' => $id,
                'updated_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Campi personalizzati aggiornati con successo',
                'data' => $customFieldsDefinition
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating custom fields', [
                'company_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'aggiornamento dei campi personalizzati'
            ], 500);
        }
    }

    /**
     * Genera codice azienda univoco
     */
    private function generateCompanyCode(string $name): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3));
        $suffix = rand(1000, 9999);
        $code = $prefix . $suffix;

        // Verifica unicità
        while (Company::where('code', $code)->exists()) {
            $suffix = rand(1000, 9999);
            $code = $prefix . $suffix;
        }

        return $code;
    }

    /**
     * Calcola storage utilizzato dall'azienda
     */
    private function calculateCompanyStorage($companyId): int
    {
        try {
            return DB::table('files')
                ->where('company_id', $companyId)
                ->sum('size') ?? 0;
        } catch (\Exception $e) {
            Log::warning('Could not calculate company storage', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
}