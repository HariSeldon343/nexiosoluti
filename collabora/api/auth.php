<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
$action = $_GET['action'] ?? 'check';

switch ($action) {
    case 'login':
        requireMethod(['POST']);
        requireCsrfToken();
        $data = getJsonInput();
        $email = sanitizeEmail($data['email'] ?? null);
        $password = $data['password'] ?? '';
        if (!$email || !$password) {
            respondWithError('Dati di accesso mancanti', 422);
        }
        $user = $auth->login($email, $password);
        $response = [
            'success' => true,
            'user' => $user,
            'csrfToken' => getCsrfToken(),
        ];
        jsonResponse($response);
        break;

    case 'logout':
        requireMethod(['POST']);
        requireCsrfToken();
        $auth->logout();
        jsonResponse(['success' => true]);
        break;

    case 'switchTenant':
        requireMethod(['POST']);
        requireCsrfToken();
        $data = getJsonInput();
        $tenantId = sanitizeInt($data['tenant_id'] ?? null);
        if (!$tenantId) {
            respondWithError('Tenant non valido', 422);
        }
        $user = $auth->switchTenant($tenantId);
        jsonResponse([
            'success' => true,
            'user' => $user,
            'csrfToken' => getCsrfToken(),
        ]);
        break;

    case 'check':
    default:
        requireMethod(['GET']);
        $user = $auth->check();
        if (!$user) {
            jsonResponse(['authenticated' => false]);
        }
        jsonResponse(['authenticated' => true, 'user' => $user]);
        break;
}
