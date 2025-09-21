@echo off
setlocal enableextensions enabledelayedexpansion

REM ==============================================================================
REM  NexioSolution Collabora Office Installer
REM  Usage: install_collabora.bat <sql_dump_path> [mysql_password]
REM  - Provide the SQL dump path as the first argument.
REM  - Optionally provide the MySQL user password as the second argument, or
REM    leave it out to be prompted interactively.
REM  - The password (whether provided as an argument, via the MYSQL_PWD
REM    environment variable, or entered interactively) is passed to the mysql
REM    client using --password=%MYSQL_PWD% so that no interactive prompt appears
REM    during unattended installs.
REM ==============================================================================

set "SCRIPT_NAME=%~nx0"

if "%~1"=="" (
    echo Usage: %SCRIPT_NAME% ^<sql_dump_path^> [mysql_password]
    exit /b 1
)

set "SQL_DUMP=%~1"
if not exist "%SQL_DUMP%" (
    echo [ERROR] SQL dump file "%SQL_DUMP%" was not found.
    exit /b 1
)

if "%~2" NEQ "" (
    set "MYSQL_PWD=%~2"
) else if not defined MYSQL_PWD (
    set /p "MYSQL_PWD=Enter MySQL password (leave blank for none): "
)

if not defined MYSQL_HOST set "MYSQL_HOST=localhost"
if not defined MYSQL_USER set "MYSQL_USER=root"
if not defined MYSQL_DATABASE set "MYSQL_DATABASE=collabora"

set "PASSWORD_FLAG=--password=%MYSQL_PWD%"

echo Importing Collabora database into %MYSQL_DATABASE% on %MYSQL_HOST% as %MYSQL_USER%...
mysql --host=%MYSQL_HOST% --user=%MYSQL_USER% %PASSWORD_FLAG% --database=%MYSQL_DATABASE% < "%SQL_DUMP%"
if errorlevel 1 (
    echo [ERROR] Collabora database import failed.
    exit /b 1
)

echo Collabora database import completed successfully.
exit /b 0
