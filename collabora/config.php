<?php
// Configurazione principale Collabora Platform

declare(strict_types=1);

ini_set('default_charset', 'UTF-8');
date_default_timezone_set('Europe/Rome');

const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'collabora';

const BASE_PATH = __DIR__;
const UPLOAD_PATH = BASE_PATH . '/uploads';
const TEMP_PATH = BASE_PATH . '/temp';

const SESSION_NAME = 'COLLABORA_SESSID';
const SESSION_COOKIE_LIFETIME = 7200;
const SESSION_COOKIE_PATH = '/';
const SESSION_COOKIE_SECURE = false; // Impostare a true su HTTPS
const SESSION_COOKIE_HTTPONLY = true;
const SESSION_COOKIE_SAMESITE = 'Lax';

// Limiti upload (default 50MB per file)
const MAX_UPLOAD_SIZE = 52428800;

// Tipi MIME consentiti (può essere esteso)
const ALLOWED_MIME_TYPES = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/zip',
    'image/jpeg',
    'image/png',
    'image/gif',
    'text/plain'
];

if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}

if (!is_dir(TEMP_PATH)) {
    mkdir(TEMP_PATH, 0755, true);
}

// Avvia la sessione personalizzata se non già avviata
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_COOKIE_LIFETIME,
        'path' => SESSION_COOKIE_PATH,
        'secure' => SESSION_COOKIE_SECURE,
        'httponly' => SESSION_COOKIE_HTTPONLY,
        'samesite' => SESSION_COOKIE_SAMESITE
    ]);
    session_start();
}
