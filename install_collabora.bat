@echo off
setlocal ENABLEDELAYEDEXPANSION

echo ===============================
echo   Installazione Collabora
echo ===============================

set "XAMPP_DIR=C:\xampp"
set "TARGET_DIR=%XAMPP_DIR%\htdocs\collabora"
set "MYSQL_BIN=%XAMPP_DIR%\mysql\bin\mysql.exe"

if not exist "%MYSQL_BIN%" (
    echo ERRORE: mysql.exe non trovato in %MYSQL_BIN%
    echo Aggiorna la variabile XAMPP_DIR all'inizio del file install_collabora.bat.
    pause
    exit /b 1
)

echo Directory di installazione: %TARGET_DIR%
if exist "%TARGET_DIR%" (
    echo La directory esiste gia'. Verranno sovrascritti i file.
)

xcopy /E /I /Y "collabora" "%TARGET_DIR%" >nul
if errorlevel 1 (
    echo ERRORE durante la copia dei file.
    pause
    exit /b 1
)

echo.
set /p DB_USER=Inserisci l'utente MySQL [default root]: 
if "!DB_USER!"=="" set "DB_USER=root"
set /p DB_PASS=Inserisci la password MySQL (lascia vuoto se non presente): 

echo.
echo Importo il database...
if "!DB_PASS!"=="" (
    "%MYSQL_BIN%" -u "!DB_USER!" < "%TARGET_DIR%\install.sql"
) else (
    "%MYSQL_BIN%" -u "!DB_USER!" -p"!DB_PASS!" < "%TARGET_DIR%\install.sql"
)
if errorlevel 1 (
    echo ERRORE durante l'import del database.
    pause
    exit /b 1
)

echo Installazione completata con successo.
echo Percorso applicazione: http://localhost/collabora/
pause
