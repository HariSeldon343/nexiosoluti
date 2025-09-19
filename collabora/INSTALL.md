# Nexiosolution Collabora - Guida all'Installazione

## Requisiti di Sistema

### Software Richiesto
- **XAMPP** 8.0+ (Windows/Linux/Mac)
- **PHP** 7.4+ o 8.0+ con estensioni:
  - PDO MySQL
  - JSON
  - mbstring
  - cURL
  - GD
  - ZIP
  - fileinfo
- **MySQL/MariaDB** 5.7+ o 10.3+
- **Apache** 2.4+ con moduli:
  - mod_rewrite
  - mod_headers
  - mod_deflate
  - mod_expires

### Requisiti Hardware Minimi
- **RAM**: 2 GB (4 GB raccomandati)
- **Spazio Disco**: 500 MB per l'applicazione + spazio per i file
- **Processore**: Dual-core 2.0 GHz+

## Installazione Rapida (5 minuti)

### Metodo 1: Installazione Automatica (Raccomandato)

1. **Avvia XAMPP**
   ```cmd
   C:\xampp\xampp-control.exe
   ```
   - Avvia Apache
   - Avvia MySQL

2. **Apri il Browser**
   ```
   http://localhost/Nexiosolution/collabora/install.php
   ```

3. **Segui la Procedura Guidata**
   - Step 1: Verifica requisiti ✓
   - Step 2: Accetta licenza ✓
   - Step 3: Configura database (usa i default per XAMPP)
   - Step 4: Configura applicazione
   - Step 5: Completa installazione

4. **Login Iniziale**
   - Username: `admin`
   - Password: `admin123`

### Metodo 2: Installazione con Batch (Windows)

1. **Esegui come Amministratore**
   ```cmd
   cd C:\xampp\htdocs\Nexiosolution\collabora
   start.bat
   ```

2. **L'applicazione si aprirà automaticamente**

### Metodo 3: Installazione Manuale

1. **Crea il Database**
   ```sql
   CREATE DATABASE nexio_collabora CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Importa le Tabelle**
   ```cmd
   mysql -u root nexio_collabora < database/schema.sql
   ```

3. **Configura l'Applicazione**
   - Copia `config.example.php` in `config.php`
   - Modifica le impostazioni database

4. **Imposta i Permessi**
   ```cmd
   icacls uploads /grant Everyone:F /T
   icacls logs /grant Everyone:F /T
   ```

## Configurazione Post-Installazione

### 1. Sicurezza (IMPORTANTE!)

```php
// In config.php, modifica:
define('DEBUG_MODE', false);
define('SECRET_KEY', 'genera-una-chiave-casuale-di-32-caratteri');
```

### 2. Email (Opzionale)
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_AUTH', true);
define('SMTP_USER', 'tua-email@gmail.com');
define('SMTP_PASS', 'tua-password');
```

### 3. Limiti Upload
```php
define('MAX_UPLOAD_SIZE', 104857600); // 100MB
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);
```

### 4. Backup Automatico
Aggiungi al Task Scheduler di Windows:
```cmd
C:\xampp\htdocs\Nexiosolution\collabora\backup.bat
```
Esegui giornalmente alle 02:00

## Struttura Directory

```
collabora/
├── api/              # Endpoint API
├── assets/           # CSS, JS, immagini
├── database/         # Script SQL
├── includes/         # File PHP comuni
├── logs/             # Log di sistema
├── uploads/          # File caricati
├── .htaccess         # Configurazione Apache
├── config.php        # Configurazione app
├── index.php         # Pagina principale
├── install.php       # Script installazione
└── test.php          # Test di sistema
```

## Risoluzione Problemi Comuni

### Errore: "Database connection failed"
```cmd
# Verifica che MySQL sia attivo
C:\xampp\mysql\bin\mysql -u root -e "SHOW DATABASES;"
```

### Errore: "Permission denied"
```cmd
# Windows - Esegui come amministratore:
icacls C:\xampp\htdocs\Nexiosolution\collabora /grant Everyone:F /T
```

### Errore: "404 Not Found"
```apache
# Verifica .htaccess - Assicurati che mod_rewrite sia attivo
# In httpd.conf:
LoadModule rewrite_module modules/mod_rewrite.so
```

### Errore: "File upload failed"
```ini
# In php.ini:
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
```

## Test dell'Installazione

Esegui il test di sistema:
```
http://localhost/Nexiosolution/collabora/test.php
```

Dovrebbe mostrare:
- ✅ Database: Connected
- ✅ PHP Version: OK
- ✅ Extensions: All loaded
- ✅ Directories: Writable
- ✅ Configuration: Valid

## Aggiornamenti

### Backup Prima dell'Aggiornamento
```cmd
backup.bat
```

### Applica Aggiornamenti
```cmd
git pull origin main
php update.php
```

## Supporto

### Log di Sistema
```
logs/error.log     # Errori PHP
logs/access.log    # Accessi
logs/activity.log  # Attività utenti
```

### Debug Mode
```php
// Temporaneamente in config.php:
define('DEBUG_MODE', true);
```

### Contatti
- Email: support@nexiosolution.com
- Documentazione: [CONFIG.md](CONFIG.md)
- Testing: [TESTING.md](TESTING.md)

## Checklist Post-Installazione

- [ ] Cambia password amministratore
- [ ] Rimuovi/rinomina `install.php`
- [ ] Configura backup automatici
- [ ] Imposta limiti upload appropriati
- [ ] Configura email SMTP
- [ ] Testa upload/download file
- [ ] Verifica permessi cartelle
- [ ] Abilita HTTPS (produzione)
- [ ] Configura firewall
- [ ] Monitora logs

## License

MIT License - Copyright (c) 2024 Nexiosolution