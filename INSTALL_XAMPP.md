# 📚 Guida Installazione NexioSolution su XAMPP

## 📋 Indice
1. [Prerequisiti](#prerequisiti)
2. [Installazione XAMPP](#installazione-xampp)
3. [Configurazione Ambiente](#configurazione-ambiente)
4. [Installazione NexioSolution](#installazione-nexiosolution)
5. [Configurazione Avanzata](#configurazione-avanzata)
6. [Test e Verifica](#test-e-verifica)
7. [Troubleshooting](#troubleshooting)
8. [Manutenzione](#manutenzione)

---

## 📌 Prerequisiti

### Requisiti di Sistema
- **Sistema Operativo**: Windows 10/11, macOS 10.14+, Linux Ubuntu 20.04+
- **RAM**: Minimo 4GB (consigliato 8GB)
- **Spazio Disco**: Minimo 2GB liberi
- **Processore**: Dual-core 2GHz o superiore

### Software Richiesto
- **XAMPP 8.2+** con:
  - PHP 8.2 o superiore
  - MySQL 8.0 / MariaDB 10.11
  - Apache 2.4
- **Node.js 18+** e npm
- **Composer 2.0+**
- **Git** (opzionale ma consigliato)

### Download Links
- XAMPP: https://www.apachefriends.org/download.html
- Node.js: https://nodejs.org/
- Composer: https://getcomposer.org/
- Git: https://git-scm.com/

---

## 🔧 Installazione XAMPP

### Windows

1. **Download e Installazione**
   ```
   1. Scarica XAMPP da https://www.apachefriends.org/
   2. Esegui il file .exe come Amministratore
   3. Disabilita UAC se richiesto
   4. Installa in C:\xampp (percorso predefinito)
   5. Seleziona componenti: Apache, MySQL, PHP, phpMyAdmin
   ```

2. **Configurazione Firewall**
   ```
   - Consenti Apache (porta 80, 443)
   - Consenti MySQL (porta 3306)
   - Consenti Node.js (porta 3000, 5173)
   ```

3. **Avvio Servizi**
   ```
   1. Apri XAMPP Control Panel
   2. Avvia Apache
   3. Avvia MySQL
   4. Verifica che entrambi siano verdi
   ```

### macOS

1. **Download e Installazione**
   ```bash
   # Scarica XAMPP-VM per macOS
   # Installa tramite DMG
   # Oppure usa Homebrew:
   brew install --cask xampp
   ```

2. **Permessi**
   ```bash
   sudo chmod -R 755 /Applications/XAMPP
   ```

### Linux

1. **Download e Installazione**
   ```bash
   # Download
   wget https://sourceforge.net/projects/xampp/files/XAMPP%20Linux/8.2.12/xampp-linux-x64-8.2.12-0-installer.run

   # Permessi esecuzione
   chmod +x xampp-linux-x64-8.2.12-0-installer.run

   # Installazione
   sudo ./xampp-linux-x64-8.2.12-0-installer.run
   ```

2. **Avvio Servizi**
   ```bash
   sudo /opt/lampp/lampp start
   ```

---

## ⚙️ Configurazione Ambiente

### 1. PHP Configuration

Modifica `C:\xampp\php\php.ini`:

```ini
; Aumenta limiti
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
post_max_size = 100M
upload_max_filesize = 100M

; Abilita estensioni richieste
extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=mysqli
extension=openssl
extension=pdo_mysql
extension=zip

; Timezone
date.timezone = Europe/Rome
```

### 2. MySQL Configuration

Modifica `C:\xampp\mysql\bin\my.ini`:

```ini
[mysqld]
# Aumenta performance
innodb_buffer_pool_size = 256M
max_connections = 200
query_cache_size = 32M
query_cache_type = 1

# Charset UTF8MB4
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
```

### 3. Apache Configuration

Abilita moduli in `C:\xampp\apache\conf\httpd.conf`:

```apache
# Decommenta queste righe
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule proxy_module modules/mod_proxy.so
LoadModule proxy_http_module modules/mod_proxy_http.so
LoadModule proxy_wstunnel_module modules/mod_proxy_wstunnel.so
LoadModule headers_module modules/mod_headers.so
LoadModule deflate_module modules/mod_deflate.so
LoadModule expires_module modules/mod_expires.so
```

---

## 📦 Installazione NexioSolution

### Metodo 1: Installazione Automatica (Consigliato)

1. **Posiziona i file del progetto**
   ```
   Copia la cartella Nexiosolution in:
   C:\xampp\htdocs\Nexiosolution
   ```

2. **Esegui script di installazione**
   ```batch
   # Apri CMD come Amministratore
   cd C:\xampp\htdocs\Nexiosolution\scripts
   setup-xampp.bat
   ```

3. **Segui le istruzioni**
   - Inserisci nome database (default: nexiosolution)
   - Inserisci username MySQL (default: root)
   - Inserisci password MySQL (lascia vuoto se non impostata)

### Metodo 2: Installazione Manuale

#### Step 1: Backend Laravel

```bash
# Naviga alla directory backend
cd C:\xampp\htdocs\Nexiosolution\backend

# Installa dipendenze PHP
composer install

# Copia file environment
copy .env.example .env

# Genera application key
php artisan key:generate

# Configura database in .env
notepad .env
```

Modifica `.env`:
```env
APP_NAME=NexioSolution
APP_ENV=local
APP_URL=http://nexiosolution.local

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nexiosolution
DB_USERNAME=root
DB_PASSWORD=

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
```

```bash
# Crea database
mysql -u root -e "CREATE DATABASE nexiosolution CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Esegui migrazioni
php artisan migrate

# Seed database
php artisan db:seed

# Link storage
php artisan storage:link

# Ottimizza
php artisan optimize
```

#### Step 2: Frontend React

```bash
# Naviga alla directory frontend
cd C:\xampp\htdocs\Nexiosolution\frontend

# Installa dipendenze Node
npm install

# Crea file .env
copy .env.example .env

# Build produzione
npm run build
```

Modifica `.env`:
```env
VITE_API_URL=http://nexiosolution.local/api
VITE_APP_NAME=NexioSolution
VITE_WEBSOCKET_URL=ws://localhost:6001
```

#### Step 3: Apache VirtualHost

1. **Aggiungi VirtualHost**

   Modifica `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:

   ```apache
   <VirtualHost *:80>
       ServerName nexiosolution.local
       ServerAlias *.nexiosolution.local
       DocumentRoot "C:/xampp/htdocs/Nexiosolution/backend/public"

       <Directory "C:/xampp/htdocs/Nexiosolution/backend/public">
           Options Indexes FollowSymLinks
           AllowOverride All
           Require all granted
       </Directory>

       ErrorLog "logs/nexiosolution-error.log"
       CustomLog "logs/nexiosolution-access.log" common
   </VirtualHost>
   ```

2. **Modifica hosts file**

   Aggiungi a `C:\Windows\System32\drivers\etc\hosts`:

   ```
   127.0.0.1 nexiosolution.local
   127.0.0.1 api.nexiosolution.local
   127.0.0.1 ws.nexiosolution.local
   ```

3. **Riavvia Apache**

---

## 🚀 Configurazione Avanzata

### CalDAV Configuration

1. **Installa dipendenze SabreDAV**
   ```bash
   cd backend
   composer require sabre/dav
   ```

2. **Configura endpoint CalDAV**
   ```php
   // routes/api.php
   Route::any('/dav/{path?}', 'CalDAVController@handle')
        ->where('path', '.*');
   ```

3. **Test con client CalDAV**
   - URL: `http://nexiosolution.local/dav`
   - Username: tuo email
   - Password: tua password

### OnlyOffice Integration

1. **Opzione 1: Docker**
   ```bash
   docker run -d -p 8080:80 onlyoffice/documentserver
   ```

2. **Opzione 2: Server remoto**
   ```env
   ONLYOFFICE_URL=https://documentserver.example.com
   ONLYOFFICE_JWT_SECRET=your-secret-key
   ```

### WebSocket Configuration

1. **Installa Laravel WebSockets**
   ```bash
   cd backend
   composer require beyondcode/laravel-websockets
   php artisan vendor:publish --provider="BeyondCode\LaravelWebSockets\WebSocketsServiceProvider"
   ```

2. **Avvia WebSocket server**
   ```bash
   php artisan websockets:serve
   ```

3. **Configura frontend**
   ```javascript
   // frontend/src/config/websocket.js
   window.Echo = new Echo({
       broadcaster: 'pusher',
       key: 'your-pusher-key',
       wsHost: 'localhost',
       wsPort: 6001,
       forceTLS: false,
       disableStats: true,
   });
   ```

### Queue Workers

1. **Configurazione**
   ```bash
   # Avvia worker
   php artisan queue:work --sleep=3 --tries=3

   # Oppure usa Supervisor (Linux/Mac)
   sudo apt-get install supervisor
   ```

2. **Supervisor config**
   ```ini
   [program:nexiosolution-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /xampp/htdocs/Nexiosolution/backend/artisan queue:work
   autostart=true
   autorestart=true
   user=www-data
   numprocs=2
   ```

### Email Configuration

1. **Sviluppo (Mailhog)**
   ```bash
   # Installa Mailhog
   go get github.com/mailhog/MailHog

   # Avvia
   MailHog

   # Configura .env
   MAIL_HOST=localhost
   MAIL_PORT=1025
   ```

2. **Produzione (SMTP)**
   ```env
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=your-email@gmail.com
   MAIL_PASSWORD=your-app-password
   MAIL_ENCRYPTION=tls
   ```

---

## ✅ Test e Verifica

### 1. Test Connessione Database

```bash
php artisan tinker
>>> DB::connection()->getPdo();
```

### 2. Test CalDAV

```bash
# Con curl
curl -X PROPFIND http://nexiosolution.local/dav \
     -H "Authorization: Basic $(echo -n 'user:pass' | base64)"
```

### 3. Test WebSocket

```javascript
// Console browser
Echo.channel('test')
    .listen('TestEvent', (e) => {
        console.log('Received:', e);
    });
```

### 4. Test Email

```bash
php artisan tinker
>>> Mail::raw('Test email', function($message) {
>>>     $message->to('test@example.com')->subject('Test');
>>> });
```

### 5. Test API

```bash
# Login
curl -X POST http://nexiosolution.local/api/login \
     -H "Content-Type: application/json" \
     -d '{"email":"admin@nexiosolution.local","password":"password"}'

# Test autenticato
curl http://nexiosolution.local/api/user \
     -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 🔧 Troubleshooting

### Problemi Comuni

#### 1. "500 Internal Server Error"

**Soluzione:**
```bash
# Controlla permessi
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

#### 2. "Database connection refused"

**Soluzione:**
- Verifica che MySQL sia avviato
- Controlla credenziali in .env
- Verifica porta 3306 non bloccata

#### 3. "npm install fallisce"

**Soluzione:**
```bash
# Clear npm cache
npm cache clean --force

# Rimuovi node_modules
rm -rf node_modules package-lock.json

# Reinstalla
npm install
```

#### 4. "WebSocket connection failed"

**Soluzione:**
- Verifica che il server WebSocket sia avviato
- Controlla firewall per porta 6001
- Verifica configurazione in .env e config/broadcasting.php

#### 5. "CalDAV non funziona"

**Soluzione:**
- Verifica modulo mod_dav abilitato in Apache
- Controlla autenticazione Basic abilitata
- Verifica permessi directory

### Log Files

Controlla i log per debug:

```
# Laravel logs
backend/storage/logs/laravel.log

# Apache logs
C:\xampp\apache\logs\error.log
C:\xampp\apache\logs\nexiosolution-error.log

# MySQL logs
C:\xampp\mysql\data\*.err

# PHP logs
C:\xampp\php\logs\php_error_log
```

---

## 🛠 Manutenzione

### Backup Automatico

1. **Setup backup giornaliero**
   ```bash
   # Windows Task Scheduler
   schtasks /create /tn "NexioBackup" /tr "C:\xampp\htdocs\Nexiosolution\scripts\backup.bat" /sc daily /st 02:00
   ```

2. **Backup manuale**
   ```bash
   cd scripts
   bash backup.sh full
   ```

### Aggiornamenti

1. **Aggiorna dipendenze**
   ```bash
   # Backend
   cd backend
   composer update
   php artisan migrate

   # Frontend
   cd ../frontend
   npm update
   npm run build
   ```

2. **Clear cache dopo aggiornamenti**
   ```bash
   php artisan optimize:clear
   php artisan optimize
   ```

### Monitoraggio

1. **Controlla stato servizi**
   ```bash
   # Script di monitoraggio
   php artisan monitor:check
   ```

2. **Analizza performance**
   ```bash
   # Laravel Telescope (se installato)
   php artisan telescope:prune
   ```

### Pulizia

```bash
# Pulizia logs vecchi
php artisan log:clear

# Pulizia cache
php artisan cache:clear
php artisan view:clear

# Pulizia sessioni
php artisan session:clear

# Ottimizza database
php artisan db:optimize
```

---

## 📞 Supporto

### Risorse Utili

- **Documentazione Laravel**: https://laravel.com/docs
- **Documentazione React**: https://react.dev
- **Forum XAMPP**: https://community.apachefriends.org
- **Stack Overflow**: https://stackoverflow.com/questions/tagged/xampp

### Contatti

- **Email Supporto**: support@nexiosolution.local
- **Issue Tracker**: https://github.com/nexiosolution/issues
- **Chat Community**: https://discord.gg/nexiosolution

### Video Tutorial

1. [Installazione Base](https://youtube.com/watch?v=...)
2. [Configurazione CalDAV](https://youtube.com/watch?v=...)
3. [Setup OnlyOffice](https://youtube.com/watch?v=...)
4. [Troubleshooting Comune](https://youtube.com/watch?v=...)

---

## 📄 Licenza

NexioSolution è rilasciato sotto licenza MIT. Vedi file LICENSE per dettagli.

---

**Ultimo aggiornamento**: Gennaio 2025
**Versione guida**: 1.0.0