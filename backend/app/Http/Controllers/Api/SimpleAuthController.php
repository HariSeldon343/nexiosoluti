<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Controller semplificato per autenticazione compatibile con frontend
 */
class SimpleAuthController extends Controller
{
    /**
     * Login semplificato
     */
    public function simpleLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Credenziali non valide'
            ], 401);
        }

        // Crea token Sanctum
        $token = $user->createToken('app')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()->name ?? 'user',
            ]
        ]);
    }

    /**
     * Registrazione semplificata
     */
    public function simpleRegister(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        // Ottieni il primo tenant disponibile
        $tenant = \App\Models\Tenant::first();

        $user = User::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $tenant ? $tenant->id : null,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        // Assegna ruolo di default
        if (class_exists('\Spatie\Permission\Models\Role')) {
            $role = \Spatie\Permission\Models\Role::where('name', 'employee')->first();
            if ($role) {
                $user->assignRole($role);
            }
        }

        $token = $user->createToken('app')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'employee',
            ]
        ], 201);
    }

    /**
     * Logout semplificato
     */
    public function simpleLogout(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout effettuato con successo'
        ]);
    }

    /**
     * Ottieni utente corrente
     */
    public function simpleUser(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utente non autenticato'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()->name ?? 'user',
            ]
        ]);
    }
}