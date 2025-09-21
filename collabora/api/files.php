<?php
require_once __DIR__ . '/../config.php';

$user = require_login($auth);
$tenantId = (int)$user['tenant']['id'];
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if ($action === 'download') {
    $fileId = (int)($_GET['id'] ?? 0);
    if (!$fileId) {
        http_response_code(404);
        exit;
    }
    $stmt = $pdo->prepare('SELECT * FROM files WHERE id = :id AND tenant_id = :tenant AND is_deleted = 0');
    $stmt->execute([':id' => $fileId, ':tenant' => $tenantId]);
    $file = $stmt->fetch();
    if (!$file) {
        http_response_code(404);
        exit;
    }
    $path = $file['storage_path'];
    if (!is_file($path)) {
        http_response_code(404);
        exit;
    }
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
    header('Content-Length: ' . (string)$file['size']);
    readfile($path);
    exit;
}

if ($action === 'restore') {
    require_method('POST');
    $fileId = (int)($_GET['id'] ?? 0);
    verify_csrf_token($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $stmt = $pdo->prepare('UPDATE files SET is_deleted = 0, deleted_at = NULL WHERE id = :id AND tenant_id = :tenant');
    $stmt->execute([':id' => $fileId, ':tenant' => $tenantId]);
    json_response(['success' => true]);
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $folderId = filter_input(INPUT_GET, 'folder_id', FILTER_VALIDATE_INT, ['options' => ['default' => null]]);
        $stmt = $pdo->prepare('SELECT id, original_name, size, mime_type, created_at FROM files WHERE tenant_id = :tenant AND is_deleted = 0 AND (:folder IS NULL OR folder_id = :folder) ORDER BY created_at DESC');
        $stmt->execute([':tenant' => $tenantId, ':folder' => $folderId]);
        $files = $stmt->fetchAll();

        $breadcrumb = [];
        if ($folderId) {
            $breadcrumb = buildBreadcrumb($folderId, $tenantId, $pdo);
        }
        json_response(['files' => $files, 'breadcrumb' => $breadcrumb]);
    case 'POST':
        if (!isset($_FILES['file'])) {
            json_response(['success' => false, 'error' => 'Nessun file inviato'], 422);
        }
        verify_csrf_token($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $folderId = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;

        $upload = $_FILES['file'];
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            json_response(['success' => false, 'error' => 'Errore upload'], 400);
        }

        $tmpPath = $upload['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);
        $size = (int)$upload['size'];
        $originalName = basename($upload['name']);
        $hash = hash_file('sha256', $tmpPath);

        $storageDir = sprintf('%s/tenant_%d/%s', UPLOAD_PATH, $tenantId, date('Y/m'));
        ensure_directory($storageDir);
        $safeName = uniqid('file_', true) . '_' . preg_replace('/[^A-Za-z0-9\._-]/', '_', $originalName);
        $storagePath = $storageDir . '/' . $safeName;
        if (!move_uploaded_file($tmpPath, $storagePath)) {
            json_response(['success' => false, 'error' => 'Impossibile salvare il file'], 500);
        }

        $stmt = $pdo->prepare('INSERT INTO files (tenant_id, folder_id, uploaded_by, filename, original_name, mime_type, size, storage_path, hash_sha256) VALUES (:tenant, :folder, :user, :filename, :original, :mime, :size, :path, :hash)');
        $stmt->execute([
            ':tenant' => $tenantId,
            ':folder' => $folderId,
            ':user' => $user['id'],
            ':filename' => $safeName,
            ':original' => $originalName,
            ':mime' => $mime,
            ':size' => $size,
            ':path' => $storagePath,
            ':hash' => $hash,
        ]);

        json_response(['success' => true]);
    case 'DELETE':
        parse_str(file_get_contents('php://input'), $input);
        $fileId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($input['id'] ?? 0);
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
        verify_csrf_token($csrf);
        $stmt = $pdo->prepare('UPDATE files SET is_deleted = 1, deleted_at = NOW() WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute([':id' => $fileId, ':tenant' => $tenantId]);
        json_response(['success' => true]);
    default:
        json_response(['error' => 'Metodo non supportato'], 405);
}

function buildBreadcrumb(int $folderId, int $tenantId, PDO $pdo): array
{
    $breadcrumb = [];
    $current = $folderId;
    while ($current) {
        $stmt = $pdo->prepare('SELECT id, name, parent_id FROM folders WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute([':id' => $current, ':tenant' => $tenantId]);
        $folder = $stmt->fetch();
        if (!$folder) {
            break;
        }
        array_unshift($breadcrumb, ['id' => (int)$folder['id'], 'name' => $folder['name']]);
        $current = $folder['parent_id'] ? (int)$folder['parent_id'] : null;
    }
    return $breadcrumb;
}
