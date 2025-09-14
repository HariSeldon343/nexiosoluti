@echo off
echo ====================================
echo NexioSolution Frontend - Diagnostica
echo ====================================
echo.

cd /d C:\xampp\htdocs\Nexiosolution\frontend

echo [CONTROLLO 1] Node.js e NPM
echo ----------------------------
node --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERRORE] Node.js NON installato!
    echo Scarica da: https://nodejs.org/
) else (
    echo [OK] Node.js:
    node --version
    echo [OK] NPM:
    npm --version
)
echo.

echo [CONTROLLO 2] Directory di lavoro
echo ----------------------------------
echo Directory corrente: %CD%
echo.

echo [CONTROLLO 3] File essenziali
echo -----------------------------
if exist package.json (
    echo [OK] package.json presente
) else (
    echo [ERRORE] package.json MANCANTE!
)

if exist index.html (
    echo [OK] index.html presente
) else (
    echo [ERRORE] index.html MANCANTE!
)

if exist vite.config.js (
    echo [OK] vite.config.js presente
) else (
    echo [ERRORE] vite.config.js MANCANTE!
)

if exist src\main.jsx (
    echo [OK] src\main.jsx presente
) else (
    echo [ERRORE] src\main.jsx MANCANTE!
)

if exist src\App.jsx (
    echo [OK] src\App.jsx presente
) else (
    echo [ERRORE] src\App.jsx MANCANTE!
)

if exist src\index.css (
    echo [OK] src\index.css presente
) else (
    echo [ERRORE] src\index.css MANCANTE!
)
echo.

echo [CONTROLLO 4] Dipendenze
echo ------------------------
if exist node_modules (
    echo [OK] node_modules presente
    dir node_modules | find "Dir(s)" | find /v "0 Dir(s)"
) else (
    echo [AVVISO] node_modules NON presente!
    echo Esegui: npm install
)

if exist package-lock.json (
    echo [OK] package-lock.json presente
) else (
    echo [INFO] package-lock.json non presente (verrà creato con npm install)
)
echo.

echo [CONTROLLO 5] Porte in uso
echo --------------------------
netstat -an | findstr :3000 >nul 2>&1
if %errorlevel% equ 0 (
    echo [AVVISO] Porta 3000 già in uso!
    echo Potrebbe esserci già un server in esecuzione.
    netstat -an | findstr :3000
) else (
    echo [OK] Porta 3000 libera
)
echo.

echo [CONTROLLO 6] Verifica NPM
echo --------------------------
npm list react react-dom vite 2>nul | findstr "react@\|react-dom@\|vite@"
if %errorlevel% neq 0 (
    echo [INFO] Alcune dipendenze potrebbero mancare
)
echo.

echo ====================================
echo RACCOMANDAZIONI:
echo ====================================
if not exist node_modules (
    echo 1. Esegui: npm install
    echo    oppure: npm install --legacy-peer-deps
    echo.
)

echo Per avviare il frontend:
echo - Metodo 1: Esegui install-and-start.bat
echo - Metodo 2: Esegui quick-start.bat (versione minimale)
echo - Metodo 3: Manualmente con: npm run dev
echo.

pause