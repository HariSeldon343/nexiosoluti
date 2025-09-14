@echo off
REM ====================================================
REM NexioSolution - Avvio Servizi
REM ====================================================
setlocal enabledelayedexpansion

echo ====================================================
echo   NexioSolution - Avvio Servizi
echo ====================================================
echo.

REM Imposta directory e variabili
set "PROJECT_DIR=C:\xampp\htdocs\Nexiosolution"
set "BACKEND_DIR=%PROJECT_DIR%\backend"
set "FRONTEND_DIR=%PROJECT_DIR%\frontend"

REM Colori per output
set "GREEN=[92m"
set "RED=[91m"
set "YELLOW=[93m"
set "RESET=[0m"

REM Verifica XAMPP
echo %YELLOW%[1/6] Verifica servizi XAMPP...%RESET%
echo ----------------------------------------

REM Verifica Apache
netstat -an | findstr :80 | findstr LISTENING >nul
if errorlevel 1 (
    echo %RED%Apache non è in esecuzione!%RESET%
    echo Avvia Apache dal XAMPP Control Panel
    echo.
    echo Tentativo di avvio automatico...
    C:\xampp\apache\bin\httpd.exe -k start 2>nul
    timeout /t 2 /nobreak >nul
    netstat -an | findstr :80 | findstr LISTENING >nul
    if errorlevel 1 (
        echo %RED%Impossibile avviare Apache automaticamente%RESET%
        echo Apri XAMPP Control Panel e avvia Apache manualmente
        pause
        exit /b 1
    )
)
echo %GREEN%√ Apache in esecuzione%RESET%

REM Verifica MySQL
netstat -an | findstr :3306 | findstr LISTENING >nul
if errorlevel 1 (
    echo %RED%MySQL non è in esecuzione!%RESET%
    echo Avvia MySQL dal XAMPP Control Panel
    echo.
    echo Tentativo di avvio automatico...
    net start mysql 2>nul
    timeout /t 2 /nobreak >nul
    netstat -an | findstr :3306 | findstr LISTENING >nul
    if errorlevel 1 (
        echo %RED%Impossibile avviare MySQL automaticamente%RESET%
        echo Apri XAMPP Control Panel e avvia MySQL manualmente
        pause
        exit /b 1
    )
)
echo %GREEN%√ MySQL in esecuzione%RESET%

REM Verifica porte disponibili
echo.
echo %YELLOW%[2/6] Verifica porte disponibili...%RESET%
echo ----------------------------------------

REM Controlla porta 8000 (Laravel)
netstat -an | findstr :8000 | findstr LISTENING >nul
if not errorlevel 1 (
    echo %YELLOW%ATTENZIONE: Porta 8000 già in uso%RESET%
    echo Chiudi l'applicazione che usa la porta 8000 o modifica la porta in .env
    set /p CONTINUE="Vuoi continuare comunque? (s/n): "
    if /i "!CONTINUE!" neq "s" exit /b 1
)

REM Controlla porta 3000 (React)
netstat -an | findstr :3000 | findstr LISTENING >nul
if not errorlevel 1 (
    echo %YELLOW%ATTENZIONE: Porta 3000 già in uso%RESET%
    echo Chiudi l'applicazione che usa la porta 3000
    set /p CONTINUE="Vuoi continuare comunque? (s/n): "
    if /i "!CONTINUE!" neq "s" exit /b 1
)

REM Controlla porta 6001 (WebSocket)
netstat -an | findstr :6001 | findstr LISTENING >nul
if not errorlevel 1 (
    echo %YELLOW%ATTENZIONE: Porta 6001 già in uso%RESET%
    echo Chiudi l'applicazione che usa la porta 6001
    set /p CONTINUE="Vuoi continuare comunque? (s/n): "
    if /i "!CONTINUE!" neq "s" exit /b 1
)

echo %GREEN%√ Porte disponibili%RESET%

REM Avvia Laravel Server
echo.
echo %YELLOW%[3/6] Avvio Laravel Server (porta 8000)...%RESET%
echo ----------------------------------------
cd /d "%BACKEND_DIR%"
start "Laravel Server" cmd /k "php artisan serve --host=0.0.0.0 --port=8000"
timeout /t 2 /nobreak >nul
echo %GREEN%√ Laravel Server avviato%RESET%

REM Avvia Queue Worker (se necessario)
echo.
echo %YELLOW%[4/6] Avvio Queue Worker...%RESET%
echo ----------------------------------------
cd /d "%BACKEND_DIR%"

REM Verifica se ci sono jobs nella coda
php artisan queue:table >nul 2>&1
if not errorlevel 1 (
    start "Queue Worker" cmd /k "php artisan queue:work --tries=3 --timeout=90"
    echo %GREEN%√ Queue Worker avviato%RESET%
) else (
    echo %YELLOW%Queue non configurato, salto questo passaggio%RESET%
)

REM Avvia WebSocket Server (se Laravel WebSockets è installato)
echo.
echo %YELLOW%[5/6] Avvio WebSocket Server...%RESET%
echo ----------------------------------------
cd /d "%BACKEND_DIR%"

php artisan list | findstr /i "websockets:serve" >nul 2>&1
if not errorlevel 1 (
    start "WebSocket Server" cmd /k "php artisan websockets:serve"
    timeout /t 2 /nobreak >nul
    echo %GREEN%√ WebSocket Server avviato%RESET%
) else (
    REM Prova con Laravel Echo Server
    where laravel-echo-server >nul 2>&1
    if not errorlevel 1 (
        if exist "%BACKEND_DIR%\laravel-echo-server.json" (
            start "Laravel Echo Server" cmd /k "laravel-echo-server start"
            echo %GREEN%√ Laravel Echo Server avviato%RESET%
        ) else (
            echo %YELLOW%WebSocket non configurato%RESET%
        )
    ) else (
        echo %YELLOW%WebSocket server non installato%RESET%
    )
)

REM Avvia Frontend Development Server
echo.
echo %YELLOW%[6/6] Avvio Frontend React (porta 3000)...%RESET%
echo ----------------------------------------
cd /d "%FRONTEND_DIR%"

if not exist node_modules (
    echo %RED%ERRORE: node_modules non trovato!%RESET%
    echo Esegui prima: npm install
    pause
    exit /b 1
)

REM Avvia il server di sviluppo React
start "React Dev Server" cmd /k "npm run dev"
echo %GREEN%√ React Dev Server in avvio...%RESET%
echo Attendi qualche secondo per il completamento del build...

REM Attendi che i servizi si avviino
echo.
echo %YELLOW%Attendo l'avvio completo dei servizi...%RESET%
timeout /t 5 /nobreak >nul

REM Verifica che i servizi siano attivi
echo.
echo %YELLOW%Verifica servizi attivi...%RESET%
echo ----------------------------------------

netstat -an | findstr :8000 | findstr LISTENING >nul
if not errorlevel 1 (
    echo %GREEN%√ Laravel API attiva su http://localhost:8000%RESET%
) else (
    echo %RED%✗ Laravel API non risponde%RESET%
)

REM Il server React potrebbe impiegare più tempo
echo %YELLOW%React Dev Server in compilazione...%RESET%
echo Potrebbe impiegare 10-30 secondi per il primo avvio

REM Apri browser
echo.
echo %YELLOW%Apertura browser...%RESET%
echo ----------------------------------------
timeout /t 5 /nobreak >nul

REM Apri le pagine nel browser predefinito
start http://localhost:3000
timeout /t 2 /nobreak >nul
start http://localhost:8000

REM Riepilogo finale
echo.
echo ====================================================
echo %GREEN%  SERVIZI AVVIATI CON SUCCESSO!%RESET%
echo ====================================================
echo.
echo %YELLOW%Servizi in esecuzione:%RESET%
echo.
echo   • Frontend React:     http://localhost:3000
echo   • Backend Laravel:    http://localhost:8000
echo   • API Documentation:  http://localhost:8000/api/documentation
echo   • phpMyAdmin:        http://localhost/phpmyadmin
echo.
echo %YELLOW%Finestre aperte:%RESET%
echo   • Laravel Server     (NON chiudere)
echo   • Queue Worker       (NON chiudere)
echo   • WebSocket Server   (NON chiudere)
echo   • React Dev Server   (NON chiudere)
echo.
echo %RED%IMPORTANTE:%RESET% Non chiudere le finestre dei servizi!
echo Per fermare tutti i servizi usa: stop-services.bat
echo.
echo %GREEN%Buon lavoro con NexioSolution!%RESET%
echo.
pause