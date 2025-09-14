@echo off
setlocal enabledelayedexpansion
chcp 65001 >nul 2>&1
title NexioSolution - Verifica Stato Sistema
color 0B

:: Configurazione percorsi
set "XAMPP_PATH=C:\xampp"
set "PROJECT_PATH=C:\xampp\htdocs\Nexiosolution"
set "BACKEND_PATH=%PROJECT_PATH%\backend"
set "FRONTEND_PATH=%PROJECT_PATH%\frontend"
set "PHP_PATH=%XAMPP_PATH%\php\php.exe"
set "MYSQL_PATH=%XAMPP_PATH%\mysql\bin\mysql.exe"

:: Variabili per il report
set "TOTAL_CHECKS=0"
set "PASSED_CHECKS=0"
set "WARNINGS=0"
set "ERRORS=0"

:: ============================================
:: BANNER
:: ============================================
cls
echo.
echo ╔══════════════════════════════════════════════════════════════════╗
echo ║                                                                  ║
echo ║     ███╗   ██╗███████╗██╗  ██╗██╗ ██████╗                      ║
echo ║     ████╗  ██║██╔════╝╚██╗██╔╝██║██╔═══██╗                     ║
echo ║     ██╔██╗ ██║█████╗   ╚███╔╝ ██║██║   ██║                     ║
echo ║     ██║╚██╗██║██╔══╝   ██╔██╗ ██║██║   ██║                     ║
echo ║     ██║ ╚████║███████╗██╔╝ ██╗██║╚██████╔╝                     ║
echo ║     ╚═╝  ╚═══╝╚══════╝╚═╝  ╚═╝╚═╝ ╚═════╝                      ║
echo ║                                                                  ║
echo ║            SOLUTION - Diagnostica Sistema v2.0                  ║
echo ╚══════════════════════════════════════════════════════════════════╝
echo.
echo [%date% %time%] Inizio diagnostica completa...
echo.

:: ============================================
:: VERIFICA COMPONENTI DI BASE
:: ============================================
echo ══════════════════════════════════════════════════════════════════
echo 1. VERIFICA COMPONENTI DI BASE
echo ══════════════════════════════════════════════════════════════════
echo.

:: Verifica XAMPP
set /a TOTAL_CHECKS+=1
echo [✓] Verifica installazione XAMPP...
if exist "%XAMPP_PATH%" (
    echo    ✅ XAMPP installato in: %XAMPP_PATH%
    set /a PASSED_CHECKS+=1

    :: Verifica versione Apache
    if exist "%XAMPP_PATH%\apache\bin\httpd.exe" (
        for /f "tokens=3" %%v in ('"%XAMPP_PATH%\apache\bin\httpd.exe" -v ^| findstr /i "Apache"') do (
            echo    └─ Apache versione: %%v
        )
    )
) else (
    echo    ❌ XAMPP non trovato in %XAMPP_PATH%
    set /a ERRORS+=1
)

:: Verifica PHP
set /a TOTAL_CHECKS+=1
echo.
echo [✓] Verifica PHP...
"%PHP_PATH%" -v >nul 2>&1
if not errorlevel 1 (
    for /f "tokens=2" %%i in ('"%PHP_PATH%" -v ^| findstr /i "^PHP"') do set PHP_VERSION=%%i
    echo    ✅ PHP !PHP_VERSION! disponibile
    set /a PASSED_CHECKS+=1

    :: Verifica estensioni PHP critiche
    echo    └─ Estensioni PHP:
    "%PHP_PATH%" -m 2>nul | findstr /i "pdo_mysql" >nul && echo       • pdo_mysql: ✓ || echo       • pdo_mysql: ✗
    "%PHP_PATH%" -m 2>nul | findstr /i "mbstring" >nul && echo       • mbstring: ✓ || echo       • mbstring: ✗
    "%PHP_PATH%" -m 2>nul | findstr /i "openssl" >nul && echo       • openssl: ✓ || echo       • openssl: ✗
    "%PHP_PATH%" -m 2>nul | findstr /i "tokenizer" >nul && echo       • tokenizer: ✓ || echo       • tokenizer: ✗
    "%PHP_PATH%" -m 2>nul | findstr /i "json" >nul && echo       • json: ✓ || echo       • json: ✗
) else (
    echo    ❌ PHP non disponibile
    set /a ERRORS+=1
)

:: Verifica MySQL
set /a TOTAL_CHECKS+=1
echo.
echo [✓] Verifica MySQL...
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if not errorlevel 1 (
    echo    ✅ MySQL in esecuzione
    set /a PASSED_CHECKS+=1

    :: Test connessione database
    echo SHOW DATABASES; | "%MYSQL_PATH%" -u root 2>nul | findstr nexiosolution >nul
    if not errorlevel 1 (
        echo    └─ Database 'nexiosolution' presente

        :: Conta tabelle
        for /f %%c in ('echo USE nexiosolution; SHOW TABLES; ^| "%MYSQL_PATH%" -u root 2^>nul ^| find /c /v ""') do (
            set /a TABLE_COUNT=%%c-1
        )
        if !TABLE_COUNT! GTR 0 (
            echo    └─ Tabelle nel database: !TABLE_COUNT!
        )
    ) else (
        echo    ⚠ Database 'nexiosolution' non trovato
        set /a WARNINGS+=1
    )
) else (
    echo    ❌ MySQL non in esecuzione
    set /a ERRORS+=1
)

:: Verifica Node.js
set /a TOTAL_CHECKS+=1
echo.
echo [✓] Verifica Node.js...
node -v >nul 2>&1
if not errorlevel 1 (
    for /f "tokens=1" %%i in ('node -v') do set NODE_VERSION=%%i
    echo    ✅ Node.js !NODE_VERSION! installato
    set /a PASSED_CHECKS+=1

    :: Verifica npm
    for /f "tokens=1" %%i in ('npm -v 2^>nul') do set NPM_VERSION=%%i
    if defined NPM_VERSION (
        echo    └─ npm !NPM_VERSION! disponibile
    )
) else (
    echo    ❌ Node.js non installato
    set /a ERRORS+=1
)

:: Verifica Composer
set /a TOTAL_CHECKS+=1
echo.
echo [✓] Verifica Composer...
composer -V >nul 2>&1
if not errorlevel 1 (
    for /f "tokens=3" %%i in ('composer -V 2^>nul ^| findstr /i "Composer"') do set COMPOSER_VERSION=%%i
    echo    ✅ Composer !COMPOSER_VERSION! installato
    set /a PASSED_CHECKS+=1
) else (
    if exist "%XAMPP_PATH%\php\composer.phar" (
        echo    ✅ Composer disponibile come composer.phar
        set /a PASSED_CHECKS+=1
    ) else (
        echo    ⚠ Composer non trovato
        set /a WARNINGS+=1
    )
)

echo.

:: ============================================
:: VERIFICA STRUTTURA PROGETTO
:: ============================================
echo ══════════════════════════════════════════════════════════════════
echo 2. VERIFICA STRUTTURA PROGETTO
echo ══════════════════════════════════════════════════════════════════
echo.

:: Verifica directory backend
set /a TOTAL_CHECKS+=1
echo [✓] Verifica backend Laravel...
if exist "%BACKEND_PATH%" (
    echo    ✅ Directory backend presente
    set /a PASSED_CHECKS+=1

    :: Verifica file critici
    echo    └─ File critici:
    if exist "%BACKEND_PATH%\artisan" (
        echo       • artisan: ✓
    ) else (
        echo       • artisan: ✗ MANCANTE
        set /a WARNINGS+=1
    )

    if exist "%BACKEND_PATH%\composer.json" (
        echo       • composer.json: ✓
    ) else (
        echo       • composer.json: ✗ MANCANTE
        set /a ERRORS+=1
    )

    if exist "%BACKEND_PATH%\.env" (
        echo       • .env: ✓
    ) else (
        echo       • .env: ✗ MANCANTE
        set /a WARNINGS+=1
    )

    if exist "%BACKEND_PATH%\vendor" (
        echo       • vendor/: ✓
    ) else (
        echo       • vendor/: ✗ MANCANTE - Esegui composer install
        set /a WARNINGS+=1
    )
) else (
    echo    ❌ Directory backend non trovata
    set /a ERRORS+=1
)

:: Verifica directory frontend
set /a TOTAL_CHECKS+=1
echo.
echo [✓] Verifica frontend React...
if exist "%FRONTEND_PATH%" (
    echo    ✅ Directory frontend presente
    set /a PASSED_CHECKS+=1

    :: Verifica file critici
    echo    └─ File critici:
    if exist "%FRONTEND_PATH%\package.json" (
        echo       • package.json: ✓
    ) else (
        echo       • package.json: ✗ MANCANTE
        set /a ERRORS+=1
    )

    if exist "%FRONTEND_PATH%\.env" (
        echo       • .env: ✓
    ) else (
        echo       • .env: ✗ MANCANTE
        set /a WARNINGS+=1
    )

    if exist "%FRONTEND_PATH%\node_modules" (
        echo       • node_modules/: ✓
    ) else (
        echo       • node_modules/: ✗ MANCANTE - Esegui npm install
        set /a WARNINGS+=1
    )

    if exist "%FRONTEND_PATH%\src" (
        echo       • src/: ✓
    ) else (
        echo       • src/: ✗ MANCANTE
        set /a ERRORS+=1
    )
) else (
    echo    ❌ Directory frontend non trovata
    set /a ERRORS+=1
)

echo.

:: ============================================
:: VERIFICA SERVIZI ATTIVI
:: ============================================
echo ══════════════════════════════════════════════════════════════════
echo 3. VERIFICA SERVIZI ATTIVI
echo ══════════════════════════════════════════════════════════════════
echo.

:: Verifica backend su porta 8000
set /a TOTAL_CHECKS+=1
echo [✓] Verifica servizio backend (porta 8000)...
netstat -an | findstr :8000 | findstr LISTENING >nul
if not errorlevel 1 (
    echo    ✅ Backend in esecuzione su porta 8000
    set /a PASSED_CHECKS+=1

    :: Test API endpoint
    powershell -Command "(Invoke-WebRequest -Uri 'http://localhost:8000/api/health' -UseBasicParsing -TimeoutSec 2).StatusCode" >nul 2>&1
    if not errorlevel 1 (
        echo    └─ API risponde correttamente
    ) else (
        echo    └─ API non raggiungibile (potrebbe essere normale)
    )
) else (
    echo    ⚠ Backend non attivo sulla porta 8000
    set /a WARNINGS+=1
)

:: Verifica frontend su porta 3000
set /a TOTAL_CHECKS+=1
echo.
echo [✓] Verifica servizio frontend (porta 3000)...
netstat -an | findstr :3000 | findstr LISTENING >nul
if not errorlevel 1 (
    echo    ✅ Frontend in esecuzione su porta 3000
    set /a PASSED_CHECKS+=1

    :: Test HTTP response
    powershell -Command "(Invoke-WebRequest -Uri 'http://localhost:3000' -UseBasicParsing -TimeoutSec 2).StatusCode" >nul 2>&1
    if not errorlevel 1 (
        echo    └─ Interfaccia web raggiungibile
    ) else (
        echo    └─ Interfaccia web non risponde
        set /a WARNINGS+=1
    )
) else (
    echo    ⚠ Frontend non attivo sulla porta 3000
    set /a WARNINGS+=1
)

:: Verifica Apache
set /a TOTAL_CHECKS+=1
echo.
echo [✓] Verifica Apache (porta 80)...
netstat -an | findstr :80 | findstr LISTENING >nul
if not errorlevel 1 (
    echo    ✅ Apache in esecuzione su porta 80
    set /a PASSED_CHECKS+=1
) else (
    echo    ⚠ Apache non attivo sulla porta 80
    set /a WARNINGS+=1
)

echo.

:: ============================================
:: INFORMAZIONI DI SISTEMA
:: ============================================
echo ══════════════════════════════════════════════════════════════════
echo 4. INFORMAZIONI DI SISTEMA
echo ══════════════════════════════════════════════════════════════════
echo.

:: Memoria disponibile
for /f "tokens=2 delims==" %%i in ('wmic OS get TotalVisibleMemorySize /value') do set /a TOTAL_MEM=%%i/1024
for /f "tokens=2 delims==" %%i in ('wmic OS get FreePhysicalMemory /value') do set /a FREE_MEM=%%i/1024
echo [ℹ] Memoria RAM: !FREE_MEM! MB liberi su !TOTAL_MEM! MB totali

:: Spazio disco
for /f "tokens=3" %%i in ('dir C:\ ^| findstr "byte"') do set FREE_SPACE=%%i
echo [ℹ] Spazio disco C:\: !FREE_SPACE! disponibili

:: Processi Node attivi
set "NODE_COUNT=0"
for /f %%i in ('tasklist ^| findstr node.exe ^| find /c /v ""') do set NODE_COUNT=%%i
echo [ℹ] Processi Node.js attivi: !NODE_COUNT!

:: Processi PHP attivi
set "PHP_COUNT=0"
for /f %%i in ('tasklist ^| findstr php.exe ^| find /c /v ""') do set PHP_COUNT=%%i
echo [ℹ] Processi PHP attivi: !PHP_COUNT!

echo.

:: ============================================
:: RACCOMANDAZIONI
:: ============================================
if !WARNINGS! GTR 0 (
    echo ══════════════════════════════════════════════════════════════════
    echo 5. RACCOMANDAZIONI
    echo ══════════════════════════════════════════════════════════════════
    echo.

    if not exist "%BACKEND_PATH%\vendor" (
        echo • Esegui 'composer install' nella directory backend
    )
    if not exist "%FRONTEND_PATH%\node_modules" (
        echo • Esegui 'npm install' nella directory frontend
    )
    if not exist "%BACKEND_PATH%\.env" (
        echo • Crea il file .env nel backend copiando .env.example
    )
    if not exist "%FRONTEND_PATH%\.env" (
        echo • Crea il file .env nel frontend copiando .env.example
    )

    netstat -an | findstr :8000 | findstr LISTENING >nul
    if errorlevel 1 (
        echo • Avvia il backend con AVVIA-NEXIOSOLUTION.bat
    )

    netstat -an | findstr :3000 | findstr LISTENING >nul
    if errorlevel 1 (
        echo • Avvia il frontend con AVVIA-NEXIOSOLUTION.bat
    )

    echo.
)

:: ============================================
:: REPORT FINALE
:: ============================================
echo ╔══════════════════════════════════════════════════════════════════╗
echo ║                     REPORT DIAGNOSTICO                          ║
echo ╠══════════════════════════════════════════════════════════════════╣
echo ║                                                                  ║

:: Calcola percentuale successo
set /a SUCCESS_PERCENT=PASSED_CHECKS*100/TOTAL_CHECKS

if !SUCCESS_PERCENT! GEQ 90 (
    echo ║  Stato Sistema: ✅ ECCELLENTE (!SUCCESS_PERCENT!%%)                          ║
    echo ║                                                                  ║
    echo ║  Il sistema è completamente operativo.                          ║
) else if !SUCCESS_PERCENT! GEQ 70 (
    echo ║  Stato Sistema: ⚠ BUONO (!SUCCESS_PERCENT!%%)                               ║
    echo ║                                                                  ║
    echo ║  Il sistema funziona con alcuni avvisi minori.                  ║
) else if !SUCCESS_PERCENT! GEQ 50 (
    echo ║  Stato Sistema: ⚠ ATTENZIONE (!SUCCESS_PERCENT!%%)                          ║
    echo ║                                                                  ║
    echo ║  Alcuni componenti richiedono attenzione.                       ║
) else (
    echo ║  Stato Sistema: ❌ CRITICO (!SUCCESS_PERCENT!%%)                             ║
    echo ║                                                                  ║
    echo ║  Il sistema richiede intervento immediato.                      ║
)

echo ║                                                                  ║
echo ║  Riepilogo controlli:                                           ║
echo ║  • Test superati: !PASSED_CHECKS!/!TOTAL_CHECKS!                                             ║
echo ║  • Avvisi: !WARNINGS!                                                       ║
echo ║  • Errori: !ERRORS!                                                       ║
echo ║                                                                  ║
echo ╠══════════════════════════════════════════════════════════════════╣
echo ║  Comandi disponibili:                                           ║
echo ║  • AVVIA-NEXIOSOLUTION.bat  - Avvia tutti i servizi             ║
echo ║  • STOP-NEXIOSOLUTION.bat   - Ferma tutti i servizi             ║
echo ║  • CHECK-NEXIOSOLUTION.bat  - Questo report (aggiornato)        ║
echo ╚══════════════════════════════════════════════════════════════════╝
echo.

:: Salva report su file
echo Generazione report dettagliato...
(
    echo NEXIOSOLUTION - REPORT DIAGNOSTICO
    echo ===================================
    echo Data: %date% %time%
    echo.
    echo COMPONENTI:
    echo - XAMPP: %XAMPP_PATH%
    echo - PHP: !PHP_VERSION!
    echo - Node.js: !NODE_VERSION!
    echo - npm: !NPM_VERSION!
    echo - Composer: !COMPOSER_VERSION!
    echo.
    echo STATO SERVIZI:
    netstat -an | findstr :8000 | findstr LISTENING >nul
    if not errorlevel 1 (
        echo - Backend: ATTIVO su porta 8000
    ) else (
        echo - Backend: NON ATTIVO
    )
    netstat -an | findstr :3000 | findstr LISTENING >nul
    if not errorlevel 1 (
        echo - Frontend: ATTIVO su porta 3000
    ) else (
        echo - Frontend: NON ATTIVO
    )
    echo.
    echo RISULTATI TEST:
    echo - Superati: !PASSED_CHECKS!/!TOTAL_CHECKS!
    echo - Avvisi: !WARNINGS!
    echo - Errori: !ERRORS!
    echo - Percentuale successo: !SUCCESS_PERCENT!%%
) > "%PROJECT_PATH%\diagnostic_report_%date:~-4%%date:~3,2%%date:~0,2%.txt"

echo Report salvato in: diagnostic_report_%date:~-4%%date:~3,2%%date:~0,2%.txt
echo.

:: Attendi conferma utente
echo Premi un tasto per chiudere...
pause >nul