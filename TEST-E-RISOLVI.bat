@echo off
setlocal enabledelayedexpansion

REM ===============================================
REM   NexioSolution - Test e Risoluzione Problemi
REM ===============================================

color 0A
cls

echo.
echo ================================================
echo   NEXIOSOLUTION - TEST E RISOLUZIONE PROBLEMI
echo ================================================
echo.

REM Vai alla directory del progetto
cd /d C:\xampp\htdocs\Nexiosolution

echo [FASE 1] Verifica Prerequisiti
echo ------------------------------------------------

REM Controlla PHP
echo Controllo PHP...
php -v >nul 2>&1
if %errorlevel% neq 0 (
    color 0C
    echo [ERRORE] PHP non trovato!
    echo.
    echo SOLUZIONE:
    echo 1. Aggiungi C:\xampp\php al PATH di sistema
    echo 2. Riavvia il terminale
    echo.
    pause
    exit /b 1
) else (
    for /f "tokens=2" %%i in ('php -v 2^>nul ^| findstr /i "PHP"') do set PHP_VER=%%i
    echo [OK] PHP !PHP_VER! trovato
)

REM Controlla Composer
echo Controllo Composer...
composer -V >nul 2>&1
if %errorlevel% neq 0 (
    echo [!] Composer non trovato. Installazione...

    REM Scarica e installa Composer
    powershell -Command "Invoke-WebRequest -Uri 'https://getcomposer.org/installer' -OutFile 'composer-setup.php'"
    php composer-setup.php --install-dir=C:\xampp\php --filename=composer.phar
    del composer-setup.php

    REM Crea batch file per Composer
    echo @echo off > C:\xampp\php\composer.bat
    echo php "%%~dp0composer.phar" %%* >> C:\xampp\php\composer.bat

    echo [OK] Composer installato
) else (
    echo [OK] Composer trovato
)

REM Controlla Node.js
echo Controllo Node.js...
node -v >nul 2>&1
if %errorlevel% neq 0 (
    color 0C
    echo [ERRORE] Node.js non trovato!
    echo.
    echo SOLUZIONE:
    echo 1. Scarica Node.js da https://nodejs.org/
    echo 2. Installa la versione LTS
    echo 3. Riavvia questo script
    echo.
    pause
    exit /b 1
) else (
    for /f "tokens=1" %%i in ('node -v') do set NODE_VER=%%i
    echo [OK] Node.js !NODE_VER! trovato
)

REM Controlla MySQL
echo Controllo MySQL...
C:\xampp\mysql\bin\mysql -u root -e "SELECT 1" >nul 2>&1
if %errorlevel% neq 0 (
    echo [!] MySQL non in esecuzione
    echo Avvio MySQL...
    start /B C:\xampp\mysql\bin\mysqld.exe
    timeout /t 5 /nobreak >nul
    echo [OK] MySQL avviato
) else (
    echo [OK] MySQL in esecuzione
)

echo.
echo [FASE 2] Setup Backend Laravel
echo ------------------------------------------------

cd backend

REM Crea .env se non esiste
if not exist .env (
    echo Creazione file .env...
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
        echo JWT_SECRET=
        echo JWT_TTL=60
        echo.
        echo BROADCAST_DRIVER=log
        echo CACHE_DRIVER=file
        echo QUEUE_CONNECTION=database
        echo SESSION_DRIVER=file
        echo.
        echo MAIL_MAILER=smtp
        echo MAIL_HOST=smtp.gmail.com
        echo MAIL_PORT=587
        echo MAIL_USERNAME=
        echo MAIL_PASSWORD=
        echo.
        echo PUSHER_APP_ID=local
        echo PUSHER_APP_KEY=local
        echo PUSHER_APP_SECRET=local
        echo PUSHER_HOST=127.0.0.1
        echo PUSHER_PORT=6001
        echo PUSHER_SCHEME=http
        echo.
        echo TENANT_IDENTIFICATION=header
        echo TENANT_HEADER=X-Tenant-ID
        echo DEFAULT_TENANT=demo
    ) > .env
    echo [OK] File .env creato
)

REM Installa dipendenze se necessario
if not exist vendor (
    echo Installazione dipendenze PHP...
    call composer install --no-interaction --no-scripts
    call composer update --no-scripts
)

REM Genera chiavi
echo Generazione chiavi applicazione...
php artisan key:generate --force >nul 2>&1

REM Crea database
echo Creazione database...
echo CREATE DATABASE IF NOT EXISTS nexiosolution CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; | C:\xampp\mysql\bin\mysql -u root 2>nul

REM Esegui migrazioni
echo Esecuzione migrazioni...
php artisan migrate --force >nul 2>&1
if %errorlevel% neq 0 (
    echo [!] Errore nelle migrazioni, ripristino...
    php artisan migrate:fresh --force >nul 2>&1
)

echo [OK] Backend configurato

echo.
echo [FASE 3] Setup Frontend React
echo ------------------------------------------------

cd ..\frontend

REM Crea .env se non esiste
if not exist .env (
    echo Creazione file .env...
    (
        echo VITE_API_URL=http://localhost:8000
        echo VITE_API_BASE=http://localhost:8000/api
        echo VITE_WS_URL=ws://localhost:6001
        echo VITE_TENANT_MODE=header
        echo VITE_DEFAULT_TENANT=demo
    ) > .env
    echo [OK] File .env creato
)

REM Installa dipendenze se necessario
if not exist node_modules (
    echo Installazione dipendenze Node...
    call npm install --legacy-peer-deps
)

echo [OK] Frontend configurato

echo.
echo [FASE 4] Test Porte
echo ------------------------------------------------

REM Test porta 3000
netstat -an | findstr :3000 >nul 2>&1
if %errorlevel% equ 0 (
    echo [!] Porta 3000 occupata
    echo Liberazione porta...
    for /f "tokens=5" %%a in ('netstat -ano ^| findstr :3000 ^| findstr LISTENING') do (
        taskkill /PID %%a /F >nul 2>&1
    )
    echo [OK] Porta 3000 liberata
) else (
    echo [OK] Porta 3000 libera
)

REM Test porta 8000
netstat -an | findstr :8000 >nul 2>&1
if %errorlevel% equ 0 (
    echo [!] Porta 8000 occupata
    echo Liberazione porta...
    for /f "tokens=5" %%a in ('netstat -ano ^| findstr :8000 ^| findstr LISTENING') do (
        taskkill /PID %%a /F >nul 2>&1
    )
    echo [OK] Porta 8000 liberata
) else (
    echo [OK] Porta 8000 libera
)

echo.
echo [FASE 5] Avvio Servizi
echo ------------------------------------------------

cd ..

echo Avvio Backend Laravel...
cd backend
start "NexioSolution Backend" cmd /c "php artisan serve --host=127.0.0.1 --port=8000"
timeout /t 3 /nobreak >nul

echo Avvio Frontend React...
cd ..\frontend
start "NexioSolution Frontend" cmd /c "npm run dev"
timeout /t 5 /nobreak >nul

echo.
echo ================================================
echo   SISTEMA AVVIATO CON SUCCESSO!
echo ================================================
echo.
echo ACCESSO APPLICAZIONE:
echo - Frontend:  http://localhost:3000
echo - Backend:   http://localhost:8000
echo - API Docs:  http://localhost:8000/api/documentation
echo.
echo CREDENZIALI TEST:
echo - Email:     admin@nexiosolution.com
echo - Password:  Admin123!
echo.
echo Per fermare i servizi, chiudi le finestre del terminale
echo o premi Ctrl+C in ogni finestra.
echo.

REM Apri browser
echo Apertura browser...
timeout /t 2 /nobreak >nul
start http://localhost:3000

echo.
pause