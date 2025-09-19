@echo off
REM ============================================================
REM  Nexiosolution Collabora - Quick Start Script for Windows
REM  Version: 1.0.0
REM ============================================================

title Nexiosolution Collabora Launcher
color 0A

echo.
echo ====================================================
echo    NEXIOSOLUTION COLLABORA - AVVIO SISTEMA
echo ====================================================
echo.

REM Check if running as administrator
net session >nul 2>&1
if %errorLevel% == 0 (
    echo [OK] Esecuzione con privilegi amministratore
) else (
    echo [!] ATTENZIONE: Eseguire come amministratore per funzioni complete
    echo.
    pause
)

REM Set paths
set XAMPP_PATH=C:\xampp
set APACHE_PATH=%XAMPP_PATH%\apache\bin
set MYSQL_PATH=%XAMPP_PATH%\mysql\bin
set PHP_PATH=%XAMPP_PATH%\php
set PROJECT_PATH=%XAMPP_PATH%\htdocs\Nexiosolution\collabora
set BROWSER_URL=http://localhost/Nexiosolution/collabora

REM Check if XAMPP is installed
if not exist "%XAMPP_PATH%" (
    echo [ERRORE] XAMPP non trovato in %XAMPP_PATH%
    echo Installa XAMPP da: https://www.apachefriends.org
    pause
    exit /b 1
)

echo [*] XAMPP trovato in: %XAMPP_PATH%
echo.

REM Check project directory
if not exist "%PROJECT_PATH%" (
    echo [ERRORE] Progetto non trovato in %PROJECT_PATH%
    pause
    exit /b 1
)

echo [*] Progetto trovato in: %PROJECT_PATH%
echo.

REM Function to check if a service is running
echo ====================================================
echo    CONTROLLO SERVIZI
echo ====================================================
echo.

REM Check Apache status
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [OK] Apache e' gia' in esecuzione
    set APACHE_RUNNING=1
) else (
    echo [*] Apache non e' in esecuzione
    set APACHE_RUNNING=0
)

REM Check MySQL status
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [OK] MySQL e' gia' in esecuzione
    set MYSQL_RUNNING=1
) else (
    echo [*] MySQL non e' in esecuzione
    set MYSQL_RUNNING=0
)

echo.
echo ====================================================
echo    AVVIO SERVIZI XAMPP
echo ====================================================
echo.

REM Start Apache if not running
if "%APACHE_RUNNING%"=="0" (
    echo [*] Avvio Apache...
    start /B "" "%XAMPP_PATH%\apache_start.bat" >nul 2>&1
    timeout /t 3 /nobreak >nul

    REM Verify Apache started
    tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe">NUL
    if "%ERRORLEVEL%"=="0" (
        echo [OK] Apache avviato con successo
    ) else (
        echo [ERRORE] Impossibile avviare Apache
        echo.
        echo Prova ad avviare manualmente XAMPP Control Panel:
        echo %XAMPP_PATH%\xampp-control.exe
        pause
        exit /b 1
    )
) else (
    echo [OK] Apache gia' attivo
)

REM Start MySQL if not running
if "%MYSQL_RUNNING%"=="0" (
    echo [*] Avvio MySQL...
    start /B "" "%XAMPP_PATH%\mysql_start.bat" >nul 2>&1
    timeout /t 3 /nobreak >nul

    REM Verify MySQL started
    tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
    if "%ERRORLEVEL%"=="0" (
        echo [OK] MySQL avviato con successo
    ) else (
        echo [ERRORE] Impossibile avviare MySQL
        echo.
        echo Prova ad avviare manualmente XAMPP Control Panel:
        echo %XAMPP_PATH%\xampp-control.exe
        pause
        exit /b 1
    )
) else (
    echo [OK] MySQL gia' attivo
)

echo.
echo ====================================================
echo    VERIFICA CONFIGURAZIONE
echo ====================================================
echo.

REM Check if config.php exists
if exist "%PROJECT_PATH%\config.php" (
    echo [OK] File di configurazione trovato
) else (
    echo [!] File di configurazione non trovato
    echo [*] Apertura installazione guidata...
    set BROWSER_URL=http://localhost/Nexiosolution/collabora/install.php
)

REM Check database connection
echo [*] Verifica connessione database...
"%PHP_PATH%\php.exe" -r "try { $pdo = new PDO('mysql:host=localhost', 'root', ''); echo '[OK] Database raggiungibile'; } catch(Exception $e) { echo '[ERRORE] Database non raggiungibile'; }" 2>nul
echo.

REM Check required directories
echo [*] Verifica directory...
if not exist "%PROJECT_PATH%\uploads" (
    echo [*] Creazione directory uploads...
    mkdir "%PROJECT_PATH%\uploads"
)

if not exist "%PROJECT_PATH%\logs" (
    echo [*] Creazione directory logs...
    mkdir "%PROJECT_PATH%\logs"
)

if not exist "%PROJECT_PATH%\assets\cache" (
    echo [*] Creazione directory cache...
    mkdir "%PROJECT_PATH%\assets\cache" 2>nul
)

echo [OK] Directory verificate
echo.

REM Run system test
echo ====================================================
echo    TEST DI SISTEMA
echo ====================================================
echo.

cd /d "%PROJECT_PATH%"
"%PHP_PATH%\php.exe" test.php --all 2>nul | findstr /C:"["

echo.
echo ====================================================
echo    APERTURA BROWSER
echo ====================================================
echo.

echo [*] Apertura applicazione nel browser...
echo URL: %BROWSER_URL%
echo.

REM Try different methods to open browser
start "" "%BROWSER_URL%" 2>nul
if %errorlevel% neq 0 (
    REM Try with Chrome
    start chrome.exe "%BROWSER_URL%" 2>nul
    if %errorlevel% neq 0 (
        REM Try with Firefox
        start firefox.exe "%BROWSER_URL%" 2>nul
        if %errorlevel% neq 0 (
            REM Try with Edge
            start msedge.exe "%BROWSER_URL%" 2>nul
            if %errorlevel% neq 0 (
                REM Try with Internet Explorer
                start iexplore.exe "%BROWSER_URL%" 2>nul
            )
        )
    )
)

echo ====================================================
echo    SISTEMA AVVIATO
echo ====================================================
echo.
echo Nexiosolution Collabora e' ora disponibile su:
echo %BROWSER_URL%
echo.
echo Credenziali di default:
echo Username: admin
echo Password: admin123
echo.
echo ====================================================
echo    COMANDI UTILI
echo ====================================================
echo.
echo [1] Apri XAMPP Control Panel
echo [2] Apri phpMyAdmin
echo [3] Visualizza logs
echo [4] Esegui backup
echo [5] Stop servizi
echo [6] Test sistema
echo [0] Esci
echo.

:menu
set /p choice="Seleziona opzione (0-6): "

if "%choice%"=="1" (
    start "" "%XAMPP_PATH%\xampp-control.exe"
    goto menu
)

if "%choice%"=="2" (
    start "" "http://localhost/phpmyadmin"
    goto menu
)

if "%choice%"=="3" (
    if exist "%PROJECT_PATH%\logs\error.log" (
        notepad "%PROJECT_PATH%\logs\error.log"
    ) else (
        echo Nessun log trovato
    )
    goto menu
)

if "%choice%"=="4" (
    if exist "%PROJECT_PATH%\backup.bat" (
        call "%PROJECT_PATH%\backup.bat"
    ) else (
        echo Script backup non trovato
    )
    goto menu
)

if "%choice%"=="5" (
    echo.
    echo Arresto servizi...
    "%XAMPP_PATH%\apache_stop.bat" >nul 2>&1
    "%XAMPP_PATH%\mysql_stop.bat" >nul 2>&1
    echo Servizi arrestati
    pause
    exit
)

if "%choice%"=="6" (
    echo.
    "%PHP_PATH%\php.exe" "%PROJECT_PATH%\test.php" --all
    pause
    goto menu
)

if "%choice%"=="0" (
    echo.
    echo Arrivederci!
    timeout /t 2 /nobreak >nul
    exit
)

echo Opzione non valida
goto menu