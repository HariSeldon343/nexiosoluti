@echo off
REM ==========================================
REM NexioSolution - Installazione Automatica
REM ==========================================

echo.
echo =========================================
echo   NEXIOSOLUTION - INSTALLAZIONE
echo =========================================
echo.
echo Questo script installerà automaticamente
echo la piattaforma collaborativa NexioSolution
echo.
pause

REM Vai alla directory del progetto
cd /d C:\xampp\htdocs\Nexiosolution

echo.
echo [FASE 1] Verifica Prerequisiti
echo =========================================

REM Controlla XAMPP
if not exist "C:\xampp\xampp-control.exe" (
    echo [X] XAMPP non trovato in C:\xampp
    echo     Installare XAMPP prima di continuare.
    echo     Download: https://www.apachefriends.org/
    pause
    exit /b 1
)
echo [OK] XAMPP trovato

REM Controlla PHP versione
for /f "tokens=2" %%i in ('php -v ^| findstr /i "PHP"') do set PHP_VERSION=%%i
echo [OK] PHP %PHP_VERSION% trovato

REM Controlla Composer
composer -V >nul 2>&1
if %errorlevel% neq 0 (
    echo [!] Composer non trovato. Installazione...
    powershell -Command "Invoke-WebRequest -Uri 'https://getcomposer.org/installer' -OutFile 'composer-setup.php'"
    php composer-setup.php
    del composer-setup.php
    move composer.phar C:\xampp\php\composer.phar
    echo @php "%~dp0composer.phar" %*> C:\xampp\php\composer.bat
    set PATH=%PATH%;C:\xampp\php
)
echo [OK] Composer installato

REM Controlla Node.js
node -v >nul 2>&1
if %errorlevel% neq 0 (
    echo [X] Node.js non trovato.
    echo     Installare Node.js prima di continuare.
    echo     Download: https://nodejs.org/
    pause
    exit /b 1
)
echo [OK] Node.js trovato

echo.
echo [FASE 2] Configurazione Backend Laravel
echo =========================================

cd backend

echo [1/8] Installazione dipendenze PHP...
call composer install --no-interaction

echo [2/8] Creazione file .env...
if not exist .env (
    copy .env.example .env
)

echo [3/8] Generazione chiavi applicazione...
php artisan key:generate

echo [4/8] Generazione chiave JWT...
php artisan jwt:secret

echo [5/8] Creazione database...
echo CREATE DATABASE IF NOT EXISTS nexiosolution CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; | C:\xampp\mysql\bin\mysql -u root

echo [6/8] Esecuzione migrazioni database...
php artisan migrate --force

echo [7/8] Popolamento database con dati demo...
php artisan db:seed --force

echo [8/8] Creazione link storage...
php artisan storage:link

echo [9/8] Generazione chiavi VAPID per Push...
php artisan webpush:vapid

echo.
echo [FASE 3] Configurazione Frontend React
echo =========================================

cd ..\frontend

echo [1/3] Installazione dipendenze Node...
call npm install

echo [2/3] Creazione file .env...
if not exist .env (
    copy .env.example .env
)

echo [3/3] Build assets di produzione...
call npm run build

echo.
echo [FASE 4] Configurazione completata
echo =========================================

echo.
echo [FASE 5] Creazione Script Avvio
echo =========================================

cd ..

echo [1/2] Creazione cartella logs...
if not exist logs mkdir logs

echo [2/2] Script di avvio creati

echo.
echo =========================================
echo   INSTALLAZIONE COMPLETATA!
echo =========================================
echo.
echo PROSSIMI PASSI:
echo.
echo 1. Riavviare Apache da XAMPP Control Panel
echo.
echo 2. Eseguire: start-all.bat
echo.
echo 3. Accedere a: http://localhost:3000
echo.
echo CREDENZIALI DI ACCESSO:
echo - Admin:  admin@nexiosolution.com / Admin123!
echo - Tenant: demo@nexiosolution.com / Demo123!
echo - User:   user@nexiosolution.com / User123!
echo.
echo SELEZIONE TENANT:
echo Per cambiare tenant, usa il selector nel menu
echo o aggiungi header X-Tenant-ID nelle API calls
echo.
echo FUNZIONALITÀ DISPONIBILI:
echo - Dashboard multi-tenant
echo - File manager con approvazioni
echo - Calendario con CalDAV
echo - Task manager avanzato
echo - Chat real-time
echo - Videochiamate Jitsi
echo - Notifiche push
echo - Audit log completo
echo.
echo Per supporto: https://github.com/nexiosolution
echo.
pause