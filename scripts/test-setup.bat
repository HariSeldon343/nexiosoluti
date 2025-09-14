@echo off
REM ====================================================
REM NexioSolution - Test Configurazione Sistema
REM ====================================================
setlocal enabledelayedexpansion

echo ====================================================
echo   NexioSolution - Test Configurazione
echo ====================================================
echo.

REM Imposta variabili
set "PROJECT_DIR=C:\xampp\htdocs\Nexiosolution"
set "ERRORS=0"
set "WARNINGS=0"

REM Colori per output
set "GREEN=[92m"
set "RED=[91m"
set "YELLOW=[93m"
set "BLUE=[94m"
set "RESET=[0m"

echo %BLUE%=== TEST PREREQUISITI DI SISTEMA ===%RESET%
echo.

REM Test PHP
echo %YELLOW%[TEST 1] PHP%RESET%
echo ----------------------------------------
php -v >nul 2>&1
if errorlevel 1 (
    echo %RED%✗ PHP non trovato nel PATH%RESET%
    echo   Soluzione: Aggiungi C:\xampp\php al PATH di sistema
    set /a ERRORS+=1
) else (
    for /f "tokens=2" %%i in ('php -v ^| findstr /i "PHP"') do set PHP_VERSION=%%i
    echo %GREEN%√ PHP trovato: !PHP_VERSION!%RESET%

    REM Verifica versione minima PHP 8.0
    for /f "tokens=1,2 delims=." %%a in ("!PHP_VERSION!") do (
        if %%a LSS 8 (
            echo %YELLOW%  ⚠ Versione PHP troppo vecchia. Richiesto: 8.0+%RESET%
            set /a WARNINGS+=1
        )
    )

    REM Test estensioni PHP richieste
    echo   Estensioni PHP:
    php -m | findstr /i "pdo_mysql" >nul
    if errorlevel 1 (
        echo %RED%  ✗ pdo_mysql non abilitato%RESET%
        set /a ERRORS+=1
    ) else (
        echo %GREEN%  √ pdo_mysql%RESET%
    )

    php -m | findstr /i "mbstring" >nul
    if errorlevel 1 (
        echo %RED%  ✗ mbstring non abilitato%RESET%
        set /a ERRORS+=1
    ) else (
        echo %GREEN%  √ mbstring%RESET%
    )

    php -m | findstr /i "openssl" >nul
    if errorlevel 1 (
        echo %RED%  ✗ openssl non abilitato%RESET%
        set /a ERRORS+=1
    ) else (
        echo %GREEN%  √ openssl%RESET%
    )

    php -m | findstr /i "tokenizer" >nul
    if errorlevel 1 (
        echo %RED%  ✗ tokenizer non abilitato%RESET%
        set /a ERRORS+=1
    ) else (
        echo %GREEN%  √ tokenizer%RESET%
    )

    php -m | findstr /i "xml" >nul
    if errorlevel 1 (
        echo %RED%  ✗ xml non abilitato%RESET%
        set /a ERRORS+=1
    ) else (
        echo %GREEN%  √ xml%RESET%
    )

    php -m | findstr /i "curl" >nul
    if errorlevel 1 (
        echo %YELLOW%  ⚠ curl non abilitato (consigliato)%RESET%
        set /a WARNINGS+=1
    ) else (
        echo %GREEN%  √ curl%RESET%
    )

    php -m | findstr /i "gd" >nul
    if errorlevel 1 (
        echo %YELLOW%  ⚠ gd non abilitato (per manipolazione immagini)%RESET%
        set /a WARNINGS+=1
    ) else (
        echo %GREEN%  √ gd%RESET%
    )
)
echo.

REM Test Node.js
echo %YELLOW%[TEST 2] Node.js%RESET%
echo ----------------------------------------
node -v >nul 2>&1
if errorlevel 1 (
    echo %RED%✗ Node.js non trovato%RESET%
    echo   Soluzione: Installa Node.js da https://nodejs.org
    set /a ERRORS+=1
) else (
    for /f %%i in ('node -v') do set NODE_VERSION=%%i
    echo %GREEN%√ Node.js trovato: !NODE_VERSION!%RESET%

    REM Verifica versione minima Node 14
    for /f "tokens=1,2 delims=v." %%a in ("!NODE_VERSION!") do (
        if %%a LSS 14 (
            echo %YELLOW%  ⚠ Versione Node.js troppo vecchia. Consigliato: 14+%RESET%
            set /a WARNINGS+=1
        )
    )
)
echo.

REM Test npm
echo %YELLOW%[TEST 3] npm%RESET%
echo ----------------------------------------
npm -v >nul 2>&1
if errorlevel 1 (
    echo %RED%✗ npm non trovato%RESET%
    set /a ERRORS+=1
) else (
    for /f %%i in ('npm -v') do set NPM_VERSION=%%i
    echo %GREEN%√ npm trovato: !NPM_VERSION!%RESET%
)
echo.

REM Test Composer
echo %YELLOW%[TEST 4] Composer%RESET%
echo ----------------------------------------
composer -V >nul 2>&1
if errorlevel 1 (
    echo %RED%✗ Composer non trovato%RESET%
    echo   Soluzione: Esegui setup-complete.bat per installarlo
    set /a ERRORS+=1
) else (
    echo %GREEN%√ Composer trovato%RESET%
    composer -V | findstr /i "version"
)
echo.

REM Test MySQL
echo %YELLOW%[TEST 5] MySQL%RESET%
echo ----------------------------------------
C:\xampp\mysql\bin\mysql --version >nul 2>&1
if errorlevel 1 (
    echo %RED%✗ MySQL non trovato%RESET%
    set /a ERRORS+=1
) else (
    echo %GREEN%√ MySQL installato%RESET%

    REM Verifica se MySQL è in esecuzione
    netstat -an | findstr :3306 | findstr LISTENING >nul
    if errorlevel 1 (
        echo %YELLOW%  ⚠ MySQL non in esecuzione%RESET%
        echo   Soluzione: Avvia MySQL dal XAMPP Control Panel
        set /a WARNINGS+=1
    ) else (
        echo %GREEN%  √ MySQL in esecuzione sulla porta 3306%RESET%

        REM Test connessione database
        C:\xampp\mysql\bin\mysql -u root -e "SELECT 1" >nul 2>&1
        if errorlevel 1 (
            echo %YELLOW%  ⚠ Impossibile connettersi a MySQL (potrebbe avere password)%RESET%
            set /a WARNINGS+=1
        ) else (
            echo %GREEN%  √ Connessione MySQL OK%RESET%

            REM Verifica database nexiosolution
            C:\xampp\mysql\bin\mysql -u root -e "USE nexiosolution" >nul 2>&1
            if errorlevel 1 (
                echo %YELLOW%  ⚠ Database 'nexiosolution' non trovato%RESET%
                echo   Soluzione: Esegui setup-complete.bat
                set /a WARNINGS+=1
            ) else (
                echo %GREEN%  √ Database 'nexiosolution' presente%RESET%
            )
        )
    )
)
echo.

REM Test Apache
echo %YELLOW%[TEST 6] Apache%RESET%
echo ----------------------------------------
if exist "C:\xampp\apache\bin\httpd.exe" (
    echo %GREEN%√ Apache installato%RESET%

    REM Verifica se Apache è in esecuzione
    netstat -an | findstr :80 | findstr LISTENING >nul
    if errorlevel 1 (
        echo %YELLOW%  ⚠ Apache non in esecuzione%RESET%
        echo   Soluzione: Avvia Apache dal XAMPP Control Panel
        set /a WARNINGS+=1
    ) else (
        echo %GREEN%  √ Apache in esecuzione sulla porta 80%RESET%
    )

    REM Verifica mod_rewrite
    C:\xampp\apache\bin\httpd.exe -M 2>nul | findstr /i "rewrite_module" >nul
    if errorlevel 1 (
        echo %YELLOW%  ⚠ mod_rewrite potrebbe non essere abilitato%RESET%
        set /a WARNINGS+=1
    ) else (
        echo %GREEN%  √ mod_rewrite abilitato%RESET%
    )
) else (
    echo %RED%✗ Apache non trovato%RESET%
    set /a ERRORS+=1
)
echo.

REM Test Porte
echo %YELLOW%[TEST 7] Porte di Rete%RESET%
echo ----------------------------------------
set "PORTS_OK=1"

REM Porta 3000 (React)
netstat -an | findstr :3000 | findstr LISTENING >nul
if not errorlevel 1 (
    echo %YELLOW%  ⚠ Porta 3000 già in uso (React Dev Server)%RESET%
    set /a WARNINGS+=1
) else (
    echo %GREEN%  √ Porta 3000 disponibile (React)%RESET%
)

REM Porta 8000 (Laravel)
netstat -an | findstr :8000 | findstr LISTENING >nul
if not errorlevel 1 (
    echo %YELLOW%  ⚠ Porta 8000 già in uso (Laravel Server)%RESET%
    set /a WARNINGS+=1
) else (
    echo %GREEN%  √ Porta 8000 disponibile (Laravel)%RESET%
)

REM Porta 6001 (WebSocket)
netstat -an | findstr :6001 | findstr LISTENING >nul
if not errorlevel 1 (
    echo %YELLOW%  ⚠ Porta 6001 già in uso (WebSocket)%RESET%
    set /a WARNINGS+=1
) else (
    echo %GREEN%  √ Porta 6001 disponibile (WebSocket)%RESET%
)
echo.

REM Test Struttura Progetto
echo %YELLOW%[TEST 8] Struttura Progetto%RESET%
echo ----------------------------------------
if exist "%PROJECT_DIR%" (
    echo %GREEN%√ Directory progetto trovata%RESET%

    if exist "%PROJECT_DIR%\backend" (
        echo %GREEN%  √ Directory backend presente%RESET%

        if exist "%PROJECT_DIR%\backend\vendor" (
            echo %GREEN%    √ Dipendenze PHP installate%RESET%
        ) else (
            echo %YELLOW%    ⚠ Dipendenze PHP non installate%RESET%
            echo      Soluzione: cd backend && composer install
            set /a WARNINGS+=1
        )

        if exist "%PROJECT_DIR%\backend\.env" (
            echo %GREEN%    √ File .env backend presente%RESET%
        ) else (
            echo %YELLOW%    ⚠ File .env backend mancante%RESET%
            echo      Soluzione: Copia .env.example in .env
            set /a WARNINGS+=1
        )
    ) else (
        echo %RED%  ✗ Directory backend mancante%RESET%
        set /a ERRORS+=1
    )

    if exist "%PROJECT_DIR%\frontend" (
        echo %GREEN%  √ Directory frontend presente%RESET%

        if exist "%PROJECT_DIR%\frontend\node_modules" (
            echo %GREEN%    √ Dipendenze Node installate%RESET%
        ) else (
            echo %YELLOW%    ⚠ Dipendenze Node non installate%RESET%
            echo      Soluzione: cd frontend && npm install
            set /a WARNINGS+=1
        )

        if exist "%PROJECT_DIR%\frontend\.env" (
            echo %GREEN%    √ File .env frontend presente%RESET%
        ) else (
            echo %YELLOW%    ⚠ File .env frontend mancante%RESET%
            set /a WARNINGS+=1
        )
    ) else (
        echo %RED%  ✗ Directory frontend mancante%RESET%
        set /a ERRORS+=1
    )
) else (
    echo %RED%✗ Directory progetto non trovata: %PROJECT_DIR%%RESET%
    set /a ERRORS+=1
)
echo.

REM Test Laravel
echo %YELLOW%[TEST 9] Laravel Framework%RESET%
echo ----------------------------------------
if exist "%PROJECT_DIR%\backend\artisan" (
    cd /d "%PROJECT_DIR%\backend"
    php artisan --version >nul 2>&1
    if errorlevel 1 (
        echo %RED%✗ Laravel non funzionante%RESET%
        set /a ERRORS+=1
    ) else (
        for /f "tokens=*" %%i in ('php artisan --version') do set LARAVEL_VERSION=%%i
        echo %GREEN%√ !LARAVEL_VERSION!%RESET%

        REM Test database connection
        php artisan tinker --execute="DB::connection()->getPdo();" >nul 2>&1
        if errorlevel 1 (
            echo %YELLOW%  ⚠ Connessione database Laravel fallita%RESET%
            echo   Verifica le impostazioni in backend\.env
            set /a WARNINGS+=1
        ) else (
            echo %GREEN%  √ Connessione database OK%RESET%
        )
    )
) else (
    echo %RED%✗ Laravel non installato%RESET%
    set /a ERRORS+=1
)
echo.

REM Riepilogo
echo %BLUE%=====================================================%RESET%
echo %BLUE%               RIEPILOGO TEST%RESET%
echo %BLUE%=====================================================%RESET%
echo.

if !ERRORS! EQU 0 (
    if !WARNINGS! EQU 0 (
        echo %GREEN%RISULTATO: TUTTO OK! ✓%RESET%
        echo.
        echo Il sistema è configurato correttamente.
        echo Puoi avviare l'applicazione con: start-services.bat
    ) else (
        echo %YELLOW%RISULTATO: OK CON AVVERTIMENTI%RESET%
        echo.
        echo Errori critici: 0
        echo Avvertimenti: !WARNINGS!
        echo.
        echo Il sistema può funzionare ma controlla gli avvertimenti sopra.
    )
) else (
    echo %RED%RISULTATO: ERRORI TROVATI%RESET%
    echo.
    echo Errori critici: !ERRORS!
    echo Avvertimenti: !WARNINGS!
    echo.
    echo Risolvi gli errori critici prima di procedere.
    echo Esegui: setup-complete.bat per configurare il sistema.
)

echo.
echo %BLUE%=====================================================%RESET%
echo.
pause