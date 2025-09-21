<?php
require_once __DIR__ . '/../config.php';

$stmt = $pdo->query('SELECT id, tenant_id, name, type, template FROM reports');
while ($report = $stmt->fetch()) {
    $filename = sprintf('%s_report_%s.pdf', $report['type'], date('Ymd')); 
    file_put_contents(BASE_PATH . '/temp/' . $filename, 'Report schedulato');
}
