<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TenantController extends Controller
{
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Lista tenant per admin
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Verifica permessi admin
            if (!Auth::user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato'
                ], 403);
            }

            $query = Tenant::query()
                ->with(['owner', 'subscription']);

            // Filtri
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('domain', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('plan')) {
                $query->where('plan', $request->input('plan'));
            }

            // Ordinamento
            $sortBy = $request->input('sort_by', 'created_at');
            $sortDesc = $request->boolean('sort_desc', true);
            $query->orderBy($sortBy, $sortDesc ? 'desc' : 'asc');

            // Paginazione
            $perPage = $request->input('per_page', 15);
            $tenants = $query->paginate($perPage);

            // Aggiungi statistiche per ogni tenant
            $tenants->getCollection()->transform(function ($tenant) {
                $tenant->statistics = [
                    'users_count' => $tenant->users()->count(),
                    'companies_count' => $tenant->companies()->count(),
                    'storage_used' => $this->calculateStorageUsed($tenant->id),
                    'last_activity' => $tenant->audit_logs()
                        ->latest()
                        ->value('created_at')
                ];
                return $tenant;
            });

            Log::info('Tenant list retrieved', [
                'admin_id' => Auth::id(),
                'count' => $tenants->total()
            ]);

            return response()->json([
                'success' => true,
                'data' => $tenants
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching tenants', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel recupero dei tenant'
            ], 500);
        }
    }

    /**
     * Dettagli tenant
     */
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Verifica permessi
            if (!$user->hasRole('super-admin') && $user->tenant_id !== (int)$id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato'
                ], 403);
            }

            $tenant = Tenant::with([
                'owner',
                'subscription',
                'companies' => function ($query) {
                    $query->withCount('users');
                }
            ])->findOrFail($id);

            // Aggiungi informazioni dettagliate
            $tenant->statistics = [
                'users' => [
                    'total' => $tenant->users()->count(),
                    'active' => $tenant->users()->where('is_active', true)->count(),
                    'by_role' => $tenant->users()
                        ->select('role', DB::raw('count(*) as count'))
                        ->groupBy('role')
                        ->pluck('count', 'role')
                ],
                'companies' => [
                    'total' => $tenant->companies()->count(),
                    'active' => $tenant->companies()->where('is_active', true)->count()
                ],
                'storage' => [
                    'used' => $this->calculateStorageUsed($tenant->id),
                    'limit' => $tenant->storage_limit,
                    'percentage' => $tenant->storage_limit > 0
                        ? round(($this->calculateStorageUsed($tenant->id) / $tenant->storage_limit) * 100, 2)
                        : 0
                ],
                'activity' => [
                    'last_login' => $tenant->users()
                        ->whereNotNull('last_login_at')
                        ->max('last_login_at'),
                    'daily_active_users' => $tenant->users()
                        ->where('last_login_at', '>=', now()->subDay())
                        ->count()
                ]
            ];

            Log::info('Tenant details retrieved', [
                'tenant_id' => $id,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $tenant
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching tenant details', [
                'tenant_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel recupero dei dettagli del tenant'
            ], 500);
        }
    }

    /**
     * Aggiorna branding e impostazioni tenant
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Verifica permessi
            if (!$user->hasRole(['super-admin', 'tenant-admin']) ||
                (!$user->hasRole('super-admin') && $user->tenant_id !== (int)$id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato'
                ], 403);
            }

            $tenant = Tenant::findOrFail($id);

            DB::beginTransaction();

            // Aggiorna dati base
            $tenant->fill($request->only([
                'name',
                'email',
                'phone',
                'address',
                'city',
                'country',
                'postal_code',
                'timezone'
            ]));

            // Gestione logo
            if ($request->hasFile('logo')) {
                // Elimina logo precedente
                if ($tenant->logo) {
                    Storage::disk('public')->delete($tenant->logo);
                }

                $logoPath = $request->file('logo')->store(
                    "tenants/{$id}/branding",
                    'public'
                );
                $tenant->logo = $logoPath;
            }

            // Aggiorna impostazioni branding
            if ($request->has('branding')) {
                $branding = $tenant->branding ?? [];
                $branding = array_merge($branding, $request->input('branding'));
                $tenant->branding = $branding;
            }

            // Aggiorna impostazioni generali
            if ($request->has('settings')) {
                $settings = $tenant->settings ?? [];
                $settings = array_merge($settings, $request->input('settings'));
                $tenant->settings = $settings;
            }

            $tenant->save();

            // Log attività
            Log::info('Tenant updated', [
                'tenant_id' => $id,
                'updated_by' => Auth::id(),
                'changes' => $tenant->getChanges()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tenant aggiornato con successo',
                'data' => $tenant
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error updating tenant', [
                'tenant_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'aggiornamento del tenant'
            ], 500);
        }
    }

    /**
     * Aggiorna tema tenant
     */
    public function updateTheme(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'primary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'secondary_color' => 'string|regex:/^#[0-9A-Fa-f]{6}$/',
                'accent_color' => 'string|regex:/^#[0-9A-Fa-f]{6}$/',
                'dark_mode' => 'boolean',
                'font_family' => 'string|in:Inter,Roboto,Open Sans,Lato,Poppins',
                'border_radius' => 'string|in:none,small,medium,large',
                'sidebar_style' => 'string|in:default,compact,minimal'
            ]);

            $tenant = $this->tenantService->getCurrentTenant();

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant non trovato'
                ], 404);
            }

            // Verifica permessi
            $user = Auth::user();
            if (!$user->hasRole(['super-admin', 'tenant-admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorizzato'
                ], 403);
            }

            // Aggiorna tema
            $theme = $tenant->theme ?? [];
            $theme = array_merge($theme, $request->all());
            $tenant->theme = $theme;
            $tenant->save();

            Log::info('Tenant theme updated', [
                'tenant_id' => $tenant->id,
                'updated_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tema aggiornato con successo',
                'data' => $theme
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating tenant theme', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'aggiornamento del tema'
            ], 500);
        }
    }

    /**
     * Cambia tenant corrente (per utenti multi-tenant)
     */
    public function switchTenant(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'tenant_id' => 'required|exists:tenants,id'
            ]);

            $user = Auth::user();
            $newTenantId = $request->input('tenant_id');

            // Verifica che l'utente abbia accesso al tenant
            if (!$user->is_multi_tenant && $user->tenant_id !== $newTenantId) {
                // Verifica se l'utente ha accesso tramite aziende condivise
                $hasAccess = DB::table('company_user')
                    ->join('companies', 'company_user.company_id', '=', 'companies.id')
                    ->where('company_user.user_id', $user->id)
                    ->where('companies.tenant_id', $newTenantId)
                    ->exists();

                if (!$hasAccess) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Non hai accesso a questo tenant'
                    ], 403);
                }
            }

            // Aggiorna tenant corrente nella sessione
            session(['current_tenant_id' => $newTenantId]);

            // Aggiorna ultimo tenant utilizzato
            $user->last_tenant_id = $newTenantId;
            $user->save();

            // Carica il nuovo tenant
            $tenant = Tenant::with(['companies'])->find($newTenantId);

            // Log cambio tenant
            Log::info('User switched tenant', [
                'user_id' => $user->id,
                'from_tenant' => $user->tenant_id,
                'to_tenant' => $newTenantId
            ]);

            // Genera nuovo token con tenant context
            $token = $user->createToken('auth-token', ['tenant_id' => $newTenantId])->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Tenant cambiato con successo',
                'data' => [
                    'tenant' => $tenant,
                    'token' => $token,
                    'permissions' => $user->getAllPermissions()->pluck('name')
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error switching tenant', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nel cambio tenant'
            ], 500);
        }
    }

    /**
     * Calcola lo spazio di storage utilizzato dal tenant
     */
    private function calculateStorageUsed($tenantId): int
    {
        try {
            $path = "tenants/{$tenantId}";
            $size = 0;

            if (Storage::disk('public')->exists($path)) {
                $files = Storage::disk('public')->allFiles($path);
                foreach ($files as $file) {
                    $size += Storage::disk('public')->size($file);
                }
            }

            return $size;
        } catch (\Exception $e) {
            Log::warning('Could not calculate storage for tenant', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
}