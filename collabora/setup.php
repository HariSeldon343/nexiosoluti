<?php
declare(strict_types=1);

/**
 * Script di setup iniziale
 * Crea tabelle database e dati demo
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/TenantManager.php';

echo "<!DOCTYPE html>\n";
echo "<html><head><title>Setup Nexio File Manager</title></head><body>\n";
echo "<h1>Setup Nexio File Manager</h1>\n";
echo "<pre>\n";

try {
    echo "Connessione al database...\n";
    $db = Database::getInstance();
    echo "✅ Connessione stabilita\n\n";

    echo "Creazione tabelle...\n";
    Database::createTables();
    echo "✅ Tabelle create/verificate\n\n";

    echo "Creazione tenant demo...\n";
    $tenant = TenantManager::getTenantByCode('demo');

    if (!$tenant) {
        $adminEmail = 'admin@demo.local';
        $tenantId = TenantManager::createTenant([
            'code' => 'demo',
            'name' => 'Demo Tenant',
            'domain' => 'demo.localhost',
            'status' => 'active',
            'storage_limit' => 10737418240, // 10GB
            'admin_email' => $adminEmail
        ]);

        echo "✅ Tenant demo creato (ID: $tenantId)\n";

        // Aggiorna password admin
        $adminHash = password_hash('password123', PASSWORD_ALGO, PASSWORD_OPTIONS);
        Database::update('users',
            ['password' => $adminHash],
            ['tenant_id' => $tenantId, 'username' => 'admin']
        );
        echo "✅ Password admin impostata\n";

        // Crea utenti aggiuntivi
        Auth::createUser([
            'tenant_id' => $tenantId,
            'username' => 'manager',
            'email' => 'manager@demo.local',
            'password' => 'password123',
            'full_name' => 'Manager User',
            'role' => 'manager'
        ]);
        echo "✅ Utente manager creato\n";

        Auth::createUser([
            'tenant_id' => $tenantId,
            'username' => 'user',
            'email' => 'user@demo.local',
            'password' => 'password123',
            'full_name' => 'Standard User',
            'role' => 'user'
        ]);
        echo "✅ Utente standard creato\n";

        // Crea cartelle demo
        $_SESSION['tenant_id'] = $tenantId;
        $_SESSION['user_id'] = 1; // Admin user

        $fm = new FileManager();

        $docsFolderId = $fm->createFolder('Documenti')['id'];
        echo "✅ Cartella 'Documenti' creata\n";

        $imagesFolderId = $fm->createFolder('Immagini')['id'];
        echo "✅ Cartella 'Immagini' creata\n";

        $projectsFolderId = $fm->createFolder('Progetti')['id'];
        echo "✅ Cartella 'Progetti' creata\n";

        // Crea sottocartelle
        $fm->createFolder('Contratti', $docsFolderId);
        $fm->createFolder('Report', $docsFolderId);
        $fm->createFolder('Logo', $imagesFolderId);
        $fm->createFolder('Screenshot', $imagesFolderId);
        echo "✅ Sottocartelle create\n";

    } else {
        echo "ℹ️ Tenant demo già esistente\n";
    }

    echo "\n";
    echo "Creazione directory upload...\n";
    $uploadDirs = [
        UPLOAD_PATH . '/demo',
        UPLOAD_PATH . '/demo/2025',
        UPLOAD_PATH . '/demo/2025/09',
        LOG_PATH
    ];

    foreach ($uploadDirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            echo "✅ Directory creata: $dir\n";
        } else {
            echo "ℹ️ Directory esistente: $dir\n";
        }
    }

    echo "\n";
    echo "Verifica permessi...\n";

    $writableDirs = [UPLOAD_PATH, LOG_PATH];
    foreach ($writableDirs as $dir) {
        if (is_writable($dir)) {
            echo "✅ Directory scrivibile: $dir\n";
        } else {
            echo "❌ Directory NON scrivibile: $dir\n";
        }
    }

    echo "\n";
    echo "Statistiche database:\n";
    $stats = Database::getStats();
    foreach ($stats as $key => $value) {
        echo "  - " . str_replace('_', ' ', $key) . ": $value\n";
    }

    echo "\n";
    echo "=====================================\n";
    echo "✅ SETUP COMPLETATO CON SUCCESSO! ✅\n";
    echo "=====================================\n\n";

    echo "CREDENZIALI DI ACCESSO:\n";
    echo "-----------------------\n";
    echo "URL: http://localhost/Nexiosolution/collabora/\n\n";

    echo "Admin:\n";
    echo "  Tenant: demo\n";
    echo "  Username: admin\n";
    echo "  Password: password123\n\n";

    echo "Manager:\n";
    echo "  Tenant: demo\n";
    echo "  Username: manager\n";
    echo "  Password: password123\n\n";

    echo "User:\n";
    echo "  Tenant: demo\n";
    echo "  Username: user\n";
    echo "  Password: password123\n";

} catch (Exception $e) {
    echo "\n❌ ERRORE: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString();
}

echo "</pre>\n";
echo "<p><a href='index.php'>Vai al File Manager</a></p>\n";
echo "</body></html>";