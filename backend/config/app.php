<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Nome Applicazione
    |--------------------------------------------------------------------------
    */
    'name' => env('APP_NAME', 'NexioSolution'),

    /*
    |--------------------------------------------------------------------------
    | Ambiente Applicazione
    |--------------------------------------------------------------------------
    */
    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    */
    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | URL Applicazione
    |--------------------------------------------------------------------------
    */
    'url' => env('APP_URL', 'http://localhost'),

    'asset_url' => env('ASSET_URL'),

    /*
    |--------------------------------------------------------------------------
    | Timezone Applicazione
    |--------------------------------------------------------------------------
    */
    'timezone' => 'Europe/Rome',

    /*
    |--------------------------------------------------------------------------
    | Configurazione Locale
    |--------------------------------------------------------------------------
    */
    'locale' => 'it',
    'fallback_locale' => 'en',
    'faker_locale' => 'it_IT',

    /*
    |--------------------------------------------------------------------------
    | Chiave di Cifratura
    |--------------------------------------------------------------------------
    */
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Driver di Manutenzione
    |--------------------------------------------------------------------------
    */
    'maintenance' => [
        'driver' => 'file',
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Providers Autocaricati
    |--------------------------------------------------------------------------
    */
    'providers' => ServiceProvider::defaultProviders()->merge([
        /*
         * Package Service Providers...
         */
        Tymon\JWTAuth\Providers\LaravelServiceProvider::class,
        Spatie\Permission\PermissionServiceProvider::class,
        BeyondCode\LaravelWebSockets\WebSocketsServiceProvider::class,

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        App\Providers\TenantServiceProvider::class,
    ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Alias delle Classi
    |--------------------------------------------------------------------------
    */
    'aliases' => Facade::defaultAliases()->merge([
        'JWTAuth' => Tymon\JWTAuth\Facades\JWTAuth::class,
        'JWTFactory' => Tymon\JWTAuth\Facades\JWTFactory::class,
    ])->toArray(),

];