@echo off
REM ====================================================
REM NexioSolution - Arresto Servizi
REM ====================================================

echo ====================================================
echo   NexioSolution - Arresto Servizi
echo ====================================================
echo.

set "GREEN=[92m"
set "RED=[91m"
set "YELLOW=[93m"
set "RESET=[0m"

echo %YELLOW%Arresto di tutti i servizi NexioSolution...%RESET%
echo.

REM Chiudi tutti i processi PHP (Laravel server, queue worker, websocket)
echo Arresto processi PHP...
taskkill /F /IM php.exe /T 2>nul
if not errorlevel 1 (
    echo %GREEN%√ Processi PHP terminati%RESET%
) else (
    echo %YELLOW%Nessun processo PHP attivo%RESET%
)

REM Chiudi Node.js (React dev server)
echo Arresto processi Node.js...
taskkill /F /IM node.exe /T 2>nul
if not errorlevel 1 (
    echo %GREEN%√ Processi Node.js terminati%RESET%
) else (
    echo %YELLOW%Nessun processo Node.js attivo%RESET%
)

REM Chiudi Laravel Echo Server se presente
taskkill /F /IM laravel-echo-server.exe /T 2>nul

echo.
echo %GREEN%=====================================================%RESET%
echo %GREEN% Tutti i servizi NexioSolution sono stati arrestati%RESET%
echo %GREEN%=====================================================%RESET%
echo.
echo NOTA: Apache e MySQL di XAMPP rimangono attivi.
echo Per fermarli usa XAMPP Control Panel.
echo.
pause