@echo off
REM ================================================
REM    NEXIOSOLUTION - AVVIO PIATTAFORMA
REM ================================================

cls
color 0A

echo.
echo ================================================
echo         NEXIOSOLUTION - AVVIO SISTEMA
echo ================================================
echo.

cd /d C:\xampp\htdocs\Nexiosolution

echo [1/6] Controllo prerequisiti...
echo ------------------------------------------------

REM Verifica XAMPP
if not exist "C:\xampp\xampp-control.exe" (
    color 0C
    echo [ERRORE] XAMPP non trovato!
    echo Installare XAMPP prima di continuare.
    pause
    exit /b 1
)
echo [OK] XAMPP trovato

REM Verifica Node.js
node -v >nul 2>&1
if %errorlevel% neq 0 (
    color 0C
    echo [ERRORE] Node.js non trovato!
    echo Installare Node.js da https://nodejs.org
    pause
    exit /b 1
)
echo [OK] Node.js trovato

echo.
echo [2/6] Pulizia processi esistenti...
echo ------------------------------------------------
taskkill /F /IM node.exe >nul 2>&1
taskkill /F /IM php.exe >nul 2>&1
for /f "tokens=5" %%a in ('netstat -ano ^| findstr :3000') do taskkill /PID %%a /F >nul 2>&1
for /f "tokens=5" %%a in ('netstat -ano ^| findstr :8000') do taskkill /PID %%a /F >nul 2>&1
echo [OK] Processi puliti

echo.
echo [3/6] Configurazione Backend Laravel...
echo ------------------------------------------------
cd backend
if not exist vendor (
    echo Installazione dipendenze backend...
    call composer install --no-interaction --quiet
)
if not exist .env (
    copy .env.example .env >nul 2>&1
    php artisan key:generate --quiet >nul 2>&1
)
echo [OK] Backend configurato

echo.
echo [4/6] Configurazione Frontend React...
echo ------------------------------------------------
cd ..\frontend
if not exist node_modules (
    echo Installazione dipendenze frontend (potrebbe richiedere alcuni minuti)...
    call npm install --legacy-peer-deps --silent
)
echo [OK] Frontend configurato

echo.
echo [5/6] Avvio servizi...
echo ------------------------------------------------

REM Avvia Backend
cd ..\backend
echo Avvio Backend Laravel...
start /min "Backend Laravel" cmd /c "php artisan serve --host=127.0.0.1 --port=8000"
timeout /t 2 /nobreak >nul

REM Avvia Frontend
cd ..\frontend
echo Avvio Frontend React...
start /min "Frontend React" cmd /c "npm run dev"
timeout /t 5 /nobreak >nul

echo [OK] Servizi avviati

echo.
echo [6/6] Verifica sistema...
echo ------------------------------------------------

REM Test frontend
curl -s http://localhost:3000 >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] Frontend attivo su http://localhost:3000
) else (
    echo [!] Frontend in avvio, attendere...
)

REM Test backend
curl -s http://localhost:8000 >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] Backend attivo su http://localhost:8000
) else (
    echo [!] Backend in avvio, attendere...
)

echo.
echo ================================================
echo         PIATTAFORMA AVVIATA CON SUCCESSO!
echo ================================================
echo.
echo ACCESSO APPLICAZIONE:
echo ---------------------
echo URL:      http://localhost:3000
echo.
echo CREDENZIALI DEMO:
echo -----------------
echo Email:    admin@nexiosolution.com
echo Password: password123
echo.
echo SERVIZI ATTIVI:
echo ---------------
echo Frontend React:  http://localhost:3000
echo Backend API:     http://localhost:8000
echo.
echo Per fermare i servizi, chiudere le finestre dei terminali
echo o eseguire STOP-NEXIOSOLUTION.bat
echo.
echo Apertura browser in corso...
timeout /t 3 /nobreak >nul
start http://localhost:3000

echo.
echo Premi un tasto per chiudere questa finestra...
pause >nul