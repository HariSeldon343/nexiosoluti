<?php
require_once __DIR__ . '/../config.php';

$hour = date('H');
$date = date('Y-m-d');

$stmt = $pdo->prepare('
    INSERT INTO metrics (tenant_id, metric_name, metric_value, date, hour)
    SELECT tenant_id, "hourly_uploads", COUNT(*), :date, :hour
    FROM files
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    GROUP BY tenant_id
    ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value)
');
$stmt->execute([':date' => $date, ':hour' => $hour]);

$stmt = $pdo->prepare('
    INSERT INTO metrics (tenant_id, metric_name, metric_value, date, hour)
    SELECT tenant_id, "hourly_active_users", COUNT(DISTINCT user_id), :date, :hour
    FROM activity_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    GROUP BY tenant_id
    ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value)
');
$stmt->execute([':date' => $date, ':hour' => $hour]);
