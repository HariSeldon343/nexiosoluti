<?php
require_once __DIR__ . '/../config.php';

$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'check';

switch ($action) {
    case 'login':
        require_method('POST');
        $data = get_json_input();
        $tenantCode = sanitize($data['tenant_code'] ?? '');
        $email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = $data['password'] ?? '';
        if (!$tenantCode || !$email || !$password) {
            json_response(['success' => false, 'error' => 'Dati mancanti'], 422);
        }
        $user = $auth->login($tenantCode, $email, $password);
        if ($user) {
            json_response(['success' => true, 'user' => $user]);
        }
        json_response(['success' => false, 'error' => 'Credenziali non valide'], 401);
    case 'logout':
        require_method('POST');
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        try {
            verify_csrf_token($csrf);
        } catch (Throwable $e) {
            json_response(['error' => 'Token CSRF non valido'], 403);
        }
        $auth->logout();
        json_response(['success' => true]);
    case 'check':
    default:
        $user = $auth->check();
        if ($user) {
            json_response(['authenticated' => true, 'user' => $user]);
        }
        json_response(['authenticated' => false]);
}
