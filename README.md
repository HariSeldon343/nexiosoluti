# 🚀 NexioSolution - Piattaforma Collaborativa Multi-Tenant

## 📋 Panoramica

NexioSolution è una piattaforma collaborativa enterprise brandizzabile e multi-tenant, progettata per team aziendali e clienti esterni. Offre un ecosistema completo per la gestione documentale, progetti, comunicazione e collaborazione.

## ✨ Funzionalità Principali

### 🏢 Multi-Tenant & White-Label
- Supporto multi-tenant con isolamento dati completo
- Branding personalizzabile per tenant (logo, colori, favicon)
- Mapping dominio/sottodominio automatico
- Gestione multi-azienda con campi personalizzati

### 📁 File System Collaborativo
- Upload drag & drop con anteprime
- Versioning automatico dei documenti
- Workflow di approvazione configurabile
- Integrazione OnlyOffice/Collabora per editing
- Condivisione con permessi granulari

### 📅 Calendario Avanzato
- Vista mese/settimana/giorno
- Eventi multi-day e ricorrenti
- Sincronizzazione CalDAV bidirezionale
- Task con occorrenze non consecutive
- Inviti e promemoria automatici

### 📋 Task Manager
- Kanban board con drag & drop
- Task su giorni non consecutivi
- Assegnazione multipla
- Checklist e progress tracking
- Timeline e dipendenze

### 💬 Chat & Collaborazione
- Messaggistica real-time con WebSocket
- Videochiamate integrate con Jitsi
- Menzioni e reazioni
- Condivisione file inline
- Stato online/typing indicator

### 🔔 Notifiche
- Email con template brandizzati
- Push notifications browser (PWA)
- Centro notifiche in-app
- Schedulatore per promemoria

### 🔐 Sicurezza
- Autenticazione JWT con refresh token
- 2FA con Google Authenticator
- Rate limiting e protezione brute force
- Audit log immutabile
- Permessi granulari RBAC

### 📱 PWA & Mobile
- Installabile come app
- Funzionamento offline
- Push notifications
- UI responsive touch-friendly
- Dark/Light mode

## 🛠️ Stack Tecnologico

### Backend
- **PHP 8.2** con **Laravel 10**
- **MariaDB/MySQL** per database
- **Redis** per cache e code
- **Laravel WebSockets** per real-time
- **SabreDAV** per CalDAV
- **JWT** per autenticazione

### Frontend
- **React 18** con **Vite**
- **Tailwind CSS** per styling
- **Zustand** per stato globale
- **FullCalendar** per calendario
- **React Beautiful DnD** per drag & drop
- **Lucide React** per icone

### Integrazioni
- **OnlyOffice/Collabora** per editing documenti
- **Jitsi Meet** per videochiamate
- **CalDAV** per sincronizzazione calendari
- **Web Push** per notifiche browser
- **SMTP** per email

## 📦 Installazione Rapida (Windows con XAMPP)

### Prerequisiti
- XAMPP con PHP 8.2+, Apache, MariaDB
- Node.js 18+ e npm
- Composer
- Git

### Installazione Automatica

1. Clona il repository in `C:\xampp\htdocs\`:
```bash
cd C:\xampp\htdocs
git clone https://github.com/nexiosolution/nexiosolution.git Nexiosolution
```

2. Esegui lo script di installazione:
```bash
cd Nexiosolution
install.bat
```

3. Avvia tutti i servizi:
```bash
start-all.bat
```

4. Accedi all'applicazione:
- URL: http://localhost:3000
- Admin: admin@nexiosolution.com / Admin123!
- Tenant: demo@nexiosolution.com / Demo123!

Per multi-tenant: usa il selector nel menu o aggiungi X-Tenant-ID negli headers

## 🐳 Installazione Docker (Opzionale)

```bash
docker-compose up -d
```

L'applicazione sarà disponibile su http://localhost

## 📁 Struttura Progetto

```
Nexiosolution/
├── backend/              # Laravel API Backend
│   ├── app/             # Logica applicativa
│   ├── config/          # Configurazioni
│   ├── database/        # Migrazioni e seeders
│   ├── routes/          # Route API
│   └── storage/         # File storage
├── frontend/            # React Frontend
│   ├── src/            # Codice sorgente
│   ├── public/         # Asset pubblici
│   └── dist/           # Build produzione
├── docker/             # Configurazioni Docker
├── scripts/            # Script utilità
├── logs/              # Log applicazione
└── docs/              # Documentazione
```

## 🔧 Configurazione

### Backend (.env)
```env
APP_NAME=NexioSolution
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nexiosolution
DB_USERNAME=root
DB_PASSWORD=

JWT_SECRET=your-secret-key

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password

PUSHER_APP_ID=nexiosolution
PUSHER_APP_KEY=nexiosolution-key
PUSHER_APP_SECRET=nexiosolution-secret

OFFICE_SERVER_URL=http://localhost:8080
JITSI_DOMAIN=meet.jit.si
```

### Frontend (.env)
```env
VITE_API_URL=http://localhost:8000
VITE_WS_URL=ws://localhost:6001
VITE_PUSHER_KEY=nexiosolution-key
```

## 🚀 Comandi Utili

### Backend
```bash
# Migrazioni database
php artisan migrate

# Seed database
php artisan db:seed

# Avvia queue worker
php artisan queue:work

# Avvia WebSocket server
php artisan websockets:serve

# Genera chiavi VAPID
php artisan webpush:vapid
```

### Frontend
```bash
# Sviluppo
npm run dev

# Build produzione
npm run build

# Preview build
npm run preview
```

## 📱 Test Multi-Tenant

1. Crea un nuovo tenant:
```bash
php artisan tinker
>>> \App\Models\Tenant::create([
    'name' => 'ACME Corp',
    'code' => 'acme',
    'is_active' => true
]);
```

2. Seleziona il tenant:
- Dal menu dropdown nell'header
- O invia header HTTP: `X-Tenant-ID: acme`
- O usa parametro URL: `?tenant=acme`

## 🔄 CalDAV Sync

### iOS/macOS
1. Impostazioni → Account → Aggiungi Account → Altro
2. Server: localhost:8000/dav
3. Username/Password: credenziali utente

### Thunderbird
1. Installa addon TbSync + Provider for CalDAV
2. Server: http://localhost:8000/dav
3. Autenticazione con credenziali utente

### Android (DAVx⁵)
1. Installa DAVx⁵ da F-Droid/Play Store
2. URL: http://localhost:8000/dav
3. Login con credenziali utente

## 🛡️ Sicurezza

### Checklist Produzione
- [ ] Cambiare tutte le password di default
- [ ] Configurare SSL/TLS con certificati validi
- [ ] Abilitare 2FA per tutti gli admin
- [ ] Configurare backup automatici
- [ ] Impostare rate limiting appropriato
- [ ] Configurare firewall e fail2ban
- [ ] Abilitare monitoring (Prometheus/Grafana)
- [ ] Configurare log rotation
- [ ] Rivedere permessi file e cartelle
- [ ] Disabilitare debug mode

## 📊 Performance

### Ottimizzazioni Consigliate
- Cache con Redis
- CDN per asset statici
- Compressione Gzip/Brotli
- Lazy loading immagini
- Code splitting React
- Database indexing
- Query optimization
- HTTP/2 su Apache/Nginx

## 🐛 Troubleshooting

### Errore "Access Denied" su Windows
```bash
# Esegui come amministratore
icacls C:\xampp\htdocs\Nexiosolution /grant Everyone:F /T
```

### WebSocket non funziona
```bash
# Verifica che la porta 6001 sia libera
netstat -an | findstr :6001

# Riavvia il servizio
php artisan websockets:restart
```

### CalDAV non sincronizza
1. Verifica che mod_dav sia abilitato in Apache
2. Controlla i log in `storage/logs/caldav.log`
3. Testa con: `curl -X PROPFIND http://localhost:8000/dav`

## 📚 Documentazione

- [Guida Utente](docs/user-guide.md)
- [Guida Amministratore](docs/admin-guide.md)
- [API Documentation](docs/api.md)
- [Architettura](docs/architecture.md)

## 🤝 Contribuire

1. Fork il repository
2. Crea un branch (`git checkout -b feature/AmazingFeature`)
3. Commit (`git commit -m 'Add AmazingFeature'`)
4. Push (`git push origin feature/AmazingFeature`)
5. Apri una Pull Request

## 📄 Licenza

Distribuito sotto licenza MIT. Vedi `LICENSE` per maggiori informazioni.

## 👥 Team

- **Architettura**: Full-Stack Senior Developer
- **Frontend**: React PWA Developer
- **Backend**: Laravel Backend Architect
- **UI/UX**: Design System Architect
- **DevOps**: Infrastructure Engineer

## 📞 Supporto

- Email: support@nexiosolution.local
- GitHub Issues: [github.com/nexiosolution/issues](https://github.com/nexiosolution/issues)
- Documentation: [docs.nexiosolution.local](http://docs.nexiosolution.local)

---

© 2024 NexioSolution - Built with ❤️ for collaboration