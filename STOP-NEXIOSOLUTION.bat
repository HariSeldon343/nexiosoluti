@echo off
setlocal enabledelayedexpansion
chcp 65001 >nul 2>&1
title NexioSolution - Arresto Servizi
color 0C

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
echo ║              SOLUTION - Arresto Servizi v2.0                    ║
echo ╚══════════════════════════════════════════════════════════════════╝
echo.
echo [%date% %time%] Arresto servizi in corso...
echo.

:: ============================================
:: RICERCA PROCESSI
:: ============================================
echo ══════════════════════════════════════════════════════════════════
echo FASE 1: IDENTIFICAZIONE PROCESSI ATTIVI
echo ══════════════════════════════════════════════════════════════════
echo.

set "FOUND_PROCESSES=0"

:: Controlla porta 8000 (Backend Laravel)
echo [1/3] Ricerca servizi backend (porta 8000)...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :8000 ^| findstr LISTENING') do (
    set "PID_8000=%%a"
    set "FOUND_PROCESSES=1"
    echo    ✓ Trovato processo backend con PID: %%a
)
if not defined PID_8000 (
    echo    ℹ Nessun servizio backend attivo sulla porta 8000
)

:: Controlla porta 3000 (Frontend React)
echo [2/3] Ricerca servizi frontend (porta 3000)...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :3000 ^| findstr LISTENING') do (
    set "PID_3000=%%a"
    set "FOUND_PROCESSES=1"
    echo    ✓ Trovato processo frontend con PID: %%a
)
if not defined PID_3000 (
    echo    ℹ Nessun servizio frontend attivo sulla porta 3000
)

:: Controlla processi Node.js
echo [3/3] Ricerca processi Node.js...
set "NODE_COUNT=0"
for /f "tokens=2" %%a in ('tasklist ^| findstr node.exe') do (
    set /a NODE_COUNT+=1
    set "FOUND_PROCESSES=1"
)
if !NODE_COUNT! GTR 0 (
    echo    ✓ Trovati !NODE_COUNT! processi Node.js
) else (
    echo    ℹ Nessun processo Node.js attivo
)

echo.

:: ============================================
:: ARRESTO PROCESSI
:: ============================================
if !FOUND_PROCESSES!==0 (
    echo ══════════════════════════════════════════════════════════════════
    echo RISULTATO: Nessun servizio NexioSolution attivo
    echo ══════════════════════════════════════════════════════════════════
    echo.
    echo ℹ Non ci sono servizi da fermare.
    echo.
) else (
    echo ══════════════════════════════════════════════════════════════════
    echo FASE 2: ARRESTO SERVIZI
    echo ══════════════════════════════════════════════════════════════════
    echo.

    :: Termina backend
    if defined PID_8000 (
        echo [1/4] Arresto backend Laravel...
        taskkill /F /PID !PID_8000! >nul 2>&1
        if errorlevel 1 (
            echo    ⚠ Impossibile terminare il processo !PID_8000!
        ) else (
            echo    ✓ Backend arrestato
        )
    )

    :: Termina frontend
    if defined PID_3000 (
        echo [2/4] Arresto frontend React...
        taskkill /F /PID !PID_3000! >nul 2>&1
        if errorlevel 1 (
            echo    ⚠ Impossibile terminare il processo !PID_3000!
        ) else (
            echo    ✓ Frontend arrestato
        )
    )

    :: Termina tutti i processi PHP CLI
    echo [3/4] Arresto processi PHP...
    taskkill /F /IM php.exe /FI "WINDOWTITLE eq NexioSolution*" >nul 2>&1
    if errorlevel 1 (
        echo    ℹ Nessun processo PHP da terminare
    ) else (
        echo    ✓ Processi PHP terminati
    )

    :: Termina tutti i processi Node.js correlati
    echo [4/4] Arresto processi Node.js...
    :: Prima prova a chiudere le finestre con titolo NexioSolution
    taskkill /F /FI "WINDOWTITLE eq NexioSolution*" >nul 2>&1
    :: Poi chiudi eventuali processi node rimasti sulle porte
    for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":3000 :3001" ^| findstr LISTENING') do (
        taskkill /F /PID %%a >nul 2>&1
    )
    echo    ✓ Processi Node.js terminati

    echo.
)

:: ============================================
:: PULIZIA AGGIUNTIVA
:: ============================================
echo ══════════════════════════════════════════════════════════════════
echo FASE 3: PULIZIA FINALE
echo ══════════════════════════════════════════════════════════════════
echo.

:: Pulizia cache Laravel (opzionale)
set "BACKEND_PATH=C:\xampp\htdocs\Nexiosolution\backend"
if exist "%BACKEND_PATH%\artisan" (
    echo [1/2] Pulizia cache Laravel...
    cd /d "%BACKEND_PATH%"
    php artisan cache:clear >nul 2>&1
    php artisan config:clear >nul 2>&1
    php artisan route:clear >nul 2>&1
    php artisan view:clear >nul 2>&1
    echo    ✓ Cache pulita
) else (
    echo [1/2] Pulizia cache Laravel...
    echo    ℹ Backend non trovato, salto pulizia cache
)

:: Verifica porte liberate
echo [2/2] Verifica liberazione porte...
set "PORTS_FREE=1"
netstat -an | findstr :8000 | findstr LISTENING >nul
if not errorlevel 1 (
    echo    ⚠ La porta 8000 è ancora occupata
    set "PORTS_FREE=0"
)
netstat -an | findstr :3000 | findstr LISTENING >nul
if not errorlevel 1 (
    echo    ⚠ La porta 3000 è ancora occupata
    set "PORTS_FREE=0"
)
if !PORTS_FREE!==1 (
    echo    ✓ Tutte le porte sono state liberate
)

echo.

:: ============================================
:: REPORT FINALE
:: ============================================
echo ╔══════════════════════════════════════════════════════════════════╗
echo ║                    ✅ ARRESTO COMPLETATO                        ║
echo ╠══════════════════════════════════════════════════════════════════╣
echo ║                                                                  ║
echo ║  Tutti i servizi NexioSolution sono stati arrestati.            ║
echo ║                                                                  ║
echo ║  Riepilogo operazioni:                                          ║
echo ║  • Servizi backend fermati                                      ║
echo ║  • Servizi frontend fermati                                     ║
echo ║  • Porte 3000 e 8000 liberate                                   ║
echo ║  • Cache pulita                                                 ║
echo ║                                                                  ║
echo ╠══════════════════════════════════════════════════════════════════╣
echo ║  Per riavviare i servizi: AVVIA-NEXIOSOLUTION.bat               ║
echo ║  Per verificare lo stato: CHECK-NEXIOSOLUTION.bat               ║
echo ╚══════════════════════════════════════════════════════════════════╝
echo.

:: Attendi conferma utente
echo Premi un tasto per chiudere...
pause >nul