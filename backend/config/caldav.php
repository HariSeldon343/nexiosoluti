<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CalDAV Server Settings
    |--------------------------------------------------------------------------
    |
    | Configurazione per il server CalDAV usando SabreDAV
    |
    */

    'server' => [
        'base_uri' => env('CALDAV_BASE_URI', '/caldav'),
        'realm' => env('CALDAV_REALM', 'NexioSolution CalDAV'),
        'timezone' => env('CALDAV_TIMEZONE', 'Europe/Rome'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Configurazione autenticazione CalDAV
    |
    */
    'auth' => [
        'type' => env('CALDAV_AUTH_TYPE', 'digest'), // basic, digest
        'backend' => \App\Services\CalDAV\AuthBackend::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Backend
    |--------------------------------------------------------------------------
    |
    | Backend per lo storage dei calendari
    |
    */
    'storage' => [
        'backend' => env('CALDAV_STORAGE_BACKEND', 'database'), // database, file
        'path' => storage_path('caldav'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Principals Backend
    |--------------------------------------------------------------------------
    |
    | Gestione dei principal (utenti) CalDAV
    |
    */
    'principals' => [
        'backend' => \App\Services\CalDAV\PrincipalsBackend::class,
        'prefix' => 'principals',
    ],

    /*
    |--------------------------------------------------------------------------
    | Calendars Backend
    |--------------------------------------------------------------------------
    |
    | Gestione dei calendari
    |
    */
    'calendars' => [
        'backend' => \App\Services\CalDAV\CalendarsBackend::class,
        'default_properties' => [
            '{DAV:}displayname' => 'Calendario Personale',
            '{http://apple.com/ns/ical/}calendar-color' => '#3788D8',
            '{urn:ietf:params:xml:ns:caldav}calendar-description' => 'Calendario principale NexioSolution',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Components
    |--------------------------------------------------------------------------
    |
    | Componenti CalDAV supportati
    |
    */
    'supported_components' => [
        'VEVENT',      // Eventi
        'VTODO',       // Task
        'VJOURNAL',    // Note
        'VFREEBUSY',   // Disponibilità
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugins
    |--------------------------------------------------------------------------
    |
    | Plugin SabreDAV da abilitare
    |
    */
    'plugins' => [
        \Sabre\DAV\Auth\Plugin::class,
        \Sabre\DAVACL\Plugin::class,
        \Sabre\CalDAV\Plugin::class,
        \Sabre\CalDAV\ICSExportPlugin::class,
        \Sabre\CalDAV\Schedule\Plugin::class,
        \Sabre\DAV\Sync\Plugin::class,
        \Sabre\DAV\Browser\Plugin::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sharing
    |--------------------------------------------------------------------------
    |
    | Configurazione condivisione calendari
    |
    */
    'sharing' => [
        'enabled' => env('CALDAV_SHARING_ENABLED', true),
        'invitations' => true,
        'notifications' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduling
    |--------------------------------------------------------------------------
    |
    | Configurazione scheduling (inviti meeting)
    |
    */
    'scheduling' => [
        'enabled' => env('CALDAV_SCHEDULING_ENABLED', true),
        'default_method' => 'REQUEST', // REQUEST, REPLY, CANCEL
        'auto_accept' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    |
    | Configurazione sincronizzazione
    |
    */
    'sync' => [
        'collection_sync' => true,
        'max_sync_token_age' => 3600 * 24 * 90, // 90 giorni
        'changes_limit' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Configurazione cache CalDAV
    |
    */
    'cache' => [
        'enabled' => env('CALDAV_CACHE_ENABLED', true),
        'driver' => env('CALDAV_CACHE_DRIVER', 'redis'),
        'ttl' => 3600, // 1 ora
    ],

    /*
    |--------------------------------------------------------------------------
    | External Calendars
    |--------------------------------------------------------------------------
    |
    | Supporto calendari esterni (Google, Outlook, etc)
    |
    */
    'external' => [
        'google' => [
            'enabled' => env('GOOGLE_CALENDAR_ENABLED', false),
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
        ],
        'outlook' => [
            'enabled' => env('OUTLOOK_CALENDAR_ENABLED', false),
            'client_id' => env('OUTLOOK_CLIENT_ID'),
            'client_secret' => env('OUTLOOK_CLIENT_SECRET'),
            'redirect_uri' => env('OUTLOOK_REDIRECT_URI'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | Limiti per prevenire abusi
    |
    */
    'limits' => [
        'max_calendars_per_user' => 10,
        'max_events_per_calendar' => 10000,
        'max_event_size' => 1024 * 100, // 100KB
        'max_attachments_per_event' => 5,
    ],
];