<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Web Push VAPID Settings
    |--------------------------------------------------------------------------
    |
    | Configurazione VAPID per Web Push Notifications
    |
    */
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@nexiosolution.com'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'pem_file' => env('VAPID_PEM_FILE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Push Services
    |--------------------------------------------------------------------------
    |
    | Servizi di push notification supportati
    |
    */
    'services' => [
        'web' => [
            'enabled' => env('WEB_PUSH_ENABLED', true),
            'ttl' => 2419200, // 28 giorni
            'urgency' => 'normal', // very-low, low, normal, high
            'topic' => 'nexiosolution',
            'batch_size' => 1000,
        ],
        'fcm' => [
            'enabled' => env('FCM_ENABLED', false),
            'server_key' => env('FCM_SERVER_KEY'),
            'sender_id' => env('FCM_SENDER_ID'),
            'api_url' => 'https://fcm.googleapis.com/fcm/send',
        ],
        'apns' => [
            'enabled' => env('APNS_ENABLED', false),
            'certificate' => env('APNS_CERTIFICATE'),
            'passphrase' => env('APNS_PASSPHRASE'),
            'sandbox' => env('APNS_SANDBOX', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    |
    | Canali di notifica configurabili per tipo
    |
    */
    'channels' => [
        'task_assigned' => ['web', 'email', 'push'],
        'task_completed' => ['web', 'email'],
        'task_overdue' => ['web', 'email', 'push'],
        'file_uploaded' => ['web'],
        'file_approved' => ['web', 'email'],
        'file_rejected' => ['web', 'email', 'push'],
        'message_received' => ['web', 'push'],
        'mention' => ['web', 'email', 'push'],
        'calendar_reminder' => ['web', 'email', 'push'],
        'approval_requested' => ['web', 'email', 'push'],
        'system_update' => ['web', 'email'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    |
    | Impostazioni predefinite per le notifiche
    |
    */
    'defaults' => [
        'icon' => '/images/notification-icon.png',
        'badge' => '/images/notification-badge.png',
        'sound' => 'default',
        'vibrate' => [200, 100, 200],
        'require_interaction' => false,
        'renotify' => false,
        'silent' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    |
    | Azioni disponibili nelle notifiche
    |
    */
    'actions' => [
        'view' => [
            'action' => 'view',
            'title' => 'Visualizza',
            'icon' => '/images/action-view.png',
        ],
        'reply' => [
            'action' => 'reply',
            'title' => 'Rispondi',
            'icon' => '/images/action-reply.png',
            'type' => 'text',
            'placeholder' => 'Scrivi una risposta...',
        ],
        'approve' => [
            'action' => 'approve',
            'title' => 'Approva',
            'icon' => '/images/action-approve.png',
        ],
        'reject' => [
            'action' => 'reject',
            'title' => 'Rifiuta',
            'icon' => '/images/action-reject.png',
        ],
        'complete' => [
            'action' => 'complete',
            'title' => 'Completa',
            'icon' => '/images/action-complete.png',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Templates
    |--------------------------------------------------------------------------
    |
    | Template per i vari tipi di notifica
    |
    */
    'templates' => [
        'task_assigned' => [
            'title' => 'Nuovo task assegnato',
            'body' => 'Ti è stato assegnato il task: :task_title',
            'icon' => '/images/task-icon.png',
            'actions' => ['view', 'complete'],
        ],
        'message_received' => [
            'title' => 'Nuovo messaggio da :sender',
            'body' => ':message',
            'icon' => '/images/message-icon.png',
            'actions' => ['view', 'reply'],
        ],
        'approval_requested' => [
            'title' => 'Richiesta di approvazione',
            'body' => ':requester richiede la tua approvazione per: :item',
            'icon' => '/images/approval-icon.png',
            'actions' => ['view', 'approve', 'reject'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Settings
    |--------------------------------------------------------------------------
    |
    | Configurazione code per l'invio delle notifiche
    |
    */
    'queue' => [
        'connection' => env('PUSH_QUEUE_CONNECTION', 'redis'),
        'queue' => env('PUSH_QUEUE_NAME', 'notifications'),
        'tries' => 3,
        'retry_after' => 90,
        'timeout' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Limiti per prevenire spam di notifiche
    |
    */
    'rate_limits' => [
        'per_user_per_minute' => 10,
        'per_user_per_hour' => 100,
        'per_user_per_day' => 500,
        'global_per_minute' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Settings
    |--------------------------------------------------------------------------
    |
    | Configurazione pulizia notifiche vecchie
    |
    */
    'cleanup' => [
        'enabled' => true,
        'read_after_days' => 30,
        'unread_after_days' => 90,
        'failed_after_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    |
    | Tracking analytics per le notifiche
    |
    */
    'analytics' => [
        'enabled' => env('PUSH_ANALYTICS_ENABLED', true),
        'track_delivery' => true,
        'track_clicks' => true,
        'track_dismissals' => true,
    ],
];