<?php
require_once __DIR__ . '/../config.php';

$user = require_login($auth);
$tenantId = (int)$user['tenant']['id'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $stmt = $pdo->prepare('SELECT id, parent_id, name FROM folders WHERE tenant_id = :tenant ORDER BY name');
        $stmt->execute([':tenant' => $tenantId]);
        $folders = $stmt->fetchAll();
        json_response(['folders' => array_map(fn($f) => [
            'id' => (int)$f['id'],
            'parent_id' => $f['parent_id'] !== null ? (int)$f['parent_id'] : null,
            'name' => $f['name'],
        ], $folders)]);
    case 'POST':
        require_method('POST');
        $data = get_json_input();
        verify_csrf_token($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $name = sanitize($data['name'] ?? '');
        $parentId = isset($data['parent_id']) ? (int)$data['parent_id'] : null;
        if (!$name) {
            json_response(['success' => false, 'error' => 'Nome obbligatorio'], 422);
        }
        $path = '/' . strtolower(preg_replace('/\s+/', '-', $name)) . '/';
        if ($parentId) {
            $stmt = $pdo->prepare('SELECT path FROM folders WHERE id = :id AND tenant_id = :tenant');
            $stmt->execute([':id' => $parentId, ':tenant' => $tenantId]);
            $parent = $stmt->fetch();
            if (!$parent) {
                json_response(['success' => false, 'error' => 'Cartella padre non trovata'], 404);
            }
            $path = rtrim($parent['path'], '/') . $path;
        }
        $stmt = $pdo->prepare('INSERT INTO folders (tenant_id, parent_id, name, path, created_by) VALUES (:tenant, :parent, :name, :path, :user)');
        $stmt->execute([
            ':tenant' => $tenantId,
            ':parent' => $parentId,
            ':name' => $name,
            ':path' => $path,
            ':user' => $user['id'],
        ]);
        json_response(['success' => true, 'folder' => ['id' => (int)$pdo->lastInsertId(), 'name' => $name, 'parent_id' => $parentId]]);
    case 'PUT':
        parse_str(file_get_contents('php://input'), $input);
        verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ''));
        $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
        $name = sanitize($input['name'] ?? '');
        if (!$id || !$name) {
            json_response(['success' => false, 'error' => 'Dati mancanti'], 422);
        }
        $stmt = $pdo->prepare('UPDATE folders SET name = :name, path = CONCAT(SUBSTRING_INDEX(path, "/", 2), :suffix) WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute([
            ':name' => $name,
            ':suffix' => '/' . strtolower(preg_replace('/\s+/', '-', $name)) . '/',
            ':id' => $id,
            ':tenant' => $tenantId,
        ]);
        json_response(['success' => true]);
    case 'DELETE':
        parse_str(file_get_contents('php://input'), $input);
        verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ''));
        $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
        if (!$id) {
            json_response(['success' => false, 'error' => 'ID mancante'], 422);
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM files WHERE folder_id = :id AND tenant_id = :tenant AND is_deleted = 0');
        $stmt->execute([':id' => $id, ':tenant' => $tenantId]);
        if ($stmt->fetchColumn() > 0) {
            json_response(['success' => false, 'error' => 'Cartella non vuota'], 409);
        }
        $stmt = $pdo->prepare('DELETE FROM folders WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute([':id' => $id, ':tenant' => $tenantId]);
        json_response(['success' => true]);
    default:
        json_response(['error' => 'Metodo non supportato'], 405);
}
