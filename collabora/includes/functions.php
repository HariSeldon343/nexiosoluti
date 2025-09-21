<?php
/**
 * Funzioni di supporto generali
 */

declare(strict_types=1);

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitize(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        json_response(['error' => 'Metodo non consentito'], 405);
    }
}

function get_json_input(): array
{
    $input = file_get_contents('php://input');
    if ($input === false || $input === '') {
        return [];
    }

    $data = json_decode($input, true);
    if (!is_array($data)) {
        json_response(['error' => 'Payload non valido'], 400);
    }
    return $data;
}

function ensure_csrf_token(): void
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function verify_csrf_token(?string $token): void
{
    ensure_csrf_token();
    if (!$token || !hash_equals($_SESSION['csrf_token'], $token)) {
        json_response(['error' => 'Token CSRF non valido'], 403);
    }
}

function require_login(Auth $auth): array
{
    $user = $auth->check();
    if (!$user) {
        json_response(['authenticated' => false], 401);
    }
    return $user;
}

function ensure_directory(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}
