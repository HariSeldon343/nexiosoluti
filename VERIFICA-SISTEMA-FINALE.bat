@echo off
cls
echo =========================================================
echo   NEXIOSOLUTION - VERIFICA SISTEMA COMPLETA
echo =========================================================
echo.

cd /d C:\xampp\htdocs\Nexiosolution

echo [FASE 1] CONTROLLO PREREQUISITI
echo --------------------------------
echo Verifica Node.js...
node -v >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERRORE] Node.js non installato. Scarica da https://nodejs.org
    pause
    exit /b 1
)
for /f "tokens=*" %%i in ('node -v') do echo [OK] Node.js: %%i

echo Verifica NPM...
npm -v >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERRORE] NPM non trovato
    pause
    exit /b 1
)
for /f "tokens=*" %%i in ('npm -v') do echo [OK] NPM: %%i

echo Verifica Python...
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [WARN] Python non trovato (necessario per backend)
) else (
    for /f "tokens=*" %%i in ('python --version') do echo [OK] Python: %%i
)

echo.
echo [FASE 2] CONTROLLO STRUTTURA FILE
echo ----------------------------------
set frontend_ok=1
set backend_ok=1

if exist frontend\package.json (
    echo [OK] Frontend package.json presente
) else (
    echo [ERRORE] Frontend package.json mancante
    set frontend_ok=0
)

if exist frontend\src\App.jsx (
    echo [OK] App.jsx presente
) else (
    echo [ERRORE] App.jsx mancante
    set frontend_ok=0
)

if exist frontend\index.html (
    echo [OK] index.html presente
) else (
    echo [ERRORE] index.html mancante
    set frontend_ok=0
)

if exist backend\main.py (
    echo [OK] Backend main.py presente
) else (
    echo [WARN] Backend main.py mancante
    set backend_ok=0
)

echo.
echo [FASE 3] CONTROLLO DIPENDENZE
echo ------------------------------
cd frontend
if exist node_modules (
    echo [OK] Dipendenze frontend installate
    dir /b node_modules | find /c /v "" >temp.txt
    set /p modules=<temp.txt
    del temp.txt
    echo     Moduli installati: %modules%
) else (
    echo [WARN] Dipendenze non installate
    echo Installazione in corso...
    call npm install --legacy-peer-deps
)

cd ..

echo.
echo [FASE 4] CONTROLLO PORTE
echo ------------------------
netstat -an | findstr :3000 >nul 2>&1
if %errorlevel% equ 0 (
    echo [INFO] Porta 3000 occupata
    for /f "tokens=5" %%a in ('netstat -ano ^| findstr :3000') do (
        for /f "tokens=1,2" %%b in ('tasklist /FI "PID eq %%a" ^| findstr %%a') do (
            echo       Processo: %%b (PID: %%a)
        )
    )
) else (
    echo [OK] Porta 3000 libera
)

netstat -an | findstr :8000 >nul 2>&1
if %errorlevel% equ 0 (
    echo [INFO] Porta 8000 occupata (backend)
) else (
    echo [OK] Porta 8000 libera (backend)
)

echo.
echo [FASE 5] TEST AVVIO FRONTEND
echo -----------------------------
echo Pulizia processi precedenti...
taskkill /F /IM node.exe >nul 2>&1

echo Avvio server di sviluppo...
cd frontend
start /min cmd /c "npm run dev"
cd ..

echo Attesa avvio server (10 secondi)...
timeout /t 10 /nobreak >nul

echo Test connessione...
powershell -Command "try { $response = Invoke-WebRequest -Uri 'http://localhost:3000' -UseBasicParsing -TimeoutSec 3; Write-Host '[OK] Frontend accessibile su http://localhost:3000' -ForegroundColor Green; $true } catch { Write-Host '[ERRORE] Frontend non risponde' -ForegroundColor Red; $false }" >result.tmp
set /p test_result=<result.tmp
del result.tmp

echo.
echo =========================================================
echo   RIEPILOGO FINALE
echo =========================================================
echo.

if %frontend_ok%==1 (
    echo [OK] Struttura frontend corretta
) else (
    echo [ERRORE] Problemi nella struttura frontend
)

echo.
echo STATO SERVIZI:
echo --------------
powershell -Command "try { Invoke-WebRequest -Uri 'http://localhost:3000' -UseBasicParsing -TimeoutSec 1 >$null; Write-Host 'Frontend: ATTIVO su http://localhost:3000' -ForegroundColor Green } catch { Write-Host 'Frontend: NON ATTIVO' -ForegroundColor Red }"

powershell -Command "try { Invoke-WebRequest -Uri 'http://localhost:8000/api/health' -UseBasicParsing -TimeoutSec 1 >$null; Write-Host 'Backend:  ATTIVO su http://localhost:8000' -ForegroundColor Green } catch { Write-Host 'Backend:  NON ATTIVO' -ForegroundColor Yellow }"

echo.
echo AZIONI SUGGERITE:
echo -----------------
echo 1. Per aprire l'applicazione: start http://localhost:3000
echo 2. Per fermare il frontend: taskkill /F /IM node.exe
echo 3. Per avviare il backend: cd backend ^&^& python main.py
echo 4. Per vedere i log: aprire la finestra del terminale

echo.
echo =========================================================
echo Premi un tasto per aprire il browser...
pause >nul

start http://localhost:3000

echo.
echo Applicazione aperta nel browser!
echo Premi un tasto per terminare il test e chiudere il server...
pause

taskkill /F /IM node.exe >nul 2>&1
echo.
echo Test completato e server fermato.
pause