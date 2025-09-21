<?php
require_once __DIR__ . '/../config.php';

$user = require_login($auth);
$tenantId = (int)$user['tenant']['id'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $start = filter_input(INPUT_GET, 'start', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $end = filter_input(INPUT_GET, 'end', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (!$start || !$end) {
            json_response(['events' => []]);
        }
        $stmt = $pdo->prepare('SELECT * FROM events WHERE tenant_id = :tenant AND start_datetime >= :start AND end_datetime <= :end ORDER BY start_datetime');
        $stmt->execute([
            ':tenant' => $tenantId,
            ':start' => $start,
            ':end' => $end,
        ]);
        json_response(['events' => $stmt->fetchAll()]);
    case 'POST':
        $data = get_json_input();
        verify_csrf_token($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $calendarId = (int)($data['calendar_id'] ?? 0);
        $title = sanitize($data['title'] ?? '');
        $start = $data['start_datetime'] ?? '';
        $end = $data['end_datetime'] ?? '';
        if (!$calendarId || !$title || !$start || !$end) {
            json_response(['success' => false, 'error' => 'Dati mancanti'], 422);
        }
        $stmt = $pdo->prepare('INSERT INTO events (tenant_id, calendar_id, title, description, location, start_datetime, end_datetime, all_day, recurrence_rule, created_by) VALUES (:tenant, :calendar, :title, :description, :location, :start, :end, :all_day, :rrule, :user)');
        $stmt->execute([
            ':tenant' => $tenantId,
            ':calendar' => $calendarId,
            ':title' => $title,
            ':description' => $data['description'] ?? null,
            ':location' => $data['location'] ?? null,
            ':start' => $start,
            ':end' => $end,
            ':all_day' => !empty($data['all_day']) ? 1 : 0,
            ':rrule' => $data['recurrence_rule'] ?? null,
            ':user' => $user['id'],
        ]);
        json_response(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
    case 'PUT':
        parse_str(file_get_contents('php://input'), $input);
        verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ''));
        $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
        if (!$id) {
            json_response(['success' => false, 'error' => 'ID mancante'], 422);
        }
        $fields = [];
        $params = [':id' => $id, ':tenant' => $tenantId];
        foreach (['title', 'description', 'location', 'start_datetime', 'end_datetime', 'recurrence_rule'] as $field) {
            if (isset($input[$field])) {
                $fields[] = "$field = :$field";
                $params[":" . $field] = $field === 'title' ? sanitize($input[$field]) : $input[$field];
            }
        }
        if (isset($input['all_day'])) {
            $fields[] = 'all_day = :all_day';
            $params[':all_day'] = (int)$input['all_day'];
        }
        if (!$fields) {
            json_response(['success' => false, 'error' => 'Nessun campo da aggiornare'], 422);
        }
        $sql = 'UPDATE events SET ' . implode(', ', $fields) . ' WHERE id = :id AND tenant_id = :tenant';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json_response(['success' => true]);
    case 'DELETE':
        parse_str(file_get_contents('php://input'), $input);
        verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ''));
        $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
        if (!$id) {
            json_response(['success' => false, 'error' => 'ID mancante'], 422);
        }
        $stmt = $pdo->prepare('DELETE FROM events WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute([':id' => $id, ':tenant' => $tenantId]);
        json_response(['success' => true]);
    default:
        json_response(['error' => 'Metodo non supportato'], 405);
}
