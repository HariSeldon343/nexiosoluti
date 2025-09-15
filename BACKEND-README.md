# Backend Laravel - NexioSolution

## Configurazione Completata

Il backend Laravel è stato configurato e preparato per funzionare con il frontend React.

## Componenti Implementati

### 1. **Autenticazione**
- JWT Authentication con Tymon/JWT-Auth
- Laravel Sanctum per compatibilità con SPA
- Controller semplificato per login/register
- Utente demo creato automaticamente

### 2. **API Endpoints**

#### Endpoints Pubblici:
- `POST /api/login` - Login utente
- `POST /api/register` - Registrazione nuovo utente
- `GET /api/v1/health` - Health check del servizio

#### Endpoints Protetti:
- `GET /api/user` - Dati utente corrente
- `POST /api/logout` - Logout utente
- `GET /api/dashboard/stats` - Statistiche dashboard

### 3. **Database**
- Migrazioni per struttura multi-tenant
- Seeders per dati demo
- Utente admin pre-configurato

### 4. **CORS**
Configurato per accettare richieste da:
- http://localhost:3000 (Frontend React)
- http://localhost:3001
- http://127.0.0.1:3000
- http://127.0.0.1:3001

## Avvio Rapido

### Metodo 1: Script Automatico (Consigliato)
```batch
# Dalla cartella principale del progetto
start-backend.bat
```

### Metodo 2: Comandi Manuali
```batch
cd C:\xampp\htdocs\Nexiosolution\backend

# Installa dipendenze (solo prima volta)
composer install

# Crea file .env (solo prima volta)
copy .env.example .env
php artisan key:generate

# Crea database e migra
php artisan migrate --seed

# Avvia server
php artisan serve --host=127.0.0.1 --port=8000
```

## Credenziali Demo

```
Email: admin@nexiosolution.com
Password: password123
```

## Test del Backend

### Test Automatico:
```batch
# Dalla cartella backend
php test-api.php
```

### Test Manuale con cURL:
```bash
# Health Check
curl http://localhost:8000/api/v1/health

# Login
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@nexiosolution.com","password":"password123"}'
```

## Struttura Directory

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       ├── AuthController.php          # Auth con JWT
│   │   │       ├── SimpleAuthController.php    # Auth semplificato
│   │   │       └── ...altri controller
│   │   └── Middleware/
│   │       └── TenantMiddleware.php
│   └── Models/
│       ├── User.php
│       ├── Tenant.php
│       └── Company.php
├── config/
│   ├── cors.php       # Configurazione CORS
│   ├── sanctum.php    # Config Sanctum
│   └── jwt.php        # Config JWT
├── database/
│   ├── migrations/    # Migrazioni database
│   └── seeders/       # Seeders con dati demo
├── routes/
│   └── api.php        # Definizione route API
└── .env               # Configurazione ambiente
```

## Risoluzione Problemi

### Errore: "SQLSTATE[HY000] [2002] No connection"
- Verifica che MySQL sia attivo in XAMPP
- Controlla credenziali in .env

### Errore: "Class not found"
```batch
composer dump-autoload
```

### Errore: "Key not generated"
```batch
php artisan key:generate
php artisan jwt:secret
```

### Errore CORS
- Verifica config/cors.php
- Pulisci cache: `php artisan config:cache`

### Database non migrato
```batch
php artisan migrate:fresh --seed
```

## Comandi Utili

```batch
# Pulisci tutte le cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Ottimizza per produzione
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Visualizza log
type storage\logs\laravel.log

# Test connessione database
php artisan tinker
>>> DB::connection()->getPdo();
```

## Integrazione con Frontend

Il frontend React su `http://localhost:3000` è già configurato per comunicare con questo backend.

Le chiamate API utilizzano:
- Base URL: `http://localhost:8000/api`
- Headers: `Authorization: Bearer {token}`
- Content-Type: `application/json`

## Supporto

Per problemi o domande:
1. Controlla i log in `storage/logs/laravel.log`
2. Esegui `test-backend.bat` per diagnostica
3. Verifica che tutti i servizi XAMPP siano attivi