<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Metodo di Identificazione Tenant
    |--------------------------------------------------------------------------
    |
    | Definisce come identificare il tenant:
    | - subdomain: tramite sottodominio (es. acme.app.com)
    | - domain: tramite dominio completo (es. acme.com)
    | - header: tramite header HTTP (X-Tenant-ID)
    | - user: tramite utente autenticato
    |
    */
    'identification_method' => env('TENANT_IDENTIFICATION_METHOD', 'subdomain'),

    /*
    |--------------------------------------------------------------------------
    | Dominio Predefinito
    |--------------------------------------------------------------------------
    */
    'default_domain' => env('TENANT_DEFAULT_DOMAIN', 'localhost'),

    /*
    |--------------------------------------------------------------------------
    | Sottodominio Predefinito
    |--------------------------------------------------------------------------
    */
    'default_subdomain' => env('TENANT_DEFAULT_SUBDOMAIN', 'app'),

    /*
    |--------------------------------------------------------------------------
    | Sottodomini Riservati
    |--------------------------------------------------------------------------
    |
    | Sottodomini che non possono essere utilizzati dai tenant
    |
    */
    'reserved_subdomains' => [
        'www',
        'api',
        'admin',
        'app',
        'mail',
        'ftp',
        'blog',
        'shop',
        'support',
        'help',
        'docs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Modelli Multi-Tenant
    |--------------------------------------------------------------------------
    |
    | Lista dei modelli che utilizzano il trait BelongsToTenant
    |
    */
    'models' => [
        App\Models\User::class,
        App\Models\Company::class,
        App\Models\Calendar::class,
        App\Models\Event::class,
        App\Models\Task::class,
        App\Models\Folder::class,
        App\Models\File::class,
        App\Models\Room::class,
        App\Models\Message::class,
        App\Models\Group::class,
        App\Models\AuditLog::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Percorso Storage Tenant
    |--------------------------------------------------------------------------
    |
    | Percorso base per lo storage dei file dei tenant
    |
    */
    'storage_path' => env('FILE_STORAGE_PATH', 'storage/app/tenants'),

    /*
    |--------------------------------------------------------------------------
    | Limiti Predefiniti
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'max_users' => 0, // 0 = illimitato
        'max_storage_mb' => 0, // 0 = illimitato
        'max_companies' => 10,
        'max_file_size_mb' => 10,
        'subscription_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Piani di Abbonamento
    |--------------------------------------------------------------------------
    */
    'plans' => [
        'basic' => [
            'name' => 'Basic',
            'max_users' => 5,
            'max_storage_mb' => 1024, // 1GB
            'max_companies' => 1,
            'features' => [
                'calendar',
                'tasks',
                'files',
            ],
        ],
        'professional' => [
            'name' => 'Professional',
            'max_users' => 25,
            'max_storage_mb' => 10240, // 10GB
            'max_companies' => 5,
            'features' => [
                'calendar',
                'tasks',
                'files',
                'chat',
                'approvals',
                'caldav',
            ],
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'max_users' => 0, // illimitato
            'max_storage_mb' => 0, // illimitato
            'max_companies' => 0, // illimitato
            'features' => [
                'calendar',
                'tasks',
                'files',
                'chat',
                'approvals',
                'caldav',
                'api',
                'webhooks',
                'custom_branding',
                'priority_support',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Multi-Tenant
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        'tenant' => \App\Http\Middleware\TenantMiddleware::class,
        'tenant.admin' => \App\Http\Middleware\TenantAdminMiddleware::class,
    ],
];