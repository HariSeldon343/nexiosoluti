<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

class JWTMiddleware
{
    /**
     * Gestisce una richiesta in entrata
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // Verifica e autentica il token JWT
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utente non trovato',
                ], 404);
            }

            // Verifica se l'utente è attivo
            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account disattivato',
                ], 403);
            }

            // Verifica se l'utente ha verificato l'email
            if (!$user->email_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email non verificata',
                ], 403);
            }

            // Imposta il tenant corrente dal token
            if ($payload = JWTAuth::payload()) {
                if ($tenantId = $payload->get('tenant_id')) {
                    session(['tenant_id' => $tenantId]);

                    // Verifica che l'utente abbia accesso al tenant
                    if (!$user->tenants()->where('tenant_id', $tenantId)->exists()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Accesso al tenant non autorizzato',
                        ], 403);
                    }
                }

                // Imposta l'azienda corrente se presente nel token
                if ($companyId = $payload->get('company_id')) {
                    session(['company_id' => $companyId]);
                }
            }

            // Aggiorna l'ultimo accesso dell'utente
            $user->update(['last_login_at' => now()]);

        } catch (TokenExpiredException $e) {
            // Il token è scaduto, prova a refresharlo
            try {
                $newToken = JWTAuth::refresh();

                // Aggiungi il nuovo token alla risposta
                $response = $next($request);

                return $response->header('Authorization', 'Bearer ' . $newToken)
                    ->header('X-Token-Refreshed', 'true');

            } catch (JWTException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token scaduto e impossibile da aggiornare',
                    'error' => 'token_expired',
                ], 401);
            }

        } catch (TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token non valido',
                'error' => 'token_invalid',
            ], 401);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token non fornito',
                'error' => 'token_absent',
            ], 401);
        }

        return $next($request);
    }
}