<?php
// Configurazione database
const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'collabora';

// Percorsi principali
const BASE_PATH = __DIR__;
const UPLOAD_PATH = BASE_PATH . '/uploads';
const TEMP_PATH = BASE_PATH . '/temp';

// Avvio sessione globale
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    session_start();
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Istanza globale
$pdo = Database::getInstance();
$auth = new Auth($pdo);
