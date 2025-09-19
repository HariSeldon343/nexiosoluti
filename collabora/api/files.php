<?php
declare(strict_types=1);

/**
 * API endpoints per gestione file
 * Upload, download, delete, rename, move, trash
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/FileManager.php';

// Headers CORS e JSON
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Gestione OPTIONS per CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Inizializza sessione e auth
Auth::initSession();
Auth::requireAuth();

// Router
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $fileManager = new FileManager();

    switch ($action) {
        case 'upload':
            handleUpload($fileManager);
            break;

        case 'download':
            handleDownload($fileManager);
            break;

        case 'download_zip':
            handleDownloadZip($fileManager);
            break;

        case 'list':
            handleList($fileManager);
            break;

        case 'delete':
            handleDelete($fileManager);
            break;

        case 'restore':
            handleRestore($fileManager);
            break;

        case 'permanent_delete':
            handlePermanentDelete($fileManager);
            break;

        case 'rename':
            handleRename($fileManager);
            break;

        case 'move':
            handleMove($fileManager);
            break;

        case 'trash':
            handleTrash($fileManager);
            break;

        case 'clean_trash':
            handleCleanTrash($fileManager);
            break;

        case 'stats':
            handleStats($fileManager);
            break;

        case 'search':
            handleSearch($fileManager);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Upload file
 */
function handleUpload(FileManager $fm): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    Auth::requirePermission('file.create');
    Auth::requireCsrfToken();

    if (empty($_FILES)) {
        throw new Exception('No files uploaded');
    }

    $folderId = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
    $results = [];

    // Gestione upload multipli
    if (isset($_FILES['files'])) {
        // Multiple files
        $results = $fm->uploadMultipleFiles($_FILES['files'], $folderId);
    } elseif (isset($_FILES['file'])) {
        // Single file
        $results['uploaded'] = [$fm->uploadFile($_FILES['file'], $folderId)];
        $results['errors'] = [];
    } else {
        throw new Exception('No file field found');
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'files' => $results['uploaded'],
        'errors' => $results['errors']
    ]);
}

/**
 * Download file
 */
function handleDownload(FileManager $fm): void {
    Auth::requirePermission('file.view');

    $fileId = (int)($_GET['id'] ?? 0);
    if (!$fileId) {
        throw new Exception('File ID required');
    }

    $fm->downloadFile($fileId);
}

/**
 * Download multipli come ZIP
 */
function handleDownloadZip(FileManager $fm): void {
    Auth::requirePermission('file.view');

    $fileIds = $_GET['ids'] ?? $_POST['ids'] ?? '';

    if (is_string($fileIds)) {
        $fileIds = explode(',', $fileIds);
    }

    $fileIds = array_map('intval', array_filter($fileIds));

    if (empty($fileIds)) {
        throw new Exception('No file IDs provided');
    }

    $fm->downloadAsZip($fileIds);
}

/**
 * Lista file
 */
function handleList(FileManager $fm): void {
    Auth::requirePermission('file.view');

    $folderId = !empty($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;

    $options = [
        'search' => $_GET['search'] ?? null,
        'extension' => $_GET['extension'] ?? null,
        'order_by' => $_GET['order_by'] ?? 'name',
        'order_dir' => $_GET['order_dir'] ?? 'ASC',
        'limit' => !empty($_GET['limit']) ? (int)$_GET['limit'] : 100,
        'offset' => !empty($_GET['offset']) ? (int)$_GET['offset'] : 0
    ];

    $files = $fm->listFiles($folderId, $options);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'files' => $files,
        'count' => count($files)
    ]);
}

/**
 * Elimina file (soft delete)
 */
function handleDelete(FileManager $fm): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    Auth::requirePermission('file.delete');
    Auth::requireCsrfToken();

    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $fileId = (int)($data['id'] ?? $_GET['id'] ?? 0);

    if (!$fileId) {
        throw new Exception('File ID required');
    }

    $fm->deleteFile($fileId);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'File moved to trash'
    ]);
}

/**
 * Ripristina file dal cestino
 */
function handleRestore(FileManager $fm): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    Auth::requirePermission('file.edit');
    Auth::requireCsrfToken();

    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $fileId = (int)($data['id'] ?? 0);

    if (!$fileId) {
        throw new Exception('File ID required');
    }

    $fm->restoreFile($fileId);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'File restored'
    ]);
}

/**
 * Elimina permanentemente
 */
function handlePermanentDelete(FileManager $fm): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    Auth::requirePermission('file.delete');
    Auth::requireCsrfToken();

    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $fileId = (int)($data['id'] ?? $_GET['id'] ?? 0);

    if (!$fileId) {
        throw new Exception('File ID required');
    }

    // Solo admin può eliminare permanentemente
    if (!Auth::checkPermission('file.admin')) {
        throw new Exception('Permission denied');
    }

    $fm->permanentlyDeleteFile($fileId);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'File permanently deleted'
    ]);
}

/**
 * Rinomina file
 */
function handleRename(FileManager $fm): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    Auth::requirePermission('file.edit');
    Auth::requireCsrfToken();

    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $fileId = (int)($data['id'] ?? 0);
    $newName = $data['name'] ?? '';

    if (!$fileId || empty($newName)) {
        throw new Exception('File ID and new name required');
    }

    $file = $fm->renameFile($fileId, $newName);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'file' => $file
    ]);
}

/**
 * Sposta file
 */
function handleMove(FileManager $fm): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    Auth::requirePermission('file.edit');
    Auth::requireCsrfToken();

    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $fileId = (int)($data['id'] ?? 0);
    $folderId = isset($data['folder_id']) ? (int)$data['folder_id'] : null;

    if (!$fileId) {
        throw new Exception('File ID required');
    }

    $file = $fm->moveFile($fileId, $folderId);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'file' => $file
    ]);
}

/**
 * Lista file nel cestino
 */
function handleTrash(FileManager $fm): void {
    Auth::requirePermission('file.view');

    $files = $fm->getTrashFiles();

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'files' => $files,
        'count' => count($files)
    ]);
}

/**
 * Pulisce cestino
 */
function handleCleanTrash(FileManager $fm): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    Auth::requirePermission('file.admin');
    Auth::requireCsrfToken();

    $cleaned = $fm->cleanTrash();

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'cleaned' => $cleaned,
        'message' => "Cleaned {$cleaned} files from trash"
    ]);
}

/**
 * Statistiche storage
 */
function handleStats(FileManager $fm): void {
    Auth::requirePermission('file.view');

    $stats = $fm->getStorageStats();

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
}

/**
 * Ricerca file
 */
function handleSearch(FileManager $fm): void {
    Auth::requirePermission('file.view');

    $query = $_GET['q'] ?? '';
    if (empty($query)) {
        throw new Exception('Search query required');
    }

    // Ricerca in tutti i file del tenant
    $options = [
        'search' => $query,
        'limit' => !empty($_GET['limit']) ? (int)$_GET['limit'] : 50
    ];

    $files = $fm->listFiles(null, $options);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'query' => $query,
        'files' => $files,
        'count' => count($files)
    ]);
}