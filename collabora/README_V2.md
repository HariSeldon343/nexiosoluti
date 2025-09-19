# 🚀 Nexio Solution V2 - Sistema di Autenticazione Multi-Tenant

## 📋 Indice

1. [Introduzione](#introduzione)
2. [Caratteristiche Principali](#caratteristiche-principali)
3. [Requisiti Sistema](#requisiti-sistema)
4. [Installazione Rapida](#installazione-rapida)
5. [Sistema di Autenticazione](#sistema-di-autenticazione)
6. [Ruoli e Permessi](#ruoli-e-permessi)
7. [Flusso di Login](#flusso-di-login)
8. [Funzionalità Admin](#funzionalità-admin)
9. [API Documentation](#api-documentation)
10. [Risoluzione Problemi](#risoluzione-problemi)
11. [FAQ](#faq)

---

## 🎯 Introduzione

Nexio Solution V2 è un sistema collaborativo avanzato con autenticazione multi-tenant che elimina la necessità del codice tenant durante il login. Il sistema determina automaticamente i tenant associati all'utente e gestisce l'accesso in base al ruolo.

### Novità della Versione 2

- ✅ **No Tenant Code Required**: Login semplificato solo con email e password
- ✅ **Auto-detection Tenant**: Sistema intelligente che rileva automaticamente i tenant
- ✅ **Multi-Tenant per Special Users**: Cambio tenant senza nuovo login
- ✅ **Dashboard Personalizzata**: Interfaccia adattiva basata sul ruolo
- ✅ **Audit Log Completo**: Tracciamento di tutte le operazioni
- ✅ **API RESTful V2**: Endpoints sicuri con autenticazione JWT

---

## ⚡ Caratteristiche Principali

### 🔐 Sistema di Autenticazione

- Login semplificato (email + password)
- Rilevamento automatico tenant
- Sessioni sicure con token JWT
- 2FA opzionale
- Rate limiting per prevenire brute force
- Recovery password con token temporizzato

### 👥 Gestione Utenti

- 4 livelli di ruolo (Admin, Special, Standard, Guest)
- Permessi granulari per funzionalità
- Profili utente personalizzabili
- Avatar e preferenze individuali
- Storico accessi e attività

### 🏢 Multi-Tenant

- Isolamento completo dei dati
- Switch tenant per utenti speciali
- Dashboard tenant-specific
- Impostazioni per tenant
- Branding personalizzato

### 📊 Funzionalità Collaborative

- Task management con priorità
- Calendario condiviso
- File sharing sicuro
- Chat in tempo reale
- Notifiche push

---

## 💻 Requisiti Sistema

### Software Richiesto

| Componente | Versione Minima | Consigliata |
|------------|-----------------|-------------|
| PHP | 7.4 | 8.0+ |
| MySQL/MariaDB | 5.7 | 8.0+ |
| Apache | 2.4 | 2.4+ |
| XAMPP | 7.4 | 8.0+ |

### Estensioni PHP Necessarie

- ✅ mysqli
- ✅ pdo_mysql
- ✅ json
- ✅ mbstring
- ✅ openssl
- ✅ fileinfo
- ✅ curl

### Configurazione PHP Consigliata

```ini
memory_limit = 256M
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
session.gc_maxlifetime = 7200
```

---

## 🚀 Installazione Rapida

### Metodo 1: Installazione Automatica (Consigliato)

1. **Avvia XAMPP**
   ```batch
   C:\xampp\xampp-control.exe
   ```

2. **Esegui lo script di avvio**
   ```batch
   cd C:\xampp\htdocs\Nexiosolution\collabora
   start_v2.bat
   ```

3. **Segui l'installazione guidata**
   - Il browser si aprirà automaticamente
   - Completa i passaggi dell'installer
   - Sistema pronto in 2 minuti

### Metodo 2: Installazione Manuale

1. **Crea il database**
   ```sql
   CREATE DATABASE nexio_collabora_v2
   CHARACTER SET utf8mb4
   COLLATE utf8mb4_unicode_ci;
   ```

2. **Configura config_v2.php**
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'nexio_collabora_v2');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

3. **Esegui installer**
   ```
   http://localhost/Nexiosolution/collabora/install_v2.php
   ```

---

## 🔑 Sistema di Autenticazione

### Come Funziona

Il nuovo sistema elimina la necessità del codice tenant:

1. **Login Utente**
   - Inserisce solo email e password
   - Nessun codice tenant richiesto

2. **Identificazione Automatica**
   - Sistema verifica credenziali
   - Recupera tenant associati
   - Determina ruolo utente

3. **Accesso Basato su Ruolo**
   - **Admin**: Accesso completo a tutti i tenant
   - **Special User**: Scelta tra tenant multipli
   - **Standard User**: Accesso al tenant primario
   - **Guest**: Accesso limitato read-only

### Sicurezza

```php
// Esempio di validazione sicura
$password_requirements = [
    'min_length' => 8,
    'require_special' => true,
    'require_number' => true,
    'require_uppercase' => true
];

// Rate limiting
$max_attempts = 5;
$lockout_duration = 900; // 15 minuti
```

---

## 👤 Ruoli e Permessi

### 🔴 Admin (Amministratore)

**Descrizione**: Controllo completo del sistema

**Permessi**:
- ✅ Gestione sistema completa
- ✅ Creazione/modifica/eliminazione utenti
- ✅ Gestione tutti i tenant
- ✅ Accesso a tutti i dati
- ✅ Configurazione sistema
- ✅ Backup e restore
- ✅ Visualizzazione audit log

**Dashboard**: Pannello amministrativo completo

### 🟡 Special User (Utente Speciale)

**Descrizione**: Accesso a multipli tenant

**Permessi**:
- ✅ Accesso multi-tenant
- ✅ Switch tra tenant autorizzati
- ✅ Gestione file e documenti
- ✅ Task management
- ✅ Calendario e eventi
- ✅ Chat e collaborazione
- ✅ Report generation

**Dashboard**: Selettore tenant + funzionalità complete

### 🟢 Standard User (Utente Standard)

**Descrizione**: Accesso singolo tenant

**Permessi**:
- ✅ Accesso al proprio tenant
- ✅ Gestione propri file
- ✅ Task personali e assegnati
- ✅ Calendario personale
- ✅ Chat con colleghi

**Dashboard**: Area di lavoro tenant-specific

### ⚪ Guest (Ospite)

**Descrizione**: Accesso limitato read-only

**Permessi**:
- ✅ Visualizzazione contenuti pubblici
- ❌ Nessuna modifica
- ❌ Nessun upload

**Dashboard**: Vista semplificata

---

## 🔄 Flusso di Login

### 1. Pagina di Login

```html
<!-- Form semplificato -->
<form method="POST" action="auth/login">
    <input type="email" name="email" required>
    <input type="password" name="password" required>
    <button type="submit">Accedi</button>
</form>
```

### 2. Processo di Autenticazione

```php
// Backend processing
1. Validazione input
2. Verifica credenziali
3. Controllo tentativi falliti
4. Recupero ruolo e tenant
5. Creazione sessione
6. Redirect dashboard appropriata
```

### 3. Dashboard Routing

```php
switch($user->role) {
    case 'admin':
        redirect('admin/dashboard');
        break;
    case 'special_user':
        redirect('tenant/selector');
        break;
    case 'standard_user':
        redirect('workspace');
        break;
    default:
        redirect('guest/view');
}
```

---

## 🛠️ Funzionalità Admin

### Pannello di Controllo

#### Gestione Utenti
- Creazione bulk utenti
- Import/Export CSV
- Assegnazione ruoli
- Reset password
- Blocco/sblocco account
- Storico accessi

#### Gestione Tenant
- Creazione nuovi tenant
- Configurazione settings
- Assegnazione utenti
- Limiti e quote
- Branding personalizzato

#### Monitoraggio Sistema
- Dashboard real-time
- Metriche performance
- Log attività
- Report utilizzo
- Alert automatici

### Comandi Admin

```php
// Esempi di operazioni admin

// Crea nuovo tenant
$admin->createTenant([
    'code' => 'NEWCO',
    'name' => 'New Company Ltd',
    'max_users' => 50
]);

// Assegna utente a tenant
$admin->assignUserToTenant($userId, $tenantId, [
    'role' => 'standard_user',
    'permissions' => ['read', 'write']
]);

// Backup sistema
$admin->backupSystem([
    'include_files' => true,
    'compress' => true
]);
```

---

## 📡 API Documentation

### Autenticazione

#### POST /api/v2/auth/login
```json
Request:
{
    "email": "user@example.com",
    "password": "SecurePass123!"
}

Response:
{
    "success": true,
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "user": {
        "id": 1,
        "email": "user@example.com",
        "role": "special_user",
        "tenants": ["TENANT01", "TENANT02"]
    }
}
```

#### GET /api/v2/auth/verify
```json
Headers:
{
    "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
}

Response:
{
    "valid": true,
    "user_id": 1,
    "expires_in": 3600
}
```

### Gestione Tenant

#### GET /api/v2/tenants/list
```json
Response:
{
    "success": true,
    "tenants": [
        {
            "id": 1,
            "code": "TENANT01",
            "name": "Azienda 1",
            "is_primary": true
        }
    ]
}
```

#### POST /api/v2/tenants/switch
```json
Request:
{
    "tenant_code": "TENANT02"
}

Response:
{
    "success": true,
    "message": "Switched to TENANT02"
}
```

### Gestione Utenti

#### GET /api/v2/users/profile
```json
Response:
{
    "id": 1,
    "email": "user@example.com",
    "name": "Mario Rossi",
    "role": "standard_user",
    "avatar_url": "/uploads/avatars/user1.jpg",
    "settings": {
        "language": "it",
        "timezone": "Europe/Rome"
    }
}
```

#### PUT /api/v2/users/profile
```json
Request:
{
    "name": "Mario Rossi",
    "phone": "+39 123 456 7890",
    "settings": {
        "notifications": true
    }
}
```

---

## 🔧 Risoluzione Problemi

### Problema: Login non funziona

**Sintomi**: Credenziali corrette ma accesso negato

**Soluzioni**:
1. Verifica stato servizi XAMPP
   ```batch
   C:\xampp\xampp-control.exe
   ```

2. Controlla database
   ```sql
   USE nexio_collabora_v2;
   SELECT * FROM users WHERE email = 'tuaemail@example.com';
   ```

3. Reset password admin
   ```php
   php reset_admin.php
   ```

### Problema: "Database connection failed"

**Soluzioni**:
1. Verifica MySQL attivo
2. Controlla credenziali in `config_v2.php`
3. Verifica database esistente:
   ```sql
   SHOW DATABASES LIKE 'nexio_collabora_v2';
   ```

### Problema: "Permission denied" su directory

**Soluzioni**:
```batch
:: Windows
icacls "C:\xampp\htdocs\Nexiosolution\collabora\uploads" /grant Everyone:F
icacls "C:\xampp\htdocs\Nexiosolution\collabora\logs" /grant Everyone:F
icacls "C:\xampp\htdocs\Nexiosolution\collabora\temp" /grant Everyone:F
```

### Problema: Sessione che scade troppo presto

**Soluzione**: Modifica `php.ini`
```ini
session.gc_maxlifetime = 7200
session.cookie_lifetime = 7200
```

### Problema: Upload file non funziona

**Soluzione**: Modifica `php.ini`
```ini
upload_max_filesize = 100M
post_max_size = 100M
max_file_uploads = 20
```

---

## ❓ FAQ

### D: Posso utilizzare il sistema senza XAMPP?

**R**: Sì, il sistema è compatibile con qualsiasi stack LAMP/WAMP. Adatta i percorsi in `config_v2.php`.

### D: Come aggiungo un nuovo ruolo?

**R**: Modifica `config_v2.php`:
```php
$ROLE_PERMISSIONS['new_role'] = [
    'permission_1',
    'permission_2'
];
```

### D: Come abilito la 2FA?

**R**: In `config_v2.php`:
```php
define('ENABLE_2FA', true);
```

### D: Posso cambiare il tema/colori?

**R**: Sì, modifica `/assets/css/theme.css` o usa il theme builder in admin.

### D: Come eseguo un backup?

**R**: Usa lo script incluso:
```batch
backup_v2.bat
```

### D: Il sistema supporta LDAP/Active Directory?

**R**: Non nativamente, ma può essere integrato modificando `auth/ldap_connector.php`.

### D: Posso usare PostgreSQL invece di MySQL?

**R**: Richiede modifiche al codice. MySQL/MariaDB sono consigliati.

### D: Come monitoro le performance?

**R**: Usa il pannello admin o esegui:
```batch
php monitor_v2.php
```

---

## 📞 Supporto

### Contatti

- **Email Admin**: asamodeo@fortibyte.it
- **Documentation**: [docs.nexiosolution.com](http://docs.nexiosolution.com)
- **GitHub**: [github.com/fortibyte/nexiosolution](http://github.com/fortibyte/nexiosolution)

### Log e Debug

Tutti i log sono salvati in `/logs/`:
- `system.log` - Log di sistema
- `access.log` - Accessi utenti
- `error.log` - Errori applicazione
- `audit.log` - Audit trail

### Comandi Utili

```batch
:: Test sistema
test_v2.bat

:: Pulizia cache
clean_cache.bat

:: Status servizi
check_status.bat

:: Backup database
backup_db.bat
```

---

## 📄 Licenza

Nexio Solution V2 © 2024 Fortibyte Solutions. Tutti i diritti riservati.

---

## 🔄 Changelog

### Versione 2.0.0 (2024)
- ✅ Nuovo sistema autenticazione senza tenant code
- ✅ Multi-tenant switching per special users
- ✅ Dashboard role-based
- ✅ API v2 con JWT
- ✅ Audit log completo
- ✅ Performance ottimizzate

### Versione 1.0.0 (2023)
- Sistema base con tenant code
- Autenticazione semplice
- Gestione file base

---

## 🚀 Quick Start

```batch
# 1. Clona o scarica il progetto
cd C:\xampp\htdocs\Nexiosolution\collabora

# 2. Avvia il sistema
start_v2.bat

# 3. Accedi con:
Email: asamodeo@fortibyte.it
Password: Ricord@1991

# Sistema pronto!
```

---

*Documentazione aggiornata al: Gennaio 2024*
*Versione Sistema: 2.0.0*