<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$auth = new Auth();
$user = $auth->requireAuth();
$tenantId = $auth->getCurrentTenantId();
$pdo = Database::getInstance()->getConnection();

$metricName = sanitizeString($_GET['name'] ?? '');
$start = sanitizeString($_GET['start'] ?? '');
$end = sanitizeString($_GET['end'] ?? '');
if (!$metricName) {
    respondWithError('Nome metrica obbligatorio', 422);
}
if (!$start) {
    $start = date('Y-m-01');
}
if (!$end) {
    $end = date('Y-m-t');
}
$stmt = $pdo->prepare('SELECT date, hour, metric_value FROM metrics WHERE tenant_id = :tenant AND metric_name = :metric AND date BETWEEN :start AND :end ORDER BY date ASC, hour ASC');
$stmt->execute(['tenant' => $tenantId, 'metric' => $metricName, 'start' => $start, 'end' => $end]);
$rows = $stmt->fetchAll();
jsonResponse(['success' => true, 'metrics' => array_map(static fn($row) => [
    'date' => $row['date'],
    'hour' => $row['hour'] !== null ? (int)$row['hour'] : null,
    'value' => (float)$row['metric_value'],
], $rows)]);
