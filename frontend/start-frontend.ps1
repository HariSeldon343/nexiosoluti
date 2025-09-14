# NexioSolution Frontend Starter Script for PowerShell

Write-Host "=====================================" -ForegroundColor Cyan
Write-Host "  NexioSolution Frontend Starter" -ForegroundColor Yellow
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host ""

# Funzione per verificare se un comando esiste
function Test-Command {
    param($Command)
    try {
        if (Get-Command $Command -ErrorAction Stop) {
            return $true
        }
    }
    catch {
        return $false
    }
}

# Verifica Node.js
if (-not (Test-Command "node")) {
    Write-Host "[ERRORE] Node.js non trovato! Per favore installa Node.js da https://nodejs.org/" -ForegroundColor Red
    Read-Host "Premi INVIO per uscire"
    exit 1
}

# Mostra versione Node.js
Write-Host "[INFO] Node.js version:" -ForegroundColor Green
node --version
Write-Host ""

# Verifica npm
if (-not (Test-Command "npm")) {
    Write-Host "[ERRORE] npm non trovato! Per favore installa npm" -ForegroundColor Red
    Read-Host "Premi INVIO per uscire"
    exit 1
}

# Mostra versione npm
Write-Host "[INFO] npm version:" -ForegroundColor Green
npm --version
Write-Host ""

# Verifica se le dipendenze sono installate
if (-not (Test-Path "node_modules")) {
    Write-Host "[INFO] Installazione dipendenze in corso..." -ForegroundColor Yellow
    Write-Host ""
    npm install
    if ($LASTEXITCODE -ne 0) {
        Write-Host "[ERRORE] Installazione dipendenze fallita!" -ForegroundColor Red
        Read-Host "Premi INVIO per uscire"
        exit 1
    }
    Write-Host ""
    Write-Host "[OK] Dipendenze installate con successo!" -ForegroundColor Green
    Write-Host ""
}

# Avvia il server di sviluppo
Write-Host "[INFO] Avvio server di sviluppo su http://localhost:3000" -ForegroundColor Cyan
Write-Host ""
Write-Host "Premi Ctrl+C per fermare il server" -ForegroundColor Yellow
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host ""

# Usa lo script specifico per Windows
npm run dev:win

# Se il server si ferma, mostra un messaggio
Write-Host ""
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host "Server fermato." -ForegroundColor Yellow
Read-Host "Premi INVIO per uscire"