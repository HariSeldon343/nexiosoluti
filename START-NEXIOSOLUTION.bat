@echo off
cls
color 0A
echo.
echo     ===================================================
echo        _   _           _       _____       _
echo       ^| \ ^| ^|         (_)     / ____^|     ^| ^|
echo       ^|  \^| ^| _____  ___  ___^| (___   ___ ^| ^|
echo       ^| . ` ^|/ _ \ \/ / ^|/ _ \\___ \ / _ \^| ^|
echo       ^| ^|\  ^|  __/^>  ^<^| ^| (_) ^|___) ^| (_) ^| ^|
echo       ^|_^| \_^|\___/_/\_\_^|\___/_____/ \___/^|_^|
echo.
echo              PIATTAFORMA COLLABORATIVA v1.0
echo     ===================================================
echo.

cd /d C:\xampp\htdocs\Nexiosolution

echo [1/4] Controllo prerequisiti...
node -v >nul 2>&1
if %errorlevel% neq 0 (
    color 0C
    echo.
    echo     [ERRORE] Node.js non installato!
    echo     Scarica da: https://nodejs.org
    echo.
    pause
    exit /b 1
)

echo [2/4] Pulizia processi precedenti...
taskkill /F /IM node.exe >nul 2>&1
for /f "tokens=5" %%a in ('netstat -ano ^| findstr :3000') do taskkill /PID %%a /F >nul 2>&1
for /f "tokens=5" %%a in ('netstat -ano ^| findstr :8000') do taskkill /PID %%a /F >nul 2>&1

echo [3/4] Verifica dipendenze frontend...
cd frontend
if not exist node_modules (
    echo      Installazione dipendenze (prima volta, attendere)...
    call npm install --legacy-peer-deps >nul 2>&1
    if %errorlevel% neq 0 (
        echo      Installazione con --force...
        call npm install --force >nul 2>&1
    )
)

echo [4/4] Avvio servizi...
echo.
echo     ===================================================
echo                    AVVIO IN CORSO...
echo     ===================================================
echo.

REM Avvia frontend
start /min cmd /k "title NexioSolution Frontend && npm run dev"

REM Avvia backend se esiste
if exist ..\backend\main.py (
    cd ..\backend
    if exist venv\Scripts\activate.bat (
        start /min cmd /k "title NexioSolution Backend && venv\Scripts\activate && python main.py"
    ) else (
        start /min cmd /k "title NexioSolution Backend && python main.py"
    )
)

cd ..

echo     Attesa avvio servizi...
timeout /t 5 /nobreak >nul

cls
color 0A
echo.
echo     ===================================================
echo                  NEXIOSOLUTION ATTIVO!
echo     ===================================================
echo.
echo     Frontend:  http://localhost:3000
powershell -Command "try { Invoke-WebRequest -Uri 'http://localhost:3000' -UseBasicParsing -TimeoutSec 2 >$null; Write-Host '                [ONLINE]' -ForegroundColor Green } catch { Write-Host '                [OFFLINE]' -ForegroundColor Red }"

echo.
echo     Backend:   http://localhost:8000
powershell -Command "try { Invoke-WebRequest -Uri 'http://localhost:8000/api/health' -UseBasicParsing -TimeoutSec 2 >$null; Write-Host '                [ONLINE]' -ForegroundColor Green } catch { Write-Host '                [OFFLINE - Avviare manualmente]' -ForegroundColor Yellow }"

echo.
echo     ===================================================
echo.
echo     Credenziali Demo:
echo     Email: admin@nexiosolution.com
echo     Pass:  password123
echo.
echo     ===================================================
echo.
echo     Apertura browser in corso...
timeout /t 2 /nobreak >nul
start http://localhost:3000

echo.
echo     Premi un tasto per FERMARE tutti i servizi...
echo     ===================================================
pause >nul

echo.
echo     Arresto servizi in corso...
taskkill /F /IM node.exe >nul 2>&1
taskkill /F /IM python.exe >nul 2>&1

color 07
echo.
echo     Servizi arrestati correttamente.
echo.
pause