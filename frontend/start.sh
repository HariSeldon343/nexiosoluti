#!/bin/bash

echo "Fixing Frontend Configuration..."

cd /mnt/c/xampp/htdocs/Nexiosolution/frontend

# Kill qualsiasi processo sulla porta 3000
echo "Liberando porta 3000..."
lsof -ti:3000 | xargs -r kill -9 2>/dev/null

# Installa dipendenze se necessario
if [ ! -d "node_modules" ]; then
    echo "Installing dependencies..."
    npm install --legacy-peer-deps
fi

echo "Starting Vite on port 3000..."
npm run dev