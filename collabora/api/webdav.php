<?php
declare(strict_types=1);

/**
 * WebDAV Endpoint
 * Provides WebDAV access to files
 */

require_once '../config.php';
require_once '../includes/WebDAVService.php';
require_once '../includes/RateLimiter.php';

// Disable output buffering
if (ob_get_level()) {
    ob_end_clean();
}

// Initialize services
$webdav = new WebDAVService();
$rateLimiter = new RateLimiter();

// Check if WebDAV is enabled
if (!defined('WEBDAV_ENABLED') || !WEBDAV_ENABLED) {
    define('WEBDAV_ENABLED', true);
}

if (!WEBDAV_ENABLED) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "WebDAV is disabled";
    exit;
}

// Rate limiting for WebDAV
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimit = $rateLimiter->check($clientIp, 'api', 'webdav');

if (!$rateLimit['allowed']) {
    http_response_code(429);
    header('Retry-After: ' . $rateLimit['retry_after']);
    echo "Too many requests";
    exit;
}

try {
    // Handle WebDAV request
    $webdav->handleRequest();

} catch (Exception $e) {
    // Log error
    error_log('WebDAV Error: ' . $e->getMessage());

    // Return appropriate error response
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Internal Server Error";
}