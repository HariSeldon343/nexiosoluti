@echo off
REM ============================================================
REM  Nexiosolution Collabora - Backup Script for Windows
REM  Version: 1.0.0
REM ============================================================

title Nexiosolution Collabora Backup
color 0E

echo.
echo ====================================================
echo    NEXIOSOLUTION COLLABORA - BACKUP SISTEMA
echo ====================================================
echo.

REM Set paths
set XAMPP_PATH=C:\xampp
set MYSQL_PATH=%XAMPP_PATH%\mysql\bin
set PHP_PATH=%XAMPP_PATH%\php
set PROJECT_PATH=%XAMPP_PATH%\htdocs\Nexiosolution\collabora
set BACKUP_PATH=%PROJECT_PATH%\backups
set ZIP_PATH=C:\Program Files\7-Zip\7z.exe

REM Generate timestamp
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set datetime=%%I
set TIMESTAMP=%datetime:~0,4%%datetime:~4,2%%datetime:~6,2%_%datetime:~8,2%%datetime:~10,2%%datetime:~12,2%
set BACKUP_NAME=backup_%TIMESTAMP%

REM Create backup directory if not exists
if not exist "%BACKUP_PATH%" (
    echo [*] Creazione directory backup...
    mkdir "%BACKUP_PATH%"
    echo [OK] Directory backup creata: %BACKUP_PATH%
) else (
    echo [OK] Directory backup: %BACKUP_PATH%
)

echo.
echo [*] Inizio backup: %date% %time%
echo [*] Nome backup: %BACKUP_NAME%
echo.

REM Create temp backup directory
set TEMP_BACKUP=%BACKUP_PATH%\%BACKUP_NAME%
mkdir "%TEMP_BACKUP%" 2>nul

echo ====================================================
echo    1. BACKUP DATABASE
echo ====================================================
echo.

REM Check if MySQL is running
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [OK] MySQL in esecuzione

    REM Read database name from config.php if exists
    if exist "%PROJECT_PATH%\config.php" (
        for /f "tokens=2 delims='" %%a in ('findstr "DB_NAME" "%PROJECT_PATH%\config.php"') do set DB_NAME=%%a

        if defined DB_NAME (
            echo [*] Database: %DB_NAME%
            echo [*] Esportazione database...

            "%MYSQL_PATH%\mysqldump.exe" -u root --databases %DB_NAME% --single-transaction --quick --lock-tables=false > "%TEMP_BACKUP%\database.sql" 2>nul

            if exist "%TEMP_BACKUP%\database.sql" (
                for %%A in ("%TEMP_BACKUP%\database.sql") do set DB_SIZE=%%~zA
                echo [OK] Database esportato: database.sql
            ) else (
                echo [ERRORE] Impossibile esportare il database
            )
        ) else (
            echo [!] Nome database non trovato nel config
        )
    ) else (
        echo [!] File config.php non trovato
        echo [*] Backup generico di tutti i database...
        "%MYSQL_PATH%\mysqldump.exe" -u root --all-databases > "%TEMP_BACKUP%\all_databases.sql" 2>nul
    )
) else (
    echo [!] MySQL non in esecuzione - skip backup database
)

echo.
echo ====================================================
echo    2. BACKUP FILE APPLICAZIONE
echo ====================================================
echo.

REM Create subdirectories
mkdir "%TEMP_BACKUP%\application" 2>nul
mkdir "%TEMP_BACKUP%\uploads" 2>nul
mkdir "%TEMP_BACKUP%\config" 2>nul

REM Backup configuration files
echo [*] Backup configurazione...
if exist "%PROJECT_PATH%\config.php" (
    copy "%PROJECT_PATH%\config.php" "%TEMP_BACKUP%\config\" >nul 2>&1
    echo [OK] config.php
)

if exist "%PROJECT_PATH%\.htaccess" (
    copy "%PROJECT_PATH%\.htaccess" "%TEMP_BACKUP%\config\" >nul 2>&1
    echo [OK] .htaccess
)

REM Backup PHP files (excluding vendor and node_modules)
echo [*] Backup file PHP...
xcopy "%PROJECT_PATH%\*.php" "%TEMP_BACKUP%\application\" /Q >nul 2>&1
xcopy "%PROJECT_PATH%\api\*.php" "%TEMP_BACKUP%\application\api\" /S /Q >nul 2>&1
xcopy "%PROJECT_PATH%\includes\*.php" "%TEMP_BACKUP%\application\includes\" /S /Q >nul 2>&1
echo [OK] File PHP copiati

REM Backup uploads directory
echo [*] Backup file caricati...
if exist "%PROJECT_PATH%\uploads" (
    xcopy "%PROJECT_PATH%\uploads\*.*" "%TEMP_BACKUP%\uploads\" /S /E /Q >nul 2>&1

    REM Count files in uploads
    set /a FILE_COUNT=0
    for /r "%PROJECT_PATH%\uploads" %%f in (*) do set /a FILE_COUNT+=1
    echo [OK] %FILE_COUNT% file in uploads
) else (
    echo [!] Directory uploads non trovata
)

REM Backup logs (last 7 days only)
echo [*] Backup logs recenti...
if exist "%PROJECT_PATH%\logs" (
    mkdir "%TEMP_BACKUP%\logs" 2>nul
    forfiles /p "%PROJECT_PATH%\logs" /s /m *.log /d -7 /c "cmd /c copy @path %TEMP_BACKUP%\logs\" >nul 2>&1
    echo [OK] Logs ultimi 7 giorni
)

echo.
echo ====================================================
echo    3. COMPRESSIONE BACKUP
echo ====================================================
echo.

REM Check if 7-Zip is installed
if exist "%ZIP_PATH%" (
    echo [*] Compressione con 7-Zip...
    "%ZIP_PATH%" a -tzip "%BACKUP_PATH%\%BACKUP_NAME%.zip" "%TEMP_BACKUP%\*" -mx=5 >nul 2>&1

    if exist "%BACKUP_PATH%\%BACKUP_NAME%.zip" (
        for %%A in ("%BACKUP_PATH%\%BACKUP_NAME%.zip") do set /a ZIP_SIZE=%%~zA/1048576
        echo [OK] Backup compresso: %BACKUP_NAME%.zip (!ZIP_SIZE! MB)

        REM Remove temp directory
        rmdir /s /q "%TEMP_BACKUP%" 2>nul
    ) else (
        echo [ERRORE] Compressione fallita
    )
) else (
    echo [!] 7-Zip non trovato - uso compressione Windows...

    REM Use PowerShell for compression
    powershell -Command "Compress-Archive -Path '%TEMP_BACKUP%\*' -DestinationPath '%BACKUP_PATH%\%BACKUP_NAME%.zip' -CompressionLevel Optimal" >nul 2>&1

    if exist "%BACKUP_PATH%\%BACKUP_NAME%.zip" (
        echo [OK] Backup compresso con PowerShell
        rmdir /s /q "%TEMP_BACKUP%" 2>nul
    ) else (
        echo [!] Backup salvato non compresso in: %TEMP_BACKUP%
    )
)

echo.
echo ====================================================
echo    4. GESTIONE BACKUP VECCHI
echo ====================================================
echo.

REM Count existing backups
set /a BACKUP_COUNT=0
for %%f in ("%BACKUP_PATH%\backup_*.zip") do set /a BACKUP_COUNT+=1
echo [*] Backup totali: %BACKUP_COUNT%

REM Keep only last 30 backups
if %BACKUP_COUNT% gtr 30 (
    echo [*] Rimozione backup vecchi (mantengo ultimi 30)...

    REM Delete backups older than 30 days
    forfiles /p "%BACKUP_PATH%" /s /m backup_*.zip /d -30 /c "cmd /c del @path" 2>nul

    echo [OK] Backup vecchi rimossi
)

REM Calculate total backup size
set /a TOTAL_SIZE=0
for %%f in ("%BACKUP_PATH%\*.zip") do set /a TOTAL_SIZE+=%%~z
set /a TOTAL_SIZE_MB=%TOTAL_SIZE%/1048576
echo [*] Spazio totale backup: %TOTAL_SIZE_MB% MB

echo.
echo ====================================================
echo    5. VERIFICA BACKUP
echo ====================================================
echo.

if exist "%BACKUP_PATH%\%BACKUP_NAME%.zip" (
    REM Test zip integrity
    if exist "%ZIP_PATH%" (
        "%ZIP_PATH%" t "%BACKUP_PATH%\%BACKUP_NAME%.zip" >nul 2>&1
        if %errorlevel% equ 0 (
            echo [OK] Integrita' backup verificata
        ) else (
            echo [ERRORE] Backup potrebbe essere corrotto!
        )
    ) else (
        echo [*] Verifica integrita' con PowerShell...
        powershell -Command "try { [System.IO.Compression.ZipFile]::OpenRead('%BACKUP_PATH%\%BACKUP_NAME%.zip').Dispose(); Write-Host '[OK] Integrita backup verificata' } catch { Write-Host '[ERRORE] Backup corrotto!' }" 2>nul
    )

    REM Create backup info file
    echo Nexiosolution Collabora Backup > "%BACKUP_PATH%\%BACKUP_NAME%.info"
    echo ========================= >> "%BACKUP_PATH%\%BACKUP_NAME%.info"
    echo Data: %date% %time% >> "%BACKUP_PATH%\%BACKUP_NAME%.info"
    echo Nome: %BACKUP_NAME% >> "%BACKUP_PATH%\%BACKUP_NAME%.info"
    echo Database: %DB_NAME% >> "%BACKUP_PATH%\%BACKUP_NAME%.info"
    echo File uploads: %FILE_COUNT% >> "%BACKUP_PATH%\%BACKUP_NAME%.info"
    echo Dimensione: %ZIP_SIZE% MB >> "%BACKUP_PATH%\%BACKUP_NAME%.info"

    echo [OK] File info creato: %BACKUP_NAME%.info
)

echo.
echo ====================================================
echo    BACKUP COMPLETATO CON SUCCESSO!
echo ====================================================
echo.
echo Percorso backup: %BACKUP_PATH%\%BACKUP_NAME%.zip
echo.
echo Per ripristinare questo backup:
echo 1. Estrai %BACKUP_NAME%.zip
echo 2. Importa database.sql in MySQL
echo 3. Copia i file nelle rispettive directory
echo.

REM Optional: Send email notification
REM You can add email notification here using PowerShell

REM Create restore script
echo @echo off > "%BACKUP_PATH%\restore_%BACKUP_NAME%.bat"
echo echo Ripristino backup %BACKUP_NAME%... >> "%BACKUP_PATH%\restore_%BACKUP_NAME%.bat"
echo echo. >> "%BACKUP_PATH%\restore_%BACKUP_NAME%.bat"
echo echo ATTENZIONE: Questo sovrascrivera' tutti i dati attuali! >> "%BACKUP_PATH%\restore_%BACKUP_NAME%.bat"
echo pause >> "%BACKUP_PATH%\restore_%BACKUP_NAME%.bat"
echo. >> "%BACKUP_PATH%\restore_%BACKUP_NAME%.bat"
echo REM Estrai backup >> "%BACKUP_PATH%\restore_%BACKUP_NAME%.bat"
echo "%ZIP_PATH%" x -y "%BACKUP_PATH%\%BACKUP_NAME%.zip" -o"%BACKUP_PATH%\restore_temp\" >> "%BACKUP_PATH%\restore_%BACKUP_NAME%.bat"
echo. >> "%BACKUP_PATH%\restore_%BACKUP_NAME%.bat"
echo REM Ripristina database >> "%BACKUP_PATH%\restore_%BACKUP_NAME%.bat"
echo "%MYSQL_PATH%\mysql.exe" -u root ^< "%BACKUP_PATH%\restore_temp\%BACKUP_NAME%\database.sql" >> "%BACKUP_PATH%\restore_%BACKUP_NAME%.bat"
echo. >> "%BACKUP_PATH%\restore_%BACKUP_NAME%.bat"
echo REM Ripristina file >> "%BACKUP_PATH%\restore_%BACKUP_NAME%.bat"
echo xcopy "%BACKUP_PATH%\restore_temp\%BACKUP_NAME%\uploads\*.*" "%PROJECT_PATH%\uploads\" /S /E /Y >> "%BACKUP_PATH%\restore_%BACKUP_NAME%.bat"
echo xcopy "%BACKUP_PATH%\restore_temp\%BACKUP_NAME%\config\*.*" "%PROJECT_PATH%\" /Y >> "%BACKUP_PATH%\restore_%BACKUP_NAME%.bat"
echo. >> "%BACKUP_PATH%\restore_%BACKUP_NAME%.bat"
echo REM Cleanup >> "%BACKUP_PATH%\restore_%BACKUP_NAME%.bat"
echo rmdir /s /q "%BACKUP_PATH%\restore_temp" >> "%BACKUP_PATH%\restore_%BACKUP_NAME%.bat"
echo. >> "%BACKUP_PATH%\restore_%BACKUP_NAME%.bat"
echo echo Ripristino completato! >> "%BACKUP_PATH%\restore_%BACKUP_NAME%.bat"
echo pause >> "%BACKUP_PATH%\restore_%BACKUP_NAME%.bat"

echo [OK] Script di ripristino creato: restore_%BACKUP_NAME%.bat
echo.

REM Log backup activity
echo %date% %time% - Backup completato: %BACKUP_NAME%.zip (%ZIP_SIZE% MB) >> "%PROJECT_PATH%\logs\backup.log"

REM Offer to open backup folder
echo Vuoi aprire la cartella backup? (S/N)
set /p OPEN_FOLDER=
if /i "%OPEN_FOLDER%"=="S" (
    explorer "%BACKUP_PATH%"
)

echo.
echo Premere un tasto per uscire...
pause >nul