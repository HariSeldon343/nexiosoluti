<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | Configurazione del mailer predefinito per l'applicazione.
    | Supporta SMTP, Sendmail, Amazon SES, Mailgun, Postmark, e altri.
    |
    */

    'default' => env('MAIL_MAILER', 'smtp'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Configurazioni per i vari servizi di invio email.
    | Ogni mailer può avere la propria configurazione specifica.
    |
    */

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
            'auth_mode' => null,
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'mailgun' => [
            'transport' => 'mailgun',
            'domain' => env('MAILGUN_DOMAIN'),
            'secret' => env('MAILGUN_SECRET'),
            'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
            'scheme' => 'https',
        ],

        'postmark' => [
            'transport' => 'postmark',
            'token' => env('POSTMARK_TOKEN'),
            'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],

        // Configurazione per ambiente di sviluppo locale
        'mailtrap' => [
            'transport' => 'smtp',
            'host' => 'smtp.mailtrap.io',
            'port' => 2525,
            'encryption' => 'tls',
            'username' => env('MAILTRAP_USERNAME'),
            'password' => env('MAILTRAP_PASSWORD'),
        ],

        // Configurazione per Gmail
        'gmail' => [
            'transport' => 'smtp',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => env('GMAIL_USERNAME'),
            'password' => env('GMAIL_PASSWORD'),
        ],

        // Configurazione per Office 365
        'office365' => [
            'transport' => 'smtp',
            'host' => 'smtp.office365.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => env('OFFICE365_USERNAME'),
            'password' => env('OFFICE365_PASSWORD'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | Indirizzo email e nome utilizzati globalmente per tutti i messaggi
    | inviati dall'applicazione. Può essere sovrascritto per singole email.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@nexiosolution.local'),
        'name' => env('MAIL_FROM_NAME', 'NexioSolution'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Global "Reply To" Address
    |--------------------------------------------------------------------------
    |
    | Indirizzo email per le risposte. Se non specificato, usa l'indirizzo "from".
    |
    */

    'reply_to' => [
        'address' => env('MAIL_REPLY_TO_ADDRESS', 'support@nexiosolution.local'),
        'name' => env('MAIL_REPLY_TO_NAME', 'NexioSolution Support'),
    ],

    /*
    |--------------------------------------------------------------------------
    | E-Mail Encryption Protocol
    |--------------------------------------------------------------------------
    |
    | Protocollo di crittografia per le connessioni SMTP.
    | Supporta 'tls', 'ssl', o null per nessuna crittografia.
    |
    */

    'encryption' => env('MAIL_ENCRYPTION', 'tls'),

    /*
    |--------------------------------------------------------------------------
    | E-Mail Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configurazione per l'invio di email tramite code.
    | Migliora le performance per invii massivi.
    |
    */

    'queue' => [
        'enabled' => env('MAIL_QUEUE_ENABLED', true),
        'connection' => env('MAIL_QUEUE_CONNECTION', 'redis'),
        'queue' => env('MAIL_QUEUE_NAME', 'emails'),
        'delay' => env('MAIL_QUEUE_DELAY', 0),
        'tries' => env('MAIL_QUEUE_TRIES', 3),
        'timeout' => env('MAIL_QUEUE_TIMEOUT', 60),
        'rate_limit' => env('MAIL_QUEUE_RATE_LIMIT', 10), // email per minuto
    ],

    /*
    |--------------------------------------------------------------------------
    | E-Mail Template Configuration
    |--------------------------------------------------------------------------
    |
    | Configurazione per i template email brandizzati.
    |
    */

    'templates' => [
        'theme' => env('MAIL_TEMPLATE_THEME', 'default'),
        'paths' => [
            resource_path('views/emails'),
        ],
        'options' => [
            'cache' => env('MAIL_TEMPLATE_CACHE', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | E-Mail Branding
    |--------------------------------------------------------------------------
    |
    | Configurazione per personalizzare l'aspetto delle email.
    |
    */

    'branding' => [
        'logo' => env('MAIL_LOGO_URL', '/images/email-logo.png'),
        'logo_width' => env('MAIL_LOGO_WIDTH', 200),
        'logo_height' => env('MAIL_LOGO_HEIGHT', 50),
        'primary_color' => env('MAIL_PRIMARY_COLOR', '#4F46E5'),
        'secondary_color' => env('MAIL_SECONDARY_COLOR', '#6366F1'),
        'background_color' => env('MAIL_BACKGROUND_COLOR', '#F9FAFB'),
        'text_color' => env('MAIL_TEXT_COLOR', '#374151'),
        'link_color' => env('MAIL_LINK_COLOR', '#4F46E5'),
        'footer_text' => env('MAIL_FOOTER_TEXT', '© ' . date('Y') . ' NexioSolution. Tutti i diritti riservati.'),
        'social_links' => [
            'facebook' => env('MAIL_SOCIAL_FACEBOOK'),
            'twitter' => env('MAIL_SOCIAL_TWITTER'),
            'linkedin' => env('MAIL_SOCIAL_LINKEDIN'),
            'instagram' => env('MAIL_SOCIAL_INSTAGRAM'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | E-Mail Categories
    |--------------------------------------------------------------------------
    |
    | Categorie di email per gestire preferenze di notifica.
    |
    */

    'categories' => [
        'transactional' => [
            'name' => 'Email Transazionali',
            'description' => 'Email critiche per il funzionamento del sistema',
            'can_unsubscribe' => false,
        ],
        'notifications' => [
            'name' => 'Notifiche',
            'description' => 'Notifiche su attività e aggiornamenti',
            'can_unsubscribe' => true,
        ],
        'marketing' => [
            'name' => 'Marketing',
            'description' => 'Newsletter e comunicazioni promozionali',
            'can_unsubscribe' => true,
        ],
        'reports' => [
            'name' => 'Report',
            'description' => 'Report periodici e statistiche',
            'can_unsubscribe' => true,
        ],
        'reminders' => [
            'name' => 'Promemoria',
            'description' => 'Promemoria per scadenze e appuntamenti',
            'can_unsubscribe' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | E-Mail Tracking
    |--------------------------------------------------------------------------
    |
    | Configurazione per il tracking delle email.
    |
    */

    'tracking' => [
        'enabled' => env('MAIL_TRACKING_ENABLED', true),
        'opens' => env('MAIL_TRACK_OPENS', true),
        'clicks' => env('MAIL_TRACK_CLICKS', true),
        'pixel_url' => env('MAIL_PIXEL_URL', '/email/pixel'),
    ],

    /*
    |--------------------------------------------------------------------------
    | E-Mail Attachments
    |--------------------------------------------------------------------------
    |
    | Configurazione per gli allegati email.
    |
    */

    'attachments' => [
        'max_size' => env('MAIL_MAX_ATTACHMENT_SIZE', 10 * 1024 * 1024), // 10MB
        'allowed_extensions' => [
            'pdf', 'doc', 'docx', 'xls', 'xlsx',
            'ppt', 'pptx', 'txt', 'csv', 'zip',
            'jpg', 'jpeg', 'png', 'gif', 'bmp',
        ],
        'scan_for_viruses' => env('MAIL_SCAN_ATTACHMENTS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | E-Mail Throttling
    |--------------------------------------------------------------------------
    |
    | Limiti per prevenire spam e abusi.
    |
    */

    'throttle' => [
        'enabled' => env('MAIL_THROTTLE_ENABLED', true),
        'per_minute' => env('MAIL_THROTTLE_PER_MINUTE', 30),
        'per_hour' => env('MAIL_THROTTLE_PER_HOUR', 100),
        'per_day' => env('MAIL_THROTTLE_PER_DAY', 1000),
        'burst' => env('MAIL_THROTTLE_BURST', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | E-Mail Bounce Handling
    |--------------------------------------------------------------------------
    |
    | Configurazione per gestire email non recapitate.
    |
    */

    'bounce' => [
        'enabled' => env('MAIL_BOUNCE_ENABLED', true),
        'address' => env('MAIL_BOUNCE_ADDRESS', 'bounce@nexiosolution.local'),
        'webhook_url' => env('MAIL_BOUNCE_WEBHOOK'),
        'auto_unsubscribe_after' => env('MAIL_BOUNCE_AUTO_UNSUBSCRIBE', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | E-Mail Testing
    |--------------------------------------------------------------------------
    |
    | Configurazione per test email in ambiente di sviluppo.
    |
    */

    'testing' => [
        'enabled' => env('MAIL_TESTING_ENABLED', false),
        'redirect_to' => env('MAIL_TESTING_ADDRESS'),
        'add_original_to' => env('MAIL_TESTING_ADD_ORIGINAL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Markdown Mail Settings
    |--------------------------------------------------------------------------
    |
    | Configurazione per email Markdown.
    |
    */

    'markdown' => [
        'theme' => 'default',
        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | E-Mail Localization
    |--------------------------------------------------------------------------
    |
    | Configurazione per email multilingua.
    |
    */

    'localization' => [
        'enabled' => true,
        'default_locale' => 'it',
        'available_locales' => ['it', 'en', 'es', 'fr', 'de'],
        'detect_from_user' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | E-Mail Logging
    |--------------------------------------------------------------------------
    |
    | Configurazione per il logging delle email.
    |
    */

    'logging' => [
        'enabled' => env('MAIL_LOGGING_ENABLED', true),
        'channel' => env('MAIL_LOG_CHANNEL', 'mail'),
        'level' => env('MAIL_LOG_LEVEL', 'info'),
        'log_body' => env('MAIL_LOG_BODY', false),
        'log_attachments' => env('MAIL_LOG_ATTACHMENTS', false),
        'retention_days' => env('MAIL_LOG_RETENTION', 30),
    ],

];