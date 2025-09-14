@echo off
echo ====================================
echo NexioSolution Frontend Setup
echo ====================================
echo.

cd /d C:\xampp\htdocs\Nexiosolution\frontend

echo [1/4] Verifica Node.js...
node --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERRORE: Node.js non trovato! Installa Node.js da https://nodejs.org/
    pause
    exit /b 1
)
node --version
npm --version
echo.

echo [2/4] Pulizia cache npm...
npm cache clean --force >nul 2>&1
echo Cache pulita.
echo.

echo [3/4] Installazione dipendenze...
echo Questo potrebbe richiedere alcuni minuti...
npm install --legacy-peer-deps
if %errorlevel% neq 0 (
    echo.
    echo ERRORE durante l'installazione! Provo con --force...
    npm install --force
)
echo.

echo [4/4] Avvio server di sviluppo...
echo.
echo ====================================
echo Frontend disponibile su:
echo http://localhost:3000
echo ====================================
echo.
echo Premi Ctrl+C per fermare il server
echo.

npm run dev

pause