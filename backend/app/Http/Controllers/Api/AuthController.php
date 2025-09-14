<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Login utente
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        // Trova l'utente
        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            return $this->unauthorized('Credenziali non valide');
        }

        // Verifica se l'account è bloccato
        if ($user->isLocked()) {
            return $this->forbidden('Account temporaneamente bloccato. Riprova più tardi.');
        }

        // Verifica la password
        if (!Hash::check($credentials['password'], $user->password)) {
            $user->incrementFailedLoginAttempts();
            return $this->unauthorized('Credenziali non valide');
        }

        // Verifica se l'utente è attivo
        if (!$user->is_active) {
            return $this->forbidden('Account non attivo');
        }

        // Verifica il tenant
        if (!$this->tenantService->hasTenant()) {
            $this->tenantService->setTenant($user->tenant);
        }

        // Reset tentativi falliti e aggiorna ultimo login
        $user->resetFailedLoginAttempts();
        $user->updateLastLogin($request->ip());

        // Genera il token JWT
        $token = JWTAuth::fromUser($user);

        // Genera refresh token
        $refreshToken = JWTAuth::claims(['type' => 'refresh'])->fromUser($user);

        return $this->success([
            'user' => $user->load(['companies', 'groups']),
            'access_token' => $token,
            'refresh_token' => $refreshToken,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60, // in secondi
            'tenant' => $this->tenantService->getBranding(),
        ], 'Login effettuato con successo');
    }

    /**
     * Registrazione nuovo utente
     */
    public function register(RegisterRequest $request)
    {
        // Verifica se il tenant ha raggiunto il limite utenti
        if ($this->tenantService->hasReachedUserLimit()) {
            return $this->forbidden('Limite utenti raggiunto per questo tenant');
        }

        $data = $request->validated();
        $data['tenant_id'] = $this->tenantService->getTenantId();
        $data['password'] = Hash::make($data['password']);

        // Crea l'utente
        $user = User::create($data);

        // Se specificata, associa l'azienda
        if (isset($data['company_id'])) {
            $company = $this->tenantService->getTenant()
                ->companies()
                ->find($data['company_id']);

            if ($company && $company->canAddUser()) {
                $company->addUser($user, 'member', true);
            }
        }

        // Genera il token
        $token = JWTAuth::fromUser($user);
        $refreshToken = JWTAuth::claims(['type' => 'refresh'])->fromUser($user);

        return $this->success([
            'user' => $user->load(['companies']),
            'access_token' => $token,
            'refresh_token' => $refreshToken,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ], 'Registrazione completata con successo', 201);
    }

    /**
     * Logout utente
     */
    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return $this->success(null, 'Logout effettuato con successo');
        } catch (\Exception $e) {
            return $this->error('Errore durante il logout');
        }
    }

    /**
     * Refresh token
     */
    public function refresh()
    {
        try {
            $token = JWTAuth::refresh();

            return $this->success([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60,
            ], 'Token aggiornato con successo');
        } catch (\Exception $e) {
            return $this->unauthorized('Token non valido o scaduto');
        }
    }

    /**
     * Ottiene il profilo utente corrente
     */
    public function me(Request $request)
    {
        $user = $request->user()->load(['companies', 'groups']);

        return $this->success([
            'user' => $user,
            'tenant' => $this->tenantService->getBranding(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    /**
     * Aggiorna il profilo utente
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|nullable|string|max:20',
            'job_title' => 'sometimes|nullable|string|max:100',
            'timezone' => 'sometimes|string|timezone',
            'locale' => 'sometimes|string|in:it,en',
            'preferences' => 'sometimes|array',
        ]);

        $user->update($validated);

        return $this->success($user, 'Profilo aggiornato con successo');
    }

    /**
     * Cambia password
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        // Verifica la password corrente
        if (!Hash::check($validated['current_password'], $user->password)) {
            return $this->validationError(['current_password' => ['Password corrente non corretta']]);
        }

        // Aggiorna la password
        $user->update([
            'password' => Hash::make($validated['new_password'])
        ]);

        // Invalida tutti i token esistenti
        JWTAuth::invalidate(JWTAuth::getToken());

        // Genera nuovo token
        $token = JWTAuth::fromUser($user);

        return $this->success([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ], 'Password modificata con successo');
    }
}