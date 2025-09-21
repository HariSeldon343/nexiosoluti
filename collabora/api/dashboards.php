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
        $stmt = $pdo->prepare('SELECT id, name, layout, is_default FROM dashboards WHERE user_id = :user AND tenant_id = :tenant ORDER BY is_default DESC, created_at DESC');
        $stmt->execute(['user' => $user['id'], 'tenant' => $tenantId]);
        $dashboards = $stmt->fetchAll();
        jsonResponse(['success' => true, 'dashboards' => array_map(static fn($row) => [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'layout' => json_decode($row['layout'], true),
            'is_default' => (bool)$row['is_default'],
        ], $dashboards)]);
        break;

    case 'POST':
        requireCsrfToken();
        $data = getJsonInput();
        $name = sanitizeString($data['name'] ?? '');
        $layout = $data['layout'] ?? [];
        if (!$name || !is_array($layout)) {
            respondWithError('Dati dashboard non validi', 422);
        }
        if (!empty($data['is_default'])) {
            $pdo->prepare('UPDATE dashboards SET is_default = 0 WHERE user_id = :user AND tenant_id = :tenant')->execute([
                'user' => $user['id'],
                'tenant' => $tenantId,
            ]);
        }
        $stmt = $pdo->prepare('INSERT INTO dashboards (user_id, tenant_id, name, layout, is_default) VALUES (:user, :tenant, :name, :layout, :default)');
        $stmt->execute([
            'user' => $user['id'],
            'tenant' => $tenantId,
            'name' => $name,
            'layout' => json_encode($layout, JSON_UNESCAPED_UNICODE),
            'default' => !empty($data['is_default']) ? 1 : 0,
        ]);
        jsonResponse(['success' => true, 'dashboard_id' => (int)$pdo->lastInsertId()], 201);
        break;

    case 'PUT':
        requireCsrfToken();
        $dashboardId = sanitizeInt($_GET['id'] ?? null);
        if (!$dashboardId) {
            respondWithError('Dashboard non valida', 422);
        }
        $data = getJsonInput();
        $stmt = $pdo->prepare('SELECT id FROM dashboards WHERE id = :id AND user_id = :user AND tenant_id = :tenant');
        $stmt->execute(['id' => $dashboardId, 'user' => $user['id'], 'tenant' => $tenantId]);
        if (!$stmt->fetch()) {
            respondWithError('Dashboard non trovata', 404);
        }
        $fields = [];
        $params = ['id' => $dashboardId];
        if (isset($data['name'])) {
            $fields[] = 'name = :name';
            $params['name'] = sanitizeString($data['name']);
        }
        if (isset($data['layout'])) {
            $fields[] = 'layout = :layout';
            $params['layout'] = json_encode($data['layout'], JSON_UNESCAPED_UNICODE);
        }
        if (isset($data['is_default'])) {
            if ($data['is_default']) {
                $pdo->prepare('UPDATE dashboards SET is_default = 0 WHERE user_id = :user AND tenant_id = :tenant AND id <> :id')->execute([
                    'user' => $user['id'],
                    'tenant' => $tenantId,
                    'id' => $dashboardId,
                ]);
            }
            $fields[] = 'is_default = :default';
            $params['default'] = (int)(bool)$data['is_default'];
        }
        if ($fields) {
            $pdo->prepare('UPDATE dashboards SET ' . implode(', ', $fields) . ' WHERE id = :id')->execute($params);
        }
        jsonResponse(['success' => true]);
        break;

    case 'DELETE':
        requireCsrfToken();
        $dashboardId = sanitizeInt($_GET['id'] ?? null);
        if (!$dashboardId) {
            respondWithError('Dashboard non valida', 422);
        }
        $stmt = $pdo->prepare('DELETE FROM dashboards WHERE id = :id AND user_id = :user AND tenant_id = :tenant');
        $stmt->execute(['id' => $dashboardId, 'user' => $user['id'], 'tenant' => $tenantId]);
        if ($stmt->rowCount() === 0) {
            respondWithError('Dashboard non trovata', 404);
        }
        jsonResponse(['success' => true]);
        break;

    default:
        respondWithError('Metodo non gestito', 405);
}
