@echo off
REM ====================================================
REM NexioSolution - Fix Rapido Problemi Comuni
REM ====================================================

echo ====================================================
echo   NexioSolution - Fix Rapido Problemi
echo ====================================================
echo.

set "PROJECT_DIR=C:\xampp\htdocs\Nexiosolution"
set "BACKEND_DIR=%PROJECT_DIR%\backend"
set "FRONTEND_DIR=%PROJECT_DIR%\frontend"

set "GREEN=[92m"
set "RED=[91m"
set "YELLOW=[93m"
set "RESET=[0m"

echo %YELLOW%Risoluzione problemi comuni...%RESET%
echo.

REM Fix 1: Permessi storage Laravel
echo %YELLOW%[1/8] Fix permessi storage Laravel...%RESET%
cd /d "%BACKEND_DIR%"
if not exist storage\framework\cache mkdir storage\framework\cache
if not exist storage\framework\sessions mkdir storage\framework\sessions
if not exist storage\framework\views mkdir storage\framework\views
if not exist storage\logs mkdir storage\logs
if not exist bootstrap\cache mkdir bootstrap\cache
echo %GREEN%√ Directory storage create/verificate%RESET%

REM Fix 2: Storage link
echo.
echo %YELLOW%[2/8] Creazione storage link...%RESET%
php artisan storage:link 2>nul
echo %GREEN%√ Storage link verificato%RESET%

REM Fix 3: Composer autoload
echo.
echo %YELLOW%[3/8] Rigenerazione autoload...%RESET%
call composer dump-autoload
echo %GREEN%√ Autoload rigenerato%RESET%

REM Fix 4: Clear all caches
echo.
echo %YELLOW%[4/8] Pulizia cache completa...%RESET%
php artisan cache:clear 2>nul
php artisan config:clear 2>nul
php artisan route:clear 2>nul
php artisan view:clear 2>nul
echo %GREEN%√ Cache pulita%RESET%

REM Fix 5: Generate key if missing
echo.
echo %YELLOW%[5/8] Verifica application key...%RESET%
findstr "APP_KEY=" .env | findstr "base64" >nul
if errorlevel 1 (
    php artisan key:generate
    echo %GREEN%√ Application key generata%RESET%
) else (
    echo %GREEN%√ Application key presente%RESET%
)

REM Fix 6: Optimize Laravel
echo.
echo %YELLOW%[6/8] Ottimizzazione Laravel...%RESET%
php artisan config:cache
php artisan route:cache 2>nul
echo %GREEN%√ Laravel ottimizzato%RESET%

REM Fix 7: Fix npm issues
echo.
echo %YELLOW%[7/8] Verifica dipendenze frontend...%RESET%
cd /d "%FRONTEND_DIR%"
if not exist node_modules (
    echo Installazione dipendenze npm...
    call npm install
    echo %GREEN%√ Dipendenze npm installate%RESET%
) else (
    echo %GREEN%√ Dipendenze npm presenti%RESET%
)

REM Fix 8: Fix common port issues
echo.
echo %YELLOW%[8/8] Verifica porte...%RESET%
netstat -an | findstr :3000 | findstr LISTENING >nul
if not errorlevel 1 (
    echo %YELLOW%Porta 3000 occupata. Termino processo...%RESET%
    for /f "tokens=5" %%a in ('netstat -aon ^| findstr :3000 ^| findstr LISTENING') do (
        taskkill /F /PID %%a 2>nul
    )
    echo %GREEN%√ Porta 3000 liberata%RESET%
)

netstat -an | findstr :8000 | findstr LISTENING >nul
if not errorlevel 1 (
    echo %YELLOW%Porta 8000 occupata. Termino processo...%RESET%
    for /f "tokens=5" %%a in ('netstat -aon ^| findstr :8000 ^| findstr LISTENING') do (
        taskkill /F /PID %%a 2>nul
    )
    echo %GREEN%√ Porta 8000 liberata%RESET%
)

echo.
echo %GREEN%=====================================================%RESET%
echo %GREEN%  FIX COMPLETATO!%RESET%
echo %GREEN%=====================================================%RESET%
echo.
echo Problemi risolti:
echo - Permessi storage corretti
echo - Storage link creato
echo - Autoload rigenerato
echo - Cache pulita e ottimizzata
echo - Application key verificata
echo - Dipendenze verificate
echo - Porte liberate
echo.
echo %YELLOW%Ora puoi avviare i servizi con: start-services.bat%RESET%
echo.
pause