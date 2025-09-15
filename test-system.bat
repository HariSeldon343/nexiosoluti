@echo off
echo =====================================
echo   NEXIOSOLUTION - TEST COMPLETO
echo =====================================

cd /d C:\xampp\htdocs\Nexiosolution

echo.
echo [1] Verifica Node.js...
node -v
if %errorlevel% neq 0 (
    echo [ERRORE] Node.js non trovato
    echo Installa Node.js da https://nodejs.org
    pause
    exit /b 1
)

echo.
echo [2] Verifica NPM...
npm -v
if %errorlevel% neq 0 (
    echo [ERRORE] NPM non trovato
    pause
    exit /b 1
)

echo.
echo [3] Verifica struttura frontend...
if not exist frontend\package.json (
    echo [ERRORE] package.json mancante
    pause
    exit /b 1
)
if not exist frontend\src\App.jsx (
    echo [ERRORE] App.jsx mancante
    pause
    exit /b 1
)
if not exist frontend\index.html (
    echo [ERRORE] index.html mancante
    pause
    exit /b 1
)
echo [OK] Struttura frontend verificata

echo.
echo [4] Verifica dipendenze...
cd frontend
if not exist node_modules (
    echo Installazione dipendenze...
    call npm install --legacy-peer-deps
    if %errorlevel% neq 0 (
        echo [ERRORE] Installazione dipendenze fallita
        pause
        exit /b 1
    )
)
echo [OK] Dipendenze verificate

echo.
echo [5] Test build...
echo Building frontend...
call npm run build
if %errorlevel% neq 0 (
    echo [ERRORE] Build fallita
    echo Provo a reinstallare le dipendenze...
    rmdir /s /q node_modules 2>nul
    del package-lock.json 2>nul
    call npm install --legacy-peer-deps
    call npm run build
    if %errorlevel% neq 0 (
        echo [ERRORE] Build definitivamente fallita
        pause
        exit /b 1
    )
)
echo [OK] Build completata

echo.
echo [6] Pulizia processi precedenti...
taskkill /F /IM node.exe >nul 2>&1
for /f "tokens=5" %%a in ('netstat -ano ^| findstr :3000') do taskkill /PID %%a /F >nul 2>&1
echo [OK] Processi puliti

echo.
echo [7] Avvio server dev...
echo Avvio del server su http://localhost:3000
start /min cmd /c "npm run dev"

echo.
echo [8] Attesa avvio server...
timeout /t 8 /nobreak >nul

echo.
echo [9] Test connessione...
curl -s -o nul -w "HTTP Status: %%{http_code}" http://localhost:3000
echo.

powershell -Command "try { $response = Invoke-WebRequest -Uri 'http://localhost:3000' -UseBasicParsing; Write-Host '[OK] Server risponde su localhost:3000' -ForegroundColor Green; Write-Host 'Apertura browser...'; Start-Process 'http://localhost:3000' } catch { Write-Host '[ERRORE] Server non risponde' -ForegroundColor Red }"

echo.
echo =====================================
echo   TEST COMPLETATO
echo =====================================
echo.
echo Il server dev e' in esecuzione su http://localhost:3000
echo Premi un tasto per chiudere il server e terminare...
pause

taskkill /F /IM node.exe >nul 2>&1
exit /b 0