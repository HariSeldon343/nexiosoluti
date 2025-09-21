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
$action = $_GET['action'] ?? '';

if ($method === 'GET' && $action === 'download') {
    $fileId = sanitizeInt($_GET['id'] ?? null);
    if (!$fileId) {
        respondWithError('File non trovato', 404);
    }
    $stmt = $pdo->prepare('SELECT * FROM files WHERE id = :id AND tenant_id = :tenant');
    $stmt->execute(['id' => $fileId, 'tenant' => $tenantId]);
    $file = $stmt->fetch();
    if (!$file || (bool)$file['is_deleted']) {
        respondWithError('File non disponibile', 404);
    }
    $filePath = $file['storage_path'];
    if (!is_file($filePath)) {
        respondWithError('File non trovato su disco', 404);
    }
    logActivity($pdo, $tenantId, $user['id'], 'download', 'file', $fileId);
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
    header('Content-Length: ' . $file['size']);
    readfile($filePath);
    exit;
}

switch ($method) {
    case 'GET':
        $folderId = sanitizeInt($_GET['folder_id'] ?? null);
        $page = sanitizeInt($_GET['page'] ?? 1) ?? 1;
        $limit = sanitizeInt($_GET['limit'] ?? 50) ?? 50;
        [$limitValue, $offset] = paginate($page, $limit);
        $params = ['tenant' => $tenantId];
        $where = 'tenant_id = :tenant AND is_deleted = 0';
        if ($folderId !== null) {
            $where .= ' AND (folder_id = :folder OR (folder_id IS NULL AND :folder IS NULL))';
            $params['folder'] = $folderId;
        }
        $stmt = $pdo->prepare("SELECT SQL_CALC_FOUND_ROWS id, folder_id, original_name, size, created_at FROM files WHERE $where ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $limitValue, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();
        $files = $stmt->fetchAll();
        $total = (int)$pdo->query('SELECT FOUND_ROWS()')->fetchColumn();
        jsonResponse([
            'success' => true,
            'files' => array_map(static fn($row) => [
                'id' => (int)$row['id'],
                'folder_id' => $row['folder_id'] !== null ? (int)$row['folder_id'] : null,
                'original_name' => $row['original_name'],
                'size' => (int)$row['size'],
                'created_at' => $row['created_at'],
            ], $files),
            'total' => $total,
            'page' => $page,
            'limit' => $limitValue,
        ]);
        break;

    case 'POST':
        requireCsrfToken();
        if ($action === 'restore') {
            $fileId = sanitizeInt($_GET['id'] ?? null);
            if (!$fileId) {
                respondWithError('File non valido', 422);
            }
            $stmt = $pdo->prepare('SELECT id, size FROM files WHERE id = :id AND tenant_id = :tenant');
            $stmt->execute(['id' => $fileId, 'tenant' => $tenantId]);
            $file = $stmt->fetch();
            if (!$file) {
                respondWithError('File non trovato', 404);
            }
            $pdo->prepare('UPDATE files SET is_deleted = 0, deleted_at = NULL WHERE id = :id')->execute(['id' => $fileId]);
            $pdo->prepare('UPDATE tenants SET storage_used = storage_used + :size WHERE id = :tenant')->execute([
                'size' => $file['size'],
                'tenant' => $tenantId,
            ]);
            logActivity($pdo, $tenantId, $user['id'], 'restore', 'file', $fileId);
            jsonResponse(['success' => true]);
        }

        if (empty($_FILES['file'])) {
            respondWithError('Nessun file inviato', 422);
        }
        $uploaded = $_FILES['file'];
        if ($uploaded['error'] !== UPLOAD_ERR_OK) {
            respondWithError('Errore durante il caricamento', 400);
        }
        if ($uploaded['size'] > MAX_UPLOAD_SIZE) {
            respondWithError('Dimensione file oltre il limite consentito', 422);
        }
        $folderId = sanitizeInt($_POST['folder_id'] ?? null);
        if ($folderId) {
            $stmt = $pdo->prepare('SELECT id, tenant_id FROM folders WHERE id = :id');
            $stmt->execute(['id' => $folderId]);
            $folder = $stmt->fetch();
            if (!$folder) {
                respondWithError('Cartella non valida', 422);
            }
            verifyOwnership((int)$folder['tenant_id'], $tenantId);
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($uploaded['tmp_name']);
        if ($mime === false) {
            respondWithError('Impossibile determinare il MIME type', 400);
        }
        if (!in_array($mime, ALLOWED_MIME_TYPES, true)) {
            respondWithError('Tipo di file non consentito', 415);
        }
        $hash = hash_file('sha256', $uploaded['tmp_name']);
        $extension = pathinfo($uploaded['name'], PATHINFO_EXTENSION);
        $safeName = bin2hex(random_bytes(16)) . ($extension ? '.' . strtolower($extension) : '');
        $storageDir = ensureUploadDirectory($tenantId);
        $storagePath = $storageDir . '/' . $safeName;
        if (!move_uploaded_file($uploaded['tmp_name'], $storagePath)) {
            respondWithError('Impossibile salvare il file', 500);
        }
        $stmt = $pdo->prepare('INSERT INTO files (tenant_id, folder_id, uploaded_by, filename, original_name, mime_type, size, storage_path, hash_sha256) VALUES (:tenant, :folder, :user, :filename, :original, :mime, :size, :path, :hash)');
        $stmt->execute([
            'tenant' => $tenantId,
            'folder' => $folderId,
            'user' => $user['id'],
            'filename' => $safeName,
            'original' => $uploaded['name'],
            'mime' => $mime,
            'size' => $uploaded['size'],
            'path' => $storagePath,
            'hash' => $hash,
        ]);
        $fileId = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE tenants SET storage_used = storage_used + :size WHERE id = :tenant')->execute([
            'size' => $uploaded['size'],
            'tenant' => $tenantId,
        ]);
        logActivity($pdo, $tenantId, $user['id'], 'upload', 'file', $fileId, ['name' => $uploaded['name']]);
        jsonResponse([
            'success' => true,
            'file' => [
                'id' => $fileId,
                'original_name' => $uploaded['name'],
                'size' => $uploaded['size'],
            ],
        ], 201);
        break;

    case 'DELETE':
        requireCsrfToken();
        $fileId = sanitizeInt($_GET['id'] ?? null);
        if (!$fileId) {
            respondWithError('File non valido', 422);
        }
        $stmt = $pdo->prepare('SELECT id, tenant_id, size FROM files WHERE id = :id');
        $stmt->execute(['id' => $fileId]);
        $file = $stmt->fetch();
        if (!$file) {
            respondWithError('File non trovato', 404);
        }
        verifyOwnership((int)$file['tenant_id'], $tenantId);
        $pdo->prepare('UPDATE files SET is_deleted = 1, deleted_at = NOW() WHERE id = :id')->execute(['id' => $fileId]);
        $pdo->prepare('UPDATE tenants SET storage_used = GREATEST(storage_used - :size, 0) WHERE id = :tenant')->execute([
            'size' => $file['size'],
            'tenant' => $tenantId,
        ]);
        logActivity($pdo, $tenantId, $user['id'], 'delete', 'file', $fileId);
        jsonResponse(['success' => true]);
        break;

    default:
        respondWithError('Metodo non gestito', 405);
}
