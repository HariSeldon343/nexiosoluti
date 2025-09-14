<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Models\Company;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Lista utenti con filtri
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            // Query base con scope tenant
            $query = User::where('tenant_id', $tenant->id)
                ->with(['roles', 'companies']);

            // Se l'utente è company-admin, mostra solo utenti delle sue aziende
            if ($user->hasRole('company-admin') && !$user->hasRole(['super-admin', 'tenant-admin'])) {
                $companyIds = $user->companies()->pluck('companies.id');
                $query->whereHas('companies', function ($q) use ($companyIds) {
                    $q->whereIn('companies.id', $companyIds);
                });
            }

            // Filtri
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            if ($request->has('role')) {
                $query->whereHas('roles', function ($q) use ($request) {
                    $q->where('name', $request->input('role'));
                });
            }

            if ($request->has('company_id')) {
                $query->whereHas('companies', function ($q) use ($request) {
                    $q->where('companies.id', $request->input('company_id'));
                });
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('is_multi_tenant')) {
                $query->where('is_multi_tenant', $request->boolean('is_multi_tenant'));
            }

            // Ordinamento
            $sortBy = $request->input('sort_by', 'name');
            $sortDesc = $request->boolean('sort_desc', false);
            $query->orderBy($sortBy, $sortDesc ? 'desc' : 'asc');

            // Paginazione
            $perPage = $request->input('per_page', 15);
            $users = $query->paginate($perPage);

            // Aggiungi informazioni extra
            $users->getCollection()->transform(function ($user) {
                $user->companies_count = $user->companies()->count();
                $user->last_activity = $user->last_login_at ?? $user->created_at;
                $user->is_online = $user->last_activity_at &&
                    $user->last_activity_at->gt(now()->subMinutes(5));
                return $user;
            });

            Log::info('Users list retrieved', [
                'tenant_id' => $tenant->id,
                'requested_by' => Auth::id(),
                'count' => $users->total()
            ]);

            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching users', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel recupero degli utenti'
            ], 500);
        }
    }

    /**
     * Crea nuovo utente
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            $currentUser = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            // Verifica permessi
            if (!$currentUser->hasPermissionTo('create-users')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato a creare utenti'
                ], 403);
            }

            DB::beginTransaction();

            // Prepara dati utente
            $data = $request->validated();
            $data['tenant_id'] = $tenant->id;
            $data['password'] = Hash::make($data['password']);
            $data['created_by'] = $currentUser->id;

            // Genera username se non fornito
            if (empty($data['username'])) {
                $data['username'] = $this->generateUsername($data['email']);
            }

            // Crea utente
            $user = User::create($data);

            // Assegna ruolo
            if ($request->has('role')) {
                $role = Role::findByName($request->input('role'));
                $user->assignRole($role);
            } else {
                // Ruolo di default
                $user->assignRole('user');
            }

            // Assegna aziende
            if ($request->has('company_ids')) {
                foreach ($request->input('company_ids') as $companyId) {
                    $company = Company::where('tenant_id', $tenant->id)
                        ->findOrFail($companyId);

                    $user->companies()->attach($companyId, [
                        'role' => 'user',
                        'joined_at' => now()
                    ]);
                }
            }

            // Invia email di benvenuto se richiesto
            if ($request->boolean('send_welcome_email', true)) {
                // TODO: Implementare invio email
                // Mail::to($user)->send(new WelcomeEmail($user, $data['password']));
            }

            // Log creazione
            Log::info('User created', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'created_by' => $currentUser->id
            ]);

            DB::commit();

            // Carica relazioni
            $user->load(['roles', 'companies']);

            return response()->json([
                'success' => true,
                'message' => 'Utente creato con successo',
                'data' => $user
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error creating user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nella creazione dell\'utente'
            ], 500);
        }
    }

    /**
     * Dettagli utente
     */
    public function show($id): JsonResponse
    {
        try {
            $currentUser = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $user = User::where('tenant_id', $tenant->id)
                ->with(['roles', 'companies', 'permissions'])
                ->findOrFail($id);

            // Verifica permessi
            if ($currentUser->id !== $user->id && !$currentUser->hasPermissionTo('view-users')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato'
                ], 403);
            }

            // Aggiungi statistiche
            $user->statistics = [
                'total_logins' => $user->login_count ?? 0,
                'last_login' => $user->last_login_at,
                'last_activity' => $user->last_activity_at,
                'tasks' => [
                    'total' => $user->tasks()->count(),
                    'completed' => $user->tasks()->where('status', 'completed')->count(),
                    'pending' => $user->tasks()->where('status', 'pending')->count()
                ],
                'files_uploaded' => $user->uploadedFiles()->count(),
                'storage_used' => $this->calculateUserStorage($user->id)
            ];

            // Aggiungi sessioni attive
            $user->active_sessions = DB::table('sessions')
                ->where('user_id', $user->id)
                ->select(['ip_address', 'user_agent', 'last_activity'])
                ->get();

            Log::info('User details retrieved', [
                'user_id' => $id,
                'requested_by' => $currentUser->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $user
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user details', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel recupero dei dettagli utente'
            ], 500);
        }
    }

    /**
     * Aggiorna utente
     */
    public function update(UpdateUserRequest $request, $id): JsonResponse
    {
        try {
            $currentUser = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            $user = User::where('tenant_id', $tenant->id)->findOrFail($id);

            // Verifica permessi
            if ($currentUser->id !== $user->id && !$currentUser->hasPermissionTo('edit-users')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato a modificare questo utente'
                ], 403);
            }

            DB::beginTransaction();

            // Aggiorna dati base
            $data = $request->validated();

            // Gestione password
            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $user->fill($data);
            $user->updated_by = $currentUser->id;
            $user->save();

            // Aggiorna avatar se fornito
            if ($request->hasFile('avatar')) {
                // TODO: Implementare upload avatar
                // $avatarPath = $request->file('avatar')->store('avatars', 'public');
                // $user->avatar = $avatarPath;
                // $user->save();
            }

            // Log aggiornamento
            Log::info('User updated', [
                'user_id' => $id,
                'updated_by' => $currentUser->id,
                'changes' => $user->getChanges()
            ]);

            DB::commit();

            // Ricarica relazioni
            $user->load(['roles', 'companies']);

            return response()->json([
                'success' => true,
                'message' => 'Utente aggiornato con successo',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error updating user', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'aggiornamento dell\'utente'
            ], 500);
        }
    }

    /**
     * Elimina utente
     */
    public function destroy($id): JsonResponse
    {
        try {
            $currentUser = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            // Non permettere auto-eliminazione
            if ($currentUser->id === (int)$id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non puoi eliminare il tuo account'
                ], 422);
            }

            // Verifica permessi
            if (!$currentUser->hasPermissionTo('delete-users')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato a eliminare utenti'
                ], 403);
            }

            $user = User::where('tenant_id', $tenant->id)->findOrFail($id);

            // Non eliminare super-admin
            if ($user->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non è possibile eliminare un super amministratore'
                ], 422);
            }

            DB::beginTransaction();

            // Trasferisci ownership di oggetti critici
            // TODO: Implementare trasferimento ownership

            // Soft delete per mantenere storico
            $user->deleted_by = $currentUser->id;
            $user->save();
            $user->delete();

            // Log eliminazione
            Log::warning('User deleted', [
                'user_id' => $id,
                'deleted_by' => $currentUser->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Utente eliminato con successo'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error deleting user', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'eliminazione dell\'utente'
            ], 500);
        }
    }

    /**
     * Assegna ruolo a utente
     */
    public function assignRole(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'role' => 'required|string|exists:roles,name'
            ]);

            $currentUser = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            // Verifica permessi
            if (!$currentUser->hasPermissionTo('manage-roles')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato a gestire i ruoli'
                ], 403);
            }

            $user = User::where('tenant_id', $tenant->id)->findOrFail($id);
            $newRole = $request->input('role');

            // Non permettere modifica ruolo super-admin
            if ($user->hasRole('super-admin') || $newRole === 'super-admin') {
                if (!$currentUser->hasRole('super-admin')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Solo un super-admin può gestire ruoli super-admin'
                    ], 403);
                }
            }

            DB::beginTransaction();

            // Rimuovi ruoli esistenti e assegna nuovo
            $user->syncRoles([$newRole]);

            // Log cambio ruolo
            Log::info('User role changed', [
                'user_id' => $id,
                'new_role' => $newRole,
                'changed_by' => $currentUser->id
            ]);

            DB::commit();

            // Ricarica ruoli e permessi
            $user->load(['roles', 'permissions']);

            return response()->json([
                'success' => true,
                'message' => 'Ruolo assegnato con successo',
                'data' => [
                    'user' => $user,
                    'permissions' => $user->getAllPermissions()->pluck('name')
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error assigning role', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'assegnazione del ruolo'
            ], 500);
        }
    }

    /**
     * Assegna aziende a utente (per utenti speciali)
     */
    public function assignCompanies(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'company_ids' => 'required|array',
                'company_ids.*' => 'exists:companies,id',
                'role' => 'in:admin,manager,user'
            ]);

            $currentUser = Auth::user();
            $tenant = $this->tenantService->getCurrentTenant();

            // Verifica permessi
            if (!$currentUser->hasPermissionTo('manage-users')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato'
                ], 403);
            }

            $user = User::where('tenant_id', $tenant->id)->findOrFail($id);
            $role = $request->input('role', 'user');

            DB::beginTransaction();

            // Sincronizza aziende
            $syncData = [];
            foreach ($request->input('company_ids') as $companyId) {
                // Verifica che l'azienda appartenga al tenant
                $company = Company::where('tenant_id', $tenant->id)
                    ->findOrFail($companyId);

                $syncData[$companyId] = [
                    'role' => $role,
                    'joined_at' => now()
                ];
            }

            $user->companies()->sync($syncData);

            // Log assegnazione
            Log::info('User companies assigned', [
                'user_id' => $id,
                'companies' => $request->input('company_ids'),
                'assigned_by' => $currentUser->id
            ]);

            DB::commit();

            // Ricarica aziende
            $user->load('companies');

            return response()->json([
                'success' => true,
                'message' => 'Aziende assegnate con successo',
                'data' => $user->companies
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error assigning companies', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'assegnazione delle aziende'
            ], 500);
        }
    }

    /**
     * Abilita/disabilita accesso multi-tenant
     */
    public function toggleMultiTenant(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'is_multi_tenant' => 'required|boolean',
                'allowed_tenants' => 'array',
                'allowed_tenants.*' => 'exists:tenants,id'
            ]);

            $currentUser = Auth::user();

            // Solo super-admin può gestire multi-tenant
            if (!$currentUser->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo super-admin può gestire accesso multi-tenant'
                ], 403);
            }

            $user = User::findOrFail($id);

            DB::beginTransaction();

            // Aggiorna flag multi-tenant
            $user->is_multi_tenant = $request->boolean('is_multi_tenant');

            // Se abilitato, gestisci tenant permessi
            if ($user->is_multi_tenant && $request->has('allowed_tenants')) {
                $allowedTenants = $user->allowed_tenants ?? [];
                $allowedTenants = array_unique(array_merge(
                    $allowedTenants,
                    $request->input('allowed_tenants')
                ));
                $user->allowed_tenants = $allowedTenants;
            }

            $user->save();

            // Log modifica
            Log::warning('User multi-tenant access changed', [
                'user_id' => $id,
                'is_multi_tenant' => $user->is_multi_tenant,
                'changed_by' => $currentUser->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Accesso multi-tenant aggiornato con successo',
                'data' => [
                    'is_multi_tenant' => $user->is_multi_tenant,
                    'allowed_tenants' => $user->allowed_tenants ?? []
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error toggling multi-tenant access', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nella modifica accesso multi-tenant'
            ], 500);
        }
    }

    /**
     * Genera username univoco
     */
    private function generateUsername(string $email): string
    {
        $base = explode('@', $email)[0];
        $base = preg_replace('/[^a-zA-Z0-9]/', '', $base);
        $username = strtolower($base);

        // Verifica unicità
        $counter = 1;
        $originalUsername = $username;
        while (User::where('username', $username)->exists()) {
            $username = $originalUsername . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Calcola storage utilizzato dall'utente
     */
    private function calculateUserStorage($userId): int
    {
        try {
            return DB::table('files')
                ->where('uploaded_by', $userId)
                ->sum('size') ?? 0;
        } catch (\Exception $e) {
            Log::warning('Could not calculate user storage', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
}