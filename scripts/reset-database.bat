@echo off
REM ====================================================
REM NexioSolution - Reset Database
REM ====================================================
setlocal enabledelayedexpansion

echo ====================================================
echo   NexioSolution - Reset Database
echo ====================================================
echo.

set "PROJECT_DIR=C:\xampp\htdocs\Nexiosolution"
set "BACKEND_DIR=%PROJECT_DIR%\backend"

set "GREEN=[92m"
set "RED=[91m"
set "YELLOW=[93m"
set "RESET=[0m"

echo %RED%ATTENZIONE: Questa operazione cancellerà tutti i dati!%RESET%
echo.
set /p CONFIRM="Sei sicuro di voler resettare il database? (s/n): "
if /i "%CONFIRM%" neq "s" (
    echo Operazione annullata.
    pause
    exit /b 0
)

cd /d "%BACKEND_DIR%"

echo.
echo %YELLOW%[1/5] Backup configurazione attuale...%RESET%
echo ----------------------------------------
if exist .env (
    copy .env .env.backup.%date:~-4%%date:~3,2%%date:~0,2%_%time:~0,2%%time:~3,2%%time:~6,2%.txt 2>nul
    echo %GREEN%√ Backup .env creato%RESET%
)

echo.
echo %YELLOW%[2/5] Eliminazione database esistente...%RESET%
echo ----------------------------------------
C:\xampp\mysql\bin\mysql -u root -e "DROP DATABASE IF EXISTS nexiosolution;" 2>nul
echo %GREEN%√ Database eliminato%RESET%

echo.
echo %YELLOW%[3/5] Creazione nuovo database...%RESET%
echo ----------------------------------------
C:\xampp\mysql\bin\mysql -u root -e "CREATE DATABASE nexiosolution CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>nul
if errorlevel 1 (
    echo %RED%Errore nella creazione del database%RESET%
    echo Verifica che MySQL sia in esecuzione
    pause
    exit /b 1
)
echo %GREEN%√ Database creato%RESET%

echo.
echo %YELLOW%[4/5] Esecuzione migrazioni...%RESET%
echo ----------------------------------------
php artisan migrate:fresh --force
if errorlevel 1 (
    echo %RED%Errore durante le migrazioni%RESET%
    pause
    exit /b 1
)
echo %GREEN%√ Migrazioni completate%RESET%

echo.
echo %YELLOW%[5/5] Seeding database (dati di esempio)...%RESET%
echo ----------------------------------------
set /p SEED="Vuoi inserire dati di esempio? (s/n): "
if /i "%SEED%"=="s" (
    php artisan db:seed --force
    if errorlevel 1 (
        echo %YELLOW%Seeding completato con avvertimenti%RESET%
    ) else (
        echo %GREEN%√ Dati di esempio inseriti%RESET%
    )
) else (
    echo Seeding saltato
)

REM Crea utente amministratore
echo.
set /p ADMIN="Vuoi creare un utente amministratore? (s/n): "
if /i "%ADMIN%"=="s" (
    echo.
    set /p ADMIN_NAME="Nome amministratore: "
    set /p ADMIN_EMAIL="Email amministratore: "
    set /p ADMIN_PASSWORD="Password amministratore: "

    php artisan tinker --execute="$user = new App\Models\User; $user->name = '!ADMIN_NAME!'; $user->email = '!ADMIN_EMAIL!'; $user->password = Hash::make('!ADMIN_PASSWORD!'); $user->save();" 2>nul

    echo %GREEN%√ Utente amministratore creato%RESET%
    echo   Email: !ADMIN_EMAIL!
    echo   Password: !ADMIN_PASSWORD!
)

echo.
echo %GREEN%=====================================================%RESET%
echo %GREEN%  DATABASE RESETTATO CON SUCCESSO!%RESET%
echo %GREEN%=====================================================%RESET%
echo.
pause