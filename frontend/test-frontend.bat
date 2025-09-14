@echo off
echo =====================================
echo   Test Rapido Frontend NexioSolution
echo =====================================
echo.
echo Questo script verifica che il frontend sia configurato correttamente.
echo.

:: Verifica Node.js
where node >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [ERRORE] Node.js non trovato!
    echo Installa Node.js da: https://nodejs.org/
    pause
    exit /b 1
)

echo [OK] Node.js trovato
node --version
echo.

:: Verifica npm
where npm >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [ERRORE] npm non trovato!
    pause
    exit /b 1
)

echo [OK] npm trovato
npm --version
echo.

:: Controlla se Vite è installato
if not exist "node_modules\vite" (
    echo [INFO] Vite non trovato. Installazione minima in corso...
    echo.

    :: Installa solo i pacchetti essenziali
    npm install --save-dev vite @vitejs/plugin-react --legacy-peer-deps
    npm install react react-dom --legacy-peer-deps

    if %ERRORLEVEL% NEQ 0 (
        echo [ERRORE] Installazione fallita!
        echo.
        echo Prova ad eseguire manualmente:
        echo   npm install --legacy-peer-deps
        pause
        exit /b 1
    )
)

echo [OK] Dipendenze essenziali installate
echo.
echo =====================================
echo Avvio server di test su http://localhost:3000
echo =====================================
echo.
echo Il browser si aprirà automaticamente.
echo Premi Ctrl+C per fermare il server.
echo.

:: Avvia il server
npm run dev:win

pause