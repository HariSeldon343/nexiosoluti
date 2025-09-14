# 🌐 Configurazione NexioSolution per Localhost

## Panoramica

NexioSolution è configurato per funzionare su **localhost** con supporto multi-tenant tramite **header HTTP** invece di subdomain. Questo permette di utilizzare la piattaforma con tunneling Cloudflare o altri servizi simili.

## 🔧 Configurazione Multi-Tenant su Localhost

### Modalità di Identificazione Tenant

La piattaforma supporta 4 modalità per identificare il tenant (in ordine di priorità):

1. **Header HTTP** `X-Tenant-ID` (raccomandato per API)
2. **Query Parameter** `?tenant=demo` (per test)
3. **Sessione** (persistenza navigazione)
4. **Path-based** `/tenant/demo/dashboard` (opzionale)

### Come Funziona

```
http://localhost:3000 → Tenant di default (demo)
http://localhost:3000?tenant=acme → Tenant ACME
```

Con header HTTP:
```bash
curl -H "X-Tenant-ID: acme" http://localhost:8000/api/users
```

## 📋 URL e Porte

- **Frontend React**: http://localhost:3000
- **Backend Laravel API**: http://localhost:8000
- **WebSocket Server**: ws://localhost:6001
- **PhpMyAdmin**: http://localhost/phpmyadmin
- **Mailhog** (test email): http://localhost:8025

## 🚀 Avvio Rapido

```batch
# Installa tutto
C:\xampp\htdocs\Nexiosolution\install.bat

# Avvia i servizi
C:\xampp\htdocs\Nexiosolution\start-all.bat

# Apri browser
http://localhost:3000
```

## 👥 Gestione Tenant

### Creare un Nuovo Tenant

```bash
cd C:\xampp\htdocs\Nexiosolution\backend
php artisan tinker

>>> use App\Models\Tenant;
>>> Tenant::create([
    'name' => 'ACME Corporation',
    'code' => 'acme',  # Identificatore univoco
    'email' => 'admin@acme.com',
    'is_active' => true,
    'settings' => [
        'primary_color' => '#FF6B6B',
        'logo_path' => null,
        'timezone' => 'Europe/Rome'
    ]
]);
```

### Switch Tenant nel Frontend

1. **Tramite UI**: Usa il selector nel menu superiore
2. **Tramite URL**: Aggiungi `?tenant=acme`
3. **Tramite API**: Invia header `X-Tenant-ID: acme`

### Configurare Tenant di Default

In `backend/.env`:
```env
DEFAULT_TENANT=demo
TENANT_IDENTIFICATION=header
TENANT_HEADER=X-Tenant-ID
```

## 🌍 Tunneling con Cloudflare

### Setup Cloudflare Tunnel

1. Installa Cloudflare Tunnel:
```bash
# Windows
winget install Cloudflare.cloudflared

# O scarica da
https://github.com/cloudflare/cloudflared/releases
```

2. Autentica:
```bash
cloudflared tunnel login
```

3. Crea tunnel:
```bash
cloudflared tunnel create nexiosolution
```

4. Configura routing:
```yaml
# config.yml
tunnel: nexiosolution
credentials-file: C:\Users\[user]\.cloudflared\[id].json

ingress:
  - hostname: nexiosolution.example.com
    service: http://localhost:3000
  - hostname: api.nexiosolution.example.com
    service: http://localhost:8000
  - hostname: ws.nexiosolution.example.com
    service: ws://localhost:6001
  - service: http_status:404
```

5. Avvia tunnel:
```bash
cloudflared tunnel run nexiosolution
```

### Configurazione Headers per Cloudflare

In `frontend/src/services/api.js`:
```javascript
// Aggiungi tenant header per Cloudflare
if (window.location.hostname.includes('example.com')) {
  const subdomain = window.location.hostname.split('.')[0];
  api.defaults.headers['X-Tenant-ID'] = subdomain;
}
```

## 🔒 Sicurezza su Localhost

### Certificati SSL Locali (Opzionale)

1. Genera certificato self-signed:
```bash
openssl req -x509 -newkey rsa:4096 -keyout key.pem -out cert.pem -days 365 -nodes
```

2. Configura in `.env`:
```env
APP_URL=https://localhost:8000
FORCE_HTTPS=false  # Non forzare in dev
```

### CORS Configuration

In `backend/config/cors.php`:
```php
'allowed_origins' => [
    'http://localhost:3000',
    'http://localhost:8000',
    'https://*.example.com'  // Per Cloudflare
],
```

## 🐛 Troubleshooting

### Problema: "Tenant non trovato"

**Soluzione**:
1. Verifica che il tenant esista nel DB
2. Controlla che sia attivo (`is_active = 1`)
3. Verifica l'header `X-Tenant-ID` nelle richieste

### Problema: WebSocket non si connette

**Soluzione**:
```batch
# Verifica che la porta sia libera
netstat -an | findstr :6001

# Riavvia WebSocket server
php artisan websockets:serve
```

### Problema: Multi-tenant non funziona

**Soluzione**:
1. Verifica in `.env`:
```env
TENANT_IDENTIFICATION=header
TENANT_HEADER=X-Tenant-ID
```

2. Controlla il middleware in `app/Http/Kernel.php`:
```php
protected $middlewareGroups = [
    'api' => [
        \App\Http\Middleware\TenantIdentification::class,
        // ...
    ],
];
```

## 📊 Testing Multi-Tenant

### Test con Postman

1. Crea collection con variabile `tenant_id`
2. Aggiungi header a tutte le richieste:
   - Key: `X-Tenant-ID`
   - Value: `{{tenant_id}}`

### Test con cURL

```bash
# Tenant Demo
curl -H "X-Tenant-ID: demo" http://localhost:8000/api/users

# Tenant ACME
curl -H "X-Tenant-ID: acme" http://localhost:8000/api/users
```

### Test Frontend

```javascript
// Console browser
localStorage.setItem('X-Tenant-ID', 'acme');
location.reload();
```

## 🚢 Deploy in Produzione

Quando sei pronto per la produzione con domini reali:

1. Cambia in `.env`:
```env
TENANT_IDENTIFICATION=subdomain
```

2. Configura DNS wildcard:
```
*.nexiosolution.com → Server IP
```

3. Configura Apache/Nginx per subdomain wildcard

4. Aggiorna CORS e sicurezza

---

© 2024 NexioSolution - Configurazione Localhost