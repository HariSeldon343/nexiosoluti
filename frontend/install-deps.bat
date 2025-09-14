@echo off
echo =====================================
echo   Installazione Dipendenze Frontend
echo =====================================
echo.

echo [INFO] Pulizia cache npm...
npm cache clean --force

echo.
echo [INFO] Installazione dipendenze...
npm install --legacy-peer-deps

echo.
echo [INFO] Installazione completata!
echo.
pause