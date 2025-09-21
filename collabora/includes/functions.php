<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function jsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getJsonInput(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

function sanitizeString(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    return trim(filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS));
}

function sanitizeEmail(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $email = filter_var($value, FILTER_SANITIZE_EMAIL);
    return $email ?: null;
}

function sanitizeInt($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    $filtered = filter_var($value, FILTER_VALIDATE_INT);
    return $filtered === false ? null : $filtered;
}

function requireMethod(array $allowedMethods): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $allowedMethods, true)) {
        jsonResponse(['success' => false, 'message' => 'Metodo non consentito'], 405);
    }
}

function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function requireCsrfToken(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET' || $method === 'HEAD') {
        return;
    }
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $bodyToken = $_POST['csrf_token'] ?? '';
    $token = $headerToken ?: $bodyToken;
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        jsonResponse(['success' => false, 'message' => 'CSRF token non valido'], 403);
    }
}

function ensureUploadDirectory(int $tenantId): string
{
    $tenantPath = UPLOAD_PATH . '/tenant_' . $tenantId;
    if (!is_dir($tenantPath)) {
        mkdir($tenantPath, 0755, true);
    }
    $yearPath = $tenantPath . '/' . date('Y');
    if (!is_dir($yearPath)) {
        mkdir($yearPath, 0755, true);
    }
    $monthPath = $yearPath . '/' . date('m');
    if (!is_dir($monthPath)) {
        mkdir($monthPath, 0755, true);
    }
    return $monthPath;
}

function logActivity(PDO $pdo, int $tenantId, int $userId, string $action, ?string $resourceType, ?int $resourceId, array $details = []): void
{
    $stmt = $pdo->prepare('INSERT INTO activity_logs (tenant_id, user_id, action, resource_type, resource_id, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $detailsJson = $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $stmt->execute([$tenantId, $userId, $action, $resourceType, $resourceId, $ip, $userAgent, $detailsJson]);
}

function verifyOwnership(int $resourceTenantId, int $currentTenantId): void
{
    if ($resourceTenantId !== $currentTenantId) {
        jsonResponse(['success' => false, 'message' => 'Accesso non autorizzato'], 403);
    }
}

function paginate(int $page, int $limit): array
{
    $page = max($page, 1);
    $limit = $limit > 0 ? min($limit, 100) : 25;
    $offset = ($page - 1) * $limit;
    return [$limit, $offset];
}

function respondWithError(string $message, int $status = 400): void
{
    jsonResponse(['success' => false, 'message' => $message], $status);
}
