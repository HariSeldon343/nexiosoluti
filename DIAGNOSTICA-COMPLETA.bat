@echo off
echo =====================================
echo   NEXIOSOLUTION - DIAGNOSTICA
echo =====================================

cd /d C:\xampp\htdocs\Nexiosolution

echo.
echo [CONTROLLO AMBIENTE]
echo --------------------
echo Directory corrente: %CD%

echo.
echo [1] Node.js:
node -v 2>nul
if %errorlevel% neq 0 (
    echo    [ERRORE] Node.js non installato
    echo    Scarica da: https://nodejs.org
) else (
    echo    [OK] Node.js installato
)

echo.
echo [2] NPM:
npm -v 2>nul
if %errorlevel% neq 0 (
    echo    [ERRORE] NPM non disponibile
) else (
    echo    [OK] NPM installato
)

echo.
echo [CONTROLLO STRUTTURA]
echo ---------------------
set errors=0

if exist frontend\package.json (
    echo [OK] frontend\package.json
) else (
    echo [MANCANTE] frontend\package.json
    set /a errors+=1
)

if exist frontend\src\App.jsx (
    echo [OK] frontend\src\App.jsx
) else (
    echo [MANCANTE] frontend\src\App.jsx
    set /a errors+=1
)

if exist frontend\src\main.jsx (
    echo [OK] frontend\src\main.jsx
) else (
    echo [MANCANTE] frontend\src\main.jsx
    set /a errors+=1
)

if exist frontend\index.html (
    echo [OK] frontend\index.html
) else (
    echo [MANCANTE] frontend\index.html
    set /a errors+=1
)

if exist frontend\vite.config.js (
    echo [OK] frontend\vite.config.js
) else (
    echo [MANCANTE] frontend\vite.config.js
    set /a errors+=1
)

if exist frontend\node_modules (
    echo [OK] frontend\node_modules (dipendenze installate)
) else (
    echo [ATTENZIONE] frontend\node_modules non trovato (dipendenze da installare)
)

echo.
echo [CONTROLLO PORTE]
echo -----------------
netstat -an | findstr :3000 >nul 2>&1
if %errorlevel% equ 0 (
    echo [ATTENZIONE] Porta 3000 già in uso
    echo Processi che usano la porta:
    for /f "tokens=5" %%a in ('netstat -ano ^| findstr :3000') do (
        echo    PID: %%a
        tasklist /FI "PID eq %%a" 2>nul | findstr /v "INFO:" | findstr /v "=========="
    )
) else (
    echo [OK] Porta 3000 libera
)

netstat -an | findstr :8000 >nul 2>&1
if %errorlevel% equ 0 (
    echo [ATTENZIONE] Porta 8000 già in uso (backend)
) else (
    echo [OK] Porta 8000 libera (backend)
)

echo.
echo [CONTROLLO BACKEND]
echo -------------------
if exist backend\requirements.txt (
    echo [OK] backend\requirements.txt
) else (
    echo [MANCANTE] backend\requirements.txt
    set /a errors+=1
)

if exist backend\main.py (
    echo [OK] backend\main.py
) else (
    echo [MANCANTE] backend\main.py
    set /a errors+=1
)

echo.
echo [RIEPILOGO]
echo -----------
if %errors% equ 0 (
    echo TUTTI I CONTROLLI PASSATI!
    echo.
    echo Per avviare la piattaforma:
    echo 1. Frontend: cd frontend ^&^& npm run dev
    echo 2. Backend: cd backend ^&^& python main.py
) else (
    echo TROVATI %errors% PROBLEMI
    echo.
    echo Soluzioni suggerite:
    if not exist frontend\node_modules (
        echo - Installa dipendenze: cd frontend ^&^& npm install --legacy-peer-deps
    )
    echo - Verifica i file mancanti sopra elencati
)

echo.
echo [TEST VELOCE FRONTEND]
echo ----------------------
cd frontend
echo Provo ad avviare il frontend per 10 secondi...
start /min cmd /c "npm run dev & timeout /t 10 & exit"
timeout /t 5 /nobreak >nul

powershell -Command "try { $response = Invoke-WebRequest -Uri 'http://localhost:3000' -UseBasicParsing -TimeoutSec 2; Write-Host '[OK] Frontend risponde su localhost:3000' -ForegroundColor Green } catch { Write-Host '[ERRORE] Frontend non risponde' -ForegroundColor Red }"

taskkill /F /IM node.exe >nul 2>&1

echo.
echo =====================================
echo   DIAGNOSTICA COMPLETATA
echo =====================================
echo.
pause