<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\AuditLog;

class AuditMiddleware
{
    /**
     * Azioni da escludere dal logging
     *
     * @var array
     */
    protected $excludedActions = [
        'GET /api/health',
        'GET /api/status',
        'OPTIONS *',
    ];

    /**
     * Dati sensibili da mascherare nei log
     *
     * @var array
     */
    protected $sensitiveFields = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'secret',
        'api_key',
        'credit_card',
        'cvv',
        'pin',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Gestisce una richiesta in entrata
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Inizia il tracking del tempo
        $startTime = microtime(true);

        // Prepara i dati di audit prima della richiesta
        $auditData = $this->prepareAuditData($request);

        // Esegui la richiesta
        $response = $next($request);

        // Calcola il tempo di esecuzione
        $executionTime = (microtime(true) - $startTime) * 1000; // in millisecondi

        // Completa i dati di audit dopo la risposta
        $auditData = $this->completeAuditData($auditData, $response, $executionTime);

        // Determina se questa azione deve essere loggata
        if ($this->shouldLog($request, $response)) {
            $this->createAuditLog($auditData);
        }

        // Log delle performance se il tempo di esecuzione è elevato
        if ($executionTime > 1000) { // Più di 1 secondo
            $this->logSlowRequest($auditData, $executionTime);
        }

        return $response;
    }

    /**
     * Prepara i dati di audit dalla richiesta
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function prepareAuditData(Request $request)
    {
        return [
            'user_id' => auth()->id(),
            'tenant_id' => session('tenant_id'),
            'company_id' => session('company_id'),
            'action' => $request->method() . ' ' . $request->path(),
            'model' => $this->extractModelFromRoute($request),
            'model_id' => $this->extractModelIdFromRoute($request),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'route' => $request->route() ? $request->route()->getName() : null,
            'request_data' => $this->sanitizeRequestData($request->all()),
            'headers' => $this->sanitizeHeaders($request->headers->all()),
            'session_id' => session()->getId(),
            'referer' => $request->header('referer'),
        ];
    }

    /**
     * Completa i dati di audit con la risposta
     *
     * @param  array  $auditData
     * @param  mixed  $response
     * @param  float  $executionTime
     * @return array
     */
    protected function completeAuditData($auditData, $response, $executionTime)
    {
        $auditData['response_code'] = method_exists($response, 'getStatusCode')
            ? $response->getStatusCode()
            : 200;

        $auditData['response_data'] = $this->extractResponseData($response);
        $auditData['execution_time'] = $executionTime;
        $auditData['memory_usage'] = memory_get_peak_usage(true) / 1024 / 1024; // in MB
        $auditData['created_at'] = now();

        return $auditData;
    }

    /**
     * Determina se l'azione deve essere loggata
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $response
     * @return bool
     */
    protected function shouldLog(Request $request, $response)
    {
        // Non loggare le azioni escluse
        $action = $request->method() . ' ' . $request->path();
        foreach ($this->excludedActions as $excluded) {
            if (fnmatch($excluded, $action)) {
                return false;
            }
        }

        // Non loggare le richieste GET di sola lettura (opzionale)
        if ($request->isMethod('GET') && !$this->isImportantReadAction($request)) {
            return false;
        }

        // Logga sempre gli errori
        if (method_exists($response, 'getStatusCode') && $response->getStatusCode() >= 400) {
            return true;
        }

        // Logga le azioni di modifica
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return true;
        }

        // Logga le azioni importanti di lettura
        return $this->isImportantReadAction($request);
    }

    /**
     * Verifica se è un'azione di lettura importante
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function isImportantReadAction(Request $request)
    {
        $importantPaths = [
            'api/audit-logs',
            'api/users/*/permissions',
            'api/files/*/download',
            'api/reports/*',
            'api/exports/*',
        ];

        foreach ($importantPaths as $path) {
            if ($request->is($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Crea il log di audit nel database
     *
     * @param  array  $auditData
     * @return void
     */
    protected function createAuditLog($auditData)
    {
        try {
            // Usa una coda per non rallentare la risposta
            dispatch(function () use ($auditData) {
                AuditLog::create($auditData);

                // Se è un'azione critica, invia notifica immediata
                if ($this->isCriticalAction($auditData['action'])) {
                    $this->notifyCriticalAction($auditData);
                }
            })->afterResponse();

        } catch (\Exception $e) {
            // Non far fallire la richiesta se il logging fallisce
            Log::error('Failed to create audit log', [
                'error' => $e->getMessage(),
                'audit_data' => $auditData,
            ]);
        }
    }

    /**
     * Estrae il model dalla route
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function extractModelFromRoute(Request $request)
    {
        $route = $request->route();

        if (!$route) {
            return null;
        }

        // Estrae il model dal nome della route (es. users.update -> User)
        $routeName = $route->getName();
        if ($routeName) {
            $parts = explode('.', $routeName);
            if (count($parts) > 0) {
                return ucfirst(str_singular($parts[0]));
            }
        }

        // Fallback: estrae dal path
        $segments = $request->segments();
        if (count($segments) > 1 && $segments[0] === 'api') {
            return ucfirst(str_singular($segments[1]));
        }

        return null;
    }

    /**
     * Estrae l'ID del model dalla route
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    protected function extractModelIdFromRoute(Request $request)
    {
        $route = $request->route();

        if (!$route) {
            return null;
        }

        // Cerca parametri comuni
        $commonParams = ['id', 'user', 'task', 'file', 'company', 'tenant'];

        foreach ($commonParams as $param) {
            if ($route->hasParameter($param)) {
                return $route->parameter($param);
            }
        }

        // Cerca il primo parametro numerico
        foreach ($route->parameters() as $key => $value) {
            if (is_numeric($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Sanitizza i dati della richiesta rimuovendo campi sensibili
     *
     * @param  array  $data
     * @return array
     */
    protected function sanitizeRequestData($data)
    {
        foreach ($this->sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***REDACTED***';
            }
        }

        // Sanitizza ricorsivamente array annidati
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sanitizeRequestData($value);
            }
        }

        return $data;
    }

    /**
     * Sanitizza gli headers rimuovendo informazioni sensibili
     *
     * @param  array  $headers
     * @return array
     */
    protected function sanitizeHeaders($headers)
    {
        $sensitiveHeaders = [
            'authorization',
            'x-api-key',
            'cookie',
            'x-csrf-token',
        ];

        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = ['***REDACTED***'];
            }
        }

        return $headers;
    }

    /**
     * Estrae i dati dalla risposta
     *
     * @param  mixed  $response
     * @return array|null
     */
    protected function extractResponseData($response)
    {
        if (!method_exists($response, 'getContent')) {
            return null;
        }

        $content = $response->getContent();

        // Limita la dimensione dei dati di risposta loggati
        if (strlen($content) > 1000) {
            return [
                'truncated' => true,
                'size' => strlen($content),
                'preview' => substr($content, 0, 1000),
            ];
        }

        // Prova a decodificare JSON
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $this->sanitizeRequestData($decoded);
        }

        return ['raw' => $content];
    }

    /**
     * Verifica se è un'azione critica
     *
     * @param  string  $action
     * @return bool
     */
    protected function isCriticalAction($action)
    {
        $criticalActions = [
            'DELETE /api/users/*',
            'POST /api/users/*/roles',
            'DELETE /api/companies/*',
            'POST /api/auth/password-reset',
            'POST /api/tenant/settings',
            'DELETE /api/files/*',
        ];

        foreach ($criticalActions as $critical) {
            if (fnmatch($critical, $action)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Notifica un'azione critica
     *
     * @param  array  $auditData
     * @return void
     */
    protected function notifyCriticalAction($auditData)
    {
        // Invia email agli amministratori
        $admins = \App\Models\User::role('admin')->get();

        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\CriticalActionPerformed($auditData));
        }
    }

    /**
     * Log di una richiesta lenta
     *
     * @param  array  $auditData
     * @param  float  $executionTime
     * @return void
     */
    protected function logSlowRequest($auditData, $executionTime)
    {
        Log::warning('Slow request detected', [
            'action' => $auditData['action'],
            'execution_time' => $executionTime,
            'url' => $auditData['url'],
            'user_id' => $auditData['user_id'],
        ]);

        // Salva nel database per analisi
        \DB::table('slow_query_logs')->insert([
            'action' => $auditData['action'],
            'url' => $auditData['url'],
            'execution_time' => $executionTime,
            'memory_usage' => $auditData['memory_usage'],
            'user_id' => $auditData['user_id'],
            'created_at' => now(),
        ]);
    }
}