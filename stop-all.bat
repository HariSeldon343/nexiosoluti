@echo off
REM ========================================
REM NexioSolution - Arresto Servizi
REM ========================================

echo.
echo =====================================
echo   NEXIOSOLUTION - ARRESTO SERVIZI
echo =====================================
echo.

echo [1/5] Arresto Laravel Server...
taskkill /FI "WINDOWTITLE eq NexioSolution - Laravel Server*" /F >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] Laravel Server arrestato
) else (
    echo [!] Laravel Server non trovato o già arrestato
)

echo [2/5] Arresto WebSocket Server...
taskkill /FI "WINDOWTITLE eq NexioSolution - WebSocket*" /F >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] WebSocket Server arrestato
) else (
    echo [!] WebSocket Server non trovato o già arrestato
)

echo [3/5] Arresto Queue Worker...
taskkill /FI "WINDOWTITLE eq NexioSolution - Queue Worker*" /F >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] Queue Worker arrestato
) else (
    echo [!] Queue Worker non trovato o già arrestato
)

echo [4/5] Arresto Scheduler...
taskkill /FI "WINDOWTITLE eq NexioSolution - Scheduler*" /F >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] Scheduler arrestato
) else (
    echo [!] Scheduler non trovato o già arrestato
)

echo [5/5] Arresto React Dev Server...
taskkill /FI "WINDOWTITLE eq NexioSolution - React Dev Server*" /F >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] React Dev Server arrestato
) else (
    echo [!] React Dev Server non trovato o già arrestato
)

REM Termina anche i processi PHP e Node rimasti
taskkill /IM php.exe /F >nul 2>&1
taskkill /IM node.exe /F >nul 2>&1

echo.
echo =====================================
echo   TUTTI I SERVIZI SONO STATI FERMATI
echo =====================================
echo.
pause