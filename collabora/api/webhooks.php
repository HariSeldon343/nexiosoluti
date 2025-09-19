<?php
declare(strict_types=1);

/**
 * Webhook Management API
 * Gestione webhook per integrazioni esterne
 */

require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/NotificationService.php';
require_once '../includes/RateLimiter.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Initialize services
$auth = new Auth();
$notificationService = new NotificationService();
$rateLimiter = new RateLimiter();

// Rate limiting check
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimit = $rateLimiter->check($clientIp, 'api', 'webhook');

if (!$rateLimit['allowed']) {
    foreach ($rateLimiter->getHeaders($rateLimit) as $header => $value) {
        header("$header: $value");
    }

    http_response_code(429);
    echo json_encode([
        'error' => 'Too many requests',
        'retry_after' => $rateLimit['retry_after']
    ]);
    exit;
}

// Authentication check (except for webhook callbacks)
$user = null;
$tenantId = null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_GET['action'] !== 'callback') {
    $user = $auth->requireAuth();
    $tenantId = $user['tenant_id'];
}

// Route request
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if ($id) {
                handleGetWebhook($id, $tenantId);
            } else {
                handleListWebhooks($tenantId);
            }
            break;

        case 'POST':
            if ($action === 'callback') {
                handleWebhookCallback();
            } elseif ($action === 'test') {
                handleTestWebhook($id, $tenantId);
            } else {
                handleCreateWebhook($tenantId);
            }
            break;

        case 'PUT':
            handleUpdateWebhook($id, $tenantId);
            break;

        case 'DELETE':
            handleDeleteWebhook($id, $tenantId);
            break;

        default:
            throw new Exception('Method not allowed', 405);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

/**
 * List webhooks
 */
function handleListWebhooks(int $tenantId): void {
    global $notificationService;

    $webhooks = $notificationService->listWebhooks($tenantId);

    // Add statistics for each webhook
    foreach ($webhooks as &$webhook) {
        $webhook['stats'] = getWebhookStats($webhook['id']);
    }

    echo json_encode([
        'success' => true,
        'webhooks' => $webhooks,
        'count' => count($webhooks)
    ]);
}

/**
 * Get single webhook
 */
function handleGetWebhook(string $id, int $tenantId): void {
    global $notificationService;

    $webhook = $notificationService->getWebhook($id);

    if (!$webhook || $webhook['tenant_id'] !== $tenantId) {
        throw new Exception('Webhook not found', 404);
    }

    // Add detailed statistics
    $webhook['stats'] = getWebhookStats($id);
    $webhook['recent_deliveries'] = getRecentDeliveries($id);

    echo json_encode([
        'success' => true,
        'webhook' => $webhook
    ]);
}

/**
 * Create webhook
 */
function handleCreateWebhook(int $tenantId): void {
    global $notificationService;

    $input = json_decode(file_get_contents('php://input'), true);

    // Validate input
    $errors = validateWebhookInput($input);
    if (!empty($errors)) {
        throw new Exception('Validation failed: ' . implode(', ', $errors), 400);
    }

    // Create webhook
    $webhookId = $notificationService->createWebhook([
        'tenant_id' => $tenantId,
        'name' => $input['name'],
        'url' => $input['url'],
        'events' => $input['events'] ?? ['*'],
        'secret' => $input['secret'] ?? bin2hex(random_bytes(32)),
        'headers' => $input['headers'] ?? [],
        'active' => $input['active'] ?? true
    ]);

    // Log activity
    logActivity('webhook_created', 'webhook', $webhookId, [
        'name' => $input['name'],
        'url' => $input['url']
    ]);

    echo json_encode([
        'success' => true,
        'webhook_id' => $webhookId,
        'message' => 'Webhook created successfully'
    ]);
}

/**
 * Update webhook
 */
function handleUpdateWebhook(string $id, int $tenantId): void {
    global $notificationService;

    $webhook = $notificationService->getWebhook($id);

    if (!$webhook || $webhook['tenant_id'] !== $tenantId) {
        throw new Exception('Webhook not found', 404);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    // Validate input
    $errors = validateWebhookInput($input, true);
    if (!empty($errors)) {
        throw new Exception('Validation failed: ' . implode(', ', $errors), 400);
    }

    // Update webhook
    $success = $notificationService->updateWebhook($id, $input);

    if (!$success) {
        throw new Exception('Failed to update webhook', 500);
    }

    // Log activity
    logActivity('webhook_updated', 'webhook', $id, $input);

    echo json_encode([
        'success' => true,
        'message' => 'Webhook updated successfully'
    ]);
}

/**
 * Delete webhook
 */
function handleDeleteWebhook(string $id, int $tenantId): void {
    global $notificationService;

    $webhook = $notificationService->getWebhook($id);

    if (!$webhook || $webhook['tenant_id'] !== $tenantId) {
        throw new Exception('Webhook not found', 404);
    }

    $success = $notificationService->deleteWebhook($id);

    if (!$success) {
        throw new Exception('Failed to delete webhook', 500);
    }

    // Log activity
    logActivity('webhook_deleted', 'webhook', $id);

    echo json_encode([
        'success' => true,
        'message' => 'Webhook deleted successfully'
    ]);
}

/**
 * Test webhook
 */
function handleTestWebhook(string $id, int $tenantId): void {
    global $notificationService;

    $webhook = $notificationService->getWebhook($id);

    if (!$webhook || $webhook['tenant_id'] !== $tenantId) {
        throw new Exception('Webhook not found', 404);
    }

    // Send test event
    $testData = [
        'event' => 'test',
        'timestamp' => time(),
        'data' => [
            'message' => 'This is a test webhook delivery',
            'webhook_id' => $id
        ]
    ];

    $success = $notificationService->sendWebhook('test', $testData, $id);

    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Test webhook sent successfully' : 'Failed to send test webhook'
    ]);
}

/**
 * Handle webhook callback (incoming webhooks)
 */
function handleWebhookCallback(): void {
    $headers = getallheaders();
    $body = file_get_contents('php://input');

    // Verify webhook signature if present
    $signature = $headers['X-Webhook-Signature'] ?? null;
    $webhookId = $headers['X-Webhook-ID'] ?? $_GET['webhook_id'] ?? null;

    if ($webhookId) {
        $isValid = verifyWebhookSignature($webhookId, $body, $signature);
        if (!$isValid) {
            throw new Exception('Invalid webhook signature', 401);
        }
    }

    // Process webhook
    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON payload', 400);
    }

    // Store webhook for processing
    $callbackId = storeWebhookCallback($webhookId, $headers, $data);

    // Process asynchronously (in production, use queue)
    processWebhookCallback($callbackId);

    // Return success immediately
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'callback_id' => $callbackId,
        'message' => 'Webhook received'
    ]);
}

/**
 * Validation functions
 */
function validateWebhookInput(array $input, bool $isUpdate = false): array {
    $errors = [];

    if (!$isUpdate || isset($input['name'])) {
        if (empty($input['name'])) {
            $errors[] = 'Name is required';
        } elseif (strlen($input['name']) > 255) {
            $errors[] = 'Name too long';
        }
    }

    if (!$isUpdate || isset($input['url'])) {
        if (empty($input['url'])) {
            $errors[] = 'URL is required';
        } elseif (!filter_var($input['url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Invalid URL';
        } elseif (!in_array(parse_url($input['url'], PHP_URL_SCHEME), ['http', 'https'])) {
            $errors[] = 'URL must use HTTP or HTTPS';
        }
    }

    if (isset($input['events'])) {
        if (!is_array($input['events'])) {
            $errors[] = 'Events must be an array';
        } else {
            $validEvents = getValidWebhookEvents();
            foreach ($input['events'] as $event) {
                if ($event !== '*' && !in_array($event, $validEvents)) {
                    $errors[] = "Invalid event: $event";
                }
            }
        }
    }

    if (isset($input['headers']) && !is_array($input['headers'])) {
        $errors[] = 'Headers must be an array';
    }

    return $errors;
}

function getValidWebhookEvents(): array {
    return [
        'file.created',
        'file.updated',
        'file.deleted',
        'file.shared',
        'file.downloaded',
        'folder.created',
        'folder.updated',
        'folder.deleted',
        'user.created',
        'user.updated',
        'user.deleted',
        'user.login',
        'user.logout',
        'tenant.quota_exceeded',
        'tenant.quota_warning',
        'system.backup_completed',
        'system.error'
    ];
}

/**
 * Webhook signature verification
 */
function verifyWebhookSignature(string $webhookId, string $body, ?string $signature): bool {
    global $notificationService;

    if (!$signature) {
        return false;
    }

    $webhook = $notificationService->getWebhook($webhookId);
    if (!$webhook || empty($webhook['secret'])) {
        return false;
    }

    $expectedSignature = 'sha256=' . hash_hmac('sha256', $body, $webhook['secret']);
    return hash_equals($expectedSignature, $signature);
}

/**
 * Webhook callback processing
 */
function storeWebhookCallback(string $webhookId, array $headers, array $data): string {
    $callbackId = generate_uuid();

    $callback = [
        'id' => $callbackId,
        'webhook_id' => $webhookId,
        'headers' => $headers,
        'data' => $data,
        'received_at' => time(),
        'status' => 'pending'
    ];

    // Store in temp file (in production, use database or queue)
    $file = APP_ROOT . '/temp/webhook_callbacks/' . $callbackId . '.json';
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($file, json_encode($callback), LOCK_EX);

    return $callbackId;
}

function processWebhookCallback(string $callbackId): void {
    $file = APP_ROOT . '/temp/webhook_callbacks/' . $callbackId . '.json';

    if (!file_exists($file)) {
        return;
    }

    $callback = json_decode(file_get_contents($file), true);

    try {
        // Process based on webhook type
        $event = $callback['data']['event'] ?? 'unknown';

        switch ($event) {
            case 'payment.success':
                processPaymentSuccess($callback['data']);
                break;

            case 'subscription.updated':
                processSubscriptionUpdate($callback['data']);
                break;

            case 'integration.sync':
                processIntegrationSync($callback['data']);
                break;

            default:
                // Store for manual processing
                storeUnprocessedCallback($callback);
        }

        // Update status
        $callback['status'] = 'processed';
        $callback['processed_at'] = time();

    } catch (Exception $e) {
        $callback['status'] = 'failed';
        $callback['error'] = $e->getMessage();
    }

    file_put_contents($file, json_encode($callback), LOCK_EX);
}

function processPaymentSuccess(array $data): void {
    // Implementation for payment success handling
}

function processSubscriptionUpdate(array $data): void {
    // Implementation for subscription update handling
}

function processIntegrationSync(array $data): void {
    // Implementation for integration sync handling
}

function storeUnprocessedCallback(array $callback): void {
    // Store in database for manual review
}

/**
 * Statistics functions
 */
function getWebhookStats(string $webhookId): array {
    // Get delivery statistics from logs
    $stats = [
        'total_deliveries' => 0,
        'successful_deliveries' => 0,
        'failed_deliveries' => 0,
        'average_response_time' => 0,
        'last_delivery' => null
    ];

    // Read from logs (simplified)
    $logFile = LOG_PATH . '/webhooks_' . date('Y-m-d') . '.log';
    if (file_exists($logFile)) {
        $logs = file($logFile, FILE_IGNORE_NEW_LINES);
        foreach ($logs as $log) {
            if (strpos($log, $webhookId) !== false) {
                $stats['total_deliveries']++;
                if (strpos($log, '[sent]') !== false) {
                    $stats['successful_deliveries']++;
                } elseif (strpos($log, '[failed]') !== false) {
                    $stats['failed_deliveries']++;
                }
            }
        }
    }

    $stats['success_rate'] = $stats['total_deliveries'] > 0
        ? round(($stats['successful_deliveries'] / $stats['total_deliveries']) * 100, 2)
        : 0;

    return $stats;
}

function getRecentDeliveries(string $webhookId, int $limit = 10): array {
    $deliveries = [];

    // Get from notification queue/logs
    // Simplified implementation
    $logFile = LOG_PATH . '/webhooks_' . date('Y-m-d') . '.log';
    if (file_exists($logFile)) {
        $logs = array_reverse(file($logFile, FILE_IGNORE_NEW_LINES));
        foreach ($logs as $log) {
            if (strpos($log, $webhookId) !== false && count($deliveries) < $limit) {
                // Parse log entry
                if (preg_match('/\[([\d\-\s:]+)\].*\[(sent|failed)\].*Response: (\d+)/', $log, $matches)) {
                    $deliveries[] = [
                        'timestamp' => strtotime($matches[1]),
                        'status' => $matches[2],
                        'response_code' => (int)$matches[3]
                    ];
                }
            }
        }
    }

    return $deliveries;
}

/**
 * Activity logging
 */
function logActivity(string $action, string $entityType, string $entityId, array $details = []): void {
    global $user, $tenantId;

    Database::insert('activity_logs', [
        'tenant_id' => $tenantId,
        'user_id' => $user['id'] ?? null,
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'details' => json_encode($details),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
}