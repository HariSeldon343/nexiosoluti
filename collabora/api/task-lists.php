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
        $stmt = $pdo->prepare('SELECT id, name, description, color, position FROM task_lists WHERE tenant_id = :tenant ORDER BY position ASC, id ASC');
        $stmt->execute(['tenant' => $tenantId]);
        $lists = $stmt->fetchAll();
        jsonResponse(['success' => true, 'lists' => array_map(static fn($row) => [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'color' => $row['color'],
            'position' => (int)$row['position'],
        ], $lists)]);
        break;

    case 'POST':
        requireCsrfToken();
        $data = getJsonInput();
        $name = sanitizeString($data['name'] ?? '');
        if (!$name) {
            respondWithError('Nome lista obbligatorio', 422);
        }
        $stmt = $pdo->prepare('INSERT INTO task_lists (tenant_id, name, description, color, position, created_by) VALUES (:tenant, :name, :description, :color, :position, :user)');
        $stmt->execute([
            'tenant' => $tenantId,
            'name' => $name,
            'description' => sanitizeString($data['description'] ?? ''),
            'color' => sanitizeString($data['color'] ?? '#6B7280'),
            'position' => sanitizeInt($data['position'] ?? 0) ?? 0,
            'user' => $user['id'],
        ]);
        $listId = (int)$pdo->lastInsertId();
        jsonResponse(['success' => true, 'list_id' => $listId], 201);
        break;

    case 'PUT':
    case 'PATCH':
        requireCsrfToken();
        $listId = sanitizeInt($_GET['id'] ?? null);
        if (!$listId) {
            respondWithError('Lista non valida', 422);
        }
        parse_str(file_get_contents('php://input'), $rawInput);
        $data = array_merge($rawInput, getJsonInput());
        $stmt = $pdo->prepare('SELECT id FROM task_lists WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute(['id' => $listId, 'tenant' => $tenantId]);
        if (!$stmt->fetch()) {
            respondWithError('Lista non trovata', 404);
        }
        $fields = [];
        $params = ['id' => $listId];
        if (isset($data['name'])) {
            $fields[] = 'name = :name';
            $params['name'] = sanitizeString($data['name']);
        }
        if (isset($data['description'])) {
            $fields[] = 'description = :description';
            $params['description'] = sanitizeString($data['description']);
        }
        if (isset($data['color'])) {
            $fields[] = 'color = :color';
            $params['color'] = sanitizeString($data['color']);
        }
        if (isset($data['position'])) {
            $fields[] = 'position = :position';
            $params['position'] = sanitizeInt($data['position']);
        }
        if ($fields) {
            $pdo->prepare('UPDATE task_lists SET ' . implode(', ', $fields) . ' WHERE id = :id')->execute($params);
        }
        jsonResponse(['success' => true]);
        break;

    case 'DELETE':
        requireCsrfToken();
        $listId = sanitizeInt($_GET['id'] ?? null);
        if (!$listId) {
            respondWithError('Lista non valida', 422);
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE task_list_id = :list AND tenant_id = :tenant');
        $stmt->execute(['list' => $listId, 'tenant' => $tenantId]);
        if ((int)$stmt->fetchColumn() > 0) {
            respondWithError('Impossibile eliminare liste con task associati', 409);
        }
        $stmt = $pdo->prepare('DELETE FROM task_lists WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute(['id' => $listId, 'tenant' => $tenantId]);
        if ($stmt->rowCount() === 0) {
            respondWithError('Lista non trovata', 404);
        }
        jsonResponse(['success' => true]);
        break;

    default:
        respondWithError('Metodo non gestito', 405);
}
