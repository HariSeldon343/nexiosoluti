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
        $stmt = $pdo->prepare('SELECT id, name, steps, created_at FROM approval_workflows WHERE tenant_id = :tenant ORDER BY created_at DESC');
        $stmt->execute(['tenant' => $tenantId]);
        $workflows = $stmt->fetchAll();
        jsonResponse(['success' => true, 'workflows' => array_map(static fn($row) => [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'steps' => json_decode($row['steps'], true),
            'created_at' => $row['created_at'],
        ], $workflows)]);
        break;

    case 'POST':
        requireCsrfToken();
        if ($action === 'request') {
            $data = getJsonInput();
            $workflowId = sanitizeInt($data['workflow_id'] ?? null);
            $fileId = sanitizeInt($data['file_id'] ?? null);
            if (!$workflowId || !$fileId) {
                respondWithError('Parametri mancanti', 422);
            }
            $stmt = $pdo->prepare('SELECT tenant_id FROM approval_workflows WHERE id = :id');
            $stmt->execute(['id' => $workflowId]);
            $workflow = $stmt->fetch();
            if (!$workflow) {
                respondWithError('Workflow non trovato', 404);
            }
            verifyOwnership((int)$workflow['tenant_id'], $tenantId);
            $stmt = $pdo->prepare('INSERT INTO approval_requests (workflow_id, file_id, requested_by) VALUES (:workflow, :file, :user)');
            $stmt->execute([
                'workflow' => $workflowId,
                'file' => $fileId,
                'user' => $user['id'],
            ]);
            jsonResponse(['success' => true, 'request_id' => (int)$pdo->lastInsertId()], 201);
            break;
        }
        $data = getJsonInput();
        $name = sanitizeString($data['name'] ?? '');
        $steps = $data['steps'] ?? [];
        if (!$name || !is_array($steps) || empty($steps)) {
            respondWithError('Dati workflow mancanti', 422);
        }
        $stmt = $pdo->prepare('INSERT INTO approval_workflows (tenant_id, name, steps, created_by) VALUES (:tenant, :name, :steps, :user)');
        $stmt->execute([
            'tenant' => $tenantId,
            'name' => $name,
            'steps' => json_encode($steps, JSON_UNESCAPED_UNICODE),
            'user' => $user['id'],
        ]);
        jsonResponse(['success' => true, 'workflow_id' => (int)$pdo->lastInsertId()], 201);
        break;

    case 'PATCH':
        requireCsrfToken();
        $requestId = sanitizeInt($_GET['id'] ?? null);
        if (!$requestId) {
            respondWithError('Richiesta non valida', 422);
        }
        parse_str(file_get_contents('php://input'), $rawInput);
        $data = array_merge($rawInput, getJsonInput());
        $stmt = $pdo->prepare('SELECT ar.*, aw.tenant_id FROM approval_requests ar INNER JOIN approval_workflows aw ON ar.workflow_id = aw.id WHERE ar.id = :id');
        $stmt->execute(['id' => $requestId]);
        $request = $stmt->fetch();
        if (!$request) {
            respondWithError('Richiesta non trovata', 404);
        }
        verifyOwnership((int)$request['tenant_id'], $tenantId);
        $fields = [];
        $params = ['id' => $requestId];
        if (isset($data['status'])) {
            $fields[] = 'status = :status';
            $params['status'] = sanitizeString($data['status']);
        }
        if (isset($data['current_step'])) {
            $fields[] = 'current_step = :step';
            $params['step'] = sanitizeInt($data['current_step']);
        }
        if (isset($data['completed_at'])) {
            $fields[] = 'completed_at = :completed';
            $params['completed'] = sanitizeString($data['completed_at']);
        }
        if ($fields) {
            $pdo->prepare('UPDATE approval_requests SET ' . implode(', ', $fields) . ' WHERE id = :id')->execute($params);
        }
        jsonResponse(['success' => true]);
        break;

    default:
        respondWithError('Metodo non gestito', 405);
}
