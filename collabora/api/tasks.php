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

if ($method === 'POST' && $action === 'comment') {
    requireCsrfToken();
    $taskId = sanitizeInt($_GET['id'] ?? null);
    if (!$taskId) {
        respondWithError('Task non valido', 422);
    }
    $stmt = $pdo->prepare('SELECT id FROM tasks WHERE id = :id AND tenant_id = :tenant');
    $stmt->execute(['id' => $taskId, 'tenant' => $tenantId]);
    if (!$stmt->fetch()) {
        respondWithError('Task non trovato', 404);
    }
    $data = getJsonInput();
    $comment = sanitizeString($data['comment'] ?? '');
    if (!$comment) {
        respondWithError('Commento non valido', 422);
    }
    $stmt = $pdo->prepare('INSERT INTO task_comments (task_id, user_id, comment) VALUES (:task, :user, :comment)');
    $stmt->execute([
        'task' => $taskId,
        'user' => $user['id'],
        'comment' => $comment,
    ]);
    jsonResponse(['success' => true], 201);
}

switch ($method) {
    case 'GET':
        $listId = sanitizeInt($_GET['list_id'] ?? null);
        if (!$listId) {
            respondWithError('Lista richiesta', 422);
        }
        $stmt = $pdo->prepare('SELECT id FROM task_lists WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute(['id' => $listId, 'tenant' => $tenantId]);
        if (!$stmt->fetch()) {
            respondWithError('Lista non trovata', 404);
        }
        $status = sanitizeString($_GET['status'] ?? '');
        $where = 'tenant_id = :tenant AND task_list_id = :list';
        $params = ['tenant' => $tenantId, 'list' => $listId];
        if ($status) {
            $where .= ' AND status = :status';
            $params['status'] = $status;
        }
        $stmt = $pdo->prepare("SELECT t.*, u.name AS assigned_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE $where ORDER BY position ASC, id ASC");
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();
        jsonResponse(['success' => true, 'tasks' => array_map('formatTask', $tasks)]);
        break;

    case 'POST':
        requireCsrfToken();
        $data = getJsonInput();
        $listId = sanitizeInt($data['task_list_id'] ?? null);
        $title = sanitizeString($data['title'] ?? '');
        if (!$listId || !$title) {
            respondWithError('Dati task mancanti', 422);
        }
        $stmt = $pdo->prepare('SELECT id FROM task_lists WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute(['id' => $listId, 'tenant' => $tenantId]);
        if (!$stmt->fetch()) {
            respondWithError('Lista non trovata', 404);
        }
        $assignedTo = isset($data['assigned_to']) ? sanitizeInt($data['assigned_to']) : null;
        if ($assignedTo) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE id = :id AND tenant_id = :tenant');
            $stmt->execute(['id' => $assignedTo, 'tenant' => $tenantId]);
            if (!$stmt->fetch()) {
                respondWithError('Assegnatario non valido', 422);
            }
        }
        $stmt = $pdo->prepare('INSERT INTO tasks (tenant_id, task_list_id, parent_task_id, title, description, status, priority, position, due_date, assigned_to, created_by) VALUES (:tenant, :list, :parent, :title, :description, :status, :priority, :position, :due_date, :assigned, :user)');
        $stmt->execute([
            'tenant' => $tenantId,
            'list' => $listId,
            'parent' => isset($data['parent_task_id']) ? sanitizeInt($data['parent_task_id']) : null,
            'title' => $title,
            'description' => sanitizeString($data['description'] ?? ''),
            'status' => sanitizeString($data['status'] ?? 'todo') ?: 'todo',
            'priority' => sanitizeString($data['priority'] ?? 'normal') ?: 'normal',
            'position' => sanitizeInt($data['position'] ?? 0) ?? 0,
            'due_date' => sanitizeString($data['due_date'] ?? '') ?: null,
            'assigned' => $assignedTo,
            'user' => $user['id'],
        ]);
        $taskId = (int)$pdo->lastInsertId();
        jsonResponse(['success' => true, 'task_id' => $taskId], 201);
        break;

    case 'PATCH':
        requireCsrfToken();
        $taskId = sanitizeInt($_GET['id'] ?? null);
        if (!$taskId) {
            respondWithError('Task non valido', 422);
        }
        parse_str(file_get_contents('php://input'), $rawInput);
        $data = array_merge($rawInput, getJsonInput());
        $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute(['id' => $taskId, 'tenant' => $tenantId]);
        $task = $stmt->fetch();
        if (!$task) {
            respondWithError('Task non trovato', 404);
        }
        $fields = [];
        $params = ['id' => $taskId];
        if (isset($data['status'])) {
            $fields[] = 'status = :status';
            $params['status'] = sanitizeString($data['status']);
        }
        if (isset($data['position'])) {
            $fields[] = 'position = :position';
            $params['position'] = sanitizeInt($data['position']);
        }
        if (isset($data['task_list_id'])) {
            $listId = sanitizeInt($data['task_list_id']);
            $stmt = $pdo->prepare('SELECT id FROM task_lists WHERE id = :id AND tenant_id = :tenant');
            $stmt->execute(['id' => $listId, 'tenant' => $tenantId]);
            if (!$stmt->fetch()) {
                respondWithError('Lista non valida', 422);
            }
            $fields[] = 'task_list_id = :list';
            $params['list'] = $listId;
        }
        if (isset($data['assigned_to'])) {
            $assignedTo = sanitizeInt($data['assigned_to']);
            if ($assignedTo) {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE id = :id AND tenant_id = :tenant');
                $stmt->execute(['id' => $assignedTo, 'tenant' => $tenantId]);
                if (!$stmt->fetch()) {
                    respondWithError('Assegnatario non valido', 422);
                }
                $fields[] = 'assigned_to = :assigned';
                $params['assigned'] = $assignedTo;
            } else {
                $fields[] = 'assigned_to = NULL';
            }
        }
        if (isset($data['completed_at'])) {
            $fields[] = 'completed_at = :completed_at';
            $params['completed_at'] = sanitizeString($data['completed_at']);
        }
        if ($fields) {
            $pdo->prepare('UPDATE tasks SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = :id')->execute($params);
        }
        jsonResponse(['success' => true]);
        break;

    case 'PUT':
        requireCsrfToken();
        $taskId = sanitizeInt($_GET['id'] ?? null);
        if (!$taskId) {
            respondWithError('Task non valido', 422);
        }
        $data = getJsonInput();
        $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute(['id' => $taskId, 'tenant' => $tenantId]);
        if (!$stmt->fetch()) {
            respondWithError('Task non trovato', 404);
        }
        $assignedTo = isset($data['assigned_to']) ? sanitizeInt($data['assigned_to']) : null;
        if ($assignedTo) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE id = :id AND tenant_id = :tenant');
            $stmt->execute(['id' => $assignedTo, 'tenant' => $tenantId]);
            if (!$stmt->fetch()) {
                respondWithError('Assegnatario non valido', 422);
            }
        }
        $stmt = $pdo->prepare('UPDATE tasks SET title = :title, description = :description, status = :status, priority = :priority, due_date = :due_date, assigned_to = :assigned, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'title' => sanitizeString($data['title'] ?? ''),
            'description' => sanitizeString($data['description'] ?? ''),
            'status' => sanitizeString($data['status'] ?? 'todo'),
            'priority' => sanitizeString($data['priority'] ?? 'normal'),
            'due_date' => sanitizeString($data['due_date'] ?? '') ?: null,
            'assigned' => $assignedTo,
            'id' => $taskId,
        ]);
        jsonResponse(['success' => true]);
        break;

    case 'DELETE':
        requireCsrfToken();
        $taskId = sanitizeInt($_GET['id'] ?? null);
        if (!$taskId) {
            respondWithError('Task non valido', 422);
        }
        $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute(['id' => $taskId, 'tenant' => $tenantId]);
        if ($stmt->rowCount() === 0) {
            respondWithError('Task non trovato', 404);
        }
        jsonResponse(['success' => true]);
        break;

    default:
        respondWithError('Metodo non gestito', 405);
}

function formatTask(array $task): array
{
    return [
        'id' => (int)$task['id'],
        'title' => $task['title'],
        'description' => $task['description'],
        'status' => $task['status'],
        'priority' => $task['priority'],
        'due_date' => $task['due_date'],
        'assigned_to' => $task['assigned_to'] !== null ? (int)$task['assigned_to'] : null,
        'assigned_name' => $task['assigned_name'],
        'position' => (int)$task['position'],
    ];
}
