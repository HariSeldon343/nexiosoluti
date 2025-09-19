# Riepilogo Fix Risoluzione Percorsi API

## Descrizione del Problema

L'applicazione Nexiosolution Collabora aveva percorsi API hardcoded che causavano errori 404 quando l'applicazione veniva installata in sottocartelle diverse da `/collabora/`. Il problema principale si verificava con installazioni in percorsi come `/Nexiosolution/collabora/` dove i percorsi API venivano risolti incorrettamente.

### Sintomi Originali:
- Login falliva con errore 404 su `/api/auth_v2.php`
- Gli endpoint API non erano raggiungibili
- I percorsi erano hardcoded come `/collabora/api/...`
- L'applicazione non funzionava se spostata in altre cartelle

## Architettura della Soluzione

### 1. Sistema di Rilevamento Dinamico
Implementato un sistema a tre livelli per determinare il percorso base:

```javascript
// Priorità di risoluzione
1. Variabile ambiente: COLLABORA_BASE_URL
2. Costante config: BASE_URL in config_v2.php
3. Auto-rilevamento: Calcolo dinamico dal percorso corrente
```

### 2. Modulo API Centralizzato
Creato `/assets/js/api.js` che:
- Rileva automaticamente il percorso base
- Gestisce tutte le chiamate API
- Fornisce metodi standardizzati per ogni endpoint
- Gestisce autenticazione e headers automaticamente

### 3. Configurazione Flessibile
Il sistema ora supporta:
- Installazioni in qualsiasi sottocartella
- Override tramite configurazione
- Fallback automatici per retrocompatibilità

## File Modificati

### File JavaScript Principali:
1. **`/assets/js/api.js`** - NUOVO
   - Modulo API centralizzato
   - Rilevamento automatico percorsi
   - Gestione unificata delle chiamate

2. **`/assets/js/auth.js`** - MODIFICATO
   - Rimossi percorsi hardcoded
   - Integrazione con modulo API
   - Supporto login dinamico

3. **`/assets/js/main.js`** - MODIFICATO
   - Import del modulo API
   - Aggiornate tutte le chiamate API
   - Rimossi riferimenti hardcoded

4. **`/assets/js/dashboard.js`** - MODIFICATO
   - Uso del modulo API per caricamento dati
   - Percorsi dinamici per tutte le richieste

5. **`/assets/js/files.js`** - MODIFICATO
   - Upload/download con percorsi dinamici
   - Integrazione WebDAV aggiornata

6. **`/assets/js/calendar.js`** - MODIFICATO
   - Endpoint CalDAV dinamici
   - Eventi calendario con percorsi corretti

7. **`/assets/js/chat.js`** - MODIFICATO
   - WebSocket con percorsi dinamici
   - Messaggi chat con API centralizzata

### File PHP Aggiornati:
1. **`/api/auth_v2.php`** - Headers CORS aggiunti
2. **`/api/auth_simple.php`** - Headers CORS aggiunti
3. **`/config_v2.php`** - Opzione BASE_URL aggiunta

### File HTML Modificati:
1. **`/index_v2.php`** - Import modulo API
2. **`/dashboard.php`** - Script paths aggiornati
3. **`/admin/*.php`** - Tutti aggiornati per percorsi dinamici

## Procedure di Test

### Test Automatici:
```bash
# Test PHP dei percorsi
php test_api_paths.php

# Test browser interattivo
Apri: http://localhost/Nexiosolution/collabora/test_api_resolution.html
```

### Test Manuali:
1. **Login Test**:
   - Vai a `/Nexiosolution/collabora/index_v2.php`
   - Login con: admin@nexio.com / admin123
   - Verifica redirect al dashboard

2. **API Test**:
   - Apri browser console (F12)
   - Esegui: `window.testAPIFromConsole()`
   - Verifica tutti gli endpoint rispondono

3. **Module Test**:
   - Dashboard: Caricamento widgets
   - Files: Upload/download
   - Calendar: Creazione eventi
   - Chat: Invio messaggi

### Test Console JavaScript:
```javascript
// Test rilevamento percorsi
import { API } from './assets/js/api.js';
console.log('Base URL:', API.getBaseUrl());
console.log('API URL:', API.getApiUrl());

// Test login
await API.auth.login('admin@nexio.com', 'admin123');

// Test endpoint
const users = await API.users.getAll();
console.log('Utenti:', users);
```

## Istruzioni di Rollback

Se necessario tornare alla versione precedente:

### 1. Ripristino File JavaScript:
```bash
# Backup attuali
cp assets/js/*.js backup/js/

# Ripristino da backup precedente
cp backup_pre_fix/js/*.js assets/js/
```

### 2. Rimozione Modulo API:
```bash
rm assets/js/api.js
rm assets/js/test-api-paths.js
```

### 3. Ripristino Percorsi Hardcoded:
Modificare `/assets/js/auth.js`:
```javascript
// Vecchio codice (NON RACCOMANDATO)
const API_BASE = '/collabora/api';
```

## Guida Migrazione per Codice Custom

### Prima (percorsi hardcoded):
```javascript
// VECCHIO METODO
fetch('/collabora/api/users.php', {
    method: 'GET',
    headers: {
        'Authorization': 'Bearer ' + token
    }
});
```

### Dopo (percorsi dinamici):
```javascript
// NUOVO METODO
import { API } from '/assets/js/api.js';

// Metodo 1: Usando il modulo API
const users = await API.users.getAll();

// Metodo 2: Usando percorsi dinamici diretti
const baseUrl = API.getBaseUrl();
fetch(baseUrl + '/api/users.php', {
    method: 'GET',
    headers: API.getHeaders()
});
```

### Migrazione Headers:
```javascript
// Prima
headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer ' + localStorage.getItem('token')
}

// Dopo
headers: API.getHeaders()
```

### Migrazione Upload:
```javascript
// Prima
const formData = new FormData();
formData.append('file', file);
fetch('/collabora/api/files.php', {
    method: 'POST',
    body: formData
});

// Dopo
await API.files.upload(file, folderId);
```

## Vantaggi della Soluzione

1. **Portabilità**: L'app funziona in qualsiasi sottocartella
2. **Manutenibilità**: Un solo punto di configurazione
3. **Scalabilità**: Facile aggiungere nuovi endpoint
4. **Affidabilità**: Fallback automatici se config mancante
5. **Testabilità**: Suite completa di test inclusa
6. **Compatibilità**: Supporto per codice legacy

## Problemi Risolti

✅ Login funziona da `/Nexiosolution/collabora/`
✅ Tutti gli endpoint API raggiungibili
✅ Upload/download file funzionanti
✅ WebSocket e WebDAV con percorsi corretti
✅ Dashboard carica correttamente
✅ Admin panel completamente funzionale

## Supporto e Assistenza

Per problemi con il nuovo sistema di percorsi:

1. Eseguire `/test_api_paths.php` per diagnostica
2. Controllare console browser per errori
3. Verificare config_v2.php per BASE_URL
4. Testare con `window.testAPIFromConsole()`

### Log e Debug:
```javascript
// Abilita debug nel modulo API
localStorage.setItem('API_DEBUG', 'true');

// Visualizza tutti i percorsi risolti
API.debugPaths();
```

## Note Finali

Questo fix risolve definitivamente il problema dei percorsi hardcoded, rendendo l'applicazione completamente portabile e installabile in qualsiasi directory. Il sistema è retrocompatibile e non richiede modifiche al database o alla struttura delle cartelle esistenti.