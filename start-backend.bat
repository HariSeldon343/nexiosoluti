@echo off
echo ================================================
echo         AVVIO BACKEND LARAVEL NEXIOSOLUTION
echo ================================================
echo.

cd /d C:\xampp\htdocs\Nexiosolution\backend

REM Verifica che PHP sia disponibile
where php >nul 2>&1
if %errorlevel% neq 0 (
    echo ERRORE: PHP non trovato nel PATH
    echo Assicurati che XAMPP sia installato e PHP sia nel PATH
    pause
    exit /b 1
)

REM Verifica che Composer sia disponibile
where composer >nul 2>&1
if %errorlevel% neq 0 (
    echo ERRORE: Composer non trovato nel PATH
    echo Installando Composer localmente...
    powershell -Command "Invoke-WebRequest -Uri https://getcomposer.org/composer.phar -OutFile composer.phar"
    set COMPOSER=php composer.phar
) else (
    set COMPOSER=composer
)

REM Installa dipendenze se necessario
if not exist vendor (
    echo Installazione dipendenze Laravel...
    call %COMPOSER% install --no-interaction --prefer-dist --optimize-autoloader
    if %errorlevel% neq 0 (
        echo ERRORE: Installazione dipendenze fallita
        pause
        exit /b 1
    )
)

REM Crea .env se non esiste
if not exist .env (
    echo Creazione file .env...
    copy .env.example .env >nul
    php artisan key:generate

    REM Genera JWT secret se necessario
    php artisan jwt:secret --force 2>nul
)

REM Verifica che MySQL sia attivo
C:\xampp\mysql\bin\mysql -u root -e "SELECT 1" >nul 2>&1
if %errorlevel% neq 0 (
    echo Avvio MySQL...
    C:\xampp\mysql_start.bat
    timeout /t 5 /nobreak >nul
)

REM Crea database se non esiste
echo Creazione database nexiosolution se non esiste...
C:\xampp\mysql\bin\mysql -u root -e "CREATE DATABASE IF NOT EXISTS nexiosolution CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>nul

REM Esegui migrazioni
echo Esecuzione migrazioni database...
php artisan migrate --force 2>nul

REM Esegui seeder per utente demo
echo Creazione utente demo...
php artisan db:seed --force 2>nul

REM Ottimizza Laravel
echo Ottimizzazione Laravel...
php artisan config:cache
php artisan route:cache 2>nul
php artisan view:cache

REM Pulisci cache
php artisan cache:clear

echo.
echo ================================================
echo    BACKEND LARAVEL AVVIATO CORRETTAMENTE!
echo ================================================
echo.
echo Avvio server su http://localhost:8000
echo.
echo Credenziali demo:
echo Email: admin@nexiosolution.com
echo Password: password123
echo.
echo Premi CTRL+C per fermare il server
echo ================================================
echo.

REM Avvia server Laravel
php artisan serve --host=127.0.0.1 --port=8000