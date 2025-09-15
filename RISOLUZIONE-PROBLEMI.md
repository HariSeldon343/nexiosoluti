# NEXIOSOLUTION - GUIDA RISOLUZIONE PROBLEMI

## PROBLEMI COMUNI E SOLUZIONI

### 1. LA PIATTAFORMA NON È ACCESSIBILE SU localhost:3000

**PROBLEMA:** Quando apri http://localhost:3000 ricevi errore "Impossibile raggiungere il sito"

**SOLUZIONI:**

#### Soluzione Rapida:
```batch
# Esegui questo comando:
AVVIA-FRONTEND-RAPIDO.bat
```

#### Soluzione Manuale:
1. Apri il terminale/CMD
2. Naviga alla cartella: `cd C:\xampp\htdocs\Nexiosolution\frontend`
3. Installa dipendenze: `npm install --legacy-peer-deps`
4. Avvia il server: `npm run dev`
5. Apri il browser su: http://localhost:3000

---

### 2. ERRORE "Cannot find module" o "Module not found"

**PROBLEMA:** Il server si avvia ma mostra errori di moduli mancanti

**SOLUZIONE:**
```batch
# Esegui il reset completo:
reset-frontend.bat
```

O manualmente:
```bash
cd frontend
rm -rf node_modules package-lock.json
npm install --legacy-peer-deps
npm run dev
```

---

### 3. PORTA 3000 GIÀ IN USO

**PROBLEMA:** Errore "Port 3000 is already in use"

**SOLUZIONE:**
```batch
# Terminale/CMD come Amministratore:
taskkill /F /IM node.exe
netstat -ano | findstr :3000
# Trova il PID e termina:
taskkill /PID [numero_pid] /F
```

---

### 4. SCHERMATA BIANCA O NERA

**PROBLEMA:** Il sito si carica ma mostra solo una pagina bianca/nera

**CAUSE POSSIBILI:**
- Errori JavaScript nella console
- Problemi con il routing
- Mancanza di AuthContext

**SOLUZIONE:**
1. Apri Console Browser (F12)
2. Controlla errori rossi
3. Se vedi "useAuth must be used within AuthProvider":
   - Il file App.jsx deve wrappare tutto in `<AuthProvider>`
   - Verifica che App.jsx sia aggiornato all'ultima versione

---

### 5. NODE.JS NON RICONOSCIUTO

**PROBLEMA:** "'node' non è riconosciuto come comando"

**SOLUZIONE:**
1. Scarica Node.js da: https://nodejs.org (versione LTS)
2. Durante installazione, seleziona "Add to PATH"
3. Riavvia il terminale/CMD
4. Verifica: `node -v`

---

### 6. NPM INSTALL FALLISCE

**PROBLEMA:** Errori durante `npm install`

**SOLUZIONI:**

#### Con conflitti di dipendenze:
```bash
npm install --legacy-peer-deps
# oppure
npm install --force
```

#### Con errori di permessi:
```bash
# Su Windows, esegui CMD come Amministratore
# Su Linux/Mac:
sudo npm install
```

#### Cache corrotta:
```bash
npm cache clean --force
npm install
```

---

### 7. REACT NON SI AGGIORNA (HOT RELOAD NON FUNZIONA)

**PROBLEMA:** Le modifiche al codice non si riflettono nel browser

**SOLUZIONI:**
1. Verifica che il server mostri: "HMR enabled"
2. Pulisci cache browser: Ctrl+F5
3. Riavvia il server dev
4. Verifica che Vite sia configurato correttamente

---

### 8. BACKEND NON RISPONDE

**PROBLEMA:** Errori di connessione al backend (porta 8000)

**SOLUZIONE:**
```batch
# Avvia il backend:
cd backend
python -m venv venv
venv\Scripts\activate
pip install -r requirements.txt
python main.py
```

---

### 9. ERRORE "ENOENT" O FILE NON TROVATI

**PROBLEMA:** Errori tipo "ENOENT: no such file or directory"

**SOLUZIONE:**
1. Verifica di essere nella directory corretta
2. Controlla che tutti i file esistano:
```batch
DIAGNOSTICA-COMPLETA.bat
```

---

### 10. BUILD FALLISCE

**PROBLEMA:** `npm run build` genera errori

**SOLUZIONI COMUNI:**
- TypeScript errors: Aggiungi `// @ts-ignore` sopra le righe problematiche
- Import errors: Verifica che tutti i file importati esistano
- Syntax errors: Controlla parentesi e virgole

---

## SCRIPT DI UTILITÀ DISPONIBILI

### Per Avvio Rapido:
- `AVVIA-FRONTEND-RAPIDO.bat` - Avvia solo il frontend
- `AVVIA-TUTTO.bat` - Avvia frontend + backend
- `AVVIO-RAPIDO.bat` - Avvio minimo essenziale

### Per Diagnostica:
- `DIAGNOSTICA-COMPLETA.bat` - Controlla tutto il sistema
- `test-system.bat` - Test completo con report
- `VERIFICA-SISTEMA-FINALE.bat` - Verifica finale dettagliata

### Per Reset/Pulizia:
- `reset-frontend.bat` - Reset completo frontend
- `STOP-NEXIOSOLUTION.bat` - Ferma tutti i servizi

---

## COMANDI UTILI DA TERMINALE

```bash
# Verifica versioni
node -v
npm -v
python --version

# Frontend
cd frontend
npm install --legacy-peer-deps
npm run dev
npm run build

# Backend
cd backend
python main.py

# Pulizia
taskkill /F /IM node.exe
rm -rf node_modules
npm cache clean --force
```

---

## VERIFICA RAPIDA FUNZIONAMENTO

1. **Test minimo:** Apri http://localhost:3000
   - Dovresti vedere la landing page di NexioSolution

2. **Test routing:** Clicca su "Accedi alla Piattaforma"
   - Dovresti vedere la pagina di login

3. **Test API:** Nella console browser:
```javascript
fetch('http://localhost:8000/api/health')
  .then(r => r.json())
  .then(console.log)
```

---

## SUPPORTO

Per problemi non risolti:
1. Esegui `DIAGNOSTICA-COMPLETA.bat` e salva l'output
2. Controlla la Console Browser (F12) per errori
3. Verifica i log nella finestra del terminale

## FILE CRITICI DA VERIFICARE

- `/frontend/package.json` - Dipendenze
- `/frontend/src/App.jsx` - Componente principale
- `/frontend/src/main.jsx` - Entry point
- `/frontend/index.html` - HTML principale
- `/frontend/vite.config.js` - Configurazione Vite
- `/frontend/src/contexts/AuthContext.jsx` - Context autenticazione

---

Ultimo aggiornamento: 14/09/2025