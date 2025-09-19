@echo off
:: ===================================================
:: NEXIO SOLUTION V2 - SCRIPT AVVIO AUTOMATICO
:: Sistema di Autenticazione Multi-Tenant Avanzato
:: ===================================================

setlocal EnableDelayedExpansion
color 0A
title Nexio Solution V2 - Avvio Sistema

:: Configurazione percorsi
set XAMPP_PATH=C:\xampp
set APACHE_PATH=%XAMPP_PATH%\apache\bin
set MYSQL_PATH=%XAMPP_PATH%\mysql\bin
set PHP_PATH=%XAMPP_PATH%\php
set PROJECT_PATH=%XAMPP_PATH%\htdocs\Nexiosolution\collabora
set PROJECT_URL=http://localhost/Nexiosolution/collabora

:: Banner iniziale
echo.
echo ========================================================
echo    NEXIO SOLUTION V2 - SISTEMA COLLABORATIVO
echo    Powered by Fortibyte Solutions
echo ========================================================
echo.

:: Verifica XAMPP installato
if not exist "%XAMPP_PATH%" (
    echo [ERRORE] XAMPP non trovato in %XAMPP_PATH%
    echo.
    echo Installa XAMPP da: https://www.apachefriends.org/
    echo.
    pause
    exit /b 1
)

:: Verifica progetto presente
if not exist "%PROJECT_PATH%" (
    echo [ERRORE] Progetto non trovato in %PROJECT_PATH%
    echo.
    echo Assicurati che il progetto sia nella directory corretta
    echo.
    pause
    exit /b 1
)

echo [1/6] Verifica servizi XAMPP...
echo ----------------------------------------

:: Controlla se Apache è in esecuzione
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [OK] Apache già in esecuzione
) else (
    echo [*] Avvio Apache...
    start /B "" "%APACHE_PATH%\httpd.exe"
    timeout /t 3 /nobreak >nul

    tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe">NUL
    if "!ERRORLEVEL!"=="0" (
        echo [OK] Apache avviato con successo
    ) else (
        echo [ERRORE] Impossibile avviare Apache
        echo.
        echo Prova ad avviare XAMPP Control Panel manualmente
        pause
        exit /b 1
    )
)

:: Controlla se MySQL è in esecuzione
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [OK] MySQL già in esecuzione
) else (
    echo [*] Avvio MySQL...
    start /B "" "%MYSQL_PATH%\mysqld.exe"
    timeout /t 5 /nobreak >nul

    tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
    if "!ERRORLEVEL!"=="0" (
        echo [OK] MySQL avviato con successo
    ) else (
        echo [ERRORE] Impossibile avviare MySQL
        echo.
        echo Prova ad avviare XAMPP Control Panel manualmente
        pause
        exit /b 1
    )
)

echo.
echo [2/6] Verifica connessione database...
echo ----------------------------------------

:: Test connessione MySQL
"%MYSQL_PATH%\mysql.exe" -u root -e "SELECT 1" >nul 2>&1
if "%ERRORLEVEL%"=="0" (
    echo [OK] Connessione database attiva
) else (
    echo [AVVISO] Database non raggiungibile con utente root senza password
    echo.
    echo Se hai impostato una password per root, modifica config_v2.php
)

:: Verifica se database esiste
"%MYSQL_PATH%\mysql.exe" -u root -e "USE nexio_collabora_v2" >nul 2>&1
if "%ERRORLEVEL%"=="0" (
    echo [OK] Database nexio_collabora_v2 trovato
    set DB_EXISTS=1
) else (
    echo [INFO] Database non trovato - verrà creato automaticamente
    set DB_EXISTS=0
)

echo.
echo [3/6] Verifica configurazione PHP...
echo ----------------------------------------

:: Verifica versione PHP
"%PHP_PATH%\php.exe" -v >nul 2>&1
if "%ERRORLEVEL%"=="0" (
    for /f "tokens=2" %%i in ('"%PHP_PATH%\php.exe" -v ^| findstr /i "^PHP"') do set PHP_VERSION=%%i
    echo [OK] PHP versione: !PHP_VERSION!
) else (
    echo [ERRORE] PHP non trovato
    pause
    exit /b 1
)

:: Verifica estensioni PHP richieste
echo [*] Verifica estensioni PHP...
"%PHP_PATH%\php.exe" -m | findstr /i "mysqli pdo_mysql json mbstring openssl" >nul
if "%ERRORLEVEL%"=="0" (
    echo [OK] Estensioni PHP necessarie presenti
) else (
    echo [AVVISO] Alcune estensioni PHP potrebbero mancare
)

echo.
echo [4/6] Verifica file progetto...
echo ----------------------------------------

:: Verifica file essenziali
set FILES_OK=1

if exist "%PROJECT_PATH%\config_v2.php" (
    echo [OK] config_v2.php presente
) else (
    echo [ERRORE] config_v2.php mancante
    set FILES_OK=0
)

if exist "%PROJECT_PATH%\index_v2.php" (
    echo [OK] index_v2.php presente
) else (
    echo [ERRORE] index_v2.php mancante
    set FILES_OK=0
)

if exist "%PROJECT_PATH%\install_v2.php" (
    echo [OK] install_v2.php presente
) else (
    echo [AVVISO] install_v2.php mancante
)

if exist "%PROJECT_PATH%\test_v2.php" (
    echo [OK] test_v2.php presente
) else (
    echo [INFO] test_v2.php mancante
)

echo.
echo [5/6] Controllo installazione...
echo ----------------------------------------

:: Verifica se installazione necessaria
if "%DB_EXISTS%"=="0" (
    echo [*] Database non configurato
    echo.
    echo ========================================
    echo  INSTALLAZIONE RICHIESTA
    echo ========================================
    echo.
    echo Il sistema deve essere installato.
    echo Apertura installazione guidata...
    echo.

    timeout /t 3 /nobreak >nul
    start "" "%PROJECT_URL%/install_v2.php"

    echo Completa l'installazione nel browser.
    echo.
    echo Credenziali Admin Default:
    echo   Email: asamodeo@fortibyte.it
    echo   Password: Ricord@1991
    echo.
    pause
    exit /b 0
)

:: Se database esiste, verifica tabelle
echo [*] Verifica struttura database...
"%MYSQL_PATH%\mysql.exe" -u root nexio_collabora_v2 -e "SELECT COUNT(*) FROM users" >nul 2>&1
if "%ERRORLEVEL%"=="0" (
    echo [OK] Struttura database corretta
) else (
    echo [AVVISO] Struttura database incompleta
    echo.
    echo Apri: %PROJECT_URL%/install_v2.php
    echo per completare l'installazione
)

echo.
echo [6/6] Avvio sistema...
echo ----------------------------------------

:: Crea file batch per test rapido
echo @echo off > "%PROJECT_PATH%\quick_test.bat"
echo cd /d "%PROJECT_PATH%" >> "%PROJECT_PATH%\quick_test.bat"
echo "%PHP_PATH%\php.exe" test_v2.php >> "%PROJECT_PATH%\quick_test.bat"
echo pause >> "%PROJECT_PATH%\quick_test.bat"

echo [OK] Sistema pronto all'uso!
echo.
echo ========================================================
echo  SISTEMA AVVIATO CON SUCCESSO
echo ========================================================
echo.
echo URLs Disponibili:
echo   - Login: %PROJECT_URL%/index_v2.php
echo   - Test: %PROJECT_URL%/test_v2.php
echo   - Admin: %PROJECT_URL%/admin/
echo.
echo Credenziali Amministratore:
echo   Email: asamodeo@fortibyte.it
echo   Password: Ricord@1991
echo.
echo Utenti Demo (Password: Demo@2024):
echo   - special@demo.com (Multi-tenant)
echo   - user1@demo.com (Single tenant)
echo   - user2@demo.com (Single tenant)
echo.
echo ========================================================
echo.

:: Menu opzioni
:MENU
echo Cosa vuoi fare?
echo.
echo   1. Apri Sistema nel Browser
echo   2. Esegui Test Completo
echo   3. Visualizza Log Sistema
echo   4. Backup Database
echo   5. Mostra Info Sistema
echo   6. Esci
echo.

set /p choice="Seleziona opzione (1-6): "

if "%choice%"=="1" goto OPEN_BROWSER
if "%choice%"=="2" goto RUN_TESTS
if "%choice%"=="3" goto VIEW_LOGS
if "%choice%"=="4" goto BACKUP_DB
if "%choice%"=="5" goto SHOW_INFO
if "%choice%"=="6" goto END

echo.
echo Opzione non valida!
echo.
goto MENU

:OPEN_BROWSER
echo.
echo Apertura sistema nel browser...
start "" "%PROJECT_URL%/index_v2.php"
echo.
goto MENU

:RUN_TESTS
echo.
echo ========================================
echo  ESECUZIONE TEST SISTEMA
echo ========================================
echo.

if exist "%PROJECT_PATH%\test_v2.php" (
    "%PHP_PATH%\php.exe" "%PROJECT_PATH%\test_v2.php"
    echo.
    echo Test completato. Report salvato in test_report_v2.html
) else (
    echo [ERRORE] File test_v2.php non trovato
)

echo.
pause
cls
goto MENU

:VIEW_LOGS
echo.
echo ========================================
echo  VISUALIZZAZIONE LOG
echo ========================================
echo.

if exist "%PROJECT_PATH%\logs" (
    echo Contenuto directory logs:
    echo.
    dir "%PROJECT_PATH%\logs" /B
    echo.

    set /p logfile="Nome file da visualizzare (o premi INVIO per tornare): "
    if not "!logfile!"=="" (
        if exist "%PROJECT_PATH%\logs\!logfile!" (
            echo.
            echo --- Contenuto !logfile! ---
            type "%PROJECT_PATH%\logs\!logfile!" | more
        ) else (
            echo File non trovato
        )
    )
) else (
    echo Directory logs non trovata
)

echo.
pause
cls
goto MENU

:BACKUP_DB
echo.
echo ========================================
echo  BACKUP DATABASE
echo ========================================
echo.

set BACKUP_FILE=%PROJECT_PATH%\backups\nexio_v2_backup_%date:~-4%%date:~3,2%%date:~0,2%_%time:~0,2%%time:~3,2%.sql
set BACKUP_FILE=%BACKUP_FILE: =0%

if not exist "%PROJECT_PATH%\backups" mkdir "%PROJECT_PATH%\backups"

echo Creazione backup in corso...
"%MYSQL_PATH%\mysqldump.exe" -u root nexio_collabora_v2 > "%BACKUP_FILE%" 2>nul

if exist "%BACKUP_FILE%" (
    echo [OK] Backup creato: %BACKUP_FILE%
) else (
    echo [ERRORE] Impossibile creare backup
)

echo.
pause
cls
goto MENU

:SHOW_INFO
echo.
echo ========================================
echo  INFORMAZIONI SISTEMA
echo ========================================
echo.
echo Percorsi:
echo   XAMPP: %XAMPP_PATH%
echo   Progetto: %PROJECT_PATH%
echo   URL: %PROJECT_URL%
echo.
echo Versioni:
echo   PHP: !PHP_VERSION!
echo.
echo Database:
echo   Nome: nexio_collabora_v2
echo   Utente: root
echo   Host: localhost
echo.
echo File Configurazione:
echo   %PROJECT_PATH%\config_v2.php
echo.
echo Directory:
echo   Uploads: %PROJECT_PATH%\uploads
echo   Logs: %PROJECT_PATH%\logs
echo   Backups: %PROJECT_PATH%\backups
echo   Temp: %PROJECT_PATH%\temp
echo.
pause
cls
goto MENU

:END
echo.
echo ========================================
echo  CHIUSURA SISTEMA
echo ========================================
echo.
echo Vuoi fermare i servizi XAMPP? (S/N)
set /p stop_services=

if /i "%stop_services%"=="S" (
    echo.
    echo Arresto servizi in corso...

    taskkill /F /IM httpd.exe >nul 2>&1
    if "%ERRORLEVEL%"=="0" echo [OK] Apache fermato

    taskkill /F /IM mysqld.exe >nul 2>&1
    if "%ERRORLEVEL%"=="0" echo [OK] MySQL fermato

    echo.
    echo Servizi arrestati.
)

echo.
echo Grazie per aver utilizzato Nexio Solution V2!
echo.
timeout /t 3 /nobreak >nul
exit /b 0