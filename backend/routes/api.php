<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Test endpoint semplice
Route::get('/test', function () {
    return response()->json([
        'status' => 'API is working',
        'time' => now()->toDateTimeString()
    ]);
});

// Login mock per test
Route::post('/login', function (Request $request) {
    $email = $request->input('email');
    $password = $request->input('password');

    // Mock login - accetta le credenziali di test
    if ($email === 'admin@nexiosolution.com' && $password === 'password123') {
        return response()->json([
            'success' => true,
            'token' => 'mock-jwt-token-' . uniqid(),
            'user' => [
                'id' => 1,
                'name' => 'Admin',
                'email' => 'admin@nexiosolution.com',
                'role' => 'admin'
            ]
        ]);
    }

    return response()->json([
        'success' => false,
        'message' => 'Credenziali non valide'
    ], 401);
});

// User info mock
Route::get('/user', function () {
    return response()->json([
        'id' => 1,
        'name' => 'Admin',
        'email' => 'admin@nexiosolution.com',
        'role' => 'admin'
    ]);
});

// Logout mock
Route::post('/logout', function () {
    return response()->json([
        'success' => true,
        'message' => 'Logout effettuato'
    ]);
});