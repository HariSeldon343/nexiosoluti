@echo off
echo =====================================
echo   NEXIOSOLUTION - AVVIO RAPIDO
echo =====================================

cd /d C:\xampp\htdocs\Nexiosolution\frontend

REM Pulizia processi precedenti
taskkill /F /IM node.exe >nul 2>&1
for /f "tokens=5" %%a in ('netstat -ano ^| findstr :3000') do taskkill /PID %%a /F >nul 2>&1

REM Verifica dipendenze
if not exist node_modules (
    echo Installazione dipendenze in corso...
    call npm install --legacy-peer-deps
)

REM Avvio server
echo.
echo Avvio server su http://localhost:3000 ...
echo.
start cmd /k npm run dev

REM Attesa e apertura browser
timeout /t 5 /nobreak >nul
start http://localhost:3000

echo.
echo =====================================
echo   SERVER AVVIATO
echo =====================================
echo.
echo La piattaforma e' accessibile su:
echo http://localhost:3000
echo.
echo Premi un tasto per terminare...
pause