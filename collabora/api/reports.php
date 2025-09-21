<?php
require_once __DIR__ . '/../config.php';

$user = require_login($auth);
$tenantId = (int)$user['tenant']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = get_json_input();
    verify_csrf_token($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $action = $data['action'] ?? null;
    if ($action === 'generate') {
        $type = $data['type'] ?? 'activity';
        $reportName = sprintf('report_%s_%s.pdf', $type, date('Ymd_His'));
        $path = BASE_PATH . '/temp/' . $reportName;
        file_put_contents($path, 'Report placeholder');
        json_response(['download_url' => 'temp/' . $reportName]);
    }
    json_response(['success' => false, 'error' => 'Azione non supportata'], 422);
}

json_response(['error' => 'Metodo non supportato'], 405);
