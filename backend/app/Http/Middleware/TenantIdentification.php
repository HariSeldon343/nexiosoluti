<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Tenant;
use App\Services\TenantService;

/**
 * Middleware per identificazione tenant in ambiente localhost
 * Supporta tre modalità:
 * 1. Header HTTP (X-Tenant-ID)
 * 2. Query parameter (?tenant=)
 * 3. Session storage
 */
class TenantIdentification
{
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Gestisce la richiesta identificando il tenant
     */
    public function handle(Request $request, Closure $next)
    {
        $tenantIdentifier = null;

        // Priorità 1: Header HTTP (per API e Cloudflare tunnel)
        if ($request->hasHeader('X-Tenant-ID')) {
            $tenantIdentifier = $request->header('X-Tenant-ID');
        }
        // Priorità 2: Query parameter (per test e debug)
        elseif ($request->has('tenant')) {
            $tenantIdentifier = $request->get('tenant');
            // Salva in sessione per persistenza
            session(['tenant_id' => $tenantIdentifier]);
        }
        // Priorità 3: Sessione (per navigazione web)
        elseif (session()->has('tenant_id')) {
            $tenantIdentifier = session('tenant_id');
        }
        // Priorità 4: Path-based (per URL tipo /tenant/demo/dashboard)
        elseif ($request->segment(1) === 'tenant' && $request->segment(2)) {
            $tenantIdentifier = $request->segment(2);
        }
        // Default: usa tenant di default
        else {
            $tenantIdentifier = config('tenant.default_tenant', 'demo');
        }

        // Trova il tenant nel database
        $tenant = $this->findTenant($tenantIdentifier);

        if (!$tenant) {
            // Se è una richiesta API, ritorna errore
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Tenant non trovato',
                    'message' => 'Il tenant specificato non esiste o non è attivo'
                ], 404);
            }

            // Per richieste web, usa tenant di default
            $tenant = $this->findTenant(config('tenant.default_tenant', 'demo'));

            if (!$tenant) {
                abort(404, 'Nessun tenant disponibile');
            }
        }

        // Imposta il tenant corrente
        $this->tenantService->setTenant($tenant);

        // Aggiungi tenant info alla richiesta
        $request->merge(['tenant' => $tenant]);

        // Aggiungi header di risposta per indicare il tenant attivo
        $response = $next($request);

        if (method_exists($response, 'header')) {
            $response->header('X-Active-Tenant', $tenant->code);
        }

        return $response;
    }

    /**
     * Trova il tenant per codice o ID
     */
    protected function findTenant($identifier)
    {
        if (!$identifier) {
            return null;
        }

        return Tenant::where('is_active', true)
            ->where(function ($query) use ($identifier) {
                $query->where('code', $identifier)
                      ->orWhere('id', $identifier);
            })
            ->first();
    }
}