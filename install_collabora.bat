@echo off
REM Script di installazione per Collabora Platform
REM Richiede XAMPP installato con MySQL e PHP disponibili nel PATH

SETLOCAL ENABLEDELAYEDEXPANSION

REM Configurazione percorsi
SET XAMPP_DIR=%~1
IF "%XAMPP_DIR%"=="" SET XAMPP_DIR=C:\xampp
SET HTDOCS_DIR=%XAMPP_DIR%\htdocs
SET TARGET_DIR=%HTDOCS_DIR%\collabora

ECHO. 
ECHO ===== Collabora Platform Installer =====
ECHO Target directory: %TARGET_DIR%

IF NOT EXIST "%HTDOCS_DIR%" (
    ECHO Errore: cartella htdocs non trovata in %XAMPP_DIR%
    EXIT /B 1
)

ECHO.
ECHO Copia dei file applicativi...
IF EXIST "%TARGET_DIR%" (
    ECHO Cartella esistente trovata. Verrà aggiornata.
) ELSE (
    MKDIR "%TARGET_DIR%"
)

xcopy /E /Y "%~dp0collabora" "%TARGET_DIR%" >nul

ECHO.
ECHO Configurazione permessi cartelle upload...
IF NOT EXIST "%TARGET_DIR%\uploads" MKDIR "%TARGET_DIR%\uploads"
IF NOT EXIST "%TARGET_DIR%\temp" MKDIR "%TARGET_DIR%\temp"

ECHO.
ECHO Importazione database...
SET MYSQL_BIN=%XAMPP_DIR%\mysql\bin\mysql.exe
IF NOT EXIST "%MYSQL_BIN%" (
    ECHO Errore: mysql.exe non trovato in %XAMPP_DIR%\mysql\bin
    EXIT /B 2
)

"%MYSQL_BIN%" -u root -p < "%TARGET_DIR%\install.sql"
IF ERRORLEVEL 1 (
    ECHO Importazione database fallita.
    EXIT /B 3
)

ECHO.
ECHO Installazione completata con successo!
ECHO Accedi a http://localhost/collabora per iniziare.

ENDLOCAL
EXIT /B 0
