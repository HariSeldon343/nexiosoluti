@echo off
REM ====================================================
REM NexioSolution - Script di Setup Completo per XAMPP Windows
REM ====================================================
setlocal enabledelayedexpansion

echo ====================================================
echo   NexioSolution - Installazione Completa
echo ====================================================
echo.

REM Imposta la directory di lavoro
set "PROJECT_DIR=C:\xampp\htdocs\Nexiosolution"
cd /d "%PROJECT_DIR%"

REM Colori per output
set "GREEN=[92m"
set "RED=[91m"
set "YELLOW=[93m"
set "RESET=[0m"

echo %YELLOW%[1/10] Verifica prerequisiti...%RESET%
echo ----------------------------------------

REM Verifica PHP
php -v >nul 2>&1
if errorlevel 1 (
    echo %RED%ERRORE: PHP non trovato. Assicurati che XAMPP sia installato e PHP sia nel PATH%RESET%
    echo Aggiungi C:\xampp\php al PATH di sistema
    pause
    exit /b 1
)
echo %GREEN%√ PHP trovato%RESET%
php -v | findstr /i "PHP"

REM Verifica Node.js
node -v >nul 2>&1
if errorlevel 1 (
    echo %RED%ERRORE: Node.js non trovato. Installa Node.js da https://nodejs.org%RESET%
    pause
    exit /b 1
)
echo %GREEN%√ Node.js trovato%RESET%
node -v

REM Verifica npm
npm -v >nul 2>&1
if errorlevel 1 (
    echo %RED%ERRORE: npm non trovato%RESET%
    pause
    exit /b 1
)
echo %GREEN%√ npm trovato%RESET%
npm -v

REM Verifica Composer
echo.
echo %YELLOW%[2/10] Verifica/Installazione Composer...%RESET%
echo ----------------------------------------
composer -V >nul 2>&1
if errorlevel 1 (
    echo Composer non trovato. Installazione in corso...

    REM Download Composer installer
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"

    REM Installa Composer
    php composer-setup.php --install-dir=C:\xampp\php --filename=composer.phar

    REM Rimuovi installer
    php -r "unlink('composer-setup.php');"

    REM Crea file bat per Composer
    echo @echo off > C:\xampp\php\composer.bat
    echo php "%%~dp0composer.phar" %%* >> C:\xampp\php\composer.bat

    echo %GREEN%√ Composer installato%RESET%
) else (
    echo %GREEN%√ Composer già installato%RESET%
    composer -V | findstr /i "Composer"
)

REM Verifica MySQL
echo.
echo %YELLOW%[3/10] Verifica MySQL...%RESET%
echo ----------------------------------------
C:\xampp\mysql\bin\mysql --version >nul 2>&1
if errorlevel 1 (
    echo %RED%ERRORE: MySQL non trovato. Avvia XAMPP Control Panel e attiva MySQL%RESET%
    pause
    exit /b 1
)
echo %GREEN%√ MySQL trovato%RESET%

REM Crea database
echo.
echo %YELLOW%[4/10] Creazione database...%RESET%
echo ----------------------------------------
echo Creazione database 'nexiosolution'...

REM Crea database se non esiste
C:\xampp\mysql\bin\mysql -u root -e "CREATE DATABASE IF NOT EXISTS nexiosolution CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>nul
if errorlevel 1 (
    echo %YELLOW%Database potrebbe già esistere o MySQL ha una password%RESET%
    set /p MYSQL_PWD="Inserisci la password di root MySQL (premi ENTER se vuota): "
    C:\xampp\mysql\bin\mysql -u root -p!MYSQL_PWD! -e "CREATE DATABASE IF NOT EXISTS nexiosolution CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
)
echo %GREEN%√ Database configurato%RESET%

REM Installa dipendenze backend
echo.
echo %YELLOW%[5/10] Installazione dipendenze backend...%RESET%
echo ----------------------------------------
cd /d "%PROJECT_DIR%\backend"

if not exist vendor (
    echo Installazione dipendenze PHP...
    call composer install --no-interaction --prefer-dist --optimize-autoloader
    if errorlevel 1 (
        echo %RED%ERRORE durante l'installazione delle dipendenze PHP%RESET%
        pause
        exit /b 1
    )
) else (
    echo Aggiornamento dipendenze PHP...
    call composer update --no-interaction --prefer-dist --optimize-autoloader
)
echo %GREEN%√ Dipendenze backend installate%RESET%

REM Crea file .env se non esiste
echo.
echo %YELLOW%[6/10] Configurazione ambiente backend...%RESET%
echo ----------------------------------------
if not exist .env (
    if exist .env.example (
        copy .env.example .env
        echo File .env creato da .env.example
    ) else (
        echo Creazione file .env di base...
        call "%PROJECT_DIR%\scripts\create-env-backend.bat"
    )
)

REM Genera chiavi
echo.
echo %YELLOW%[7/10] Generazione chiavi applicazione...%RESET%
echo ----------------------------------------
php artisan key:generate --force
echo %GREEN%√ Chiave applicazione generata%RESET%

REM Genera JWT secret se il pacchetto è installato
php artisan list | findstr /i "jwt:secret" >nul 2>&1
if not errorlevel 1 (
    php artisan jwt:secret --force
    echo %GREEN%√ JWT secret generato%RESET%
)

REM Esegui migrazioni
echo.
echo %YELLOW%[8/10] Esecuzione migrazioni database...%RESET%
echo ----------------------------------------
php artisan migrate --force
if errorlevel 1 (
    echo %YELLOW%ATTENZIONE: Errore durante le migrazioni%RESET%
    echo Verifica la connessione al database nel file .env
)

REM Link storage
php artisan storage:link 2>nul
echo %GREEN%√ Storage link creato%RESET%

REM Cache configurazione
php artisan config:cache
php artisan route:cache 2>nul
echo %GREEN%√ Cache configurazione creata%RESET%

REM Installa dipendenze frontend
echo.
echo %YELLOW%[9/10] Installazione dipendenze frontend...%RESET%
echo ----------------------------------------
cd /d "%PROJECT_DIR%\frontend"

if not exist node_modules (
    echo Installazione dipendenze Node.js...
    call npm install
    if errorlevel 1 (
        echo %RED%ERRORE durante l'installazione delle dipendenze Node.js%RESET%
        echo Prova a cancellare package-lock.json e riprovare
        pause
        exit /b 1
    )
) else (
    echo Aggiornamento dipendenze Node.js...
    call npm update
)
echo %GREEN%√ Dipendenze frontend installate%RESET%

REM Crea file .env frontend se non esiste
if not exist .env (
    echo Creazione file .env frontend...
    call "%PROJECT_DIR%\scripts\create-env-frontend.bat"
)

REM Configurazione Apache
echo.
echo %YELLOW%[10/10] Configurazione Apache...%RESET%
echo ----------------------------------------
echo.
echo %YELLOW%IMPORTANTE: Configurazione manuale richiesta%RESET%
echo.
echo 1. Apri C:\xampp\apache\conf\extra\httpd-vhosts.conf
echo 2. Aggiungi la configurazione dal file: %PROJECT_DIR%\scripts\apache-config.txt
echo 3. Riavvia Apache dal XAMPP Control Panel
echo.
echo Per visualizzare la configurazione:
type "%PROJECT_DIR%\scripts\apache-config.txt" 2>nul
echo.

REM Riepilogo finale
echo.
echo ====================================================
echo %GREEN%  INSTALLAZIONE COMPLETATA CON SUCCESSO!%RESET%
echo ====================================================
echo.
echo %YELLOW%Prossimi passi:%RESET%
echo.
echo 1. Configura Apache con il VirtualHost (vedi sopra)
echo 2. Avvia i servizi con: %PROJECT_DIR%\scripts\start-services.bat
echo 3. Accedi all'applicazione:
echo    - Frontend: http://localhost:3000
echo    - Backend API: http://localhost:8000
echo    - phpMyAdmin: http://localhost/phpmyadmin
echo.
echo %YELLOW%Script utili disponibili:%RESET%
echo - start-services.bat    : Avvia tutti i servizi
echo - stop-services.bat     : Ferma tutti i servizi
echo - test-setup.bat        : Verifica configurazione
echo - reset-database.bat    : Reset database
echo - clear-cache.bat       : Pulisci cache
echo.
pause