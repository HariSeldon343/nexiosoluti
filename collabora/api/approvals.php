<?php
require_once __DIR__ . '/../config.php';

$user = require_login($auth);
$tenantId = (int)$user['tenant']['id'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $stmt = $pdo->prepare('SELECT id, name, steps, created_at FROM approval_workflows WHERE tenant_id = :tenant ORDER BY created_at DESC');
        $stmt->execute([':tenant' => $tenantId]);
        $workflows = $stmt->fetchAll();
        $requestsStmt = $pdo->prepare('SELECT r.id, r.workflow_id, r.file_id, r.status, r.current_step, r.requested_at FROM approval_requests r INNER JOIN approval_workflows w ON w.id = r.workflow_id WHERE w.tenant_id = :tenant ORDER BY r.requested_at DESC');
        $requestsStmt->execute([':tenant' => $tenantId]);
        json_response(['workflows' => $workflows, 'requests' => $requestsStmt->fetchAll()]);
    case 'POST':
        $data = get_json_input();
        verify_csrf_token($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (($data['type'] ?? '') === 'workflow') {
            $name = sanitize($data['name'] ?? '');
            if (!$name) {
                json_response(['success' => false, 'error' => 'Nome obbligatorio'], 422);
            }
            $stmt = $pdo->prepare('INSERT INTO approval_workflows (tenant_id, name, steps, created_by) VALUES (:tenant, :name, :steps, :user)');
            $stmt->execute([
                ':tenant' => $tenantId,
                ':name' => $name,
                ':steps' => json_encode($data['steps'] ?? []),
                ':user' => $user['id'],
            ]);
            json_response(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        }
        if (($data['type'] ?? '') === 'request') {
            $workflowId = (int)($data['workflow_id'] ?? 0);
            $fileId = (int)($data['file_id'] ?? 0);
            if (!$workflowId || !$fileId) {
                json_response(['success' => false, 'error' => 'Dati mancanti'], 422);
            }
            $stmt = $pdo->prepare('INSERT INTO approval_requests (workflow_id, file_id, requested_by) VALUES (:workflow, :file, :user)');
            $stmt->execute([
                ':workflow' => $workflowId,
                ':file' => $fileId,
                ':user' => $user['id'],
            ]);
            json_response(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        }
        json_response(['success' => false, 'error' => 'Tipo non supportato'], 422);
    case 'PATCH':
        parse_str(file_get_contents('php://input'), $input);
        verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ''));
        $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
        $status = $input['status'] ?? null;
        if (!$id || !in_array($status, ['pending', 'approved', 'rejected'], true)) {
            json_response(['success' => false, 'error' => 'Dati non validi'], 422);
        }
        $stmt = $pdo->prepare('UPDATE approval_requests SET status = :status, completed_at = CASE WHEN :status IN ("approved","rejected") THEN NOW() ELSE completed_at END WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $id]);
        json_response(['success' => true]);
    default:
        json_response(['error' => 'Metodo non supportato'], 405);
}
