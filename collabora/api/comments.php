<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$auth = new Auth();
$user = $auth->requireAuth();
$tenantId = $auth->getCurrentTenantId();
$pdo = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

switch ($method) {
    case 'GET':
        $fileId = sanitizeInt($_GET['file_id'] ?? null);
        if (!$fileId) {
            respondWithError('File non valido', 422);
        }
        $stmt = $pdo->prepare('SELECT c.id, c.comment, c.user_id, u.name, c.parent_comment_id, c.x_position, c.y_position, c.page_number, c.status, c.created_at FROM file_comments c INNER JOIN files f ON c.file_id = f.id INNER JOIN users u ON c.user_id = u.id WHERE c.file_id = :file AND f.tenant_id = :tenant ORDER BY c.created_at ASC');
        $stmt->execute(['file' => $fileId, 'tenant' => $tenantId]);
        $comments = $stmt->fetchAll();
        jsonResponse(['success' => true, 'comments' => array_map(static fn($row) => [
            'id' => (int)$row['id'],
            'comment' => $row['comment'],
            'user_id' => (int)$row['user_id'],
            'user_name' => $row['name'],
            'parent_comment_id' => $row['parent_comment_id'] !== null ? (int)$row['parent_comment_id'] : null,
            'x_position' => $row['x_position'] !== null ? (int)$row['x_position'] : null,
            'y_position' => $row['y_position'] !== null ? (int)$row['y_position'] : null,
            'page_number' => $row['page_number'] !== null ? (int)$row['page_number'] : null,
            'status' => $row['status'],
            'created_at' => $row['created_at'],
        ], $comments)]);
        break;

    case 'POST':
        requireCsrfToken();
        $data = getJsonInput();
        $fileId = sanitizeInt($data['file_id'] ?? null);
        if (!$fileId) {
            respondWithError('File non valido', 422);
        }
        $stmt = $pdo->prepare('SELECT tenant_id FROM files WHERE id = :id');
        $stmt->execute(['id' => $fileId]);
        $file = $stmt->fetch();
        if (!$file) {
            respondWithError('File non trovato', 404);
        }
        verifyOwnership((int)$file['tenant_id'], $tenantId);
        $comment = sanitizeString($data['comment'] ?? '');
        if (!$comment) {
            respondWithError('Commento obbligatorio', 422);
        }
        $stmt = $pdo->prepare('INSERT INTO file_comments (file_id, user_id, comment, parent_comment_id, x_position, y_position, page_number, status) VALUES (:file, :user, :comment, :parent, :x, :y, :page, :status)');
        $stmt->execute([
            'file' => $fileId,
            'user' => $user['id'],
            'comment' => $comment,
            'parent' => isset($data['parent_comment_id']) ? sanitizeInt($data['parent_comment_id']) : null,
            'x' => isset($data['x_position']) ? sanitizeInt($data['x_position']) : null,
            'y' => isset($data['y_position']) ? sanitizeInt($data['y_position']) : null,
            'page' => isset($data['page_number']) ? sanitizeInt($data['page_number']) : null,
            'status' => sanitizeString($data['status'] ?? 'open'),
        ]);
        jsonResponse(['success' => true], 201);
        break;

    default:
        respondWithError('Metodo non gestito', 405);
}
