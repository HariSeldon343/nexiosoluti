# NexioSolution Frontend - Guida Avvio Rapido

## Prerequisiti
- Node.js versione 18 o superiore
- npm versione 9 o superiore
- Windows con XAMPP installato

## Avvio Rapido (Windows)

### Metodo 1: Script Automatico (Consigliato)
Doppio click su uno di questi file nella cartella frontend:
- `start-frontend.bat` - Script completo con controlli
- `test-frontend.bat` - Test rapido con dipendenze minime

### Metodo 2: Installazione Manuale

1. **Apri il terminale** (CMD o PowerShell) nella cartella frontend:
   ```
   cd C:\xampp\htdocs\Nexiosolution\frontend
   ```

2. **Installa le dipendenze**:
   ```
   npm install --legacy-peer-deps
   ```

3. **Avvia il server di sviluppo**:
   ```
   npm run dev:win
   ```

4. **Apri il browser** su: http://localhost:3000

## Script Disponibili

- `npm run dev` - Avvia il server di sviluppo
- `npm run dev:win` - Avvia il server specifico per Windows
- `npm run build` - Compila per produzione
- `npm run preview` - Anteprima build di produzione
- `npm run lint` - Controlla il codice
- `npm run reinstall` - Reinstalla tutte le dipendenze

## Struttura File Principali

```
frontend/
├── index.html          # Entry point HTML
├── package.json        # Dipendenze e script
├── vite.config.js      # Configurazione Vite
├── .env               # Variabili d'ambiente
├── start-frontend.bat  # Script avvio Windows
├── test-frontend.bat   # Script test rapido
├── install-deps.bat    # Script installazione dipendenze
└── src/
    ├── main.jsx       # Entry point React
    ├── App.jsx        # Componente principale
    ├── App.simple.jsx # App di test semplificata
    └── index.css      # Stili Tailwind CSS

## Risoluzione Problemi

### Errore: "vite: not found"
**Soluzione**: Installa le dipendenze
```
npm install --legacy-peer-deps
```

### Errore: "Cannot find module"
**Soluzione**: Reinstalla completamente
```
npm run reinstall
```

### Porta 3000 già in uso
**Soluzione**: Cambia porta nel vite.config.js o termina il processo che usa la porta

### Errori di permessi su Windows
**Soluzione**: Esegui CMD come amministratore

## Configurazione API Backend

Il frontend è configurato per comunicare con:
- API Backend: http://localhost:8000
- WebSocket: ws://localhost:6001

Modifica il file `.env` per cambiare questi endpoint.

## Build per Produzione

1. Compila l'applicazione:
   ```
   npm run build
   ```

2. La build sarà nella cartella `dist/`

3. Per testare la build:
   ```
   npm run preview
   ```

## Supporto PWA

L'app è configurata come Progressive Web App con:
- Service Worker per funzionamento offline
- Manifest per installazione
- Cache intelligente delle risorse
- Push notifications

## Note per lo Sviluppo

- Hot Module Replacement (HMR) attivo
- Tailwind CSS configurato
- React DevTools compatibile
- Source maps attive in development

## Aiuto

Per problemi o domande:
1. Controlla i log nella console del browser (F12)
2. Verifica i log del terminale
3. Assicurati che il backend sia in esecuzione su porta 8000

---
Frontend configurato e pronto per l'uso!