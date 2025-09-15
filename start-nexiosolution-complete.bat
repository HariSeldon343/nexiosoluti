@echo off
echo ================================================
echo     AVVIO COMPLETO NEXIOSOLUTION
echo ================================================
echo.

REM Verifica XAMPP
echo Verifica installazione XAMPP...
if not exist "C:\xampp\xampp-control.exe" (
    echo [ERRORE] XAMPP non trovato in C:\xampp\
    echo Installa XAMPP prima di procedere
    pause
    exit /b 1
)

REM Avvia servizi XAMPP
echo.
echo ================================================
echo STEP 1: AVVIO SERVIZI XAMPP
echo ================================================
echo.

echo Avvio Apache...
C:\xampp\apache_start.bat 2>nul
timeout /t 2 /nobreak >nul

echo Avvio MySQL...
C:\xampp\mysql_start.bat 2>nul
timeout /t 3 /nobreak >nul

REM Verifica servizi
C:\xampp\mysql\bin\mysql -u root -e "SELECT 1" >nul 2>&1
if %errorlevel% neq 0 (
    echo [WARNING] MySQL potrebbe non essere attivo
    echo Apri XAMPP Control Panel e avvia manualmente Apache e MySQL
    start C:\xampp\xampp-control.exe
    echo.
    echo Premi un tasto quando i servizi sono attivi...
    pause >nul
)

echo.
echo ================================================
echo STEP 2: AVVIO BACKEND LARAVEL
echo ================================================
echo.

echo Apertura nuova finestra per il Backend...
start "Backend Laravel - NexioSolution" cmd /k "cd /d C:\xampp\htdocs\Nexiosolution && start-backend.bat"

timeout /t 5 /nobreak >nul

echo.
echo ================================================
echo STEP 3: AVVIO FRONTEND REACT
echo ================================================
echo.

echo Apertura nuova finestra per il Frontend...
start "Frontend React - NexioSolution" cmd /k "cd /d C:\xampp\htdocs\Nexiosolution\frontend && npm start"

timeout /t 5 /nobreak >nul

echo.
echo ================================================
echo     NEXIOSOLUTION AVVIATO CON SUCCESSO!
echo ================================================
echo.
echo SERVIZI ATTIVI:
echo.
echo [Backend API]  http://localhost:8000
echo [Frontend]     http://localhost:3000
echo.
echo CREDENZIALI DEMO:
echo Email:    admin@nexiosolution.com
echo Password: password123
echo.
echo ================================================
echo.
echo Il browser si aprira' automaticamente tra 10 secondi...
echo.

timeout /t 10 /nobreak >nul

REM Apri il browser
start http://localhost:3000

echo.
echo ================================================
echo     SISTEMA COMPLETAMENTE OPERATIVO
echo ================================================
echo.
echo Per fermare tutti i servizi:
echo 1. Chiudi le finestre del Backend e Frontend (CTRL+C)
echo 2. Ferma Apache e MySQL da XAMPP Control Panel
echo.
echo ================================================
echo.
pause