@echo off
REM ====================================================
REM NexioSolution - Backup Progetto
REM ====================================================
setlocal enabledelayedexpansion

echo ====================================================
echo   NexioSolution - Backup Progetto
echo ====================================================
echo.

set "PROJECT_DIR=C:\xampp\htdocs\Nexiosolution"
set "BACKUP_DIR=C:\xampp\htdocs\Nexiosolution_Backups"
set "TIMESTAMP=%date:~-4%%date:~3,2%%date:~0,2%_%time:~0,2%%time:~3,2%%time:~6,2%"
set "TIMESTAMP=!TIMESTAMP: =0!"
set "BACKUP_NAME=NexioSolution_Backup_!TIMESTAMP!"

set "GREEN=[92m"
set "RED=[91m"
set "YELLOW=[93m"
set "RESET=[0m"

REM Crea directory backup se non esiste
if not exist "%BACKUP_DIR%" (
    mkdir "%BACKUP_DIR%"
    echo %GREEN%√ Directory backup creata%RESET%
)

set "CURRENT_BACKUP=%BACKUP_DIR%\%BACKUP_NAME%"
mkdir "%CURRENT_BACKUP%"

echo Backup in: %CURRENT_BACKUP%
echo.

REM Backup database
echo %YELLOW%[1/5] Backup database...%RESET%
echo ----------------------------------------
C:\xampp\mysql\bin\mysqldump -u root nexiosolution > "%CURRENT_BACKUP%\database.sql" 2>nul
if errorlevel 1 (
    echo %YELLOW%Database non trovato o MySQL non in esecuzione%RESET%
) else (
    echo %GREEN%√ Database esportato%RESET%
)

REM Backup file .env
echo.
echo %YELLOW%[2/5] Backup configurazioni...%RESET%
echo ----------------------------------------
mkdir "%CURRENT_BACKUP%\config"

if exist "%PROJECT_DIR%\backend\.env" (
    copy "%PROJECT_DIR%\backend\.env" "%CURRENT_BACKUP%\config\backend.env" >nul
    echo %GREEN%√ Backend .env salvato%RESET%
)

if exist "%PROJECT_DIR%\frontend\.env" (
    copy "%PROJECT_DIR%\frontend\.env" "%CURRENT_BACKUP%\config\frontend.env" >nul
    echo %GREEN%√ Frontend .env salvato%RESET%
)

REM Backup storage (uploads, etc)
echo.
echo %YELLOW%[3/5] Backup file caricati...%RESET%
echo ----------------------------------------
if exist "%PROJECT_DIR%\backend\storage\app" (
    xcopy "%PROJECT_DIR%\backend\storage\app" "%CURRENT_BACKUP%\storage\app" /E /I /Q /Y >nul
    echo %GREEN%√ Storage app copiato%RESET%
)

if exist "%PROJECT_DIR%\backend\public\uploads" (
    xcopy "%PROJECT_DIR%\backend\public\uploads" "%CURRENT_BACKUP%\uploads" /E /I /Q /Y >nul
    echo %GREEN%√ Uploads copiati%RESET%
)

REM Backup codice sorgente (opzionale)
echo.
set /p BACKUP_CODE="Vuoi includere il codice sorgente? (s/n): "
if /i "%BACKUP_CODE%"=="s" (
    echo.
    echo %YELLOW%[4/5] Backup codice sorgente...%RESET%
    echo ----------------------------------------
    echo Questo potrebbe richiedere diversi minuti...

    REM Backend (escludi vendor e node_modules)
    echo Backup backend...
    xcopy "%PROJECT_DIR%\backend" "%CURRENT_BACKUP%\backend" /E /I /Q /Y ^
        /EXCLUDE:%PROJECT_DIR%\scripts\backup-exclude.txt >nul 2>&1
    echo %GREEN%√ Backend copiato%RESET%

    REM Frontend (escludi node_modules)
    echo Backup frontend...
    xcopy "%PROJECT_DIR%\frontend" "%CURRENT_BACKUP%\frontend" /E /I /Q /Y ^
        /EXCLUDE:%PROJECT_DIR%\scripts\backup-exclude.txt >nul 2>&1
    echo %GREEN%√ Frontend copiato%RESET%

    REM Scripts
    xcopy "%PROJECT_DIR%\scripts" "%CURRENT_BACKUP%\scripts" /E /I /Q /Y >nul
    echo %GREEN%√ Scripts copiati%RESET%
) else (
    echo %YELLOW%Codice sorgente non incluso%RESET%
)

REM Crea file di informazioni backup
echo.
echo %YELLOW%[5/5] Creazione info backup...%RESET%
echo ----------------------------------------
(
echo ====================================================
echo NexioSolution Backup Information
echo ====================================================
echo.
echo Backup Date: %date% %time%
echo Backup Location: %CURRENT_BACKUP%
echo.
echo Contents:
echo - Database: database.sql
echo - Configuration: config\
echo - Storage: storage\
echo - Uploads: uploads\
if /i "%BACKUP_CODE%"=="s" (
echo - Source Code: backend\, frontend\, scripts\
)
echo.
echo Restore Instructions:
echo 1. Import database.sql to MySQL
echo 2. Copy config files to respective locations
echo 3. Restore storage and uploads directories
echo 4. If source included, copy to xampp\htdocs\Nexiosolution
echo.
echo ====================================================
) > "%CURRENT_BACKUP%\README.txt"
echo %GREEN%√ Info backup create%RESET%

REM Comprimi backup (opzionale, richiede 7-Zip)
echo.
where 7z >nul 2>&1
if not errorlevel 1 (
    set /p COMPRESS="Vuoi comprimere il backup? (s/n): "
    if /i "!COMPRESS!"=="s" (
        echo.
        echo Compressione backup...
        7z a -tzip "%BACKUP_DIR%\%BACKUP_NAME%.zip" "%CURRENT_BACKUP%\*" -mx5 >nul
        if not errorlevel 1 (
            echo %GREEN%√ Backup compresso in %BACKUP_NAME%.zip%RESET%
            set /p DELETE_UNCOMPRESSED="Eliminare backup non compresso? (s/n): "
            if /i "!DELETE_UNCOMPRESSED!"=="s" (
                rmdir /S /Q "%CURRENT_BACKUP%"
                echo %GREEN%√ Backup non compresso eliminato%RESET%
            )
        )
    )
)

echo.
echo %GREEN%=====================================================%RESET%
echo %GREEN%  BACKUP COMPLETATO CON SUCCESSO!%RESET%
echo %GREEN%=====================================================%RESET%
echo.
echo Backup salvato in:
echo %CURRENT_BACKUP%
echo.
pause