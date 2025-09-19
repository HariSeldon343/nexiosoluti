# API Authentication Contract Documentation

## Panoramica
Documentazione completa del contratto API per il sistema di autenticazione di Nexio Solution V2.
Il sistema supporta due endpoint principali: `auth_simple.php` e `auth_v2.php`.

## Endpoint Disponibili

### 1. `/api/auth_simple.php`
Endpoint semplificato per autenticazione base con supporto JSON e form-encoded.

### 2. `/api/auth_v2.php`
Endpoint avanzato con funzionalità complete di gestione sessioni e tenant.

---

## Formato JSON Richiesto

### Login Request
```json
{
    "action": "login",
    "email": "user@example.com",
    "password": "userpassword"
}
```

**Campi obbligatori:**
- `action` (string): Deve essere "login"
- `email` (string): Email dell'utente
- `password` (string): Password in chiaro

---

## Azioni Supportate

### auth_simple.php

| Azione | Metodo | Descrizione | Payload Richiesto |
|--------|--------|-------------|-------------------|
| `login` | POST | Effettua il login | `{"action":"login","email":"...","password":"..."}` |
| `logout` | POST | Effettua il logout | `{"action":"logout"}` |
| `switch_tenant` | POST | Cambia tenant attivo | `{"action":"switch_tenant","tenant_id":123}` |
| `check` | POST | Verifica stato autenticazione | `{"action":"check"}` |
| `test` | POST | Test configurazione API | `{"action":"test"}` |

### auth_v2.php

| Azione | Metodo | Descrizione | Payload Richiesto |
|--------|--------|-------------|-------------------|
| `login` | POST | Effettua il login | `{"action":"login","email":"...","password":"..."}` |
| `logout` | POST | Effettua il logout | `{"action":"logout"}` |
| `switch_tenant` | POST | Cambia tenant con CSRF | `{"action":"switch_tenant","tenant_id":123,"csrf_token":"..."}` |
| `test` | POST | Test configurazione API | `{"action":"test"}` |

#### Endpoint REST aggiuntivi (auth_v2.php)
| Endpoint | Metodo | Descrizione |
|----------|--------|-------------|
| `/me` | GET | Ottieni utente corrente |
| `/tenants` | GET | Lista tenant disponibili |
| `/current-tenant` | GET | Tenant attivo corrente |
| `/permissions` | GET | Permessi utente |
| `/session-status` | GET | Stato sessione |
| `/csrf-token` | GET | Genera token CSRF |
| `/password` | PUT | Cambia password |
| `/profile` | PUT | Aggiorna profilo |
| `/sessions` | DELETE | Termina tutte le sessioni |

---

## Formati di Risposta

### Risposta Successo Login
```json
{
    "success": true,
    "message": "Login effettuato con successo",
    "user": {
        "id": 1,
        "email": "user@example.com",
        "first_name": "Nome",
        "last_name": "Cognome",
        "role": "admin",
        "is_system_admin": true
    },
    "tenants": [
        {
            "id": 1,
            "name": "Tenant 1",
            "code": "TENANT1"
        }
    ],
    "current_tenant_id": 1,
    "session_id": "abc123...",
    "csrf_token": "xyz789..." // Solo per auth_v2.php
}
```

### Risposta Errore
```json
{
    "success": false,
    "error": "Credenziali non valide",
    "debug": {
        "request_method": "POST",
        "content_type": "application/json",
        "received_data": {...}
    }
}
```

### Risposta Check Autenticazione
```json
{
    "success": true,
    "authenticated": true,
    "user": {
        "id": 1,
        "email": "user@example.com",
        "role": "admin"
    }
}
```

### Risposta Test Endpoint
```json
{
    "success": true,
    "message": "API endpoint is working correctly",
    "endpoint": "auth_simple.php",
    "method": "POST",
    "timestamp": "2025-01-18 10:30:00"
}
```

---

## Esempi cURL

### Login con auth_simple.php
```bash
curl -X POST http://localhost/Nexiosolution/collabora/api/auth_simple.php \
  -H "Content-Type: application/json" \
  -d '{"action":"login","email":"asamodeo@fortibyte.it","password":"Ricord@1991"}'
```

### Login con auth_v2.php
```bash
curl -X POST http://localhost/Nexiosolution/collabora/api/auth_v2.php \
  -H "Content-Type: application/json" \
  -d '{"action":"login","email":"asamodeo@fortibyte.it","password":"Ricord@1991"}'
```

### Check Stato Autenticazione
```bash
curl -X POST http://localhost/Nexiosolution/collabora/api/auth_simple.php \
  -H "Content-Type: application/json" \
  -d '{"action":"check"}'
```

### Test Endpoint
```bash
curl -X POST http://localhost/Nexiosolution/collabora/api/auth_simple.php \
  -H "Content-Type: application/json" \
  -d '{"action":"test"}'
```

### Logout
```bash
curl -X POST http://localhost/Nexiosolution/collabora/api/auth_simple.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{"action":"logout"}'
```

### Switch Tenant
```bash
curl -X POST http://localhost/Nexiosolution/collabora/api/auth_simple.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{"action":"switch_tenant","tenant_id":2}'
```

### Get Current User (auth_v2.php REST)
```bash
curl -X GET http://localhost/Nexiosolution/collabora/api/auth_v2.php/me \
  -H "Cookie: PHPSESSID=your_session_id"
```

---

## Codici di Errore HTTP

| Codice | Significato | Scenario |
|--------|------------|----------|
| **200** | OK | Richiesta completata con successo |
| **400** | Bad Request | Parametri mancanti o invalidi, JSON malformato |
| **401** | Unauthorized | Credenziali non valide o sessione scaduta |
| **403** | Forbidden | Token CSRF non valido o permessi insufficienti |
| **404** | Not Found | Endpoint o azione non trovata |
| **405** | Method Not Allowed | Metodo HTTP non supportato |
| **500** | Internal Server Error | Errore del server |

---

## Messaggi di Errore Comuni

### Errori di Autenticazione
- `"Email e password sono obbligatori"` - Campi mancanti nel payload
- `"Credenziali non valide"` - Email o password errate
- `"Utente non trovato"` - Email non registrata nel sistema
- `"Account disabilitato"` - Utente esistente ma non attivo

### Errori di Validazione
- `"Invalid JSON input: Syntax error"` - JSON malformato
- `"No input data received. Expected JSON with 'action' field"` - Payload vuoto
- `"No action specified"` - Campo action mancante
- `"Invalid action: 'xyz'"` - Azione non riconosciuta

### Errori di Sessione
- `"Non autenticato"` - Sessione non valida o scaduta
- `"Token CSRF non valido"` - CSRF token mancante o invalido
- `"Sessione scaduta"` - Timeout della sessione

### Errori di Permessi
- `"Permessi insufficienti"` - Utente non autorizzato
- `"Accesso negato al tenant"` - Tenant non associato all'utente

---

## Headers HTTP

### Request Headers Richiesti
```http
Content-Type: application/json
Accept: application/json
```

### Request Headers Opzionali
```http
X-CSRF-Token: <csrf_token>
Cookie: PHPSESSID=<session_id>
```

### Response Headers
```http
Content-Type: application/json; charset=utf-8
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
```

---

## Note sulla Sicurezza

1. **Password Hashing**: Le password sono hashate con Argon2id o bcrypt
2. **CSRF Protection**: Token CSRF richiesto per operazioni di scrittura in auth_v2.php
3. **Session Security**: Cookie HTTPOnly, SameSite=Strict
4. **Rate Limiting**: Implementato per prevenire brute force
5. **SQL Injection**: Tutte le query usano prepared statements PDO

---

## Debug Mode

Per abilitare la modalità debug e ricevere informazioni dettagliate sugli errori:

1. Aggiungi il parametro `debug=true` nel payload
2. O imposta la variabile di ambiente `APP_DEBUG=true`

Esempio risposta con debug:
```json
{
    "success": false,
    "error": "Credenziali non valide",
    "debug": {
        "request_method": "POST",
        "content_type": "application/json",
        "received_data": {
            "action": "login",
            "email": "test@example.com"
        },
        "timestamp": "2025-01-18 10:30:00",
        "server": "Apache/2.4.58 (Win64) OpenSSL/3.1.3 PHP/8.2.12"
    }
}
```

---

## Compatibilità

- **PHP**: 8.0+
- **MySQL/MariaDB**: 5.7+ / 10.3+
- **Browser**: Tutti i browser moderni con supporto JSON
- **Content-Type**: Supporta sia `application/json` che `application/x-www-form-urlencoded`

---

## Versioning

- **auth_simple.php**: v1.0.0 - Endpoint stabile per retrocompatibilità
- **auth_v2.php**: v2.0.0 - Endpoint con funzionalità avanzate

---

## Contatti e Supporto

Per problemi o domande sull'API di autenticazione:
- Email: support@nexiosolution.com
- Documentazione: `/docs/API_AUTH_CONTRACT.md`
- Test Suite: `/test_auth_final.php`