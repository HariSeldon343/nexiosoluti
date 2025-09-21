<?php
require_once __DIR__ . '/../config.php';

$user = require_login($auth);
$tenantId = (int)$user['tenant']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = get_json_input();
    verify_csrf_token($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $type = $data['type'] ?? 'metric';
    $config = $data['config'] ?? [];

    switch ($type) {
        case 'metric':
            $metricName = $config['metric_name'] ?? 'hourly_uploads';
            $stmt = $pdo->prepare('SELECT metric_value FROM metrics WHERE tenant_id = :tenant AND metric_name = :name ORDER BY created_at DESC LIMIT 1');
            $stmt->execute([':tenant' => $tenantId, ':name' => $metricName]);
            $value = $stmt->fetchColumn() ?: 0;
            json_response(['value' => (float)$value, 'trend' => '+0%', 'sparkline' => []]);
        case 'list':
            $stmt = $pdo->prepare('SELECT original_name, created_at FROM files WHERE tenant_id = :tenant ORDER BY created_at DESC LIMIT 10');
            $stmt->execute([':tenant' => $tenantId]);
            json_response(['items' => $stmt->fetchAll()]);
        default:
            json_response(['value' => 0]);
    }
}

json_response(['error' => 'Metodo non supportato'], 405);
