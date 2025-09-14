# ISTRUZIONI PER AVVIARE IL FRONTEND

## METODO RAPIDO (CONSIGLIATO)

1. **Doppio click su: `START-FRONTEND.bat`**
   - Questo script fa TUTTO automaticamente
   - Installa le dipendenze se necessario
   - Avvia il server
   - Apre il browser

## REQUISITI

- **Node.js** installato (versione 16 o superiore)
  - Verifica con: `node --version`
  - Se non installato: https://nodejs.org/

## RISOLUZIONE PROBLEMI

### Errore 404 su localhost:3000

**Causa**: Il server non è partito correttamente

**Soluzioni**:
1. Esegui `START-FRONTEND.bat`
2. Se non funziona, esegui `diagnose.bat` per vedere cosa manca

### "Node.js non trovato"

**Soluzione**:
1. Installa Node.js da https://nodejs.org/
2. Scarica la versione "LTS"
3. Installa con le opzioni di default
4. Riavvia il PC
5. Riprova con `START-FRONTEND.bat`

### "npm install" fallisce

**Soluzioni nell'ordine**:
1. Esegui: `npm install --legacy-peer-deps`
2. Se non funziona: `npm cache clean --force` poi `npm install --force`
3. Se ancora non funziona: cancella `node_modules` e `package-lock.json`, poi riprova

### Porta 3000 già in uso

**Soluzioni**:
1. Chiudi altre applicazioni che usano la porta 3000
2. Oppure modifica la porta in `vite.config.js`

## FILE IMPORTANTI

- `START-FRONTEND.bat` - Script principale per avviare tutto
- `diagnose.bat` - Verifica problemi
- `package.json` - Configurazione dipendenze
- `vite.config.js` - Configurazione Vite
- `src/App.jsx` - Componente principale React

## STATO ATTUALE

✅ **RISOLTO**: Il frontend ora funziona correttamente
- App.jsx semplificata per test iniziale
- Script automatici creati
- Configurazione verificata

## COMANDI UTILI

```bash
# Installa dipendenze
npm install

# Avvia server di sviluppo
npm run dev

# Build di produzione
npm run build

# Preview build
npm run preview
```

## STRUTTURA CORRETTA

```
frontend/
├── node_modules/        (creato dopo npm install)
├── src/
│   ├── App.jsx         ✅
│   ├── main.jsx        ✅
│   └── index.css       ✅
├── index.html          ✅ (nella ROOT, non in public!)
├── package.json        ✅
├── vite.config.js      ✅
└── START-FRONTEND.bat  ✅
```

---

**IMPORTANTE**: Usa sempre `START-FRONTEND.bat` per avviare il frontend!