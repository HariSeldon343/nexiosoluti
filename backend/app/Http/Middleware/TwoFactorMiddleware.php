<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorMiddleware
{
    /**
     * Google2FA instance
     *
     * @var \PragmaRX\Google2FA\Google2FA
     */
    protected $google2fa;

    /**
     * Crea una nuova istanza del middleware
     *
     * @param  \PragmaRX\Google2FA\Google2FA  $google2fa
     * @return void
     */
    public function __construct(Google2FA $google2fa)
    {
        $this->google2fa = $google2fa;
    }

    /**
     * Gestisce una richiesta in entrata
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $required
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $required = null)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non autenticato',
            ], 401);
        }

        // Se 2FA è richiesto ma non abilitato per l'utente
        if ($required === 'required' && !$user->two_factor_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Autenticazione a due fattori richiesta',
                'action' => 'setup_2fa',
            ], 403);
        }

        // Se l'utente ha 2FA abilitato
        if ($user->two_factor_enabled) {
            // Verifica se la sessione 2FA è già stata verificata
            if ($this->is2FAVerified($user)) {
                // Verifica se la sessione è scaduta
                if ($this->is2FASessionExpired()) {
                    session()->forget('2fa_verified');
                    session()->forget('2fa_verified_at');

                    return response()->json([
                        'success' => false,
                        'message' => 'Sessione 2FA scaduta',
                        'action' => 'verify_2fa',
                    ], 401);
                }

                // Rinnova il timestamp della sessione
                $this->renew2FASession();

                return $next($request);
            }

            // Se il codice 2FA è fornito nella richiesta
            if ($request->has('two_factor_code')) {
                $code = $request->input('two_factor_code');

                // Verifica il codice
                if ($this->verify2FACode($user, $code)) {
                    $this->mark2FAAsVerified($user);

                    // Log dell'accesso con 2FA
                    $this->log2FAAccess($user, $request, true);

                    return $next($request);
                } else {
                    // Log del tentativo fallito
                    $this->log2FAAccess($user, $request, false);

                    // Incrementa il contatore di tentativi falliti
                    $this->incrementFailedAttempts($user);

                    return response()->json([
                        'success' => false,
                        'message' => 'Codice 2FA non valido',
                    ], 401);
                }
            }

            // Se usa un recovery code
            if ($request->has('recovery_code')) {
                $recoveryCode = $request->input('recovery_code');

                if ($this->verifyRecoveryCode($user, $recoveryCode)) {
                    $this->mark2FAAsVerified($user);

                    // Rimuovi il recovery code usato
                    $this->consumeRecoveryCode($user, $recoveryCode);

                    // Notifica l'utente che ha usato un recovery code
                    $this->notifyRecoveryCodeUsed($user);

                    return $next($request);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Codice di recupero non valido',
                    ], 401);
                }
            }

            // Richiedi il codice 2FA
            return response()->json([
                'success' => false,
                'message' => 'Verifica 2FA richiesta',
                'action' => 'verify_2fa',
                'backup_codes_remaining' => $user->two_factor_recovery_codes ?
                    count(json_decode($user->two_factor_recovery_codes, true)) : 0,
            ], 401);
        }

        return $next($request);
    }

    /**
     * Verifica se la sessione 2FA è stata verificata
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    protected function is2FAVerified($user)
    {
        return session('2fa_verified') === $user->id;
    }

    /**
     * Verifica se la sessione 2FA è scaduta
     *
     * @return bool
     */
    protected function is2FASessionExpired()
    {
        $verifiedAt = session('2fa_verified_at');

        if (!$verifiedAt) {
            return true;
        }

        // Sessione valida per 2 ore
        $expirationMinutes = config('auth.two_factor_session_lifetime', 120);

        return now()->diffInMinutes($verifiedAt) > $expirationMinutes;
    }

    /**
     * Rinnova la sessione 2FA
     *
     * @return void
     */
    protected function renew2FASession()
    {
        session(['2fa_verified_at' => now()]);
    }

    /**
     * Verifica il codice 2FA
     *
     * @param  \App\Models\User  $user
     * @param  string  $code
     * @return bool
     */
    protected function verify2FACode($user, $code)
    {
        // Rimuovi spazi dal codice
        $code = str_replace(' ', '', $code);

        // Verifica il codice TOTP
        $valid = $this->google2fa->verifyKey(
            $user->two_factor_secret,
            $code,
            2 // Window di tolleranza di 2 (30 secondi prima e dopo)
        );

        return $valid;
    }

    /**
     * Marca la sessione 2FA come verificata
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    protected function mark2FAAsVerified($user)
    {
        session([
            '2fa_verified' => $user->id,
            '2fa_verified_at' => now(),
        ]);

        // Resetta il contatore di tentativi falliti
        cache()->forget('2fa_failed_attempts:' . $user->id);
    }

    /**
     * Verifica un codice di recupero
     *
     * @param  \App\Models\User  $user
     * @param  string  $code
     * @return bool
     */
    protected function verifyRecoveryCode($user, $code)
    {
        if (!$user->two_factor_recovery_codes) {
            return false;
        }

        $recoveryCodes = json_decode($user->two_factor_recovery_codes, true);

        foreach ($recoveryCodes as $recoveryCode) {
            if (hash_equals($recoveryCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Consuma un codice di recupero
     *
     * @param  \App\Models\User  $user
     * @param  string  $code
     * @return void
     */
    protected function consumeRecoveryCode($user, $code)
    {
        $recoveryCodes = json_decode($user->two_factor_recovery_codes, true);
        $recoveryCodes = array_diff($recoveryCodes, [$code]);

        $user->update([
            'two_factor_recovery_codes' => json_encode(array_values($recoveryCodes)),
        ]);
    }

    /**
     * Notifica l'utente che ha usato un recovery code
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    protected function notifyRecoveryCodeUsed($user)
    {
        $user->notify(new \App\Notifications\RecoveryCodeUsed(
            count(json_decode($user->two_factor_recovery_codes, true))
        ));
    }

    /**
     * Log dell'accesso 2FA
     *
     * @param  \App\Models\User  $user
     * @param  \Illuminate\Http\Request  $request
     * @param  bool  $success
     * @return void
     */
    protected function log2FAAccess($user, Request $request, $success)
    {
        \DB::table('two_factor_logs')->insert([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'success' => $success,
            'created_at' => now(),
        ]);
    }

    /**
     * Incrementa il contatore di tentativi falliti
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    protected function incrementFailedAttempts($user)
    {
        $key = '2fa_failed_attempts:' . $user->id;
        $attempts = cache()->increment($key, 1);

        // Blocca dopo 5 tentativi falliti
        if ($attempts >= 5) {
            // Blocca l'account per 15 minuti
            $user->update([
                'two_factor_locked_until' => now()->addMinutes(15),
            ]);

            // Notifica l'utente
            $user->notify(new \App\Notifications\TwoFactorLocked());

            // Resetta il contatore
            cache()->forget($key);
        }
    }
}