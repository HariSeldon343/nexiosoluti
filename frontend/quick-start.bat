@echo off
echo ====================================
echo NexioSolution - Quick Start (Versione Minima)
echo ====================================
echo.

cd /d C:\xampp\htdocs\Nexiosolution\frontend

echo [1/5] Verifica Node.js...
node --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERRORE: Node.js non trovato! Installa Node.js da https://nodejs.org/
    echo Scarica da: https://nodejs.org/en/download/
    pause
    exit /b 1
)
echo Node.js trovato:
node --version
npm --version
echo.

echo [2/5] Backup configurazione attuale...
if exist package.json.backup del package.json.backup
if exist vite.config.js.backup del vite.config.js.backup
copy package.json package.json.backup >nul 2>&1
copy vite.config.js vite.config.js.backup >nul 2>&1
echo Backup creato.
echo.

echo [3/5] Utilizzo configurazione minimale...
copy package-minimal.json package.json /Y >nul
copy vite.config.simple.js vite.config.js /Y >nul
echo Configurazione minimale applicata.
echo.

echo [4/5] Installazione dipendenze minime...
echo Solo React, Vite e Tailwind CSS...
if exist node_modules (
    echo Pulizia node_modules esistente...
    rmdir /s /q node_modules
)
npm install
if %errorlevel% neq 0 (
    echo.
    echo ERRORE! Provo con --legacy-peer-deps...
    npm install --legacy-peer-deps
)
echo.

echo [5/5] Avvio server di sviluppo...
echo.
echo ====================================
echo Frontend disponibile su:
echo http://localhost:3000
echo ====================================
echo.
echo Se il browser non si apre automaticamente,
echo apri manualmente: http://localhost:3000
echo.
echo Premi Ctrl+C per fermare il server
echo.

npm run dev

pause