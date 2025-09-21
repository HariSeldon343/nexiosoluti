<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = Database::getInstance()->getConnection();
$token = sanitizeString($_GET['token'] ?? '');
if (!$token) {
    http_response_code(404);
    echo 'Link non valido';
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM share_links WHERE share_token = :token');
$stmt->execute(['token' => $token]);
$link = $stmt->fetch();
if (!$link) {
    http_response_code(404);
    echo 'Link non trovato';
    exit;
}

if ($link['expires_at'] && strtotime($link['expires_at']) < time()) {
    echo 'Questo link è scaduto.';
    exit;
}
if ($link['max_downloads'] !== null && (int)$link['download_count'] >= (int)$link['max_downloads']) {
    echo 'Limite download raggiunto.';
    exit;
}

if (!empty($link['password_hash'])) {
    $authorized = $_SESSION['share_auth'][$token] ?? false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';
        if (password_verify($password, $link['password_hash'])) {
            $_SESSION['share_auth'][$token] = true;
            $authorized = true;
        } else {
            $error = 'Password non corretta';
        }
    }
    if (!$authorized) {
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Accesso Protetto</title></head><body>';
        echo '<h2>Inserisci la password per accedere</h2>';
        if (isset($error)) {
            echo '<p style="color:red">' . htmlspecialchars($error) . '</p>';
        }
        echo '<form method="post">';
        echo '<input type="password" name="password" placeholder="Password" required />';
        echo '<button type="submit">Accedi</button>';
        echo '</form></body></html>';
        exit;
    }
}

$pdo->prepare('INSERT INTO share_access_logs (share_link_id, action, ip_address) VALUES (:link, :action, :ip)')->execute([
    'link' => $link['id'],
    'action' => 'download',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
]);

if ($link['resource_type'] === 'file') {
    $stmt = $pdo->prepare('SELECT * FROM files WHERE id = :id');
    $stmt->execute(['id' => $link['resource_id']]);
    $file = $stmt->fetch();
    if (!$file || !is_file($file['storage_path'])) {
        echo 'File non disponibile.';
        exit;
    }
    $pdo->prepare('UPDATE share_links SET download_count = download_count + 1 WHERE id = :id')->execute(['id' => $link['id']]);
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
    header('Content-Length: ' . $file['size']);
    readfile($file['storage_path']);
    exit;
}

echo 'Tipo di risorsa non supportato.';
