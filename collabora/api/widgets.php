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

if ($method === 'GET' && $action === 'data') {
    $widgetId = sanitizeInt($_GET['id'] ?? null);
    if (!$widgetId) {
        respondWithError('Widget non valido', 422);
    }
    $stmt = $pdo->prepare('SELECT w.*, d.user_id FROM dashboard_widgets w INNER JOIN dashboards d ON w.dashboard_id = d.id WHERE w.id = :id AND d.user_id = :user AND d.tenant_id = :tenant');
    $stmt->execute(['id' => $widgetId, 'user' => $user['id'], 'tenant' => $tenantId]);
    $widget = $stmt->fetch();
    if (!$widget) {
        respondWithError('Widget non trovato', 404);
    }
    $config = json_decode($widget['config'], true) ?? [];
    jsonResponse(['success' => true, 'data' => buildWidgetData($pdo, $tenantId, $widget['widget_type'], $config)]);
}

switch ($method) {
    case 'GET':
        $dashboardId = sanitizeInt($_GET['dashboard_id'] ?? null);
        if (!$dashboardId) {
            respondWithError('Dashboard non valida', 422);
        }
        $stmt = $pdo->prepare('SELECT w.id, w.widget_type, w.title, w.position, w.config FROM dashboard_widgets w INNER JOIN dashboards d ON w.dashboard_id = d.id WHERE w.dashboard_id = :dashboard AND d.user_id = :user AND d.tenant_id = :tenant ORDER BY JSON_EXTRACT(w.position, "$.y"), JSON_EXTRACT(w.position, "$.x")');
        $stmt->execute(['dashboard' => $dashboardId, 'user' => $user['id'], 'tenant' => $tenantId]);
        $widgets = $stmt->fetchAll();
        jsonResponse(['success' => true, 'widgets' => array_map(static fn($row) => [
            'id' => (int)$row['id'],
            'widget_type' => $row['widget_type'],
            'title' => $row['title'],
            'position' => json_decode($row['position'], true),
            'config' => json_decode($row['config'], true),
        ], $widgets)]);
        break;

    case 'POST':
        requireCsrfToken();
        $data = getJsonInput();
        $dashboardId = sanitizeInt($data['dashboard_id'] ?? null);
        if (!$dashboardId) {
            respondWithError('Dashboard non valida', 422);
        }
        $stmt = $pdo->prepare('SELECT id FROM dashboards WHERE id = :id AND user_id = :user AND tenant_id = :tenant');
        $stmt->execute(['id' => $dashboardId, 'user' => $user['id'], 'tenant' => $tenantId]);
        if (!$stmt->fetch()) {
            respondWithError('Dashboard non trovata', 404);
        }
        $widgetType = sanitizeString($data['widget_type'] ?? 'metric');
        $title = sanitizeString($data['title'] ?? 'Widget');
        $position = $data['position'] ?? ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1];
        $config = $data['config'] ?? [];
        $stmt = $pdo->prepare('INSERT INTO dashboard_widgets (dashboard_id, widget_type, title, position, config) VALUES (:dashboard, :type, :title, :position, :config)');
        $stmt->execute([
            'dashboard' => $dashboardId,
            'type' => $widgetType,
            'title' => $title,
            'position' => json_encode($position, JSON_UNESCAPED_UNICODE),
            'config' => json_encode($config, JSON_UNESCAPED_UNICODE),
        ]);
        jsonResponse(['success' => true, 'widget_id' => (int)$pdo->lastInsertId()], 201);
        break;

    case 'DELETE':
        requireCsrfToken();
        $widgetId = sanitizeInt($_GET['id'] ?? null);
        if (!$widgetId) {
            respondWithError('Widget non valido', 422);
        }
        $stmt = $pdo->prepare('DELETE FROM dashboard_widgets USING dashboard_widgets INNER JOIN dashboards ON dashboard_widgets.dashboard_id = dashboards.id WHERE dashboard_widgets.id = :id AND dashboards.user_id = :user AND dashboards.tenant_id = :tenant');
        $stmt->execute(['id' => $widgetId, 'user' => $user['id'], 'tenant' => $tenantId]);
        if ($stmt->rowCount() === 0) {
            respondWithError('Widget non trovato', 404);
        }
        jsonResponse(['success' => true]);
        break;

    default:
        respondWithError('Metodo non gestito', 405);
}

function buildWidgetData(PDO $pdo, int $tenantId, string $type, array $config): array
{
    switch ($type) {
        case 'metric':
            $metric = $config['metric_name'] ?? 'files_uploaded';
            $stmt = $pdo->prepare('SELECT metric_value, date FROM metrics WHERE tenant_id = :tenant AND metric_name = :metric ORDER BY date DESC, hour DESC LIMIT 30');
            $stmt->execute(['tenant' => $tenantId, 'metric' => $metric]);
            $rows = $stmt->fetchAll();
            $values = array_map(static fn($row) => (float)$row['metric_value'], array_reverse($rows));
            $current = $rows[0]['metric_value'] ?? 0;
            $previous = $rows[1]['metric_value'] ?? $current;
            $trend = $previous != 0 ? (($current - $previous) / $previous) * 100 : 0;
            return [
                'value' => round((float)$current, 2),
                'trend' => sprintf('%+.1f%%', $trend),
                'sparkline' => $values,
            ];
        case 'list':
            return ['items' => []];
        case 'chart':
            return ['labels' => [], 'series' => []];
        case 'gauge':
            return ['value' => 0, 'max' => $config['max_value'] ?? 100];
        default:
            return [];
    }
}
