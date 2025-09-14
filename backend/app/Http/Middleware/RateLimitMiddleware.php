<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    /**
     * Il rate limiter instance
     *
     * @var \Illuminate\Cache\RateLimiter
     */
    protected $limiter;

    /**
     * Crea una nuova istanza del middleware
     *
     * @param  \Illuminate\Cache\RateLimiter  $limiter
     * @return void
     */
    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Gestisce una richiesta in entrata
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $type
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $type = 'default')
    {
        $key = $this->resolveRequestSignature($request, $type);
        $maxAttempts = $this->getMaxAttempts($type);
        $decayMinutes = $this->getDecayMinutes($type);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return $this->buildResponse($request, $key, $maxAttempts);
        }

        $this->limiter->hit($key, $decayMinutes * 60);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }

    /**
     * Risolve la firma della richiesta per il rate limiting
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $type
     * @return string
     */
    protected function resolveRequestSignature(Request $request, $type)
    {
        $prefix = 'rate_limit:' . $type . ':';

        // Per le richieste di login, usa IP + email
        if ($type === 'login') {
            $email = $request->input('email', '');
            return $prefix . sha1($request->ip() . '|' . $email);
        }

        // Per API autenticate, usa user ID + tenant ID
        if (auth()->check()) {
            $userId = auth()->id();
            $tenantId = session('tenant_id', 'default');
            return $prefix . $userId . ':' . $tenantId;
        }

        // Per richieste non autenticate, usa IP
        return $prefix . $request->ip();
    }

    /**
     * Ottieni il numero massimo di tentativi per tipo
     *
     * @param  string  $type
     * @return int
     */
    protected function getMaxAttempts($type)
    {
        $limits = [
            'login' => 5,           // 5 tentativi di login
            'password_reset' => 3,  // 3 richieste di reset password
            'api' => 60,           // 60 richieste API al minuto
            'api_heavy' => 10,     // 10 richieste pesanti al minuto
            'upload' => 20,        // 20 upload al minuto
            'export' => 5,         // 5 export al minuto
            'default' => 60,       // 60 richieste generiche al minuto
        ];

        return $limits[$type] ?? $limits['default'];
    }

    /**
     * Ottieni il tempo di decay in minuti
     *
     * @param  string  $type
     * @return int
     */
    protected function getDecayMinutes($type)
    {
        $decays = [
            'login' => 15,          // Reset dopo 15 minuti
            'password_reset' => 60, // Reset dopo 1 ora
            'api' => 1,            // Reset dopo 1 minuto
            'api_heavy' => 5,      // Reset dopo 5 minuti
            'upload' => 1,         // Reset dopo 1 minuto
            'export' => 10,        // Reset dopo 10 minuti
            'default' => 1,        // Reset dopo 1 minuto
        ];

        return $decays[$type] ?? $decays['default'];
    }

    /**
     * Crea la risposta per quando il limite è superato
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $key
     * @param  int  $maxAttempts
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function buildResponse(Request $request, $key, $maxAttempts)
    {
        $retryAfter = $this->limiter->availableIn($key);

        // Log del tentativo bloccato per sicurezza
        $this->logBlockedAttempt($request, $key);

        // Se è una richiesta di login, incrementa il contatore di sicurezza
        if (str_contains($key, 'login')) {
            $this->handleSuspiciousLoginActivity($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Troppe richieste. Riprova tra ' . $retryAfter . ' secondi.',
            'retry_after' => $retryAfter,
        ], 429)->withHeaders([
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
        ]);
    }

    /**
     * Aggiungi gli header del rate limit alla risposta
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  int  $maxAttempts
     * @param  int  $remainingAttempts
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function addHeaders(Response $response, $maxAttempts, $remainingAttempts)
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ]);

        return $response;
    }

    /**
     * Calcola i tentativi rimanenti
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @return int
     */
    protected function calculateRemainingAttempts($key, $maxAttempts)
    {
        return $maxAttempts - $this->limiter->attempts($key) + 1;
    }

    /**
     * Log del tentativo bloccato
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $key
     * @return void
     */
    protected function logBlockedAttempt(Request $request, $key)
    {
        \Log::warning('Rate limit exceeded', [
            'key' => $key,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
            'method' => $request->method(),
        ]);

        // Salva nel database per analytics
        \DB::table('rate_limit_logs')->insert([
            'key' => $key,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
            'method' => $request->method(),
            'created_at' => now(),
        ]);
    }

    /**
     * Gestisce attività di login sospette
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function handleSuspiciousLoginActivity(Request $request)
    {
        $ip = $request->ip();
        $email = $request->input('email');

        // Incrementa il contatore di tentativi falliti
        $attempts = cache()->increment('failed_login_attempts:' . $ip, 1);

        // Se ci sono troppi tentativi falliti, blocca temporaneamente l'IP
        if ($attempts > 10) {
            cache()->put('blocked_ip:' . $ip, true, now()->addHours(1));

            // Notifica gli amministratori
            dispatch(function () use ($ip, $email) {
                \Mail::to(config('mail.admin_email'))->queue(
                    new \App\Mail\SuspiciousActivityAlert($ip, $email)
                );
            });
        }

        // Log dell'attività sospetta
        \DB::table('suspicious_activities')->insert([
            'type' => 'excessive_login_attempts',
            'ip_address' => $ip,
            'email' => $email,
            'user_agent' => $request->userAgent(),
            'details' => json_encode($request->all()),
            'created_at' => now(),
        ]);
    }
}