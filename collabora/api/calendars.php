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
        $stmt = $pdo->prepare('SELECT id, name, color, is_default FROM calendars WHERE tenant_id = :tenant ORDER BY name');
        $stmt->execute(['tenant' => $tenantId]);
        $calendars = $stmt->fetchAll();
        jsonResponse(['success' => true, 'calendars' => array_map(static fn($row) => [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'color' => $row['color'],
            'is_default' => (bool)$row['is_default'],
        ], $calendars)]);
        break;

    case 'POST':
        requireCsrfToken();
        $data = getJsonInput();
        $name = sanitizeString($data['name'] ?? '');
        $color = sanitizeString($data['color'] ?? '#2563EB');
        $isDefault = !empty($data['is_default']);
        if (!$name) {
            respondWithError('Nome calendario obbligatorio', 422);
        }
        if ($isDefault) {
            $pdo->prepare('UPDATE calendars SET is_default = 0 WHERE tenant_id = :tenant')->execute(['tenant' => $tenantId]);
        }
        $stmt = $pdo->prepare('INSERT INTO calendars (tenant_id, name, color, is_default, created_by) VALUES (:tenant, :name, :color, :default, :user)');
        $stmt->execute([
            'tenant' => $tenantId,
            'name' => $name,
            'color' => $color,
            'default' => $isDefault ? 1 : 0,
            'user' => $user['id'],
        ]);
        $calendarId = (int)$pdo->lastInsertId();
        jsonResponse(['success' => true, 'calendar_id' => $calendarId], 201);
        break;

    case 'PUT':
    case 'PATCH':
        requireCsrfToken();
        $calendarId = sanitizeInt($_GET['id'] ?? null);
        if (!$calendarId) {
            respondWithError('Calendario non valido', 422);
        }
        parse_str(file_get_contents('php://input'), $rawInput);
        $data = array_merge($rawInput, getJsonInput());
        $stmt = $pdo->prepare('SELECT * FROM calendars WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute(['id' => $calendarId, 'tenant' => $tenantId]);
        $calendar = $stmt->fetch();
        if (!$calendar) {
            respondWithError('Calendario non trovato', 404);
        }
        $fields = [];
        $params = ['id' => $calendarId];
        if (isset($data['name'])) {
            $fields[] = 'name = :name';
            $params['name'] = sanitizeString($data['name']);
        }
        if (isset($data['color'])) {
            $fields[] = 'color = :color';
            $params['color'] = sanitizeString($data['color']);
        }
        if (isset($data['is_default'])) {
            $isDefault = (bool)$data['is_default'];
            if ($isDefault) {
                $pdo->prepare('UPDATE calendars SET is_default = 0 WHERE tenant_id = :tenant')->execute(['tenant' => $tenantId]);
            }
            $fields[] = 'is_default = :default';
            $params['default'] = $isDefault ? 1 : 0;
        }
        if ($fields) {
            $pdo->prepare('UPDATE calendars SET ' . implode(', ', $fields) . ' WHERE id = :id')->execute($params);
        }
        jsonResponse(['success' => true]);
        break;

    case 'DELETE':
        requireCsrfToken();
        $calendarId = sanitizeInt($_GET['id'] ?? null);
        if (!$calendarId) {
            respondWithError('Calendario non valido', 422);
        }
        $stmt = $pdo->prepare('DELETE FROM calendars WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute(['id' => $calendarId, 'tenant' => $tenantId]);
        if ($stmt->rowCount() === 0) {
            respondWithError('Calendario non trovato', 404);
        }
        jsonResponse(['success' => true]);
        break;

    default:
        respondWithError('Metodo non gestito', 405);
}
