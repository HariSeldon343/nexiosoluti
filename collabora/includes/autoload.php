<?php declare(strict_types=1);

/**
 * PSR-4 Autoloader per il sistema Nexiosolution Collabora
 * Gestisce il caricamento automatico delle classi con namespace
 */

spl_autoload_register(function ($class) {
    // Prefisso namespace del progetto
    $prefix = 'Collabora\\';

    // Directory base per il namespace
    $base_dir = __DIR__ . '/';

    // Verifica se la classe usa il namespace del progetto
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No, passa al prossimo autoloader registrato
        return;
    }

    // Ottieni il nome della classe relativo
    $relative_class = substr($class, $len);

    // Sostituisci il separatore namespace con il separatore directory,
    // aggiungi .php e converti il nome in minuscolo per il file
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Se il file esiste, caricalo
    if (file_exists($file)) {
        require_once $file;
        return;
    }

    // Prova anche con il nome del file in minuscolo (per compatibilità)
    $file_lower = $base_dir . strtolower(str_replace('\\', '/', $relative_class)) . '.php';
    if (file_exists($file_lower)) {
        require_once $file_lower;
        return;
    }

    // Mapping manuale per classi specifiche che non seguono la convenzione
    $classMap = [
        'Collabora\\Auth\\AuthenticationV2' => __DIR__ . '/auth_v2.php',
        'Collabora\\Auth\\UserRole' => __DIR__ . '/auth_v2.php',
        'Collabora\\Auth\\UserStatus' => __DIR__ . '/auth_v2.php',
    ];

    if (isset($classMap[$class])) {
        require_once $classMap[$class];
        return;
    }
});

// Autoloader secondario per classi senza namespace (retrocompatibilità)
spl_autoload_register(function ($class) {
    // Rimuovi namespace se presente
    $className = $class;
    if (strpos($className, '\\') !== false) {
        $parts = explode('\\', $className);
        $className = end($parts);
    }

    // Lista di percorsi dove cercare le classi
    $paths = [
        __DIR__ . '/',
        __DIR__ . '/../models/',
        __DIR__ . '/../controllers/',
        __DIR__ . '/../services/',
        __DIR__ . '/../helpers/',
    ];

    foreach ($paths as $path) {
        $file = $path . $className . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }

        // Prova anche con il nome in minuscolo
        $file_lower = $path . strtolower($className) . '.php';
        if (file_exists($file_lower)) {
            require_once $file_lower;
            return;
        }
    }
});

// Carica automaticamente le funzioni helper se il file esiste
$helperFile = __DIR__ . '/helpers.php';
if (file_exists($helperFile)) {
    require_once $helperFile;
}

// Carica il file di database se esiste
$dbFile = __DIR__ . '/db.php';
if (file_exists($dbFile)) {
    require_once $dbFile;
}