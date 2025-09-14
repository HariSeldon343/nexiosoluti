#!/bin/bash

echo "========================================"
echo "NexioSolution - Setup Script"
echo "========================================"
echo ""

# Verifica che siamo nella directory corretta
if [ ! -f "backend/composer.json" ]; then
    echo "ERRORE: Esegui questo script dalla root del progetto Nexiosolution"
    exit 1
fi

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}[1/8] Installazione dipendenze backend...${NC}"
cd backend

# Installa Composer se non presente
if ! command -v composer &> /dev/null; then
    echo "Composer non trovato, installazione..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
fi

# Installa dipendenze
composer install --no-dev --optimize-autoloader

echo ""
echo -e "${GREEN}[2/8] Creazione file .env...${NC}"
if [ ! -f ".env" ]; then
    cp .env.example .env
    echo -e "${YELLOW}File .env creato. Configuralo con i tuoi parametri!${NC}"
else
    echo "File .env già esistente, non sovrascritto."
fi

echo ""
echo -e "${GREEN}[3/8] Generazione chiave applicazione...${NC}"
php artisan key:generate

echo ""
echo -e "${GREEN}[4/8] Generazione JWT secret...${NC}"
php artisan jwt:secret

echo ""
echo -e "${YELLOW}[5/8] Creazione database...${NC}"
echo "ATTENZIONE: Assicurati che MySQL sia avviato!"
echo "Crea un database chiamato 'nexiosolution'"
echo "Premi ENTER quando pronto..."
read

echo ""
echo -e "${GREEN}[6/8] Esecuzione migrazioni database...${NC}"
php artisan migrate --force

echo ""
echo -e "${GREEN}[7/8] Creazione link storage pubblico...${NC}"
php artisan storage:link

echo ""
echo -e "${GREEN}[8/8] Impostazione permessi...${NC}"
chmod -R 775 storage bootstrap/cache
chmod -R 777 storage/logs

echo ""
echo -e "${GREEN}[9/9] Ottimizzazione cache...${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo ""
echo "========================================"
echo -e "${GREEN}Setup completato!${NC}"
echo "========================================"
echo ""
echo "Prossimi passi:"
echo "1. Configura il file backend/.env con i tuoi parametri"
echo "2. Imposta il Virtual Host per il dominio"
echo "3. Avvia il WebSocket server: php artisan websockets:serve"
echo "4. Avvia il queue worker: php artisan queue:work"
echo ""
echo "Per avviare il server di sviluppo:"
echo "  cd backend"
echo "  php artisan serve --host=localhost --port=8000"
echo ""