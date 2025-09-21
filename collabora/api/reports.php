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

switch ($method) {
    case 'GET':
        $stmt = $pdo->prepare('SELECT id, name, type, template, schedule, created_at FROM reports WHERE tenant_id = :tenant ORDER BY created_at DESC');
        $stmt->execute(['tenant' => $tenantId]);
        $reports = $stmt->fetchAll();
        jsonResponse(['success' => true, 'reports' => array_map(static fn($row) => [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'type' => $row['type'],
            'template' => $row['template'],
            'schedule' => json_decode($row['schedule'], true),
            'created_at' => $row['created_at'],
        ], $reports)]);
        break;

    case 'POST':
        requireCsrfToken();
        if ($action === 'generate') {
            $data = getJsonInput();
            $type = sanitizeString($data['type'] ?? '');
            if (!$type) {
                respondWithError('Tipo report obbligatorio', 422);
            }
            $filename = 'report_' . $type . '_' . date('Ymd_His') . '.pdf';
            $reportDir = BASE_PATH . '/temp/reports';
            if (!is_dir($reportDir)) {
                mkdir($reportDir, 0755, true);
            }
            $filepath = $reportDir . '/' . $filename;
            file_put_contents($filepath, 'Report generato il ' . date('Y-m-d H:i:s'));
            jsonResponse(['success' => true, 'download_url' => '/collabora/temp/reports/' . $filename]);
            break;
        }
        $data = getJsonInput();
        $name = sanitizeString($data['name'] ?? '');
        $type = sanitizeString($data['type'] ?? '');
        $template = sanitizeString($data['template'] ?? '');
        $parameters = $data['parameters'] ?? [];
        if (!$name || !$type || !$template) {
            respondWithError('Dati report mancanti', 422);
        }
        $stmt = $pdo->prepare('INSERT INTO reports (tenant_id, name, type, template, parameters, schedule, created_by) VALUES (:tenant, :name, :type, :template, :parameters, :schedule, :user)');
        $stmt->execute([
            'tenant' => $tenantId,
            'name' => $name,
            'type' => $type,
            'template' => $template,
            'parameters' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
            'schedule' => isset($data['schedule']) ? json_encode($data['schedule'], JSON_UNESCAPED_UNICODE) : null,
            'user' => $user['id'],
        ]);
        jsonResponse(['success' => true, 'report_id' => (int)$pdo->lastInsertId()], 201);
        break;

    default:
        respondWithError('Metodo non gestito', 405);
}
