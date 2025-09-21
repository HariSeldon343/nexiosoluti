<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$auth = new Auth();
$user = $auth->requireAuth();
$tenantId = $auth->getCurrentTenantId();
$pdo = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? null;

switch ($method) {
    case 'GET':
        $fileId = sanitizeInt($_GET['file_id'] ?? null);
        if (!$fileId) {
            respondWithError('File non valido', 422);
        }
        $stmt = $pdo->prepare('SELECT id, version_number, filename, storage_path, size, change_summary, uploaded_by, created_at FROM file_versions WHERE file_id = :file ORDER BY version_number DESC');
        $stmt->execute(['file' => $fileId]);
        $versions = $stmt->fetchAll();
        jsonResponse(['success' => true, 'versions' => array_map(static fn($row) => [
            'id' => (int)$row['id'],
            'version_number' => (int)$row['version_number'],
            'filename' => $row['filename'],
            'storage_path' => $row['storage_path'],
            'size' => (int)$row['size'],
            'change_summary' => $row['change_summary'],
            'uploaded_by' => (int)$row['uploaded_by'],
            'created_at' => $row['created_at'],
        ], $versions)]);
        break;

    case 'POST':
        requireCsrfToken();
        if ($action === 'restore') {
            restoreVersion($pdo, $tenantId, $user['id']);
            break;
        }
        if (empty($_FILES['file'])) {
            respondWithError('Nessun file inviato', 422);
        }
        $fileId = sanitizeInt($_POST['file_id'] ?? null);
        if (!$fileId) {
            respondWithError('File non valido', 422);
        }
        $stmt = $pdo->prepare('SELECT * FROM files WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute(['id' => $fileId, 'tenant' => $tenantId]);
        $file = $stmt->fetch();
        if (!$file) {
            respondWithError('File non trovato', 404);
        }
        $uploaded = $_FILES['file'];
        if ($uploaded['error'] !== UPLOAD_ERR_OK) {
            respondWithError('Errore upload', 400);
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($uploaded['tmp_name']);
        if ($mime === false) {
            respondWithError('Tipo file sconosciuto', 415);
        }
        $versionNumber = getNextVersionNumber($pdo, $fileId);
        $versionDir = buildVersionDirectory($tenantId, $fileId);
        if (!is_dir($versionDir) && !mkdir($versionDir, 0755, true)) {
            respondWithError('Impossibile creare directory versioni', 500);
        }
        $versionFilename = 'v' . $versionNumber . '-' . $file['filename'];
        $versionPath = $versionDir . '/' . $versionFilename;
        if (!copy($file['storage_path'], $versionPath)) {
            respondWithError('Impossibile salvare versione precedente', 500);
        }
        $pdo->prepare('INSERT INTO file_versions (file_id, version_number, filename, storage_path, size, hash_sha256, change_summary, uploaded_by) VALUES (:file, :version, :filename, :path, :size, :hash, :summary, :user)')->execute([
            'file' => $fileId,
            'version' => $versionNumber,
            'filename' => $file['filename'],
            'path' => $versionPath,
            'size' => $file['size'],
            'hash' => $file['hash_sha256'],
            'summary' => sanitizeString($_POST['change_summary'] ?? ''),
            'user' => $user['id'],
        ]);
        $hash = hash_file('sha256', $uploaded['tmp_name']);
        $extension = pathinfo($uploaded['name'], PATHINFO_EXTENSION);
        $safeName = bin2hex(random_bytes(16)) . ($extension ? '.' . strtolower($extension) : '');
        $storageDir = ensureUploadDirectory($tenantId);
        $storagePath = $storageDir . '/' . $safeName;
        if (!move_uploaded_file($uploaded['tmp_name'], $storagePath)) {
            respondWithError('Impossibile salvare il file', 500);
        }
        $pdo->prepare('UPDATE files SET filename = :filename, original_name = :original, mime_type = :mime, size = :size, storage_path = :path, hash_sha256 = :hash, updated_at = NOW() WHERE id = :id')->execute([
            'filename' => $safeName,
            'original' => $uploaded['name'],
            'mime' => $mime,
            'size' => $uploaded['size'],
            'path' => $storagePath,
            'hash' => $hash,
            'id' => $fileId,
        ]);
        $sizeDiff = $uploaded['size'] - $file['size'];
        $pdo->prepare('UPDATE tenants SET storage_used = storage_used + :diff WHERE id = :tenant')->execute([
            'diff' => $sizeDiff,
            'tenant' => $tenantId,
        ]);
        jsonResponse(['success' => true]);
        break;

    default:
        respondWithError('Metodo non gestito', 405);
}

function getNextVersionNumber(\PDO $pdo, int $fileId): int
{
    $stmt = $pdo->prepare('SELECT COALESCE(MAX(version_number), 0) + 1 FROM file_versions WHERE file_id = :file');
    $stmt->execute(['file' => $fileId]);
    return (int)$stmt->fetchColumn();
}

function buildVersionDirectory(int $tenantId, int $fileId): string
{
    return UPLOAD_PATH . '/tenant_' . $tenantId . '/versions/file_' . $fileId;
}

function restoreVersion(\PDO $pdo, int $tenantId, int $userId): void
{
    $versionId = sanitizeInt($_GET['id'] ?? null);
    if (!$versionId) {
        respondWithError('Versione non valida', 422);
    }
    $stmt = $pdo->prepare('SELECT v.*, f.tenant_id FROM file_versions v INNER JOIN files f ON v.file_id = f.id WHERE v.id = :id');
    $stmt->execute(['id' => $versionId]);
    $version = $stmt->fetch();
    if (!$version) {
        respondWithError('Versione non trovata', 404);
    }
    verifyOwnership((int)$version['tenant_id'], $tenantId);
    $fileStmt = $pdo->prepare('SELECT * FROM files WHERE id = :id');
    $fileStmt->execute(['id' => $version['file_id']]);
    $file = $fileStmt->fetch();
    if (!$file) {
        respondWithError('File non trovato', 404);
    }
    $currentVersionNumber = getNextVersionNumber($pdo, (int)$version['file_id']);
    $versionDir = buildVersionDirectory($tenantId, (int)$version['file_id']);
    if (!is_dir($versionDir) && !mkdir($versionDir, 0755, true)) {
        respondWithError('Impossibile creare directory versioni', 500);
    }
    $currentVersionPath = $versionDir . '/v' . $currentVersionNumber . '-' . $file['filename'];
    if (!copy($file['storage_path'], $currentVersionPath)) {
        respondWithError('Impossibile salvare versione corrente', 500);
    }
    $pdo->prepare('INSERT INTO file_versions (file_id, version_number, filename, storage_path, size, hash_sha256, change_summary, uploaded_by) VALUES (:file, :version, :filename, :path, :size, :hash, :summary, :user)')->execute([
        'file' => $version['file_id'],
        'version' => $currentVersionNumber,
        'filename' => $file['filename'],
        'path' => $currentVersionPath,
        'size' => $file['size'],
        'hash' => $file['hash_sha256'],
        'summary' => 'Ripristino manuale',
        'user' => $userId,
    ]);
    $newPath = ensureUploadDirectory($tenantId) . '/' . $version['filename'];
    if (!copy($version['storage_path'], $newPath)) {
        respondWithError('Impossibile ripristinare il file', 500);
    }
    $pdo->prepare('UPDATE files SET filename = :filename, storage_path = :path, size = :size, hash_sha256 = :hash, updated_at = NOW() WHERE id = :id')->execute([
        'filename' => basename($newPath),
        'path' => $newPath,
        'size' => $version['size'],
        'hash' => $version['hash_sha256'],
        'id' => $version['file_id'],
    ]);
    $sizeDiff = $version['size'] - $file['size'];
    $pdo->prepare('UPDATE tenants SET storage_used = storage_used + :diff WHERE id = :tenant')->execute([
        'diff' => $sizeDiff,
        'tenant' => $tenantId,
    ]);
    jsonResponse(['success' => true]);
}
