@echo off
REM ====================================================
REM NexioSolution - Creazione file .env Frontend
REM ====================================================

set "FRONTEND_DIR=C:\xampp\htdocs\Nexiosolution\frontend"
cd /d "%FRONTEND_DIR%"

echo Creazione file .env per frontend React...

(
echo # ====================================================
echo # NexioSolution Frontend Configuration
echo # Environment: Local Development
echo # ====================================================
echo.
echo # API Backend URL - IMPORTANTE: usa localhost:8000 per Laravel
echo VITE_API_URL=http://localhost:8000
echo VITE_API_BASE_URL=http://localhost:8000/api
echo.
echo # App Configuration
echo VITE_APP_NAME=NexioSolution
echo VITE_APP_ENV=development
echo VITE_APP_DEBUG=true
echo.
echo # WebSocket Configuration (se utilizzato^)
echo VITE_WEBSOCKET_ENABLED=false
echo VITE_WEBSOCKET_HOST=localhost
echo VITE_WEBSOCKET_PORT=6001
echo VITE_WEBSOCKET_KEY=
echo VITE_WEBSOCKET_CLUSTER=mt1
echo.
echo # Pusher Configuration (se utilizzato^)
echo VITE_PUSHER_APP_KEY=
echo VITE_PUSHER_APP_CLUSTER=mt1
echo.
echo # Features Toggle
echo VITE_ENABLE_REGISTRATION=true
echo VITE_ENABLE_PASSWORD_RESET=true
echo VITE_ENABLE_SOCIAL_LOGIN=false
echo VITE_ENABLE_TWO_FACTOR=false
echo.
echo # Storage URLs
echo VITE_STORAGE_URL=http://localhost:8000/storage
echo.
echo # Third Party Services
echo.
echo # Google Maps (se utilizzato^)
echo VITE_GOOGLE_MAPS_API_KEY=
echo.
echo # Google Analytics (se utilizzato^)
echo VITE_GA_TRACKING_ID=
echo.
echo # Facebook SDK (se utilizzato^)
echo VITE_FACEBOOK_APP_ID=
echo.
echo # Stripe (se utilizzato per pagamenti^)
echo VITE_STRIPE_PUBLIC_KEY=
echo.
echo # OnlyOffice (se configurato^)
echo VITE_ONLYOFFICE_ENABLED=false
echo VITE_ONLYOFFICE_SERVER_URL=
echo.
echo # Jitsi Meet (se configurato^)
echo VITE_JITSI_ENABLED=false
echo VITE_JITSI_DOMAIN=meet.jit.si
echo.
echo # CalDAV/CardDAV (se configurato^)
echo VITE_CALDAV_ENABLED=false
echo VITE_CARDDAV_ENABLED=false
echo.
echo # Development Server Port
echo VITE_PORT=3000
echo.
echo # Build Configuration
echo VITE_BUILD_PATH=dist
echo VITE_PUBLIC_PATH=/
echo.
echo # Debugging
echo VITE_ENABLE_DEBUG_PANEL=true
echo VITE_LOG_LEVEL=debug
echo.
echo # Locale Settings
echo VITE_DEFAULT_LOCALE=it
echo VITE_FALLBACK_LOCALE=en
echo VITE_AVAILABLE_LOCALES=it,en
echo.
echo # Date/Time Format
echo VITE_DATE_FORMAT=DD/MM/YYYY
echo VITE_TIME_FORMAT=HH:mm
echo VITE_TIMEZONE=Europe/Rome
echo.
echo # Security
echo VITE_ENABLE_HTTPS=false
echo VITE_CSP_ENABLED=false
echo.
echo # Performance
echo VITE_ENABLE_PWA=false
echo VITE_ENABLE_SERVICE_WORKER=false
echo VITE_CHUNK_SIZE_WARNING_LIMIT=500
) > .env

echo.
echo File .env creato con successo in: %FRONTEND_DIR%\.env
echo.
echo NOTA: Questo file contiene la configurazione per il frontend React.
echo L'URL dell'API backend è impostato su http://localhost:8000
echo.
pause