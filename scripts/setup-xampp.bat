@echo off
REM ====================================================
REM NexioSolution - Script di Installazione per XAMPP
REM ====================================================
REM Questo script automatizza l'installazione e configurazione
REM di NexioSolution su ambiente XAMPP Windows

setlocal EnableDelayedExpansion

REM Colori per output
set RED=[91m
set GREEN=[92m
set YELLOW=[93m
set BLUE=[94m
set MAGENTA=[95m
set CYAN=[96m
set WHITE=[97m
set RESET=[0m

echo.
echo %CYAN%=====================================================%RESET%
echo %CYAN%     NexioSolution - Installazione XAMPP%RESET%
echo %CYAN%=====================================================%RESET%
echo.

REM Verifica se lo script è eseguito come amministratore
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo %RED%ERRORE: Questo script richiede privilegi di amministratore!%RESET%
    echo %YELLOW%Per favore, esegui come amministratore.%RESET%
    pause
    exit /b 1
)

REM Variabili di configurazione
set XAMPP_PATH=C:\xampp
set HTDOCS_PATH=%XAMPP_PATH%\htdocs
set PROJECT_PATH=%HTDOCS_PATH%\Nexiosolution
set PHP_PATH=%XAMPP_PATH%\php
set MYSQL_PATH=%XAMPP_PATH%\mysql\bin
set APACHE_PATH=%XAMPP_PATH%\apache

REM Verifica installazione XAMPP
echo %BLUE%[1/15] Verifica installazione XAMPP...%RESET%
if not exist "%XAMPP_PATH%" (
    echo %RED%ERRORE: XAMPP non trovato in %XAMPP_PATH%%RESET%
    echo %YELLOW%Per favore, installa XAMPP prima di eseguire questo script.%RESET%
    echo Download: https://www.apachefriends.org/download.html
    pause
    exit /b 1
)
echo %GREEN%✓ XAMPP trovato%RESET%

REM Verifica versione PHP
echo %BLUE%[2/15] Verifica versione PHP...%RESET%
"%PHP_PATH%\php.exe" -v | findstr /C:"PHP 8." >nul
if %errorLevel% neq 0 (
    echo %RED%ERRORE: PHP 8.x richiesto!%RESET%
    echo %YELLOW%Versione attuale:%RESET%
    "%PHP_PATH%\php.exe" -v
    pause
    exit /b 1
)
echo %GREEN%✓ PHP 8.x trovato%RESET%

REM Verifica Composer
echo %BLUE%[3/15] Verifica Composer...%RESET%
where composer >nul 2>&1
if %errorLevel% neq 0 (
    echo %YELLOW%Composer non trovato. Installazione in corso...%RESET%

    REM Download Composer
    powershell -Command "Invoke-WebRequest -Uri 'https://getcomposer.org/installer' -OutFile 'composer-setup.php'"
    "%PHP_PATH%\php.exe" composer-setup.php --install-dir=%PHP_PATH% --filename=composer.bat
    del composer-setup.php

    if exist "%PHP_PATH%\composer.bat" (
        echo %GREEN%✓ Composer installato%RESET%
        set COMPOSER_PATH=%PHP_PATH%\composer.bat
    ) else (
        echo %RED%ERRORE: Installazione Composer fallita!%RESET%
        pause
        exit /b 1
    )
) else (
    echo %GREEN%✓ Composer trovato%RESET%
    set COMPOSER_PATH=composer
)

REM Verifica Node.js e npm
echo %BLUE%[4/15] Verifica Node.js e npm...%RESET%
where node >nul 2>&1
if %errorLevel% neq 0 (
    echo %RED%ERRORE: Node.js non trovato!%RESET%
    echo %YELLOW%Per favore, installa Node.js 18+ da: https://nodejs.org/%RESET%
    pause
    exit /b 1
)
node -v | findstr /C:"v18" /C:"v19" /C:"v20" >nul
if %errorLevel% neq 0 (
    echo %YELLOW%ATTENZIONE: Si consiglia Node.js 18+%RESET%
)
echo %GREEN%✓ Node.js trovato%RESET%

REM Verifica Git
echo %BLUE%[5/15] Verifica Git...%RESET%
where git >nul 2>&1
if %errorLevel% neq 0 (
    echo %YELLOW%Git non trovato. Non sarà possibile utilizzare controllo versione.%RESET%
) else (
    echo %GREEN%✓ Git trovato%RESET%
)

REM Naviga alla directory del progetto
echo %BLUE%[6/15] Navigazione alla directory del progetto...%RESET%
cd /d "%PROJECT_PATH%"
if %errorLevel% neq 0 (
    echo %RED%ERRORE: Directory progetto non trovata!%RESET%
    pause
    exit /b 1
)
echo %GREEN%✓ Directory progetto: %PROJECT_PATH%%RESET%

REM Backup file di configurazione se esistenti
echo %BLUE%[7/15] Backup configurazioni esistenti...%RESET%
if exist "backend\.env" (
    copy "backend\.env" "backend\.env.backup.%date:~-4,4%%date:~-10,2%%date:~-7,2%.%time:~0,2%%time:~3,2%" >nul
    echo %GREEN%✓ Backup .env creato%RESET%
)

REM Configurazione Backend Laravel
echo.
echo %CYAN%=== Configurazione Backend Laravel ===%RESET%
echo.

cd backend

REM Installa dipendenze PHP
echo %BLUE%[8/15] Installazione dipendenze PHP...%RESET%
call %COMPOSER_PATH% install --no-interaction --prefer-dist --optimize-autoloader
if %errorLevel% neq 0 (
    echo %RED%ERRORE: Installazione dipendenze PHP fallita!%RESET%
    pause
    exit /b 1
)
echo %GREEN%✓ Dipendenze PHP installate%RESET%

REM Crea file .env se non esiste
echo %BLUE%[9/15] Configurazione ambiente Laravel...%RESET%
if not exist ".env" (
    copy ".env.example" ".env" >nul
    echo %GREEN%✓ File .env creato%RESET%

    REM Genera application key
    "%PHP_PATH%\php.exe" artisan key:generate
    echo %GREEN%✓ Application key generata%RESET%
) else (
    echo %YELLOW%File .env già esistente%RESET%
)

REM Configura database
echo %BLUE%[10/15] Configurazione database...%RESET%
echo.
echo %YELLOW%Configurazione Database MySQL:%RESET%
echo.

set /p DB_NAME="Nome database [nexiosolution]: " || set DB_NAME=nexiosolution
set /p DB_USER="Username MySQL [root]: " || set DB_USER=root
set /p DB_PASS="Password MySQL [vuota]: " || set DB_PASS=

REM Aggiorna .env con le credenziali database
powershell -Command "(Get-Content .env) -replace 'DB_DATABASE=.*', 'DB_DATABASE=%DB_NAME%' | Set-Content .env"
powershell -Command "(Get-Content .env) -replace 'DB_USERNAME=.*', 'DB_USERNAME=%DB_USER%' | Set-Content .env"
powershell -Command "(Get-Content .env) -replace 'DB_PASSWORD=.*', 'DB_PASSWORD=%DB_PASS%' | Set-Content .env"
powershell -Command "(Get-Content .env) -replace 'DB_HOST=.*', 'DB_HOST=127.0.0.1' | Set-Content .env"

REM Crea database se non esiste
echo %BLUE%Creazione database...%RESET%
"%MYSQL_PATH%\mysql.exe" -u%DB_USER% -p%DB_PASS% -e "CREATE DATABASE IF NOT EXISTS %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if %errorLevel% eq 0 (
    echo %GREEN%✓ Database %DB_NAME% pronto%RESET%
) else (
    echo %YELLOW%ATTENZIONE: Impossibile creare database automaticamente%RESET%
    echo %YELLOW%Per favore, crea manualmente il database '%DB_NAME%'%RESET%
)

REM Esegui migrazioni
echo %BLUE%[11/15] Esecuzione migrazioni database...%RESET%
"%PHP_PATH%\php.exe" artisan migrate --force
if %errorLevel% neq 0 (
    echo %YELLOW%ATTENZIONE: Migrazioni fallite. Verifica la connessione al database.%RESET%
) else (
    echo %GREEN%✓ Migrazioni completate%RESET%
)

REM Seed database
echo %BLUE%Popolamento database con dati di esempio...%RESET%
"%PHP_PATH%\php.exe" artisan db:seed --force
if %errorLevel% eq 0 (
    echo %GREEN%✓ Database popolato%RESET%
)

REM Crea link storage
echo %BLUE%[12/15] Creazione link storage...%RESET%
"%PHP_PATH%\php.exe" artisan storage:link
echo %GREEN%✓ Storage link creato%RESET%

REM Ottimizza Laravel
echo %BLUE%Ottimizzazione Laravel...%RESET%
"%PHP_PATH%\php.exe" artisan config:cache
"%PHP_PATH%\php.exe" artisan route:cache
"%PHP_PATH%\php.exe" artisan view:cache
echo %GREEN%✓ Laravel ottimizzato%RESET%

REM Configurazione Frontend React
echo.
echo %CYAN%=== Configurazione Frontend React ===%RESET%
echo.

cd ..\frontend

REM Installa dipendenze Node.js
echo %BLUE%[13/15] Installazione dipendenze Node.js...%RESET%
call npm install
if %errorLevel% neq 0 (
    echo %RED%ERRORE: Installazione dipendenze Node.js fallita!%RESET%
    pause
    exit /b 1
)
echo %GREEN%✓ Dipendenze Node.js installate%RESET%

REM Crea file .env per frontend
echo %BLUE%Configurazione ambiente React...%RESET%
if not exist ".env" (
    echo VITE_API_URL=http://localhost/Nexiosolution/backend/public/api > .env
    echo VITE_APP_NAME=NexioSolution >> .env
    echo VITE_WEBSOCKET_URL=ws://localhost:6001 >> .env
    echo VITE_ONLYOFFICE_URL=http://localhost:8080 >> .env
    echo %GREEN%✓ File .env frontend creato%RESET%
)

REM Build frontend
echo %BLUE%[14/15] Build frontend...%RESET%
call npm run build
if %errorLevel% eq 0 (
    echo %GREEN%✓ Frontend compilato%RESET%
) else (
    echo %YELLOW%ATTENZIONE: Build frontend fallito%RESET%
)

cd ..

REM Configurazione Apache VirtualHost
echo.
echo %CYAN%=== Configurazione Apache ===%RESET%
echo.

echo %BLUE%[15/15] Configurazione VirtualHost Apache...%RESET%

REM Backup httpd-vhosts.conf
if exist "%APACHE_PATH%\conf\extra\httpd-vhosts.conf" (
    copy "%APACHE_PATH%\conf\extra\httpd-vhosts.conf" "%APACHE_PATH%\conf\extra\httpd-vhosts.conf.backup" >nul
    echo %GREEN%✓ Backup httpd-vhosts.conf creato%RESET%
)

REM Crea configurazione VirtualHost
echo %BLUE%Creazione VirtualHost per NexioSolution...%RESET%
(
echo.
echo # NexioSolution VirtualHost
echo ^<VirtualHost *:80^>
echo     ServerName nexiosolution.local
echo     ServerAlias *.nexiosolution.local
echo     DocumentRoot "%PROJECT_PATH%/backend/public"
echo
echo     ^<Directory "%PROJECT_PATH%/backend/public"^>
echo         Options Indexes FollowSymLinks
echo         AllowOverride All
echo         Require all granted
echo     ^</Directory^>
echo
echo     # Proxy per WebSocket
echo     ProxyPass /ws ws://localhost:6001/
echo     ProxyPassReverse /ws ws://localhost:6001/
echo
echo     # Log files
echo     ErrorLog "%XAMPP_PATH%/apache/logs/nexiosolution-error.log"
echo     CustomLog "%XAMPP_PATH%/apache/logs/nexiosolution-access.log" common
echo ^</VirtualHost^>
) >> "%APACHE_PATH%\conf\extra\httpd-vhosts.conf"
echo %GREEN%✓ VirtualHost configurato%RESET%

REM Aggiorna hosts file
echo %BLUE%Aggiornamento file hosts...%RESET%
findstr /C:"nexiosolution.local" C:\Windows\System32\drivers\etc\hosts >nul
if %errorLevel% neq 0 (
    echo 127.0.0.1 nexiosolution.local >> C:\Windows\System32\drivers\etc\hosts
    echo 127.0.0.1 *.nexiosolution.local >> C:\Windows\System32\drivers\etc\hosts
    echo %GREEN%✓ File hosts aggiornato%RESET%
) else (
    echo %YELLOW%nexiosolution.local già presente in hosts%RESET%
)

REM Abilita moduli Apache necessari
echo %BLUE%Abilitazione moduli Apache...%RESET%
powershell -Command "(Get-Content '%APACHE_PATH%\conf\httpd.conf') -replace '#LoadModule rewrite_module', 'LoadModule rewrite_module' | Set-Content '%APACHE_PATH%\conf\httpd.conf'"
powershell -Command "(Get-Content '%APACHE_PATH%\conf\httpd.conf') -replace '#LoadModule proxy_module', 'LoadModule proxy_module' | Set-Content '%APACHE_PATH%\conf\httpd.conf'"
powershell -Command "(Get-Content '%APACHE_PATH%\conf\httpd.conf') -replace '#LoadModule proxy_http_module', 'LoadModule proxy_http_module' | Set-Content '%APACHE_PATH%\conf\httpd.conf'"
powershell -Command "(Get-Content '%APACHE_PATH%\conf\httpd.conf') -replace '#LoadModule proxy_wstunnel_module', 'LoadModule proxy_wstunnel_module' | Set-Content '%APACHE_PATH%\conf\httpd.conf'"
echo %GREEN%✓ Moduli Apache abilitati%RESET%

REM Restart Apache
echo %BLUE%Riavvio Apache...%RESET%
"%XAMPP_PATH%\apache\bin\httpd.exe" -k restart >nul 2>&1
echo %GREEN%✓ Apache riavviato%RESET%

REM Crea script di avvio servizi
echo %BLUE%Creazione script di avvio...%RESET%
(
echo @echo off
echo echo Avvio servizi NexioSolution...
echo.
echo REM Avvia Apache e MySQL
echo "%XAMPP_PATH%\xampp_start.exe"
echo.
echo REM Avvia Queue Worker
echo start /B "%PHP_PATH%\php.exe" "%PROJECT_PATH%\backend\artisan" queue:work --sleep=3 --tries=3
echo.
echo REM Avvia WebSocket Server
echo start /B "%PHP_PATH%\php.exe" "%PROJECT_PATH%\backend\artisan" websockets:serve
echo.
echo REM Avvia Frontend Dev Server (opzionale)
echo REM cd /d "%PROJECT_PATH%\frontend" ^&^& npm run dev
echo.
echo echo Servizi avviati!
echo echo Accedi a: http://nexiosolution.local
echo pause
) > "%PROJECT_PATH%\start-services.bat"
echo %GREEN%✓ Script di avvio creato: start-services.bat%RESET%

REM Crea script di stop servizi
(
echo @echo off
echo echo Arresto servizi NexioSolution...
echo.
echo REM Ferma Apache e MySQL
echo "%XAMPP_PATH%\xampp_stop.exe"
echo.
echo REM Killa processi PHP
echo taskkill /F /IM php.exe
echo.
echo echo Servizi arrestati!
echo pause
) > "%PROJECT_PATH%\stop-services.bat"
echo %GREEN%✓ Script di stop creato: stop-services.bat%RESET%

REM Installazione completata
echo.
echo %GREEN%=====================================================%RESET%
echo %GREEN%     INSTALLAZIONE COMPLETATA CON SUCCESSO!%RESET%
echo %GREEN%=====================================================%RESET%
echo.
echo %CYAN%Informazioni di accesso:%RESET%
echo.
echo   %WHITE%URL Applicazione:%RESET% http://nexiosolution.local
echo   %WHITE%PHPMyAdmin:%RESET% http://localhost/phpmyadmin
echo   %WHITE%Database:%RESET% %DB_NAME%
echo.
echo %CYAN%Credenziali di default:%RESET%
echo.
echo   %WHITE%Admin:%RESET%
echo     Email: admin@nexiosolution.local
echo     Password: password
echo.
echo   %WHITE%Tenant Demo:%RESET%
echo     Email: tenant@nexiosolution.local
echo     Password: password
echo.
echo %CYAN%Comandi utili:%RESET%
echo.
echo   %WHITE%Avvia servizi:%RESET% start-services.bat
echo   %WHITE%Ferma servizi:%RESET% stop-services.bat
echo   %WHITE%Queue Worker:%RESET% php artisan queue:work
echo   %WHITE%WebSocket:%RESET% php artisan websockets:serve
echo   %WHITE%Frontend Dev:%RESET% cd frontend ^&^& npm run dev
echo.
echo %CYAN%Prossimi passi:%RESET%
echo.
echo   1. Avvia i servizi con: start-services.bat
echo   2. Accedi a http://nexiosolution.local
echo   3. Configura le impostazioni del tenant
echo   4. Importa dati di esempio (opzionale)
echo.
echo %YELLOW%NOTA:%RESET% Per il supporto CalDAV, OnlyOffice e altre funzionalità
echo       avanzate, consulta la documentazione completa.
echo.

pause
exit /b 0