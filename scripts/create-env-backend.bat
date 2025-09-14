@echo off
REM ====================================================
REM NexioSolution - Creazione file .env Backend
REM ====================================================

set "BACKEND_DIR=C:\xampp\htdocs\Nexiosolution\backend"
cd /d "%BACKEND_DIR%"

echo Creazione file .env per backend Laravel...

(
echo # ====================================================
echo # NexioSolution Backend Configuration
echo # Environment: Local Development (XAMPP Windows^)
echo # ====================================================
echo.
echo APP_NAME=NexioSolution
echo APP_ENV=local
echo APP_KEY=
echo APP_DEBUG=true
echo APP_URL=http://localhost:8000
echo.
echo # Frontend URL per CORS
echo FRONTEND_URL=http://localhost:3000
echo SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000
echo SESSION_DOMAIN=localhost
echo.
echo LOG_CHANNEL=stack
echo LOG_DEPRECATIONS_CHANNEL=null
echo LOG_LEVEL=debug
echo.
echo # ====================================================
echo # Database Configuration (XAMPP MySQL^)
echo # ====================================================
echo DB_CONNECTION=mysql
echo DB_HOST=127.0.0.1
echo DB_PORT=3306
echo DB_DATABASE=nexiosolution
echo DB_USERNAME=root
echo DB_PASSWORD=
echo.
echo # ====================================================
echo # Cache and Session
echo # ====================================================
echo BROADCAST_DRIVER=log
echo CACHE_DRIVER=file
echo FILESYSTEM_DISK=local
echo QUEUE_CONNECTION=database
echo SESSION_DRIVER=file
echo SESSION_LIFETIME=120
echo.
echo # ====================================================
echo # Redis (Optional - decommentare se installato^)
echo # ====================================================
echo # REDIS_HOST=127.0.0.1
echo # REDIS_PASSWORD=null
echo # REDIS_PORT=6379
echo.
echo # ====================================================
echo # Mail Configuration (XAMPP^)
echo # ====================================================
echo MAIL_MAILER=smtp
echo MAIL_HOST=localhost
echo MAIL_PORT=1025
echo MAIL_USERNAME=null
echo MAIL_PASSWORD=null
echo MAIL_ENCRYPTION=null
echo MAIL_FROM_ADDRESS="info@nexiosolution.local"
echo MAIL_FROM_NAME="${APP_NAME}"
echo.
echo # Per test email in sviluppo (MailHog o Mailtrap^)
echo # MAIL_MAILER=smtp
echo # MAIL_HOST=smtp.mailtrap.io
echo # MAIL_PORT=2525
echo # MAIL_USERNAME=your_username
echo # MAIL_PASSWORD=your_password
echo.
echo # ====================================================
echo # AWS (Optional^)
echo # ====================================================
echo AWS_ACCESS_KEY_ID=
echo AWS_SECRET_ACCESS_KEY=
echo AWS_DEFAULT_REGION=us-east-1
echo AWS_BUCKET=
echo AWS_USE_PATH_STYLE_ENDPOINT=false
echo.
echo # ====================================================
echo # Pusher / WebSockets (Optional^)
echo # ====================================================
echo PUSHER_APP_ID=
echo PUSHER_APP_KEY=
echo PUSHER_APP_SECRET=
echo PUSHER_HOST=
echo PUSHER_PORT=443
echo PUSHER_SCHEME=https
echo PUSHER_APP_CLUSTER=mt1
echo.
echo # Laravel WebSockets (se installato^)
echo LARAVEL_WEBSOCKETS_SSL_LOCAL_CERT=null
echo LARAVEL_WEBSOCKETS_SSL_LOCAL_PK=null
echo LARAVEL_WEBSOCKETS_SSL_PASSPHRASE=null
echo.
echo # ====================================================
echo # JWT Authentication (se installato^)
echo # ====================================================
echo JWT_SECRET=
echo JWT_TTL=60
echo JWT_REFRESH_TTL=20160
echo JWT_ALGO=HS256
echo.
echo # ====================================================
echo # Vite Configuration
echo # ====================================================
echo VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
echo VITE_PUSHER_HOST="${PUSHER_HOST}"
echo VITE_PUSHER_PORT="${PUSHER_PORT}"
echo VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
echo VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
echo.
echo # ====================================================
echo # Additional Services
echo # ====================================================
echo.
echo # OnlyOffice Document Server (se configurato^)
echo ONLYOFFICE_DOCUMENT_SERVER_URL=
echo ONLYOFFICE_JWT_SECRET=
echo.
echo # Jitsi Meet (se configurato^)
echo JITSI_DOMAIN=meet.jit.si
echo JITSI_APP_ID=
echo JITSI_JWT_SECRET=
echo.
echo # CalDAV/CardDAV (SabreDAV^)
echo CALDAV_ENABLED=false
echo CALDAV_BASE_URI=/dav
echo CARDDAV_ENABLED=false
echo.
echo # ====================================================
echo # Development Tools
echo # ====================================================
echo DEBUGBAR_ENABLED=true
echo TELESCOPE_ENABLED=false
echo.
echo # ====================================================
echo # Security
echo # ====================================================
echo SECURE_COOKIES=false
echo SAME_SITE_COOKIES=lax
) > .env

echo.
echo File .env creato con successo in: %BACKEND_DIR%\.env
echo.
echo IMPORTANTE: Esegui questi comandi per completare la configurazione:
echo   1. php artisan key:generate
echo   2. php artisan jwt:secret (se usi JWT)
echo.
pause