<?php
require_once __DIR__ . '/../config.php';

$token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (!$token) {
    http_response_code(404);
    exit('Link non valido');
}

$stmt = $pdo->prepare('SELECT * FROM share_links WHERE share_token = :token');
$stmt->execute([':token' => $token]);
$link = $stmt->fetch();
if (!$link) {
    http_response_code(404);
    exit('Link non trovato');
}

if ($link['expires_at'] && strtotime($link['expires_at']) < time()) {
    exit('Il link è scaduto');
}

if ($link['max_downloads'] !== null && (int)$link['download_count'] >= (int)$link['max_downloads']) {
    exit('Limite download raggiunto');
}

if ($link['password_hash']) {
    $submitted = $_POST['password'] ?? null;
    if (!$submitted || !password_verify($submitted, $link['password_hash'])) {
        echo '<form method="post"><h2>Inserisci la password</h2><input type="password" name="password"><button type="submit">Accedi</button></form>';
        exit;
    }
}

if ($link['resource_type'] === 'file') {
    $fileStmt = $pdo->prepare('SELECT * FROM files WHERE id = :id');
    $fileStmt->execute([':id' => $link['resource_id']]);
    $file = $fileStmt->fetch();
    if (!$file || !is_file($file['storage_path'])) {
        exit('File non disponibile');
    }

    if (isset($_GET['download'])) {
        $pdo->prepare('UPDATE share_links SET download_count = download_count + 1 WHERE id = :id')->execute([':id' => $link['id']]);
        $log = $pdo->prepare('INSERT INTO share_access_logs (share_link_id, action, ip_address) VALUES (:share, "download", :ip)');
        $log->execute([':share' => $link['id'], ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        header('Content-Type: ' . $file['mime_type']);
        header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
        readfile($file['storage_path']);
        exit;
    }

    $pdo->prepare('INSERT INTO share_access_logs (share_link_id, action, ip_address) VALUES (:share, "view", :ip)')->execute([
        ':share' => $link['id'],
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ]);
    echo '<h2>Download file</h2>';
    echo '<p>' . htmlspecialchars($file['original_name'], ENT_QUOTES | ENT_HTML5) . '</p>';
    echo '<a href="?token=' . urlencode($token) . '&download=1">Scarica</a>';
    exit;
}

echo 'Risorsa non supportata';
