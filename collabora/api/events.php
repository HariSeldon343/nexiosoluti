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
        $start = sanitizeString($_GET['start'] ?? null);
        $end = sanitizeString($_GET['end'] ?? null);
        if (!$start || !$end) {
            $start = date('Y-m-01');
            $end = date('Y-m-t');
        }
        $stmt = $pdo->prepare('SELECT e.id, e.calendar_id, e.title, e.description, e.location, e.start_datetime, e.end_datetime, e.all_day, e.created_by, c.color, c.name AS calendar_name FROM events e INNER JOIN calendars c ON e.calendar_id = c.id WHERE e.tenant_id = :tenant AND e.start_datetime <= :end AND e.end_datetime >= :start ORDER BY e.start_datetime');
        $stmt->execute([
            'tenant' => $tenantId,
            'start' => $start . ' 00:00:00',
            'end' => $end . ' 23:59:59',
        ]);
        $events = $stmt->fetchAll();
        jsonResponse(['success' => true, 'events' => array_map('formatEvent', $events)]);
        break;

    case 'POST':
        requireCsrfToken();
        $data = getJsonInput();
        $calendarId = sanitizeInt($data['calendar_id'] ?? null);
        $title = sanitizeString($data['title'] ?? '');
        $startDatetime = sanitizeString($data['start_datetime'] ?? '');
        $endDatetime = sanitizeString($data['end_datetime'] ?? '');
        $allDay = !empty($data['all_day']);
        if (!$calendarId || !$title || !$startDatetime || !$endDatetime) {
            respondWithError('Dati evento non completi', 422);
        }
        if (!validateDateTime($startDatetime) || !validateDateTime($endDatetime)) {
            respondWithError('Formato data/ora non valido', 422);
        }
        $stmt = $pdo->prepare('SELECT id FROM calendars WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute(['id' => $calendarId, 'tenant' => $tenantId]);
        if (!$stmt->fetch()) {
            respondWithError('Calendario non valido', 404);
        }
        $stmt = $pdo->prepare('INSERT INTO events (tenant_id, calendar_id, title, description, location, start_datetime, end_datetime, all_day, created_by) VALUES (:tenant, :calendar, :title, :description, :location, :start_dt, :end_dt, :all_day, :user)');
        $stmt->execute([
            'tenant' => $tenantId,
            'calendar' => $calendarId,
            'title' => $title,
            'description' => sanitizeString($data['description'] ?? ''),
            'location' => sanitizeString($data['location'] ?? ''),
            'start_dt' => $startDatetime,
            'end_dt' => $endDatetime,
            'all_day' => $allDay ? 1 : 0,
            'user' => $user['id'],
        ]);
        $eventId = (int)$pdo->lastInsertId();
        saveParticipants($pdo, $eventId, $data['participants'] ?? []);
        jsonResponse(['success' => true, 'event_id' => $eventId], 201);
        break;

    case 'PUT':
    case 'PATCH':
        requireCsrfToken();
        $eventId = sanitizeInt($_GET['id'] ?? null);
        if (!$eventId) {
            respondWithError('Evento non valido', 422);
        }
        parse_str(file_get_contents('php://input'), $rawInput);
        $data = array_merge($rawInput, getJsonInput());
        $stmt = $pdo->prepare('SELECT * FROM events WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute(['id' => $eventId, 'tenant' => $tenantId]);
        $event = $stmt->fetch();
        if (!$event) {
            respondWithError('Evento non trovato', 404);
        }
        $fields = [];
        $params = ['id' => $eventId];
        if (isset($data['calendar_id'])) {
            $calendarId = sanitizeInt($data['calendar_id']);
            $check = $pdo->prepare('SELECT id FROM calendars WHERE id = :id AND tenant_id = :tenant');
            $check->execute(['id' => $calendarId, 'tenant' => $tenantId]);
            if (!$check->fetch()) {
                respondWithError('Calendario non valido', 404);
            }
            $fields[] = 'calendar_id = :calendar';
            $params['calendar'] = $calendarId;
        }
        if (isset($data['title'])) {
            $fields[] = 'title = :title';
            $params['title'] = sanitizeString($data['title']);
        }
        if (isset($data['description'])) {
            $fields[] = 'description = :description';
            $params['description'] = sanitizeString($data['description']);
        }
        if (isset($data['location'])) {
            $fields[] = 'location = :location';
            $params['location'] = sanitizeString($data['location']);
        }
        if (isset($data['start_datetime'])) {
            $value = sanitizeString($data['start_datetime']);
            if (!validateDateTime($value)) {
                respondWithError('Data di inizio non valida', 422);
            }
            $fields[] = 'start_datetime = :start_dt';
            $params['start_dt'] = $value;
        }
        if (isset($data['end_datetime'])) {
            $value = sanitizeString($data['end_datetime']);
            if (!validateDateTime($value)) {
                respondWithError('Data di fine non valida', 422);
            }
            $fields[] = 'end_datetime = :end_dt';
            $params['end_dt'] = $value;
        }
        if (isset($data['all_day'])) {
            $fields[] = 'all_day = :all_day';
            $params['all_day'] = (int)(bool)$data['all_day'];
        }
        if ($fields) {
            $pdo->prepare('UPDATE events SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = :id')->execute($params);
        }
        if (isset($data['participants']) && is_array($data['participants'])) {
            $pdo->prepare('DELETE FROM event_participants WHERE event_id = :event')->execute(['event' => $eventId]);
            saveParticipants($pdo, $eventId, $data['participants']);
        }
        jsonResponse(['success' => true]);
        break;

    case 'DELETE':
        requireCsrfToken();
        $eventId = sanitizeInt($_GET['id'] ?? null);
        if (!$eventId) {
            respondWithError('Evento non valido', 422);
        }
        $stmt = $pdo->prepare('DELETE FROM events WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute(['id' => $eventId, 'tenant' => $tenantId]);
        if ($stmt->rowCount() === 0) {
            respondWithError('Evento non trovato', 404);
        }
        jsonResponse(['success' => true]);
        break;

    default:
        respondWithError('Metodo non gestito', 405);
}

function formatEvent(array $event): array
{
    return [
        'id' => (int)$event['id'],
        'calendar_id' => (int)$event['calendar_id'],
        'title' => $event['title'],
        'description' => $event['description'],
        'location' => $event['location'],
        'start_datetime' => $event['start_datetime'],
        'end_datetime' => $event['end_datetime'],
        'all_day' => (bool)$event['all_day'],
        'color' => $event['color'],
        'calendar_name' => $event['calendar_name'],
    ];
}

function validateDateTime(string $value): bool
{
    $date = date_create($value);
    return $date !== false;
}

function saveParticipants(\PDO $pdo, int $eventId, array $participants): void
{
    foreach ($participants as $participantId) {
        $participantId = sanitizeInt($participantId);
        if (!$participantId) {
            continue;
        }
        $pdo->prepare('INSERT INTO event_participants (event_id, user_id) VALUES (:event, :user) ON DUPLICATE KEY UPDATE status = VALUES(status)')->execute([
            'event' => $eventId,
            'user' => $participantId,
        ]);
    }
}
