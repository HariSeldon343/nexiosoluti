<?php
require_once __DIR__ . '/../config.php';

$user = require_login($auth);
$tenantId = (int)$user['tenant']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $name = filter_input(INPUT_GET, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'hourly_uploads';
    $start = filter_input(INPUT_GET, 'start', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $end = filter_input(INPUT_GET, 'end', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $sql = 'SELECT date, hour, metric_value FROM metrics WHERE tenant_id = :tenant AND metric_name = :name';
    $params = [':tenant' => $tenantId, ':name' => $name];
    if ($start) {
        $sql .= ' AND date >= :start';
        $params[':start'] = $start;
    }
    if ($end) {
        $sql .= ' AND date <= :end';
        $params[':end'] = $end;
    }
    $sql .= ' ORDER BY date ASC, hour ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json_response(['metrics' => $stmt->fetchAll()]);
}

json_response(['error' => 'Metodo non supportato'], 405);
