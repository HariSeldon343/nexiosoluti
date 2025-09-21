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

switch ($method) {
    case 'GET':
        if (isset($_GET['tree'])) {
            $stmt = $pdo->prepare('SELECT id, parent_id, name, path FROM folders WHERE tenant_id = :tenant ORDER BY parent_id, name');
            $stmt->execute(['tenant' => $tenantId]);
            $folders = $stmt->fetchAll();
            $tree = buildTree($folders);
            jsonResponse(['success' => true, 'folders' => $tree]);
        }
        $parentIdRaw = $_GET['parent_id'] ?? null;
        $parentId = $parentIdRaw === null || $parentIdRaw === 'null' || $parentIdRaw === '' ? null : sanitizeInt($parentIdRaw);
        if ($parentId) {
            $stmt = $pdo->prepare('SELECT id, name, path, parent_id FROM folders WHERE tenant_id = :tenant AND parent_id = :parent ORDER BY name');
            $stmt->execute(['tenant' => $tenantId, 'parent' => $parentId]);
        } else {
            $stmt = $pdo->prepare('SELECT id, name, path, parent_id FROM folders WHERE tenant_id = :tenant AND parent_id IS NULL ORDER BY name');
            $stmt->execute(['tenant' => $tenantId]);
        }
        $folders = $stmt->fetchAll();
        jsonResponse(['success' => true, 'folders' => array_map('formatFolder', $folders)]);
        break;

    case 'POST':
        requireCsrfToken();
        $data = getJsonInput();
        $name = sanitizeString($data['name'] ?? '');
        if (!$name) {
            respondWithError('Nome cartella obbligatorio', 422);
        }
        $parentId = isset($data['parent_id']) && $data['parent_id'] !== '' ? sanitizeInt($data['parent_id']) : null;
        $parentPath = '/';
        if ($parentId) {
            $stmt = $pdo->prepare('SELECT id, path, tenant_id FROM folders WHERE id = :id');
            $stmt->execute(['id' => $parentId]);
            $parent = $stmt->fetch();
            if (!$parent) {
                respondWithError('Cartella padre non trovata', 404);
            }
            verifyOwnership((int)$parent['tenant_id'], $tenantId);
            $parentPath = rtrim($parent['path'], '/') . '/';
        }
        $slug = createSlug($name);
        $path = ($parentPath === '/' ? '/' . $slug : $parentPath . $slug) . '/';
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM folders WHERE tenant_id = :tenant AND path = :path');
        $stmt->execute(['tenant' => $tenantId, 'path' => $path]);
        if ((int)$stmt->fetchColumn() > 0) {
            respondWithError('Esiste già una cartella con questo nome', 409);
        }
        $stmt = $pdo->prepare('INSERT INTO folders (tenant_id, parent_id, name, path, created_by) VALUES (:tenant, :parent, :name, :path, :user)');
        $stmt->execute([
            'tenant' => $tenantId,
            'parent' => $parentId,
            'name' => $name,
            'path' => $path,
            'user' => $user['id'],
        ]);
        $folderId = (int)$pdo->lastInsertId();
        logActivity($pdo, $tenantId, $user['id'], 'create', 'folder', $folderId, ['name' => $name]);
        jsonResponse(['success' => true, 'folder' => ['id' => $folderId, 'name' => $name, 'path' => $path, 'parent_id' => $parentId]], 201);
        break;

    case 'PUT':
    case 'PATCH':
        requireCsrfToken();
        parse_str(file_get_contents('php://input'), $rawInput);
        $data = array_merge($rawInput, getJsonInput());
        $folderId = sanitizeInt($_GET['id'] ?? null);
        if (!$folderId) {
            respondWithError('Cartella non valida', 422);
        }
        $stmt = $pdo->prepare('SELECT * FROM folders WHERE id = :id');
        $stmt->execute(['id' => $folderId]);
        $folder = $stmt->fetch();
        if (!$folder) {
            respondWithError('Cartella non trovata', 404);
        }
        verifyOwnership((int)$folder['tenant_id'], $tenantId);
        $name = sanitizeString($data['name'] ?? $folder['name']);
        if (!$name) {
            respondWithError('Nome cartella obbligatorio', 422);
        }
        $slug = createSlug($name);
        $parentPath = $folder['parent_id'] ? getParentPath((int)$folder['parent_id'], $pdo, $tenantId) : '/';
        $newPath = ($parentPath === '/' ? '/' . $slug : $parentPath . $slug) . '/';
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM folders WHERE tenant_id = :tenant AND path = :path AND id <> :id');
        $stmt->execute(['tenant' => $tenantId, 'path' => $newPath, 'id' => $folderId]);
        if ((int)$stmt->fetchColumn() > 0) {
            respondWithError('Esiste già una cartella con questo nome', 409);
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE folders SET name = :name, path = :path WHERE id = :id')->execute([
                'name' => $name,
                'path' => $newPath,
                'id' => $folderId,
            ]);
            // Aggiorna path figli
            $stmtChildren = $pdo->prepare('SELECT id, path FROM folders WHERE parent_id = :parent');
            $stmtChildren->execute(['parent' => $folderId]);
            while ($child = $stmtChildren->fetch()) {
                $newChildPath = preg_replace('#^' . preg_quote($folder['path'], '#') . '#', $newPath, $child['path']);
                $pdo->prepare('UPDATE folders SET path = :path WHERE id = :id')->execute(['path' => $newChildPath, 'id' => $child['id']]);
            }
            $pdo->commit();
        } catch (\Throwable $throwable) {
            $pdo->rollBack();
            respondWithError('Errore durante l\'aggiornamento della cartella', 500);
        }
        logActivity($pdo, $tenantId, $user['id'], 'update', 'folder', $folderId, ['name' => $name]);
        jsonResponse(['success' => true]);
        break;

    case 'DELETE':
        requireCsrfToken();
        $folderId = sanitizeInt($_GET['id'] ?? null);
        if (!$folderId) {
            respondWithError('Cartella non valida', 422);
        }
        $stmt = $pdo->prepare('SELECT * FROM folders WHERE id = :id');
        $stmt->execute(['id' => $folderId]);
        $folder = $stmt->fetch();
        if (!$folder) {
            respondWithError('Cartella non trovata', 404);
        }
        verifyOwnership((int)$folder['tenant_id'], $tenantId);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM folders WHERE parent_id = :id');
        $stmt->execute(['id' => $folderId]);
        if ((int)$stmt->fetchColumn() > 0) {
            respondWithError('Eliminare prima le sottocartelle', 409);
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM files WHERE folder_id = :id AND is_deleted = 0');
        $stmt->execute(['id' => $folderId]);
        if ((int)$stmt->fetchColumn() > 0) {
            respondWithError('La cartella non è vuota', 409);
        }
        $pdo->prepare('DELETE FROM folders WHERE id = :id')->execute(['id' => $folderId]);
        logActivity($pdo, $tenantId, $user['id'], 'delete', 'folder', $folderId);
        jsonResponse(['success' => true]);
        break;

    default:
        respondWithError('Metodo non gestito', 405);
}

function buildTree(array $folders, $parentId = null): array
{
    $branch = [];
    foreach ($folders as $folder) {
        if ((int)$folder['parent_id'] === (int)$parentId || ($parentId === null && $folder['parent_id'] === null)) {
            $children = buildTree($folders, $folder['id']);
            $node = formatFolder($folder);
            if ($children) {
                $node['children'] = $children;
            }
            $branch[] = $node;
        }
    }
    return $branch;
}

function formatFolder(array $folder): array
{
    return [
        'id' => (int)$folder['id'],
        'name' => $folder['name'],
        'path' => $folder['path'],
        'parent_id' => $folder['parent_id'] !== null ? (int)$folder['parent_id'] : null,
    ];
}

function createSlug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/u', '-', $value);
    $value = trim($value, '-');
    return $value !== '' ? $value : bin2hex(random_bytes(4));
}

function getParentPath(int $parentId, \PDO $pdo, int $tenantId): string
{
    $stmt = $pdo->prepare('SELECT path, tenant_id FROM folders WHERE id = :id');
    $stmt->execute(['id' => $parentId]);
    $parent = $stmt->fetch();
    if (!$parent) {
        return '/';
    }
    verifyOwnership((int)$parent['tenant_id'], $tenantId);
    return rtrim($parent['path'], '/') . '/';
}
