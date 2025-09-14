<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Tenant;
use App\Models\Scopes\TenantScope;

class TenantMiddleware
{
    /**
     * Gestisce una richiesta in entrata per isolare i dati del tenant
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Ottieni il tenant dal contesto (session, header, subdomain, etc.)
        $tenantId = $this->resolveTenantId($request);

        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant non identificato',
            ], 400);
        }

        // Verifica che il tenant esista e sia attivo
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant non trovato',
            ], 404);
        }

        if (!$tenant->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant non attivo',
            ], 403);
        }

        // Verifica la scadenza del tenant
        if ($tenant->expires_at && $tenant->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Sottoscrizione scaduta',
            ], 403);
        }

        // Imposta il tenant corrente globalmente
        app()->singleton('tenant', function () use ($tenant) {
            return $tenant;
        });

        // Imposta il tenant_id nella sessione
        session(['tenant_id' => $tenantId]);

        // Configura il database per il tenant se usa database separati
        if ($tenant->database_name) {
            $this->configureTenantDatabase($tenant);
        }

        // Applica automaticamente il TenantScope a tutti i modelli
        TenantScope::setTenantId($tenantId);

        // Aggiungi il tenant_id alla richiesta per uso nei controller
        $request->merge(['tenant_id' => $tenantId]);

        // Log dell'accesso del tenant per analytics
        $this->logTenantAccess($tenant, $request);

        return $next($request);
    }

    /**
     * Risolve l'ID del tenant dalla richiesta
     *
     * @param  \Illuminate\Http\Request  $request
     * @return int|null
     */
    protected function resolveTenantId(Request $request)
    {
        // Priorità 1: Header X-Tenant-ID
        if ($request->hasHeader('X-Tenant-ID')) {
            return $request->header('X-Tenant-ID');
        }

        // Priorità 2: Subdomain
        $host = $request->getHost();
        $subdomain = explode('.', $host)[0];

        if ($subdomain && $subdomain !== 'www' && $subdomain !== 'api') {
            $tenant = Tenant::where('subdomain', $subdomain)->first();
            if ($tenant) {
                return $tenant->id;
            }
        }

        // Priorità 3: Sessione (per utenti autenticati)
        if (session()->has('tenant_id')) {
            return session('tenant_id');
        }

        // Priorità 4: Token JWT
        if (auth()->check()) {
            $user = auth()->user();
            if ($user->tenant_id) {
                return $user->tenant_id;
            }

            // Se l'utente ha accesso a più tenant, usa il default
            $defaultTenant = $user->tenants()->wherePivot('is_default', true)->first();
            if ($defaultTenant) {
                return $defaultTenant->id;
            }

            // Altrimenti usa il primo tenant disponibile
            $firstTenant = $user->tenants()->first();
            if ($firstTenant) {
                return $firstTenant->id;
            }
        }

        // Priorità 5: Query parameter (solo per alcune route pubbliche)
        if ($request->has('tenant') && $this->isPublicRoute($request)) {
            return $request->query('tenant');
        }

        return null;
    }

    /**
     * Configura il database per il tenant
     *
     * @param  \App\Models\Tenant  $tenant
     * @return void
     */
    protected function configureTenantDatabase(Tenant $tenant)
    {
        // Configura la connessione al database del tenant
        config([
            'database.connections.tenant' => [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => $tenant->database_name,
                'username' => $tenant->database_username ?? env('DB_USERNAME'),
                'password' => $tenant->database_password ?? env('DB_PASSWORD'),
                'unix_socket' => env('DB_SOCKET', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
            ]
        ]);

        // Imposta la connessione tenant come default
        \DB::setDefaultConnection('tenant');

        // Riconnetti per applicare le nuove configurazioni
        \DB::reconnect('tenant');
    }

    /**
     * Verifica se la route è pubblica
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function isPublicRoute(Request $request)
    {
        $publicRoutes = [
            'api/public/*',
            'api/auth/register',
            'api/auth/login',
        ];

        foreach ($publicRoutes as $route) {
            if ($request->is($route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log dell'accesso del tenant per analytics
     *
     * @param  \App\Models\Tenant  $tenant
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function logTenantAccess(Tenant $tenant, Request $request)
    {
        // Log asincrono per non rallentare la richiesta
        dispatch(function () use ($tenant, $request) {
            \DB::table('tenant_access_logs')->insert([
                'tenant_id' => $tenant->id,
                'user_id' => auth()->id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'method' => $request->method(),
                'path' => $request->path(),
                'created_at' => now(),
            ]);

            // Aggiorna statistiche del tenant
            $tenant->increment('total_requests');
            $tenant->update(['last_activity_at' => now()]);
        })->afterResponse();
    }
}