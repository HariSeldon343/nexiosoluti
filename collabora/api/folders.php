<?php
declare(strict_types=1);

/**
 * API endpoints per gestione cartelle
 * Create, list, rename, move, delete
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/FileManager.php';

// Headers CORS e JSON
header('Content-Type: application/json');
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
        case 'create':
            handleCreate($fileManager);
            break;

        case 'list':
            handleList($fileManager);
            break;

        case 'tree':
            handleTree($fileManager);
            break;

        case 'rename':
            handleRename($fileManager);
            break;

        case 'move':
            handleMove($fileManager);
            break;

        case 'delete':
            handleDelete($fileManager);
            break;

        case 'info':
            handleInfo($fileManager);
            break;

        case 'breadcrumb':
            handleBreadcrumb($fileManager);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Crea nuova cartella
 */
function handleCreate(FileManager $fm): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    Auth::requirePermission('folder.create');
    Auth::requireCsrfToken();

    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $name = $data['name'] ?? '';
    $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;

    if (empty($name)) {
        throw new Exception('Folder name required');
    }

    $folder = $fm->createFolder($name, $parentId);

    echo json_encode([
        'success' => true,
        'folder' => $folder
    ]);
}

/**
 * Lista cartelle
 */
function handleList(FileManager $fm): void {
    Auth::requirePermission('folder.view');

    $parentId = !empty($_GET['parent_id']) ? (int)$_GET['parent_id'] : null;
    $folders = $fm->listFolders($parentId);

    echo json_encode([
        'success' => true,
        'folders' => $folders,
        'count' => count($folders)
    ]);
}

/**
 * Albero cartelle completo
 */
function handleTree(FileManager $fm): void {
    Auth::requirePermission('folder.view');

    $tree = buildFolderTree();

    echo json_encode([
        'success' => true,
        'tree' => $tree
    ]);
}

/**
 * Rinomina cartella
 */
function handleRename(FileManager $fm): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    Auth::requirePermission('folder.edit');
    Auth::requireCsrfToken();

    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $folderId = (int)($data['id'] ?? 0);
    $newName = $data['name'] ?? '';

    if (!$folderId || empty($newName)) {
        throw new Exception('Folder ID and new name required');
    }

    $cleanName = clean_filename($newName);

    // Aggiorna nome e path
    Database::update('folders', ['name' => $cleanName], [
        'id' => $folderId,
        'tenant_id' => Auth::getTenantId()
    ]);

    // Aggiorna path di tutte le sottocartelle
    updateFolderPaths($folderId);

    // Log attività
    Auth::logActivity('folder_rename', 'folder', $folderId, [
        'new_name' => $cleanName
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Folder renamed successfully'
    ]);
}

/**
 * Sposta cartella
 */
function handleMove(FileManager $fm): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    Auth::requirePermission('folder.edit');
    Auth::requireCsrfToken();

    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $folderId = (int)($data['id'] ?? 0);
    $newParentId = isset($data['parent_id']) ? (int)$data['parent_id'] : null;

    if (!$folderId) {
        throw new Exception('Folder ID required');
    }

    // Previeni loop (cartella dentro se stessa)
    if ($newParentId === $folderId) {
        throw new Exception('Cannot move folder into itself');
    }

    // Verifica che newParentId non sia una sottocartella di folderId
    if ($newParentId && isSubfolder($newParentId, $folderId)) {
        throw new Exception('Cannot move folder into its own subfolder');
    }

    // Aggiorna parent
    Database::update('folders', ['parent_id' => $newParentId], [
        'id' => $folderId,
        'tenant_id' => Auth::getTenantId()
    ]);

    // Aggiorna tutti i path
    updateFolderPaths($folderId);

    // Log attività
    Auth::logActivity('folder_move', 'folder', $folderId, [
        'new_parent' => $newParentId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Folder moved successfully'
    ]);
}

/**
 * Elimina cartella
 */
function handleDelete(FileManager $fm): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    Auth::requirePermission('folder.delete');
    Auth::requireCsrfToken();

    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $folderId = (int)($data['id'] ?? $_GET['id'] ?? 0);

    if (!$folderId) {
        throw new Exception('Folder ID required');
    }

    $fm->deleteFolder($folderId);

    echo json_encode([
        'success' => true,
        'message' => 'Folder deleted successfully'
    ]);
}

/**
 * Info cartella
 */
function handleInfo(FileManager $fm): void {
    Auth::requirePermission('folder.view');

    $folderId = (int)($_GET['id'] ?? 0);
    if (!$folderId) {
        throw new Exception('Folder ID required');
    }

    $folder = Database::selectOne(
        "SELECT f.*, u.full_name as created_by_name
         FROM folders f
         LEFT JOIN users u ON f.created_by = u.id
         WHERE f.id = :id AND f.tenant_id = :tenant_id",
        ['id' => $folderId, 'tenant_id' => Auth::getTenantId()]
    );

    if (!$folder) {
        throw new Exception('Folder not found');
    }

    // Conta contenuto
    $stats = Database::selectOne(
        "SELECT
            (SELECT COUNT(*) FROM files WHERE folder_id = :id AND is_deleted = FALSE) as file_count,
            (SELECT COALESCE(SUM(size), 0) FROM files WHERE folder_id = :id AND is_deleted = FALSE) as total_size,
            (SELECT COUNT(*) FROM folders WHERE parent_id = :id) as subfolder_count",
        ['id' => $folderId]
    );

    $folder['stats'] = $stats;

    echo json_encode([
        'success' => true,
        'folder' => $folder
    ]);
}

/**
 * Breadcrumb navigazione
 */
function handleBreadcrumb(FileManager $fm): void {
    Auth::requirePermission('folder.view');

    $folderId = !empty($_GET['id']) ? (int)$_GET['id'] : null;

    $breadcrumb = [];

    // Root sempre presente
    $breadcrumb[] = [
        'id' => null,
        'name' => 'Root',
        'path' => '/'
    ];

    if ($folderId) {
        $current = $folderId;

        while ($current) {
            $folder = Database::selectOne(
                "SELECT id, parent_id, name, path
                 FROM folders
                 WHERE id = :id AND tenant_id = :tenant_id",
                ['id' => $current, 'tenant_id' => Auth::getTenantId()]
            );

            if ($folder) {
                array_splice($breadcrumb, 1, 0, [[
                    'id' => $folder['id'],
                    'name' => $folder['name'],
                    'path' => $folder['path']
                ]]);
                $current = $folder['parent_id'];
            } else {
                break;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'breadcrumb' => $breadcrumb
    ]);
}

/**
 * Helper: costruisce albero cartelle ricorsivo
 */
function buildFolderTree(?int $parentId = null): array {
    $tenantId = Auth::getTenantId();

    $folders = Database::select(
        "SELECT id, name, parent_id, path,
         (SELECT COUNT(*) FROM folders WHERE parent_id = f.id) as has_children
         FROM folders f
         WHERE tenant_id = :tenant_id AND " .
        ($parentId === null ? "parent_id IS NULL" : "parent_id = :parent_id") .
        " ORDER BY name",
        array_filter([
            'tenant_id' => $tenantId,
            'parent_id' => $parentId
        ])
    );

    $tree = [];
    foreach ($folders as $folder) {
        $node = [
            'id' => $folder['id'],
            'name' => $folder['name'],
            'path' => $folder['path'],
            'children' => []
        ];

        if ($folder['has_children'] > 0) {
            $node['children'] = buildFolderTree($folder['id']);
        }

        $tree[] = $node;
    }

    return $tree;
}

/**
 * Helper: verifica se una cartella è sottocartella di un'altra
 */
function isSubfolder(int $checkId, int $parentId): bool {
    $current = $checkId;

    while ($current) {
        $folder = Database::selectOne(
            "SELECT parent_id FROM folders
             WHERE id = :id AND tenant_id = :tenant_id",
            ['id' => $current, 'tenant_id' => Auth::getTenantId()]
        );

        if (!$folder) {
            return false;
        }

        if ($folder['parent_id'] == $parentId) {
            return true;
        }

        $current = $folder['parent_id'];
    }

    return false;
}

/**
 * Helper: aggiorna path di cartella e sottocartelle
 */
function updateFolderPaths(int $folderId): void {
    $folder = Database::selectOne(
        "SELECT * FROM folders
         WHERE id = :id AND tenant_id = :tenant_id",
        ['id' => $folderId, 'tenant_id' => Auth::getTenantId()]
    );

    if (!$folder) {
        return;
    }

    // Calcola nuovo path
    $newPath = '/';
    if ($folder['parent_id']) {
        $parent = Database::selectOne(
            "SELECT path FROM folders WHERE id = :id",
            ['id' => $folder['parent_id']]
        );
        if ($parent) {
            $newPath = $parent['path'] . $folder['name'] . '/';
        }
    } else {
        $newPath = '/' . $folder['name'] . '/';
    }

    // Aggiorna path
    Database::update('folders', ['path' => $newPath], ['id' => $folderId]);

    // Aggiorna ricorsivamente sottocartelle
    $subfolders = Database::select(
        "SELECT id FROM folders WHERE parent_id = :parent_id",
        ['parent_id' => $folderId]
    );

    foreach ($subfolders as $subfolder) {
        updateFolderPaths($subfolder['id']);
    }
}