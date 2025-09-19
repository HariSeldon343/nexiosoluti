# Documentazione Fix Redirect Loop - Nexio Collabora

## Data: 2025-01-19
## Autore: PHP Backend Architect

## Problema Identificato

Il sistema presentava un loop di reindirizzamento infinito tra `index_v2.php` e `admin/index.php` causato da:

1. **Mancanza di `session_start()`** in `admin/index.php`
2. **Session cookie path errato** - impostato a `/` invece di `/Nexiosolution/collabora/`
3. **Mancanza di logica di prevenzione loop**
4. **Configurazione sessione inconsistente tra i file**

## Soluzioni Implementate

### 1. Fix Session Initialization in admin/index.php

**File:** `/admin/index.php`

**Modifiche:**
- Aggiunto `session_start()` all'inizio del file con configurazione corretta
- Implementata logica di prevenzione loop di reindirizzamento
- Aggiunto logging dettagliato per debug

```php
// Inizializzazione sessione con path corretto
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 7200,
        'path' => '/Nexiosolution/collabora/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Prevenzione loop
if (SessionHelper::detectRedirectLoop('admin')) {
    // Mostra errore invece di continuare il loop
}
```

### 2. Correzione Session Cookie Path

**File:** `/config_v2.php`

**Modifica:**
```php
// Prima (ERRATO)
define('SESSION_PATH', '/');

// Dopo (CORRETTO)
define('SESSION_PATH', '/Nexiosolution/collabora/');
```

Questo garantisce che i cookie di sessione siano accessibili in tutta l'applicazione nella sottocartella.

### 3. SessionHelper Implementation

**Nuovo File:** `/includes/session_helper.php`

Creato un helper centralizzato per la gestione delle sessioni che fornisce:

- **Inizializzazione consistente** della sessione
- **Rilevamento automatico dei loop** di reindirizzamento
- **Logging dettagliato** per debug
- **Metodi utility** per autenticazione e autorizzazione

Caratteristiche principali:
```php
namespace Collabora\Session;

class SessionHelper {
    // Inizializzazione con configurazione corretta
    public static function init(): bool

    // Verifica autenticazione
    public static function isAuthenticated(): bool

    // Verifica ruolo admin
    public static function isAdmin(): bool

    // Rileva loop di reindirizzamento
    public static function detectRedirectLoop(string $context, int $maxRedirects = 3): bool

    // Reset contatore redirect
    public static function resetRedirectCount(string $context): void

    // Info debug
    public static function getDebugInfo(): array
}
```

### 4. Aggiornamento SimpleAuth.php

**File:** `/includes/SimpleAuth.php`

**Modifiche:**
- Configurazione sessione consistente con il resto dell'applicazione
- Uso delle costanti di configurazione da `config_v2.php`
- Logging migliorato

```php
public function __construct() {
    if (session_status() === PHP_SESSION_NONE) {
        // Carica configurazione
        if (!defined('SESSION_PATH')) {
            require_once __DIR__ . '/../config_v2.php';
        }

        // Configura parametri sessione
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => SESSION_PATH,
            // ... altri parametri
        ]);

        session_name(SESSION_NAME);
        session_start();
    }
}
```

### 5. Aggiornamento index_v2.php

**File:** `/index_v2.php`

**Modifiche:**
- Caricamento configurazione prima di inizializzare la sessione
- Uso consistente delle costanti di configurazione
- Logging per debug

```php
// Carica config prima
require_once 'config_v2.php';

// Poi inizializza sessione con config corretta
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => SESSION_PATH,
        // ... altri parametri
    ]);
    session_name(SESSION_NAME);
    session_start();
}
```

## Test e Verifica

### Script di Test Creato

**File:** `/test_redirect_fix.php`

Script completo che verifica:
1. Configurazione PHP
2. File di configurazione
3. SessionHelper functionality
4. Database connection
5. SimpleAuth login
6. Loop detection
7. File permissions
8. Admin access simulation

**Come eseguire il test:**
```bash
php test_redirect_fix.php
```

### Test Manuale nel Browser

1. Apri: `http://localhost/Nexiosolution/collabora/index_v2.php`
2. Effettua login con:
   - Email: `asamodeo@fortibyte.it`
   - Password: `Ricord@1991`
3. Verifica che:
   - Admin sia reindirizzato a `/admin/index.php`
   - Non ci siano loop (max 3 redirect)
   - La sessione persista tra le pagine

## Logging e Debug

### Log Locations

Tutti i log vengono scritti nel log degli errori PHP (solitamente in XAMPP: `C:\xampp\apache\logs\error.log`)

### Log Format

```
[SESSION_HELPER][timestamp][level][function] message
[ADMIN] message
[INDEX_V2] message
[SimpleAuth] message
```

### Come Abilitare/Disabilitare Debug

In `config_v2.php`:
```php
define('DEBUG_MODE', true);  // true per sviluppo, false per produzione
```

## Best Practices Implementate

1. **Configurazione Centralizzata**: Tutte le impostazioni di sessione in `config_v2.php`

2. **Inizializzazione Consistente**: Stesso metodo di inizializzazione in tutti i file

3. **Prevenzione Loop**: Contatore automatico con limite configurabile

4. **Logging Dettagliato**: Ogni operazione critica viene loggata

5. **Error Handling**: Pagine di errore user-friendly con info debug opzionali

6. **Session Security**:
   - HTTPOnly cookies
   - SameSite=Lax
   - Path restriction
   - Session name personalizzato

## Troubleshooting

### Problema: Loop continua dopo il fix

**Soluzioni:**
1. Svuota cache browser e cookie
2. Verifica che `SESSION_PATH` sia corretto in `config_v2.php`
3. Controlla i log per errori specifici
4. Esegui `test_redirect_fix.php` per diagnostica

### Problema: Sessione non persiste tra pagine

**Soluzioni:**
1. Verifica che tutti i file usino lo stesso `SESSION_NAME`
2. Controlla che `SESSION_PATH` corrisponda al path dell'applicazione
3. Verifica che i cookie siano abilitati nel browser

### Problema: "Headers already sent" error

**Soluzioni:**
1. Assicurati che `session_start()` sia chiamato prima di qualsiasi output
2. Verifica che non ci siano spazi/caratteri prima di `<?php`
3. Controlla encoding file (deve essere UTF-8 senza BOM)

## File Modificati - Riepilogo

1. `/admin/index.php` - Aggiunto session_start() e loop prevention
2. `/config_v2.php` - Corretto SESSION_PATH
3. `/includes/SimpleAuth.php` - Configurazione sessione consistente
4. `/index_v2.php` - Inizializzazione sessione corretta
5. `/includes/session_helper.php` - **NUOVO** - Helper centralizzato
6. `/test_redirect_fix.php` - **NUOVO** - Script di test

## Raccomandazioni Future

1. **Migrazione a Redis/Memcached**: Per sessioni in ambiente multi-server
2. **Session Rotation**: Rigenerare session ID dopo login per sicurezza
3. **Session Timeout**: Implementare timeout inattività lato server
4. **Monitoring**: Aggiungere metriche per monitorare redirect loop in produzione
5. **Rate Limiting**: Implementare rate limiting su tentativi di login

## Conclusione

Il problema del redirect loop è stato risolto implementando:
- Configurazione consistente delle sessioni
- Path corretto per cookie in sottocartella
- Logica di prevenzione loop robusta
- Logging dettagliato per debugging

Il sistema ora gestisce correttamente le sessioni e previene loop di reindirizzamento, mantenendo la sicurezza e fornendo strumenti di debug utili per la manutenzione futura.