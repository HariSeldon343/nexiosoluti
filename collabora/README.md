# Nexio File Manager - Sistema Multi-Tenant PHP 8.3+

Sistema completo di gestione file multi-tenant sviluppato in PHP vanilla (senza framework) con isolamento dati per tenant, deduplicazione SHA256, e gestione avanzata dei permessi.

## 🚀 Caratteristiche Principali

- **Multi-Tenant**: Isolamento completo dei dati per tenant
- **Autenticazione Session-based**: Sistema sicuro con CSRF protection
- **Deduplicazione File**: SHA256 hashing per ottimizzare lo storage
- **Soft Delete**: Cestino con retention di 30 giorni
- **Drag & Drop**: Upload intuitivo con trascinamento
- **ZIP Download**: Download multipli file come archivio
- **Gestione Cartelle**: Struttura gerarchica con breadcrumb
- **Ricerca Real-time**: Ricerca veloce tra tutti i file
- **Activity Logging**: Tracciamento completo delle attività

## 📋 Requisiti

- PHP 8.3+
- MySQL 5.7+ o MariaDB 10.3+
- XAMPP per Windows (o equivalente)
- Estensioni PHP richieste:
  - PDO
  - PDO_MySQL
  - FileInfo
  - ZIP
  - MBString
  - OpenSSL

## 🛠️ Installazione

### 1. Setup Database

```bash
# Accedi a http://localhost/Nexiosolution/collabora/setup.php
```

Il setup script creerà automaticamente:
- Tutte le tabelle necessarie
- Tenant demo
- Utenti di test
- Cartelle di esempio
- Directory upload

### 2. Credenziali Default

**Amministratore:**
- Tenant: `demo`
- Username: `admin`
- Password: `password123`

**Manager:**
- Tenant: `demo`
- Username: `manager`
- Password: `password123`

**Utente Standard:**
- Tenant: `demo`
- Username: `user`
- Password: `password123`

## 📁 Struttura Progetto

```
/collabora
├── /api                # API endpoints
│   ├── auth.php       # Autenticazione
│   ├── files.php      # Gestione file
│   └── folders.php    # Gestione cartelle
├── /includes          # Classi core
│   ├── db.php         # Database layer
│   ├── auth.php       # Autenticazione
│   ├── FileManager.php    # Gestione file
│   └── TenantManager.php  # Multi-tenancy
├── /uploads           # Storage file (per tenant)
├── /logs             # Log di sistema
├── config.php        # Configurazione
├── index.php         # Entry point principale
└── setup.php         # Script installazione
```

## 🔒 Sicurezza

### Protezioni Implementate

- **SQL Injection**: Prepared statements su tutte le query
- **XSS**: Output escaping e sanitizzazione input
- **CSRF**: Token su tutte le operazioni write
- **Path Traversal**: Validazione percorsi file
- **MIME Type Validation**: Controllo reale tipo file
- **Rate Limiting**: Protezione da abusi
- **Session Security**: HTTPOnly, SameSite, rigenerazione ID
- **Password Hashing**: Argon2id con salt

### Headers di Sicurezza

```
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
```

## 🎯 API Endpoints

### Autenticazione

```php
POST /api/auth.php?action=login
POST /api/auth.php?action=logout
GET  /api/auth.php?action=check
POST /api/auth.php?action=register
POST /api/auth.php?action=change_password
```

### File

```php
POST   /api/files.php?action=upload
GET    /api/files.php?action=download&id={id}
GET    /api/files.php?action=download_zip&ids={id1,id2}
GET    /api/files.php?action=list&folder_id={id}
DELETE /api/files.php?action=delete&id={id}
POST   /api/files.php?action=restore&id={id}
PUT    /api/files.php?action=rename&id={id}
PUT    /api/files.php?action=move&id={id}
GET    /api/files.php?action=trash
GET    /api/files.php?action=search&q={query}
```

### Cartelle

```php
POST   /api/folders.php?action=create
GET    /api/folders.php?action=list&parent_id={id}
GET    /api/folders.php?action=tree
PUT    /api/folders.php?action=rename&id={id}
PUT    /api/folders.php?action=move&id={id}
DELETE /api/folders.php?action=delete&id={id}
GET    /api/folders.php?action=breadcrumb&id={id}
```

## 👥 Sistema Ruoli e Permessi

### Admin
- Accesso completo al sistema
- Gestione utenti e tenant
- Eliminazione permanente file
- Pulizia cestino
- Visualizzazione log

### Manager
- Gestione completa file e cartelle
- Visualizzazione utenti
- Download ZIP
- Accesso cestino

### User
- Upload e visualizzazione file
- Creazione cartelle
- Download singoli file
- Modifica propri file

## 📊 Multi-Tenancy

### Isolamento Dati

Ogni tenant ha:
- Database records isolati (tenant_id)
- Directory upload separata (/uploads/{tenant_code}/)
- Limite storage personalizzato
- Utenti indipendenti
- Log attività separati

### Identificazione Tenant

Il sistema identifica il tenant tramite:
1. Subdomain (es: demo.localhost)
2. Header HTTP (X-Tenant-Code)
3. Sessione attiva
4. Parametro URL

## 🔧 Configurazione

### config.php

```php
// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'nexiosolution');
define('DB_USER', 'root');
define('DB_PASS', '');

// Limiti
define('MAX_FILE_SIZE', 104857600); // 100MB
define('MAX_ZIP_SIZE', 524288000);  // 500MB

// Retention
define('TRASH_RETENTION_DAYS', 30);
define('LOG_RETENTION_DAYS', 90);
```

## 🚦 Performance

### Ottimizzazioni Implementate

- **Deduplicazione SHA256**: Riduzione storage per file duplicati
- **Lazy Loading**: Caricamento on-demand
- **Query Optimization**: Indici su tutte le foreign key
- **File Chunking**: Upload di file grandi a blocchi
- **Response Caching**: Cache headers per asset statici
- **Connection Pooling**: Riutilizzo connessioni DB

## 🧪 Testing

### Test Funzionali

```bash
# 1. Test Login
curl -X POST http://localhost/Nexiosolution/collabora/api/auth.php?action=login \
  -d "tenant_code=demo&username=admin&password=password123"

# 2. Test Upload
curl -X POST http://localhost/Nexiosolution/collabora/api/files.php?action=upload \
  -H "X-CSRF-Token: {token}" \
  -F "files[]=@test.pdf"

# 3. Test Download
curl http://localhost/Nexiosolution/collabora/api/files.php?action=download&id=1
```

## 📝 Logging

Il sistema logga:
- Accessi e logout
- Upload/download file
- Modifiche file e cartelle
- Errori database
- Tentativi di accesso falliti

Log location: `/logs/`

## 🔄 Backup

### Database Backup

```sql
mysqldump -u root nexiosolution > backup_$(date +%Y%m%d).sql
```

### File Backup

```bash
tar -czf uploads_backup.tar.gz uploads/
```

## 📈 Statistiche Storage

Il sistema traccia per ogni tenant:
- Storage totale allocato
- Storage utilizzato
- Numero file attivi
- File nel cestino
- Cartelle create

## 🐛 Troubleshooting

### Errore "Permission Denied"

```bash
# Windows XAMPP
chmod -R 755 uploads/
chmod -R 755 logs/
```

### Errore Foreign Key

```sql
SET FOREIGN_KEY_CHECKS = 0;
-- Drop tables
SET FOREIGN_KEY_CHECKS = 1;
```

### Session non funzionante

Verifica in php.ini:
```ini
session.save_path = "C:\xampp\tmp"
session.gc_maxlifetime = 7200
```

## 🚀 Deployment

### Produzione Checklist

1. ✅ Cambia credenziali database
2. ✅ Imposta ENVIRONMENT = 'production'
3. ✅ Abilita HTTPS
4. ✅ Configura backup automatici
5. ✅ Imposta limiti upload appropriati
6. ✅ Configura log rotation
7. ✅ Rimuovi setup.php
8. ✅ Imposta permessi corretti su directory

## 📜 Licenza

Sistema proprietario - Tutti i diritti riservati

## 👨‍💻 Sviluppato da

Nexio Solutions - Sistema Multi-tenant File Management

---

**Note**: Questo sistema è stato sviluppato completamente in PHP vanilla senza l'uso di framework esterni, dimostrando la possibilità di creare applicazioni enterprise-grade con PHP puro e pattern architetturali solidi.