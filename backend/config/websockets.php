<?php

use BeyondCode\LaravelWebSockets\Dashboard\Http\Middleware\Authorize;

return [
    /*
    |--------------------------------------------------------------------------
    | Dashboard Settings
    |--------------------------------------------------------------------------
    |
    | Configurazione della dashboard WebSocket
    |
    */
    'dashboard' => [
        'port' => env('LARAVEL_WEBSOCKETS_PORT', 6001),
    ],

    /*
    |--------------------------------------------------------------------------
    | Applications Manager
    |--------------------------------------------------------------------------
    |
    | Gestione delle applicazioni WebSocket
    |
    */
    'apps' => [
        [
            'id' => env('PUSHER_APP_ID', 'nexiosolution'),
            'name' => env('APP_NAME', 'NexioSolution'),
            'key' => env('PUSHER_APP_KEY', 'nexiosolution-key'),
            'secret' => env('PUSHER_APP_SECRET', 'nexiosolution-secret'),
            'path' => env('PUSHER_APP_PATH'),
            'capacity' => null,
            'enable_client_messages' => true,
            'enable_statistics' => true,
            'encrypted' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | Origini consentite per le connessioni WebSocket
    |
    */
    'allowed_origins' => [
        env('WEBSOCKET_ALLOWED_ORIGIN', '*'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum Request Size
    |--------------------------------------------------------------------------
    |
    | Dimensione massima del payload in kilobyte
    |
    */
    'max_request_size_in_kb' => 250,

    /*
    |--------------------------------------------------------------------------
    | Path
    |--------------------------------------------------------------------------
    |
    | Path per le connessioni WebSocket
    |
    */
    'path' => 'laravel-websockets',

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware per le richieste WebSocket
    |
    */
    'middleware' => [
        'web',
        Authorize::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Statistics
    |--------------------------------------------------------------------------
    |
    | Configurazione statistiche WebSocket
    |
    */
    'statistics' => [
        'model' => \BeyondCode\LaravelWebSockets\Statistics\Models\WebSocketsStatisticsEntry::class,
        'logger' => \BeyondCode\LaravelWebSockets\Statistics\Logger\HttpStatisticsLogger::class,
        'interval_in_seconds' => 60,
        'delete_statistics_older_than_days' => 60,
        'perform_dns_lookup' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | SSL Configuration
    |--------------------------------------------------------------------------
    |
    | Configurazione SSL per connessioni sicure
    |
    */
    'ssl' => [
        'local_cert' => env('LARAVEL_WEBSOCKETS_SSL_LOCAL_CERT', null),
        'capath' => env('LARAVEL_WEBSOCKETS_SSL_CA', null),
        'local_pk' => env('LARAVEL_WEBSOCKETS_SSL_LOCAL_PK', null),
        'passphrase' => env('LARAVEL_WEBSOCKETS_SSL_PASSPHRASE', null),
        'verify_peer' => env('LARAVEL_WEBSOCKETS_SSL_VERIFY_PEER', true),
        'allow_self_signed' => env('LARAVEL_WEBSOCKETS_SSL_ALLOW_SELF_SIGNED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Channel Manager
    |--------------------------------------------------------------------------
    |
    | Gestione dei canali WebSocket
    |
    */
    'channel_manager' => \BeyondCode\LaravelWebSockets\WebSockets\Channels\ChannelManagers\ArrayChannelManager::class,

    /*
    |--------------------------------------------------------------------------
    | Presence Channel Authentication
    |--------------------------------------------------------------------------
    |
    | Autenticazione per i canali presence
    |
    */
    'presence' => [
        'prefix' => 'presence-',
        'auth_key' => 'auth',
        'user_id_key' => 'user_id',
        'user_info_key' => 'user_info',
    ],

    /*
    |--------------------------------------------------------------------------
    | Private Channel Authentication
    |--------------------------------------------------------------------------
    |
    | Autenticazione per i canali privati
    |
    */
    'private' => [
        'prefix' => 'private-',
        'auth_key' => 'auth',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configurazione code per eventi WebSocket
    |
    */
    'queue' => [
        'default' => env('WEBSOCKETS_QUEUE', 'default'),
        'connection' => env('WEBSOCKETS_QUEUE_CONNECTION', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Throttling
    |--------------------------------------------------------------------------
    |
    | Rate limiting per le connessioni
    |
    */
    'throttle' => [
        'enabled' => env('WEBSOCKETS_THROTTLE_ENABLED', false),
        'max_connections_per_ip' => env('WEBSOCKETS_MAX_CONNECTIONS_PER_IP', 100),
        'max_events_per_second' => env('WEBSOCKETS_MAX_EVENTS_PER_SECOND', 10),
    ],
];