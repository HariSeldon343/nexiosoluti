@echo off
echo ================================================
echo      TEST BACKEND LARAVEL NEXIOSOLUTION
echo ================================================
echo.

cd /d C:\xampp\htdocs\Nexiosolution\backend

REM Verifica PHP
echo Verifica PHP...
php -v >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERRORE] PHP non trovato
) else (
    echo [OK] PHP installato
)

REM Verifica Composer
echo Verifica Composer...
where composer >nul 2>&1
if %errorlevel% neq 0 (
    if exist composer.phar (
        echo [OK] Composer.phar presente
    ) else (
        echo [WARNING] Composer non trovato
    )
) else (
    echo [OK] Composer installato
)

REM Verifica MySQL
echo Verifica MySQL...
C:\xampp\mysql\bin\mysql -u root -e "SELECT 1" >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERRORE] MySQL non attivo
) else (
    echo [OK] MySQL attivo
)

REM Verifica Database
echo Verifica Database...
C:\xampp\mysql\bin\mysql -u root -e "USE nexiosolution" 2>nul
if %errorlevel% neq 0 (
    echo [WARNING] Database nexiosolution non esiste
) else (
    echo [OK] Database nexiosolution presente
)

REM Verifica .env
echo Verifica file .env...
if exist .env (
    echo [OK] File .env presente
) else (
    echo [ERRORE] File .env mancante
)

REM Verifica vendor
echo Verifica dipendenze...
if exist vendor (
    echo [OK] Dipendenze installate
) else (
    echo [ERRORE] Dipendenze non installate
)

echo.
echo ================================================
echo        TEST ENDPOINT API
echo ================================================
echo.

REM Test Health Check
echo Test endpoint /api/v1/health...
curl -s -o nul -w "Status: %%{http_code}\n" http://localhost:8000/api/v1/health 2>nul
if %errorlevel% neq 0 (
    echo [ERRORE] Server non raggiungibile
    echo.
    echo Prova ad avviare il backend con: start-backend.bat
) else (
    echo [OK] Server raggiungibile
)

echo.
echo ================================================
echo        RIEPILOGO TEST
echo ================================================
echo.
echo Se vedi errori sopra, esegui questi comandi:
echo.
echo 1. Avvia XAMPP Control Panel
echo 2. Avvia Apache e MySQL
echo 3. Esegui: start-backend.bat
echo.
echo ================================================
pause