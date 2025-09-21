<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->query('SELECT id, tenant_id, name, type FROM reports WHERE schedule IS NOT NULL');
$reports = $stmt->fetchAll();
foreach ($reports as $report) {
    $log = sprintf("[%s] Report programmato eseguito: %s (%s)\n", date('Y-m-d H:i:s'), $report['name'], $report['type']);
    file_put_contents(BASE_PATH . '/temp/report-log.txt', $log, FILE_APPEND);
}
