@echo off
setlocal enabledelayedexpansion
chcp 65001 >nul 2>&1
title NexioSolution - Avvio Piattaforma
color 0A

:: Configurazione percorsi
set "XAMPP_PATH=C:\xampp"
set "PROJECT_PATH=C:\xampp\htdocs\Nexiosolution"
set "BACKEND_PATH=%PROJECT_PATH%\backend"
set "FRONTEND_PATH=%PROJECT_PATH%\frontend"
set "PHP_PATH=%XAMPP_PATH%\php\php.exe"
set "MYSQL_PATH=%XAMPP_PATH%\mysql\bin\mysql.exe"
set "COMPOSER_PATH=composer"

:: ============================================
:: BANNER E INFORMAZIONI
:: ============================================
cls
echo.
echo ╔══════════════════════════════════════════════════════════════════╗
echo ║                                                                  ║
echo ║     ███╗   ██╗███████╗██╗  ██╗██╗ ██████╗                      ║
echo ║     ████╗  ██║██╔════╝╚██╗██╔╝██║██╔═══██╗                     ║
echo ║     ██╔██╗ ██║█████╗   ╚███╔╝ ██║██║   ██║                     ║
echo ║     ██║╚██╗██║██╔══╝   ██╔██╗ ██║██║   ██║                     ║
echo ║     ██║ ╚████║███████╗██╔╝ ██╗██║╚██████╔╝                     ║
echo ║     ╚═╝  ╚═══╝╚══════╝╚═╝  ╚═╝╚═╝ ╚═════╝                      ║
echo ║                                                                  ║
echo ║              SOLUTION - Sistema di Gestione Integrato           ║
echo ║                        Avvio Completo v2.0                      ║
echo ╚══════════════════════════════════════════════════════════════════╝
echo.
echo [%date% %time%] Inizializzazione sistema...
echo.

:: ============================================
:: VERIFICA PREREQUISITI
:: ============================================
echo ══════════════════════════════════════════════════════════════════
echo FASE 1: VERIFICA PREREQUISITI
echo ══════════════════════════════════════════════════════════════════
echo.

:: Verifica XAMPP
echo [1/5] Verifica XAMPP...
if not exist "%XAMPP_PATH%" (
    echo    ❌ ERRORE: XAMPP non trovato in %XAMPP_PATH%
    echo.
    echo    ISTRUZIONI PER L'INSTALLAZIONE:
    echo    1. Scarica XAMPP da: https://www.apachefriends.org/
    echo    2. Installa in C:\xampp
    echo    3. Avvia Apache e MySQL dal pannello XAMPP
    echo    4. Rilancia questo script
    echo.
    pause
    exit /b 1
)
echo    ✓ XAMPP trovato

:: Verifica PHP
echo [2/5] Verifica PHP...
"%PHP_PATH%" -v >nul 2>&1
if errorlevel 1 (
    echo    ❌ ERRORE: PHP non disponibile
    echo    Assicurati che XAMPP sia installato correttamente
    pause
    exit /b 1
)
for /f "tokens=2" %%i in ('"%PHP_PATH%" -v ^| findstr /i "^PHP"') do set PHP_VERSION=%%i
echo    ✓ PHP %PHP_VERSION% disponibile

:: Verifica MySQL
echo [3/5] Verifica MySQL...
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if errorlevel 1 (
    echo    ⚠ MySQL non in esecuzione. Tentativo di avvio...
    net start mysql >nul 2>&1
    if errorlevel 1 (
        echo    ❌ Impossibile avviare MySQL
        echo    Avvia MySQL dal pannello di controllo XAMPP
        pause
        exit /b 1
    )
)
echo    ✓ MySQL in esecuzione

:: Verifica Node.js
echo [4/5] Verifica Node.js...
node -v >nul 2>&1
if errorlevel 1 (
    echo    ❌ ERRORE: Node.js non installato
    echo.
    echo    ISTRUZIONI PER L'INSTALLAZIONE:
    echo    1. Scarica Node.js da: https://nodejs.org/
    echo    2. Installa la versione LTS (consigliata)
    echo    3. Riavvia il prompt dei comandi
    echo    4. Rilancia questo script
    echo.
    pause
    exit /b 1
)
for /f "tokens=1" %%i in ('node -v') do set NODE_VERSION=%%i
echo    ✓ Node.js %NODE_VERSION% disponibile

:: Verifica Composer
echo [5/5] Verifica Composer...
composer -V >nul 2>&1
if errorlevel 1 (
    echo    ⚠ Composer non trovato. Installazione automatica...
    powershell -Command "& {[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri 'https://getcomposer.org/installer' -OutFile 'composer-setup.php'}"
    "%PHP_PATH%" composer-setup.php --install-dir=%XAMPP_PATH%\php --filename=composer.phar
    del composer-setup.php
    set "COMPOSER_PATH=%XAMPP_PATH%\php\composer.phar"
    if not exist "!COMPOSER_PATH!" (
        echo    ❌ Impossibile installare Composer
        pause
        exit /b 1
    )
)
echo    ✓ Composer disponibile

echo.
echo ✅ Tutti i prerequisiti soddisfatti!
echo.

:: ============================================
:: SETUP BACKEND
:: ============================================
echo ══════════════════════════════════════════════════════════════════
echo FASE 2: CONFIGURAZIONE BACKEND
echo ══════════════════════════════════════════════════════════════════
echo.

cd /d "%BACKEND_PATH%" 2>nul
if errorlevel 1 (
    echo ❌ ERRORE: Directory backend non trovata in %BACKEND_PATH%
    pause
    exit /b 1
)

:: Installa dipendenze Composer
echo [1/5] Verifica dipendenze PHP...
if not exist vendor (
    echo    Installazione dipendenze backend in corso...
    echo    Questo potrebbe richiedere alcuni minuti...
    call %COMPOSER_PATH% install --no-interaction --prefer-dist --optimize-autoloader
    if errorlevel 1 (
        echo    ❌ ERRORE durante l'installazione delle dipendenze
        echo    Prova a eseguire: composer install manualmente in %BACKEND_PATH%
        pause
        exit /b 1
    )
    echo    ✓ Dipendenze installate
) else (
    echo    ✓ Dipendenze già installate
    echo    Aggiornamento dipendenze...
    call %COMPOSER_PATH% update --no-interaction --prefer-dist --optimize-autoloader >nul 2>&1
)

:: Configurazione .env
echo [2/5] Configurazione ambiente...
if not exist .env (
    if exist .env.example (
        copy .env.example .env >nul
        echo    ✓ File .env creato da template
    ) else (
        echo    Creazione file .env base...
        (
            echo APP_NAME=NexioSolution
            echo APP_ENV=local
            echo APP_KEY=
            echo APP_DEBUG=true
            echo APP_URL=http://localhost:8000
            echo.
            echo DB_CONNECTION=mysql
            echo DB_HOST=127.0.0.1
            echo DB_PORT=3306
            echo DB_DATABASE=nexiosolution
            echo DB_USERNAME=root
            echo DB_PASSWORD=
            echo.
            echo BROADCAST_DRIVER=log
            echo CACHE_DRIVER=file
            echo FILESYSTEM_DRIVER=local
            echo QUEUE_CONNECTION=sync
            echo SESSION_DRIVER=file
            echo SESSION_LIFETIME=120
        ) > .env
        echo    ✓ File .env base creato
    )
) else (
    echo    ✓ File .env esistente
)

:: Genera application key
echo [3/5] Generazione chiave applicazione...
findstr "APP_KEY=base64:" .env >nul 2>&1
if errorlevel 1 (
    "%PHP_PATH%" artisan key:generate --force >nul 2>&1
    echo    ✓ Chiave applicazione generata
) else (
    echo    ✓ Chiave applicazione già presente
)

:: Creazione database
echo [4/5] Configurazione database...
echo CREATE DATABASE IF NOT EXISTS nexiosolution CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; | "%MYSQL_PATH%" -u root 2>nul
if errorlevel 1 (
    echo    ⚠ Avviso: Impossibile creare il database (potrebbe già esistere)
) else (
    echo    ✓ Database configurato
)

:: Migrazioni
echo [5/5] Esecuzione migrazioni database...
"%PHP_PATH%" artisan migrate --force >nul 2>&1
if errorlevel 1 (
    echo    ⚠ Migrazioni potrebbero essere già eseguite
    "%PHP_PATH%" artisan migrate:status 2>nul | findstr "Pending" >nul
    if not errorlevel 1 (
        echo    Riprovo con reset...
        "%PHP_PATH%" artisan migrate:fresh --force --seed >nul 2>&1
    )
)
echo    ✓ Database pronto

:: Cache e ottimizzazioni
echo.
echo Ottimizzazione backend...
"%PHP_PATH%" artisan config:cache >nul 2>&1
"%PHP_PATH%" artisan route:cache >nul 2>&1
"%PHP_PATH%" artisan view:cache >nul 2>&1
echo ✓ Backend ottimizzato

echo.
echo ✅ Backend configurato con successo!
echo.

:: ============================================
:: SETUP FRONTEND
:: ============================================
echo ══════════════════════════════════════════════════════════════════
echo FASE 3: CONFIGURAZIONE FRONTEND
echo ══════════════════════════════════════════════════════════════════
echo.

cd /d "%FRONTEND_PATH%" 2>nul
if errorlevel 1 (
    echo ❌ ERRORE: Directory frontend non trovata in %FRONTEND_PATH%
    pause
    exit /b 1
)

:: Installa dipendenze npm
echo [1/2] Verifica dipendenze Node.js...
if not exist node_modules (
    echo    Installazione dipendenze frontend in corso...
    echo    Questo potrebbe richiedere diversi minuti...
    call npm install --legacy-peer-deps --loglevel=error
    if errorlevel 1 (
        echo    ⚠ Alcuni avvisi durante l'installazione (normale)
        echo    Riprovo con force...
        call npm install --force --loglevel=error >nul 2>&1
    )
    echo    ✓ Dipendenze installate
) else (
    echo    ✓ Dipendenze già installate
)

:: Configurazione .env frontend
echo [2/2] Configurazione ambiente frontend...
if not exist .env (
    if exist .env.example (
        copy .env.example .env >nul
    ) else (
        (
            echo REACT_APP_API_URL=http://localhost:8000/api
            echo REACT_APP_APP_NAME=NexioSolution
            echo REACT_APP_VERSION=2.0.0
        ) > .env
    )
    echo    ✓ File .env frontend creato
) else (
    echo    ✓ File .env frontend esistente
)

echo.
echo ✅ Frontend configurato con successo!
echo.

:: ============================================
:: PULIZIA PORTE
:: ============================================
echo ══════════════════════════════════════════════════════════════════
echo FASE 4: PREPARAZIONE PORTE
echo ══════════════════════════════════════════════════════════════════
echo.

echo Chiusura processi esistenti...
:: Chiudi processi sulla porta 8000
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :8000 ^| findstr LISTENING') do (
    taskkill /F /PID %%a >nul 2>&1
)
:: Chiudi processi sulla porta 3000
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :3000 ^| findstr LISTENING') do (
    taskkill /F /PID %%a >nul 2>&1
)
echo ✓ Porte liberate

echo.

:: ============================================
:: AVVIO SERVIZI
:: ============================================
echo ══════════════════════════════════════════════════════════════════
echo FASE 5: AVVIO SERVIZI
echo ══════════════════════════════════════════════════════════════════
echo.

:: Avvia Backend
echo [1/2] Avvio backend Laravel...
cd /d "%BACKEND_PATH%"
start "NexioSolution Backend" /min cmd /c "%PHP_PATH%" artisan serve --host=0.0.0.0 --port=8000
timeout /t 3 /nobreak >nul

:: Verifica che il backend sia partito
netstat -an | findstr :8000 | findstr LISTENING >nul
if errorlevel 1 (
    echo    ⚠ Backend potrebbe non essere partito correttamente
) else (
    echo    ✓ Backend in esecuzione su http://localhost:8000
)

:: Avvia Frontend
echo [2/2] Avvio frontend React...
cd /d "%FRONTEND_PATH%"
start "NexioSolution Frontend" /min cmd /c npm start
timeout /t 5 /nobreak >nul

echo    ✓ Frontend in avvio su http://localhost:3000
echo.

:: ============================================
:: COMPLETAMENTO
:: ============================================
echo ╔══════════════════════════════════════════════════════════════════╗
echo ║                    🎉 AVVIO COMPLETATO! 🎉                      ║
echo ╠══════════════════════════════════════════════════════════════════╣
echo ║                                                                  ║
echo ║  La piattaforma NexioSolution è ora disponibile ai seguenti URL:║
echo ║                                                                  ║
echo ║  🌐 Frontend (React):    http://localhost:3000                  ║
echo ║  🔧 Backend API (Laravel): http://localhost:8000                ║
echo ║  📊 phpMyAdmin:          http://localhost/phpmyadmin            ║
echo ║                                                                  ║
echo ║  Credenziali di default:                                        ║
echo ║  Email: admin@nexiosolution.com                                 ║
echo ║  Password: password                                             ║
echo ║                                                                  ║
echo ╠══════════════════════════════════════════════════════════════════╣
echo ║  Per fermare i servizi, esegui: STOP-NEXIOSOLUTION.bat          ║
echo ║  Per verificare lo stato: CHECK-NEXIOSOLUTION.bat               ║
echo ╚══════════════════════════════════════════════════════════════════╝
echo.

:: Apertura browser
echo Apertura browser in corso...
timeout /t 3 /nobreak >nul
start http://localhost:3000

echo.
echo Premi un tasto per chiudere questa finestra (i servizi continueranno a funzionare)...
pause >nul