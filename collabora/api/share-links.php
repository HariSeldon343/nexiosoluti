<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$auth = new Auth();
$user = $auth->requireAuth();
$tenantId = $auth->getCurrentTenantId();
$pdo = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'POST';

switch ($method) {
    case 'GET':
        $resourceType = sanitizeString($_GET['resource_type'] ?? '');
        $resourceId = sanitizeInt($_GET['resource_id'] ?? null);
        if (!$resourceType || !$resourceId) {
            respondWithError('Parametri mancanti', 422);
        }
        $stmt = $pdo->prepare('SELECT id, share_token, expires_at, max_downloads, download_count, created_at FROM share_links WHERE tenant_id = :tenant AND resource_type = :type AND resource_id = :resource');
        $stmt->execute(['tenant' => $tenantId, 'type' => $resourceType, 'resource' => $resourceId]);
        $links = $stmt->fetchAll();
        jsonResponse(['success' => true, 'links' => array_map(static fn($row) => [
            'id' => (int)$row['id'],
            'token' => $row['share_token'],
            'expires_at' => $row['expires_at'],
            'max_downloads' => $row['max_downloads'] !== null ? (int)$row['max_downloads'] : null,
            'download_count' => (int)$row['download_count'],
            'created_at' => $row['created_at'],
        ], $links)]);
        break;

    case 'POST':
        requireCsrfToken();
        $data = getJsonInput();
        $resourceType = sanitizeString($data['resource_type'] ?? '');
        $resourceId = sanitizeInt($data['resource_id'] ?? null);
        if (!in_array($resourceType, ['file', 'folder'], true) || !$resourceId) {
            respondWithError('Risorsa non valida', 422);
        }
        if ($resourceType === 'file') {
            $stmt = $pdo->prepare('SELECT tenant_id FROM files WHERE id = :id');
        } else {
            $stmt = $pdo->prepare('SELECT tenant_id FROM folders WHERE id = :id');
        }
        $stmt->execute(['id' => $resourceId]);
        $resource = $stmt->fetch();
        if (!$resource) {
            respondWithError('Risorsa non trovata', 404);
        }
        verifyOwnership((int)$resource['tenant_id'], $tenantId);
        $token = bin2hex(random_bytes(16));
        $passwordHash = null;
        if (!empty($data['password'])) {
            $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        $expiresAt = !empty($data['expires_at']) ? sanitizeString($data['expires_at']) : null;
        $maxDownloads = isset($data['max_downloads']) ? sanitizeInt($data['max_downloads']) : null;
        $stmt = $pdo->prepare('INSERT INTO share_links (tenant_id, resource_type, resource_id, share_token, password_hash, expires_at, max_downloads, created_by) VALUES (:tenant, :type, :resource, :token, :password, :expires, :max_downloads, :user)');
        $stmt->execute([
            'tenant' => $tenantId,
            'type' => $resourceType,
            'resource' => $resourceId,
            'token' => $token,
            'password' => $passwordHash,
            'expires' => $expiresAt,
            'max_downloads' => $maxDownloads,
            'user' => $user['id'],
        ]);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $baseDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        $baseDir = preg_replace('#/api$#', '', $baseDir ?? '');
        $basePath = rtrim($baseDir, '/');
        $url = sprintf('%s://%s%s/public/share.php?token=%s', $scheme, $_SERVER['HTTP_HOST'], $basePath, urlencode($token));
        jsonResponse(['success' => true, 'url' => $url], 201);
        break;

    case 'DELETE':
        requireCsrfToken();
        $shareId = sanitizeInt($_GET['id'] ?? null);
        if (!$shareId) {
            respondWithError('Link non valido', 422);
        }
        $stmt = $pdo->prepare('DELETE FROM share_links WHERE id = :id AND tenant_id = :tenant');
        $stmt->execute(['id' => $shareId, 'tenant' => $tenantId]);
        if ($stmt->rowCount() === 0) {
            respondWithError('Link non trovato', 404);
        }
        jsonResponse(['success' => true]);
        break;

    default:
        respondWithError('Metodo non gestito', 405);
}
