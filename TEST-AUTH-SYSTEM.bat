@echo off
echo ========================================
echo    TEST SISTEMA AUTENTICAZIONE
echo       NEXIOSOLUTION PLATFORM
echo ========================================
echo.

echo [STEP 1] Verifica Backend Laravel
echo ---------------------------------
curl -s http://localhost:8000/test-api.php > nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] Backend risponde su porta 8000
    echo.
    echo Test API Response:
    curl -s http://localhost:8000/test-api.php
    echo.
) else (
    echo [ERRORE] Backend non risponde
    echo Avviare prima il backend con: start-backend.bat
    echo.
)

echo.
echo [STEP 2] Test Health Check API
echo ---------------------------------
curl -s http://localhost:8000/api/v1/health > nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] API Health Check funzionante
    curl -s http://localhost:8000/api/v1/health
    echo.
) else (
    echo [WARN] API Health Check non risponde
)

echo.
echo [STEP 3] Test Login Endpoint
echo ---------------------------------
echo Testing con credenziali demo...

curl -X POST http://localhost:8000/api/login ^
  -H "Content-Type: application/json" ^
  -H "Accept: application/json" ^
  -d "{\"email\":\"admin@nexiosolution.com\",\"password\":\"password123\"}" ^
  -s > test-login-response.json 2>nul

if exist test-login-response.json (
    echo.
    echo Risposta Login:
    type test-login-response.json
    echo.
    del test-login-response.json
) else (
    echo [ERRORE] Impossibile testare login
)

echo.
echo [STEP 4] Verifica Frontend React
echo ---------------------------------
curl -s http://localhost:3000 > nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] Frontend React attivo su porta 3000
) else (
    echo [WARN] Frontend non attivo
    echo Avviare con: cd frontend ^&^& npm start
)

echo.
echo ========================================
echo    RIEPILOGO CONFIGURAZIONE
echo ========================================
echo.
echo BACKEND Laravel:
echo - URL: http://localhost:8000
echo - API: http://localhost:8000/api
echo - Test: http://localhost:8000/test-api.php
echo.
echo FRONTEND React:
echo - URL: http://localhost:3000
echo - Login: http://localhost:3000/login
echo.
echo CREDENZIALI DEMO:
echo - Email: admin@nexiosolution.com
echo - Password: password123
echo - Ruolo: Admin
echo.
echo DATABASE:
echo - Nome: nexiosolution
echo - User: root (no password)
echo.
echo ========================================
echo    ISTRUZIONI PER AVVIO COMPLETO
echo ========================================
echo.
echo 1. Aprire un terminale per il BACKEND:
echo    cd backend
echo    php artisan serve
echo.
echo 2. Aprire un altro terminale per il FRONTEND:
echo    cd frontend
echo    npm start
echo.
echo 3. Aprire browser su http://localhost:3000
echo.
echo 4. Fare login con le credenziali demo
echo.
echo ========================================
pause