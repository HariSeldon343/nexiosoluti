<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Password;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\User;
use App\Services\TwoFactorService;
use App\Services\JWTService;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Events\UserLoggedIn;
use App\Events\UserRegistered;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * JWT Service
     *
     * @var JWTService
     */
    protected $jwtService;

    /**
     * Two Factor Service
     *
     * @var TwoFactorService
     */
    protected $twoFactorService;

    /**
     * Crea una nuova istanza del controller
     *
     * @param  JWTService  $jwtService
     * @param  TwoFactorService  $twoFactorService
     * @return void
     */
    public function __construct(JWTService $jwtService, TwoFactorService $twoFactorService)
    {
        $this->jwtService = $jwtService;
        $this->twoFactorService = $twoFactorService;

        $this->middleware('auth:api', [
            'except' => ['login', 'register', 'forgotPassword', 'resetPassword', 'verify']
        ]);

        $this->middleware('throttle:login', [
            'only' => ['login']
        ]);
    }

    /**
     * Login utente e generazione token JWT
     *
     * @param  LoginRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        try {
            // Trova l'utente
            $user = User::where('email', $credentials['email'])->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credenziali non valide',
                ], 401);
            }

            // Verifica se l'account è attivo
            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account disattivato. Contatta l\'amministratore.',
                ], 403);
            }

            // Verifica se l'email è verificata
            if (!$user->email_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email non verificata. Controlla la tua casella di posta.',
                    'action' => 'verify_email',
                ], 403);
            }

            // Verifica la password
            if (!Hash::check($credentials['password'], $user->password)) {
                // Log del tentativo fallito
                $this->logFailedLogin($user, $request);

                return response()->json([
                    'success' => false,
                    'message' => 'Credenziali non valide',
                ], 401);
            }

            // Genera il token JWT con claims personalizzati
            $customClaims = [
                'tenant_id' => $request->input('tenant_id', $user->tenant_id),
                'company_id' => $request->input('company_id', $user->company_id),
            ];

            $token = $this->jwtService->generateToken($user, $customClaims);
            $refreshToken = $this->jwtService->generateRefreshToken($user);

            // Verifica se è richiesta la 2FA
            if ($user->two_factor_enabled) {
                // Salva i token temporaneamente
                cache()->put(
                    '2fa_pending:' . $user->id,
                    ['token' => $token, 'refresh_token' => $refreshToken],
                    now()->addMinutes(5)
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Autenticazione a due fattori richiesta',
                    'action' => 'verify_2fa',
                    'user_id' => $user->id,
                ], 200);
            }

            // Aggiorna ultimo login
            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            // Dispatch evento di login
            event(new UserLoggedIn($user));

            return response()->json([
                'success' => true,
                'message' => 'Login effettuato con successo',
                'data' => [
                    'user' => $user->load(['roles', 'permissions']),
                    'access_token' => $token,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60,
                ],
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Impossibile creare il token',
            ], 500);
        }
    }

    /**
     * Verifica codice 2FA
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify2FA(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'code' => 'required|string',
        ]);

        $user = User::find($request->user_id);

        // Verifica il codice 2FA
        if (!$this->twoFactorService->verifyCode($user, $request->code)) {
            return response()->json([
                'success' => false,
                'message' => 'Codice 2FA non valido',
            ], 401);
        }

        // Recupera i token salvati
        $tokens = cache()->get('2fa_pending:' . $user->id);

        if (!$tokens) {
            return response()->json([
                'success' => false,
                'message' => 'Sessione scaduta. Effettua nuovamente il login.',
            ], 401);
        }

        // Rimuovi i token dalla cache
        cache()->forget('2fa_pending:' . $user->id);

        // Aggiorna ultimo login
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Dispatch evento di login
        event(new UserLoggedIn($user));

        return response()->json([
            'success' => true,
            'message' => 'Autenticazione 2FA completata',
            'data' => [
                'user' => $user->load(['roles', 'permissions']),
                'access_token' => $tokens['token'],
                'refresh_token' => $tokens['refresh_token'],
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60,
            ],
        ], 200);
    }

    /**
     * Setup autenticazione a due fattori
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function setup2FA(Request $request)
    {
        $user = auth()->user();

        if ($user->two_factor_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Autenticazione a due fattori già abilitata',
            ], 400);
        }

        $secret = $this->twoFactorService->generateSecret();
        $qrCode = $this->twoFactorService->generateQRCode($user, $secret);
        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();

        // Salva temporaneamente il secret
        cache()->put(
            '2fa_setup:' . $user->id,
            [
                'secret' => $secret,
                'recovery_codes' => $recoveryCodes,
            ],
            now()->addMinutes(10)
        );

        return response()->json([
            'success' => true,
            'message' => 'Setup 2FA iniziato',
            'data' => [
                'qr_code' => $qrCode,
                'secret' => $secret,
                'recovery_codes' => $recoveryCodes,
            ],
        ], 200);
    }

    /**
     * Conferma setup 2FA
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirm2FA(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = auth()->user();

        // Recupera i dati di setup
        $setupData = cache()->get('2fa_setup:' . $user->id);

        if (!$setupData) {
            return response()->json([
                'success' => false,
                'message' => 'Sessione di setup scaduta',
            ], 400);
        }

        // Verifica il codice con il secret temporaneo
        if (!$this->twoFactorService->verifyCode($user, $request->code, $setupData['secret'])) {
            return response()->json([
                'success' => false,
                'message' => 'Codice non valido',
            ], 401);
        }

        // Abilita 2FA per l'utente
        $user->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => encrypt($setupData['secret']),
            'two_factor_recovery_codes' => encrypt(json_encode($setupData['recovery_codes'])),
        ]);

        // Rimuovi i dati di setup dalla cache
        cache()->forget('2fa_setup:' . $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Autenticazione a due fattori abilitata con successo',
        ], 200);
    }

    /**
     * Disabilita 2FA
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function disable2FA(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = auth()->user();

        // Verifica la password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password non corretta',
            ], 401);
        }

        // Disabilita 2FA
        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Autenticazione a due fattori disabilitata',
        ], 200);
    }

    /**
     * Registrazione nuovo utente
     *
     * @param  RegisterRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(RegisterRequest $request)
    {
        // Crea il nuovo utente
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'tenant_id' => $request->tenant_id,
            'company_id' => $request->company_id,
        ]);

        // Assegna ruolo di default
        $user->assignRole('user');

        // Invia email di verifica
        $user->sendEmailVerificationNotification();

        // Dispatch evento di registrazione
        event(new UserRegistered($user));

        // Genera token se auto-login è abilitato
        if (config('auth.auto_login_after_register', false)) {
            $token = $this->jwtService->generateToken($user);

            return response()->json([
                'success' => true,
                'message' => 'Registrazione completata con successo',
                'data' => [
                    'user' => $user,
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60,
                ],
            ], 201);
        }

        return response()->json([
            'success' => true,
            'message' => 'Registrazione completata. Verifica la tua email.',
            'data' => [
                'user' => $user,
            ],
        ], 201);
    }

    /**
     * Logout utente
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        try {
            // Invalida il token corrente
            JWTAuth::invalidate(JWTAuth::getToken());

            // Pulisci la sessione 2FA se presente
            session()->forget(['2fa_verified', '2fa_verified_at']);

            return response()->json([
                'success' => true,
                'message' => 'Logout effettuato con successo',
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errore durante il logout',
            ], 500);
        }
    }

    /**
     * Refresh del token JWT
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh(Request $request)
    {
        try {
            $refreshToken = $request->input('refresh_token');

            if (!$refreshToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Refresh token mancante',
                ], 400);
            }

            // Verifica e refresh del token
            $newTokens = $this->jwtService->refreshToken($refreshToken);

            return response()->json([
                'success' => true,
                'message' => 'Token aggiornato con successo',
                'data' => [
                    'access_token' => $newTokens['access_token'],
                    'refresh_token' => $newTokens['refresh_token'],
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token non valido o scaduto',
            ], 401);
        }
    }

    /**
     * Richiesta reset password
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        // Invia email di reset password
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'success' => true,
                'message' => 'Link di reset password inviato alla tua email',
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Impossibile inviare il link di reset',
        ], 400);
    }

    /**
     * Reset password
     *
     * @param  ResetPasswordRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'password_changed_at' => now(),
                ])->save();

                // Invalida tutti i token esistenti
                JWTAuth::invalidate(JWTAuth::getToken());
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Password reimpostata con successo',
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Errore durante il reset della password',
        ], 400);
    }

    /**
     * Ottieni profilo utente corrente
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        $user = auth()->user()->load([
            'roles',
            'permissions',
            'companies',
            'tenants',
        ]);

        return response()->json([
            'success' => true,
            'data' => $user,
        ], 200);
    }

    /**
     * Verifica email
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'hash' => 'required',
        ]);

        $user = User::findOrFail($request->id);

        if (!hash_equals((string) $request->hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'success' => false,
                'message' => 'Link di verifica non valido',
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email già verificata',
            ], 400);
        }

        $user->markEmailAsVerified();

        return response()->json([
            'success' => true,
            'message' => 'Email verificata con successo',
        ], 200);
    }

    /**
     * Log di un tentativo di login fallito
     *
     * @param  User  $user
     * @param  Request  $request
     * @return void
     */
    protected function logFailedLogin($user, Request $request)
    {
        \DB::table('failed_login_attempts')->insert([
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'attempted_at' => now(),
        ]);

        // Incrementa il contatore di tentativi falliti
        $user->increment('failed_login_attempts');

        // Blocca l'account dopo 5 tentativi
        if ($user->failed_login_attempts >= 5) {
            $user->update([
                'is_active' => false,
                'locked_at' => now(),
            ]);

            // Notifica l'utente
            $user->notify(new \App\Notifications\AccountLocked());
        }
    }
}