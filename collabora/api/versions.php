<?php
require_once __DIR__ . '/../config.php';

$user = require_login($auth);
$tenantId = (int)$user['tenant']['id'];
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$fileId = filter_input(INPUT_GET, 'file_id', FILTER_VALIDATE_INT) ?: (int)($_POST['file_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$fileId) {
        json_response(['versions' => []]);
    }
    $stmt = $pdo->prepare('SELECT v.id, v.version_number, v.filename, v.size, v.created_at FROM file_versions v INNER JOIN files f ON f.id = v.file_id WHERE v.file_id = :file AND f.tenant_id = :tenant ORDER BY v.version_number DESC');
    $stmt->execute([':file' => $fileId, ':tenant' => $tenantId]);
    json_response(['versions' => $stmt->fetchAll()]);
}

if ($action === 'restore' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $versionId = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT v.version_number, v.storage_path AS version_path, v.size, v.filename, f.id AS file_id, f.storage_path AS file_path FROM file_versions v INNER JOIN files f ON f.id = v.file_id WHERE v.id = :id AND f.tenant_id = :tenant');
    $stmt->execute([':id' => $versionId, ':tenant' => $tenantId]);
    $version = $stmt->fetch();
    if (!$version || !is_file($version['version_path'])) {
        json_response(['success' => false, 'error' => 'Versione non trovata'], 404);
    }
    copy($version['version_path'], $version['file_path']);
    $pdo->prepare('UPDATE files SET size = :size, original_name = :name, updated_at = NOW() WHERE id = :file')->execute([
        ':size' => $version['size'],
        ':name' => $version['filename'],
        ':file' => $version['file_id'],
    ]);
    json_response(['success' => true]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!$fileId || !isset($_FILES['file'])) {
        json_response(['success' => false, 'error' => 'Dati mancanti'], 422);
    }
    $stmt = $pdo->prepare('SELECT * FROM files WHERE id = :id AND tenant_id = :tenant');
    $stmt->execute([':id' => $fileId, ':tenant' => $tenantId]);
    $file = $stmt->fetch();
    if (!$file) {
        json_response(['success' => false, 'error' => 'File non trovato'], 404);
    }
    $upload = $_FILES['file'];
    if ($upload['error'] !== UPLOAD_ERR_OK) {
        json_response(['success' => false, 'error' => 'Errore upload'], 400);
    }
    $tmpPath = $upload['tmp_name'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);
    $size = (int)$upload['size'];
    $storageDir = dirname($file['storage_path']);
    ensure_directory($storageDir);
    $versionNumberStmt = $pdo->prepare('SELECT IFNULL(MAX(version_number), 0) + 1 FROM file_versions WHERE file_id = :file');
    $versionNumberStmt->execute([':file' => $fileId]);
    $versionNumber = (int)$versionNumberStmt->fetchColumn();
    $newPath = $storageDir . '/version_' . $versionNumber . '_' . preg_replace('/[^A-Za-z0-9\._-]/', '_', basename($upload['name']));
    move_uploaded_file($tmpPath, $newPath);

    $pdo->prepare('INSERT INTO file_versions (file_id, version_number, filename, storage_path, size, hash_sha256, uploaded_by) VALUES (:file, :version, :filename, :path, :size, :hash, :user)')->execute([
        ':file' => $fileId,
        ':version' => $versionNumber,
        ':filename' => basename($upload['name']),
        ':path' => $newPath,
        ':size' => $size,
        ':hash' => hash_file('sha256', $newPath),
        ':user' => $user['id'],
    ]);
    $pdo->prepare('UPDATE files SET storage_path = :path, size = :size, mime_type = :mime, original_name = :original, updated_at = NOW() WHERE id = :id')->execute([
        ':path' => $newPath,
        ':size' => $size,
        ':mime' => $mime,
        ':original' => $upload['name'],
        ':id' => $fileId,
    ]);
    json_response(['success' => true]);
}

json_response(['error' => 'Metodo non supportato'], 405);
