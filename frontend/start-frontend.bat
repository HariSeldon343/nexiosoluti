@echo off
cls
color 0A
echo ===============================================
echo     NEXIOSOLUTION FRONTEND - AUTO START
echo ===============================================
echo.

cd /d C:\xampp\htdocs\Nexiosolution\frontend

:: Verifica Node.js
echo Controllo Node.js...
node --version >nul 2>&1
if %errorlevel% neq 0 (
    color 0C
    echo.
    echo ##############################################
    echo  ERRORE: Node.js non installato!
    echo ##############################################
    echo.
    echo  Devi installare Node.js per procedere.
    echo.
    echo  1. Vai su: https://nodejs.org/
    echo  2. Scarica la versione LTS
    echo  3. Installa Node.js
    echo  4. Riavvia questo script
    echo.
    echo ##############################################
    pause
    exit /b 1
)

echo Node.js OK:
node --version
npm --version
echo.

:: Controlla se node_modules esiste
if not exist node_modules (
    echo ===============================================
    echo  PRIMA INSTALLAZIONE RILEVATA
    echo ===============================================
    echo.
    echo Installazione dipendenze in corso...
    echo Questo richiederà 2-5 minuti...
    echo.

    :: Pulizia preventiva
    if exist package-lock.json del package-lock.json

    :: Prova installazione
    npm install --legacy-peer-deps

    if %errorlevel% neq 0 (
        echo.
        echo Errore durante l'installazione!
        echo Provo metodo alternativo...
        npm cache clean --force
        npm install --force
    )

    echo.
    echo ===============================================
    echo  INSTALLAZIONE COMPLETATA!
    echo ===============================================
    echo.
)

:: Controlla porta 3000
netstat -an | findstr :3000 >nul 2>&1
if %errorlevel% equ 0 (
    echo.
    echo ATTENZIONE: La porta 3000 è già in uso!
    echo.
    echo Opzioni:
    echo 1. Chiudi l'altra applicazione che usa la porta 3000
    echo 2. Oppure premi INVIO per continuare comunque
    echo.
    pause
)

:: Avvio server
cls
echo ===============================================
echo     NEXIOSOLUTION FRONTEND
echo ===============================================
echo.
echo  Avvio server di sviluppo...
echo.
echo  Il browser si aprirà automaticamente su:
echo  http://localhost:3000
echo.
echo  Se non si apre, apri manualmente il link sopra.
echo.
echo ===============================================
echo.
echo  Comandi utili:
echo  - Ctrl+C per fermare il server
echo  - 'r' + INVIO per riavviare
echo  - 'q' + INVIO per uscire
echo.
echo ===============================================
echo.

:: Avvia Vite
npm run dev

:: Se arriviamo qui, il server è stato fermato
echo.
echo Server fermato.
echo.
pause
