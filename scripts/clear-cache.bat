@echo off
REM ====================================================
REM NexioSolution - Pulizia Cache
REM ====================================================

echo ====================================================
echo   NexioSolution - Pulizia Cache
echo ====================================================
echo.

set "PROJECT_DIR=C:\xampp\htdocs\Nexiosolution"
set "BACKEND_DIR=%PROJECT_DIR%\backend"
set "FRONTEND_DIR=%PROJECT_DIR%\frontend"

set "GREEN=[92m"
set "RED=[91m"
set "YELLOW=[93m"
set "RESET=[0m"

REM Pulizia cache Laravel
echo %YELLOW%[1/4] Pulizia cache Laravel...%RESET%
echo ----------------------------------------
cd /d "%BACKEND_DIR%"

echo Pulizia application cache...
php artisan cache:clear
echo %GREEN%√ Application cache pulita%RESET%

echo Pulizia route cache...
php artisan route:clear
echo %GREEN%√ Route cache pulita%RESET%

echo Pulizia config cache...
php artisan config:clear
echo %GREEN%√ Config cache pulita%RESET%

echo Pulizia view cache...
php artisan view:clear
echo %GREEN%√ View cache pulita%RESET%

echo Pulizia compiled files...
php artisan clear-compiled 2>nul
echo %GREEN%√ Compiled files puliti%RESET%

REM Elimina file di log
echo.
echo %YELLOW%[2/4] Pulizia file di log...%RESET%
echo ----------------------------------------
if exist "%BACKEND_DIR%\storage\logs\*.log" (
    del /Q "%BACKEND_DIR%\storage\logs\*.log" 2>nul
    echo %GREEN%√ File di log eliminati%RESET%
) else (
    echo Nessun file di log da eliminare
)

REM Pulizia cache npm
echo.
echo %YELLOW%[3/4] Pulizia cache npm...%RESET%
echo ----------------------------------------
cd /d "%FRONTEND_DIR%"
call npm cache clean --force 2>nul
echo %GREEN%√ Cache npm pulita%RESET%

REM Pulizia build frontend
echo.
echo %YELLOW%[4/4] Pulizia build frontend...%RESET%
echo ----------------------------------------
if exist "%FRONTEND_DIR%\dist" (
    rmdir /S /Q "%FRONTEND_DIR%\dist" 2>nul
    echo %GREEN%√ Directory dist eliminata%RESET%
)
if exist "%FRONTEND_DIR%\build" (
    rmdir /S /Q "%FRONTEND_DIR%\build" 2>nul
    echo %GREEN%√ Directory build eliminata%RESET%
)
if exist "%FRONTEND_DIR%\.next" (
    rmdir /S /Q "%FRONTEND_DIR%\.next" 2>nul
    echo %GREEN%√ Directory .next eliminata%RESET%
)

REM Rigenera cache ottimizzata
echo.
set /p OPTIMIZE="Vuoi rigenerare la cache ottimizzata? (s/n): "
if /i "%OPTIMIZE%"=="s" (
    echo.
    echo %YELLOW%Rigenerazione cache ottimizzata...%RESET%
    cd /d "%BACKEND_DIR%"

    php artisan config:cache
    echo %GREEN%√ Config cache rigenerata%RESET%

    php artisan route:cache 2>nul
    echo %GREEN%√ Route cache rigenerata%RESET%

    php artisan view:cache 2>nul
    echo %GREEN%√ View cache rigenerata%RESET%

    call composer dump-autoload -o
    echo %GREEN%√ Autoload ottimizzato%RESET%
)

echo.
echo %GREEN%=====================================================%RESET%
echo %GREEN%  CACHE PULITA CON SUCCESSO!%RESET%
echo %GREEN%=====================================================%RESET%
echo.
pause