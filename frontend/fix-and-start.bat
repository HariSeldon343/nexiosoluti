@echo off
echo Fixing Frontend Configuration...

cd /d C:\xampp\htdocs\Nexiosolution\frontend

REM Kill qualsiasi processo sulla porta 3000
for /f "tokens=5" %%a in ('netstat -ano ^| findstr :3000') do taskkill /PID %%a /F 2>nul

REM Kill processi node esistenti
taskkill /F /IM node.exe 2>nul

REM Installa dipendenze se necessario
if not exist node_modules (
    echo Installing dependencies...
    call npm install --legacy-peer-deps
)

echo Starting Vite on port 3000...
call npm run dev