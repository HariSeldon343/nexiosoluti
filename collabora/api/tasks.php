<?php
require_once __DIR__ . '/../config.php';

$user = require_login($auth);
$tenantId = (int)$user['tenant']['id'];
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($action === 'comment' && $id) {
    require_method('POST');
    $data = get_json_input();
    verify_csrf_token($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $comment = trim($data['comment'] ?? '');
    if ($comment === '') {
        json_response(['success' => false, 'error' => 'Commento vuoto'], 422);
    }
    $stmt = $pdo->prepare('INSERT INTO task_comments (task_id, user_id, comment) SELECT :task, :user, :comment FROM tasks WHERE id = :task AND tenant_id = :tenant');
    $stmt->execute([
        ':task' => $id,
        ':user' => $user['id'],
        ':comment' => $comment,
        ':tenant' => $tenantId,
    ]);
    json_response(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $listId = filter_input(INPUT_GET, 'list_id', FILTER_VALIDATE_INT);
        $status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $params = [':tenant' => $tenantId];
        $conditions = ['tenant_id = :tenant'];
        if ($listId) {
            $conditions[] = 'task_list_id = :list';
            $params[':list'] = $listId;
        }
        if ($status) {
            $conditions[] = 'status = :status';
            $params[':status'] = $status;
        }
        $sql = 'SELECT * FROM tasks WHERE ' . implode(' AND ', $conditions) . ' ORDER BY position, created_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json_response(['tasks' => $stmt->fetchAll()]);
    case 'POST':
        $data = get_json_input();
        verify_csrf_token($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $listId = (int)($data['task_list_id'] ?? 0);
        $title = sanitize($data['title'] ?? '');
        if (!$listId || !$title) {
            json_response(['success' => false, 'error' => 'Dati mancanti'], 422);
        }
        $stmt = $pdo->prepare('INSERT INTO tasks (tenant_id, task_list_id, parent_task_id, title, description, status, priority, due_date, assigned_to, created_by) VALUES (:tenant, :list, :parent, :title, :description, :status, :priority, :due_date, :assigned, :user)');
        $stmt->execute([
            ':tenant' => $tenantId,
            ':list' => $listId,
            ':parent' => $data['parent_task_id'] ?? null,
            ':title' => $title,
            ':description' => $data['description'] ?? null,
            ':status' => $data['status'] ?? 'todo',
            ':priority' => $data['priority'] ?? 'normal',
            ':due_date' => $data['due_date'] ?? null,
            ':assigned' => $data['assigned_to'] ?? null,
            ':user' => $user['id'],
        ]);
        json_response(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
    case 'PATCH':
        parse_str(file_get_contents('php://input'), $input);
        verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ''));
        $taskId = (int)($_GET['id'] ?? $input['id'] ?? 0);
        if (!$taskId) {
            json_response(['success' => false, 'error' => 'ID mancante'], 422);
        }
        $fields = [];
        $params = [':id' => $taskId, ':tenant' => $tenantId];
        foreach (['status', 'priority', 'due_date', 'assigned_to', 'position'] as $field) {
            if (isset($input[$field])) {
                $fields[] = "$field = :$field";
                $params[":" . $field] = $input[$field];
            }
        }
        if (!$fields) {
            json_response(['success' => false, 'error' => 'Nessun campo da aggiornare'], 422);
        }
        $sql = 'UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = :id AND tenant_id = :tenant';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json_response(['success' => true]);
    case 'PUT':
        parse_str(file_get_contents('php://input'), $input);
        verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ''));
        $taskId = (int)($_GET['id'] ?? $input['id'] ?? 0);
        if (!$taskId) {
            json_response(['success' => false, 'error' => 'ID mancante'], 422);
        }
        $fields = [];
        $params = [':id' => $taskId, ':tenant' => $tenantId];
        foreach (['title', 'description', 'status', 'priority', 'due_date', 'assigned_to'] as $field) {
            if (isset($input[$field])) {
                $fields[] = "$field = :$field";
                $params[":" . $field] = $field === 'title' ? sanitize($input[$field]) : $input[$field];
            }
        }
        if (!$fields) {
            json_response(['success' => false, 'error' => 'Nessun campo da aggiornare'], 422);
        }
        $sql = 'UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = :id AND tenant_id = :tenant';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json_response(['success' => true]);
    case 'DELETE':
        parse_str(file_get_contents('php://input'), $input);
        verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ''));
        $taskId = (int)($_GET['id'] ?? $input['id'] ?? 0);
        if (!$taskId) {
            json_response(['success' => false, 'error' => 'ID mancante'], 422);
        }
        $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute([':id' => $taskId, ':tenant' => $tenantId]);
        json_response(['success' => true]);
    default:
        json_response(['error' => 'Metodo non supportato'], 405);
}
