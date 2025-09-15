@echo off
echo =====================================
echo   RESET COMPLETO FRONTEND
echo =====================================

cd /d C:\xampp\htdocs\Nexiosolution\frontend

echo.
echo [1] Fermando tutti i processi Node.js...
taskkill /F /IM node.exe >nul 2>&1
for /f "tokens=5" %%a in ('netstat -ano ^| findstr :3000') do taskkill /PID %%a /F >nul 2>&1
echo [OK] Processi fermati

echo.
echo [2] Pulizia cache e file temporanei...
rmdir /s /q node_modules 2>nul
rmdir /s /q dist 2>nul
rmdir /s /q .vite 2>nul
del package-lock.json 2>nul
del npm-debug.log 2>nul
del yarn-error.log 2>nul
echo [OK] Cache pulita

echo.
echo [3] Reinstallazione dipendenze...
echo Installazione in corso (potrebbe richiedere alcuni minuti)...
call npm install --legacy-peer-deps
if %errorlevel% neq 0 (
    echo [ERRORE] Installazione fallita
    echo Provo con --force...
    call npm install --force
    if %errorlevel% neq 0 (
        echo [ERRORE CRITICO] Impossibile installare le dipendenze
        pause
        exit /b 1
    )
)
echo [OK] Dipendenze reinstallate

echo.
echo [4] Verifica build...
call npm run build
if %errorlevel% neq 0 (
    echo [ATTENZIONE] Build ha generato errori
    echo Continuiamo comunque con il dev server...
)

echo.
echo [5] Avvio server di sviluppo...
echo.
echo =====================================
echo   SERVER IN AVVIO SU localhost:3000
echo =====================================
echo.

start cmd /k "npm run dev"

timeout /t 5 /nobreak >nul

echo.
echo Apertura browser...
start http://localhost:3000

echo.
echo =====================================
echo   RESET COMPLETATO
echo =====================================
echo.
echo Il server dovrebbe essere attivo su http://localhost:3000
echo Se vedi errori nella console, premi Ctrl+C e riavvia con: npm run dev
echo.
pause