# NexioSolution Frontend Setup Script
# PowerShell version per maggiore affidabilità

$ErrorActionPreference = "Stop"

Write-Host "====================================" -ForegroundColor Cyan
Write-Host "NexioSolution Frontend Setup" -ForegroundColor Cyan
Write-Host "====================================" -ForegroundColor Cyan
Write-Host ""

# Cambia directory
Set-Location "C:\xampp\htdocs\Nexiosolution\frontend"

# Verifica Node.js
Write-Host "[1/5] Verifica Node.js..." -ForegroundColor Yellow
try {
    $nodeVersion = node --version
    $npmVersion = npm --version
    Write-Host "Node.js: $nodeVersion" -ForegroundColor Green
    Write-Host "NPM: $npmVersion" -ForegroundColor Green
} catch {
    Write-Host "ERRORE: Node.js non trovato!" -ForegroundColor Red
    Write-Host "Scarica Node.js da: https://nodejs.org/en/download/" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Premi un tasto per uscire..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit 1
}
Write-Host ""

# Controllo se esistono node_modules
Write-Host "[2/5] Controllo dipendenze..." -ForegroundColor Yellow
if (Test-Path "node_modules") {
    Write-Host "node_modules trovato. Vuoi reinstallare le dipendenze? (S/N)" -ForegroundColor Cyan
    $response = Read-Host
    if ($response -eq 'S' -or $response -eq 's') {
        Write-Host "Rimozione node_modules..." -ForegroundColor Yellow
        Remove-Item -Recurse -Force node_modules
        if (Test-Path "package-lock.json") {
            Remove-Item -Force package-lock.json
        }
    }
}
Write-Host ""

# Installazione dipendenze
if (-not (Test-Path "node_modules")) {
    Write-Host "[3/5] Installazione dipendenze..." -ForegroundColor Yellow
    Write-Host "Questo potrebbe richiedere alcuni minuti..." -ForegroundColor Gray

    # Prova prima con npm install normale
    try {
        npm install
        Write-Host "Dipendenze installate con successo!" -ForegroundColor Green
    } catch {
        Write-Host "Errore nell'installazione standard, provo con --legacy-peer-deps..." -ForegroundColor Yellow
        try {
            npm install --legacy-peer-deps
            Write-Host "Dipendenze installate con --legacy-peer-deps!" -ForegroundColor Green
        } catch {
            Write-Host "Errore! Provo con --force..." -ForegroundColor Yellow
            npm install --force
        }
    }
} else {
    Write-Host "[3/5] Dipendenze già installate." -ForegroundColor Green
}
Write-Host ""

# Verifica file essenziali
Write-Host "[4/5] Verifica file essenziali..." -ForegroundColor Yellow
$essentialFiles = @(
    "index.html",
    "vite.config.js",
    "src/main.jsx",
    "src/App.jsx",
    "src/index.css"
)

$allFilesPresent = $true
foreach ($file in $essentialFiles) {
    if (Test-Path $file) {
        Write-Host "  ✓ $file" -ForegroundColor Green
    } else {
        Write-Host "  ✗ $file MANCANTE!" -ForegroundColor Red
        $allFilesPresent = $false
    }
}

if (-not $allFilesPresent) {
    Write-Host ""
    Write-Host "ATTENZIONE: Alcuni file essenziali mancano!" -ForegroundColor Red
    Write-Host "Il server potrebbe non avviarsi correttamente." -ForegroundColor Yellow
}
Write-Host ""

# Avvio server
Write-Host "[5/5] Avvio server di sviluppo..." -ForegroundColor Yellow
Write-Host ""
Write-Host "====================================" -ForegroundColor Cyan
Write-Host "Frontend disponibile su:" -ForegroundColor Green
Write-Host "http://localhost:3000" -ForegroundColor White
Write-Host "====================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Premi Ctrl+C per fermare il server" -ForegroundColor Yellow
Write-Host ""

# Avvia il server
npm run dev