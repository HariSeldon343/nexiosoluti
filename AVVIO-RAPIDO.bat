@echo off
REM ===============================================
REM   NexioSolution - AVVIO RAPIDO COMPLETO
REM ===============================================

cls
color 0E

echo.
echo =========================================
echo    NEXIOSOLUTION - AVVIO RAPIDO
echo =========================================
echo.
echo Preparazione ambiente...
echo.

cd /d C:\xampp\htdocs\Nexiosolution

REM Chiudi processi esistenti
taskkill /F /IM php.exe >nul 2>&1
taskkill /F /IM node.exe >nul 2>&1

REM Backend
echo [1/4] Avvio Backend Laravel...
cd backend
start /min cmd /c "php artisan serve --host=127.0.0.1 --port=8000"
timeout /t 2 /nobreak >nul

REM Frontend
echo [2/4] Avvio Frontend React...
cd ..\frontend
start /min cmd /c "npm run dev --host"
timeout /t 3 /nobreak >nul

echo [3/4] Attesa avvio servizi...
timeout /t 5 /nobreak >nul

echo [4/4] Apertura browser...
start http://localhost:3000

echo.
echo =========================================
echo    SERVIZI ATTIVI
echo =========================================
echo.
echo  Frontend:  http://localhost:3000
echo  Backend:   http://localhost:8000
echo.
echo  Premi un tasto per chiudere questo messaggio
echo  (i servizi continueranno a funzionare)
echo.
pause >nul