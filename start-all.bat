@echo off
REM ========================================
REM NexioSolution - Avvio Completo Servizi
REM ========================================

echo.
echo =====================================
echo   NEXIOSOLUTION - AVVIO SERVIZI
echo =====================================
echo.

REM Controlla se XAMPP è in esecuzione
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe">NUL
if "%ERRORLEVEL%"=="1" (
    echo [!] Apache non è in esecuzione. Avviare XAMPP prima di continuare.
    echo.
    echo Avviare XAMPP? [S/N]
    set /p risposta=
    if /i "%risposta%"=="s" (
        echo Avvio XAMPP...
        start C:\xampp\xampp-control.exe
        echo Attendere l'avvio di Apache e MySQL, poi premere un tasto...
        pause >nul
    ) else (
        echo Avvio annullato.
        pause
        exit /b 1
    )
)

REM Vai alla directory del progetto
cd /d C:\xampp\htdocs\Nexiosolution

echo.
echo [1/7] Verifica ambiente...
echo =====================================

REM Controlla PHP
php -v >nul 2>&1
if %errorlevel% neq 0 (
    echo [X] PHP non trovato. Aggiungere C:\xampp\php al PATH di sistema.
    pause
    exit /b 1
)
echo [OK] PHP trovato

REM Controlla Composer
composer -V >nul 2>&1
if %errorlevel% neq 0 (
    echo [X] Composer non trovato. Installare Composer.
    pause
    exit /b 1
)
echo [OK] Composer trovato

REM Controlla Node
node -v >nul 2>&1
if %errorlevel% neq 0 (
    echo [X] Node.js non trovato. Installare Node.js.
    pause
    exit /b 1
)
echo [OK] Node.js trovato

echo.
echo [2/7] Avvio Backend Laravel...
echo =====================================
cd backend
start "NexioSolution - Laravel Server" cmd /k php artisan serve --host=127.0.0.1 --port=8000
timeout /t 3 >nul

echo.
echo [3/7] Avvio WebSocket Server...
echo =====================================
start "NexioSolution - WebSocket" cmd /k php artisan websockets:serve
timeout /t 2 >nul

echo.
echo [4/7] Avvio Queue Worker...
echo =====================================
start "NexioSolution - Queue Worker" cmd /k php artisan queue:work --tries=3
timeout /t 2 >nul

echo.
echo [5/7] Avvio Schedule Worker...
echo =====================================
start "NexioSolution - Scheduler" cmd /k php artisan schedule:work
timeout /t 2 >nul

echo.
echo [6/7] Avvio Frontend React...
echo =====================================
cd ..\frontend
start "NexioSolution - React Dev Server" cmd /k npm run dev
timeout /t 5 >nul

echo.
echo [7/7] Apertura Browser...
echo =====================================
timeout /t 3 >nul
start http://localhost:3000

echo.
echo =====================================
echo   TUTTI I SERVIZI SONO ATTIVI!
echo =====================================
echo.
echo SERVIZI IN ESECUZIONE:
echo - Laravel API:     http://localhost:8000
echo - React Frontend:  http://localhost:3000
echo - WebSocket:       ws://localhost:6001
echo - Queue Worker:    Attivo
echo - Scheduler:       Attivo
echo.
echo CREDENZIALI DI ACCESSO:
echo - Admin:    admin@nexiosolution.local / Admin123!
echo - Tenant:   demo@nexiosolution.local / Demo123!
echo.
echo Per fermare tutti i servizi, eseguire: stop-all.bat
echo.
pause