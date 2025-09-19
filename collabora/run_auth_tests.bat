@echo off
REM ===============================================================================
REM Script per eseguire i test di autenticazione su Windows
REM Richiede Git Bash installato o WSL
REM ===============================================================================

echo ================================================================================
echo                    NEXIO SOLUTION - TEST AUTENTICAZIONE
echo ================================================================================
echo.

REM Controlla se Git Bash è installato
where git >nul 2>nul
if %errorlevel%==0 (
    echo Git trovato. Esecuzione test con Git Bash...
    echo.

    REM Esegui lo script bash usando Git Bash
    "C:\Program Files\Git\bin\bash.exe" test_curl_auth.sh

    if %errorlevel%==0 (
        echo.
        echo [32mTutti i test sono passati![0m
    ) else (
        echo.
        echo [31mAlcuni test sono falliti. Controlla i dettagli sopra.[0m
    )
) else (
    echo Git Bash non trovato. Esecuzione test PHP...
    echo.

    REM Fallback su test PHP
    php test_auth_final.php
)

echo.
echo ================================================================================
echo Per vedere i risultati nel browser, visita:
echo http://localhost/Nexiosolution/collabora/test_auth_final.php
echo ================================================================================
echo.
pause