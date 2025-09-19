# Nexiosolution Collabora - Guida al Testing

## Panoramica

Questa guida fornisce procedure dettagliate per testare tutte le funzionalità del sistema di gestione file Nexiosolution Collabora.

## Test Automatici

### Esecuzione Test Suite Completa
```bash
# Esegui tutti i test
php test.php --all

# Test specifici
php test.php --database
php test.php --upload
php test.php --api
php test.php --security
```

### Output Atteso
```
===========================================
 NEXIOSOLUTION COLLABORA - SYSTEM TEST
===========================================
[✓] PHP Version: 8.0.25
[✓] Database Connection: Connected
[✓] Required Extensions: All loaded
[✓] Directory Permissions: All writable
[✓] Configuration: Valid
[✓] API Endpoints: All responding
[✓] File Operations: Working
===========================================
 ALL TESTS PASSED! System ready.
===========================================
```

## Test Manuali

### 1. Test di Autenticazione

#### Test Login
1. Accedi a: `http://localhost/Nexiosolution/collabora/`
2. Inserisci credenziali:
   - Username: `admin`
   - Password: `admin123`
3. **Risultato atteso**: Redirect alla dashboard

#### Test Logout
1. Clicca su "Logout" nel menu utente
2. **Risultato atteso**: Redirect alla pagina di login

#### Test Sessione
1. Login come admin
2. Apri una nuova scheda
3. Vai a: `http://localhost/Nexiosolution/collabora/`
4. **Risultato atteso**: Già autenticato, mostra dashboard

#### Test Password Errata
1. Inserisci password errata
2. **Risultato atteso**: Messaggio "Credenziali non valide"

### 2. Test Upload File

#### Upload Singolo File
1. Clicca "Upload File"
2. Seleziona un file PDF < 100MB
3. **Risultato atteso**:
   - Progress bar al 100%
   - File visibile nella lista
   - Messaggio di successo

#### Upload Multiplo
1. Seleziona 5 file contemporaneamente
2. **Risultato atteso**:
   - Tutti i file caricati
   - Progress bar per ogni file

#### Test Limiti Upload
1. Prova a caricare file > 100MB
2. **Risultato atteso**: Errore "File troppo grande"

#### Test Estensioni
1. Prova a caricare file .exe
2. **Risultato atteso**: Errore "Tipo file non permesso"

### 3. Test Gestione Cartelle

#### Creazione Cartella
1. Clicca "Nuova Cartella"
2. Inserisci nome: "Test Folder"
3. **Risultato atteso**: Cartella creata e visibile

#### Navigazione
1. Doppio click sulla cartella
2. **Risultato atteso**:
   - Entra nella cartella
   - Breadcrumb aggiornato

#### Rinomina Cartella
1. Click destro → Rinomina
2. Nuovo nome: "Renamed Folder"
3. **Risultato atteso**: Nome aggiornato

#### Elimina Cartella
1. Click destro → Elimina
2. Conferma eliminazione
3. **Risultato atteso**: Cartella rimossa

### 4. Test Operazioni File

#### Download File
1. Click su un file → Download
2. **Risultato atteso**: File scaricato correttamente

#### Anteprima File
1. Click su file immagine/PDF
2. **Risultato atteso**: Mostra anteprima

#### Condivisione File
1. Click destro → Condividi
2. Genera link
3. **Risultato atteso**:
   - Link generato tipo: `share/ABC123`
   - Link funzionante

#### Ricerca File
1. Usa barra di ricerca: "test"
2. **Risultato atteso**: Mostra solo file con "test" nel nome

### 5. Test API

#### Test Autenticazione API
```bash
curl -X POST http://localhost/Nexiosolution/collabora/api/auth.php \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```
**Risposta attesa**: Token JWT

#### Test Lista File
```bash
curl -X GET http://localhost/Nexiosolution/collabora/api/files.php \
  -H "Authorization: Bearer YOUR_TOKEN"
```
**Risposta attesa**: JSON array di file

#### Test Upload via API
```bash
curl -X POST http://localhost/Nexiosolution/collabora/api/files.php \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@test.pdf"
```
**Risposta attesa**: File ID e success:true

### 6. Test Performance

#### Test Carico
1. Carica 100 file da 1MB ciascuno
2. **Tempo atteso**: < 60 secondi totali

#### Test Concurrent Users
1. Apri 5 browser/tab
2. Login con utenti diversi
3. Upload simultaneo
4. **Risultato atteso**: Nessun conflitto

#### Test Large Directory
1. Crea cartella con 1000 file
2. Apri la cartella
3. **Tempo caricamento**: < 3 secondi

### 7. Test Sicurezza

#### SQL Injection
1. Nel campo username inserisci: `admin' OR '1'='1`
2. **Risultato atteso**: Login fallisce

#### XSS Attack
1. Nome file: `<script>alert('XSS')</script>.txt`
2. **Risultato atteso**: Script non eseguito, nome sanitizzato

#### Path Traversal
1. Prova accesso: `/uploads/../config.php`
2. **Risultato atteso**: Accesso negato

#### CSRF Protection
1. Prova richiesta POST senza token
2. **Risultato atteso**: Richiesta rifiutata

### 8. Test Browser Compatibility

#### Browser da Testare
- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ⚠️ Internet Explorer (non supportato)

#### Test Responsive
1. Ridimensiona finestra a 320px
2. **Risultato atteso**: Layout mobile responsive

### 9. Test Backup e Restore

#### Backup Manuale
```cmd
C:\xampp\htdocs\Nexiosolution\collabora\backup.bat
```
**Risultato atteso**: File backup creato in `/backups/`

#### Test Restore
1. Elimina un file
2. Esegui restore dal backup
3. **Risultato atteso**: File ripristinato

## Checklist Test Completa

### Test Funzionali
- [ ] Login/Logout funziona
- [ ] Upload file singolo
- [ ] Upload file multipli
- [ ] Download file
- [ ] Creazione cartelle
- [ ] Navigazione cartelle
- [ ] Rinomina file/cartelle
- [ ] Elimina file/cartelle
- [ ] Ricerca file
- [ ] Condivisione link
- [ ] Anteprima file

### Test Non Funzionali
- [ ] Performance upload (< 1 sec/MB)
- [ ] Tempo caricamento pagina (< 3 sec)
- [ ] Responsive design
- [ ] Browser compatibility
- [ ] Accessibilità (WCAG 2.1)

### Test di Sicurezza
- [ ] SQL Injection protetto
- [ ] XSS protetto
- [ ] CSRF protetto
- [ ] Path traversal bloccato
- [ ] File permissions corretti
- [ ] Password hash sicuri
- [ ] Session management

### Test di Sistema
- [ ] Backup funziona
- [ ] Restore funziona
- [ ] Log registrati correttamente
- [ ] Email inviate
- [ ] Quota storage rispettata
- [ ] Cleanup file temporanei

## Script di Test Automatizzati

### Test Database Connectivity
```php
<?php
require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    echo "✅ Database connected\n";
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
```

### Test File Upload
```php
<?php
function testFileUpload() {
    $testFile = [
        'name' => 'test.txt',
        'type' => 'text/plain',
        'tmp_name' => tempnam(sys_get_temp_dir(), 'test'),
        'size' => 1024,
        'error' => 0
    ];

    file_put_contents($testFile['tmp_name'], 'Test content');

    // Simula upload
    $uploadDir = __DIR__ . '/uploads/';
    $destination = $uploadDir . uniqid() . '_' . $testFile['name'];

    if (move_uploaded_file($testFile['tmp_name'], $destination)) {
        echo "✅ File upload works\n";
        unlink($destination); // Cleanup
    } else {
        echo "❌ File upload failed\n";
    }
}

testFileUpload();
```

### Test API Response
```php
<?php
function testAPI($endpoint) {
    $ch = curl_init("http://localhost/Nexiosolution/collabora/api/$endpoint");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 || $httpCode === 401) {
        echo "✅ API $endpoint responding\n";
    } else {
        echo "❌ API $endpoint error (HTTP $httpCode)\n";
    }
}

testAPI('auth.php');
testAPI('files.php');
testAPI('folders.php');
```

## Report dei Bug

### Template Segnalazione Bug
```markdown
**Descrizione**: [Cosa non funziona]
**Passi per Riprodurre**:
1. [Passo 1]
2. [Passo 2]
**Risultato Atteso**: [Cosa dovrebbe succedere]
**Risultato Attuale**: [Cosa succede invece]
**Screenshot**: [Se applicabile]
**Browser/OS**: [Chrome 95 / Windows 10]
**Priorità**: [Alta/Media/Bassa]
```

### Dove Segnalare
- Email: bugs@nexiosolution.com
- Issue Tracker: https://github.com/nexiosolution/collabora/issues

## Monitoraggio

### Metriche da Monitorare
- Uptime sistema: > 99.9%
- Tempo risposta API: < 200ms
- Errori/ora: < 10
- Storage utilizzato: < 80%
- CPU usage: < 70%
- RAM usage: < 80%

### Log da Controllare
```bash
# Errori PHP
tail -f logs/error.log

# Accessi
tail -f logs/access.log

# Attività utenti
tail -f logs/activity.log

# Upload falliti
grep "upload failed" logs/error.log
```

## Test di Regressione

Prima di ogni rilascio, eseguire:
1. Test suite automatica completa
2. Test manuali critici
3. Test su 3 browser diversi
4. Test performance
5. Backup completo

## Continuous Integration

### GitHub Actions Workflow
```yaml
name: Test Suite
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
      - name: Run tests
        run: php test.php --all
```

## Conclusione

Seguendo questa guida, potrai assicurarti che Nexiosolution Collabora funzioni correttamente in tutti gli scenari. Esegui i test regolarmente, specialmente dopo aggiornamenti o modifiche al codice.