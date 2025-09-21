<?php
require_once __DIR__ . '/../config.php';

$user = require_login($auth);
$tenantId = (int)$user['tenant']['id'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $stmt = $pdo->prepare('SELECT id, resource_type, resource_id, share_token, expires_at, download_count FROM share_links WHERE tenant_id = :tenant ORDER BY created_at DESC');
        $stmt->execute([':tenant' => $tenantId]);
        json_response(['links' => $stmt->fetchAll()]);
    case 'POST':
        $data = get_json_input();
        verify_csrf_token($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $resourceType = in_array($data['resource_type'] ?? '', ['file', 'folder'], true) ? $data['resource_type'] : null;
        $resourceId = (int)($data['resource_id'] ?? 0);
        if (!$resourceType || !$resourceId) {
            json_response(['success' => false, 'error' => 'Dati mancanti'], 422);
        }
        $token = bin2hex(random_bytes(16));
        $passwordHash = !empty($data['password']) ? password_hash($data['password'], PASSWORD_BCRYPT) : null;
        $stmt = $pdo->prepare('INSERT INTO share_links (tenant_id, resource_type, resource_id, share_token, password_hash, expires_at, max_downloads, created_by) VALUES (:tenant, :type, :resource, :token, :password, :expires, :max_downloads, :user)');
        $stmt->execute([
            ':tenant' => $tenantId,
            ':type' => $resourceType,
            ':resource' => $resourceId,
            ':token' => $token,
            ':password' => $passwordHash,
            ':expires' => $data['expires_at'] ?? null,
            ':max_downloads' => $data['max_downloads'] ?? null,
            ':user' => $user['id'],
        ]);
        json_response(['success' => true, 'url' => sprintf('%s/public/share.php?token=%s', dirname($_SERVER['SCRIPT_NAME']), $token)]);
    case 'DELETE':
        parse_str(file_get_contents('php://input'), $input);
        verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ''));
        $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
        if (!$id) {
            json_response(['success' => false, 'error' => 'ID mancante'], 422);
        }
        $stmt = $pdo->prepare('DELETE FROM share_links WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute([':id' => $id, ':tenant' => $tenantId]);
        json_response(['success' => true]);
    default:
        json_response(['error' => 'Metodo non supportato'], 405);
}
