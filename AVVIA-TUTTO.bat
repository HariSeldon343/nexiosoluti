@echo off
REM =========================================================
REM     NEXIOSOLUTION - AVVIO COMPLETO PIATTAFORMA
REM =========================================================

cls
color 0A

echo.
echo =========================================================
echo        NEXIOSOLUTION - AVVIO PIATTAFORMA
echo =========================================================
echo.

cd /d C:\xampp\htdocs\Nexiosolution

echo [1/5] Pulizia processi esistenti...
taskkill /F /IM php.exe >nul 2>&1
taskkill /F /IM node.exe >nul 2>&1
for /f "tokens=5" %%a in ('netstat -ano ^| findstr :3000') do taskkill /PID %%a /F >nul 2>&1
for /f "tokens=5" %%a in ('netstat -ano ^| findstr :8000') do taskkill /PID %%a /F >nul 2>&1

echo [2/5] Avvio Backend Laravel...
cd backend
if not exist vendor (
    echo Installazione dipendenze backend...
    call composer install --no-interaction
)
if not exist .env copy .env.example .env
start "Backend Laravel" cmd /c "php artisan serve --host=127.0.0.1 --port=8000"
timeout /t 3 /nobreak >nul

echo [3/5] Avvio Frontend React...
cd ..\frontend
if not exist node_modules (
    echo Installazione dipendenze frontend...
    call npm install --legacy-peer-deps
)
start "Frontend React" cmd /c "npm run dev"
timeout /t 5 /nobreak >nul

echo [4/5] Verifica servizi...
echo.
netstat -an | findstr :3000 >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] Frontend attivo su porta 3000
) else (
    echo [!] Frontend non raggiungibile
)

netstat -an | findstr :8000 >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] Backend attivo su porta 8000
) else (
    echo [!] Backend non raggiungibile
)

echo.
echo [5/5] Apertura browser...
timeout /t 2 /nobreak >nul
start http://localhost:3000

echo.
echo =========================================================
echo              PIATTAFORMA AVVIATA!
echo =========================================================
echo.
echo Frontend:  http://localhost:3000
echo Backend:   http://localhost:8000
echo API:       http://localhost:8000/api
echo.
echo Credenziali test:
echo Email:     admin@nexiosolution.local
echo Password:  Admin@123
echo.
echo Per fermare tutti i servizi: STOP-NEXIOSOLUTION.bat
echo.
pause