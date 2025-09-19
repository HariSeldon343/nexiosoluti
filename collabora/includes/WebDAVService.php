<?php
declare(strict_types=1);

/**
 * WebDAV Server Implementation
 * Fornisce accesso WebDAV ai file con supporto multi-tenant
 */

class WebDAVService {
    private string $basePath;
    private array $currentUser;
    private int $tenantId;
    private StorageInterface $storage;
    private array $locks = [];
    private string $locksFile;

    // WebDAV methods
    const METHOD_OPTIONS = 'OPTIONS';
    const METHOD_GET = 'GET';
    const METHOD_HEAD = 'HEAD';
    const METHOD_POST = 'POST';
    const METHOD_DELETE = 'DELETE';
    const METHOD_TRACE = 'TRACE';
    const METHOD_PROPFIND = 'PROPFIND';
    const METHOD_PROPPATCH = 'PROPPATCH';
    const METHOD_MKCOL = 'MKCOL';
    const METHOD_COPY = 'COPY';
    const METHOD_MOVE = 'MOVE';
    const METHOD_LOCK = 'LOCK';
    const METHOD_UNLOCK = 'UNLOCK';
    const METHOD_PUT = 'PUT';

    public function __construct() {
        $this->basePath = UPLOAD_PATH;
        $this->storage = StorageManager::disk('local');
        $this->locksFile = APP_ROOT . '/temp/webdav_locks.json';
        $this->loadLocks();
    }

    /**
     * Gestisce richiesta WebDAV
     */
    public function handleRequest(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $this->getRequestPath();

        // Autenticazione
        if (!$this->authenticate()) {
            $this->sendAuthRequired();
            return;
        }

        // Log attività
        $this->logActivity($method, $path);

        // Route metodo WebDAV
        switch ($method) {
            case self::METHOD_OPTIONS:
                $this->handleOptions();
                break;
            case self::METHOD_GET:
            case self::METHOD_HEAD:
                $this->handleGet($path, $method === self::METHOD_HEAD);
                break;
            case self::METHOD_PUT:
                $this->handlePut($path);
                break;
            case self::METHOD_DELETE:
                $this->handleDelete($path);
                break;
            case self::METHOD_MKCOL:
                $this->handleMkcol($path);
                break;
            case self::METHOD_COPY:
            case self::METHOD_MOVE:
                $this->handleCopyMove($path, $method === self::METHOD_MOVE);
                break;
            case self::METHOD_PROPFIND:
                $this->handlePropfind($path);
                break;
            case self::METHOD_PROPPATCH:
                $this->handleProppatch($path);
                break;
            case self::METHOD_LOCK:
                $this->handleLock($path);
                break;
            case self::METHOD_UNLOCK:
                $this->handleUnlock($path);
                break;
            default:
                $this->sendStatus(405, 'Method Not Allowed');
        }
    }

    /**
     * Autentica richiesta WebDAV
     */
    private function authenticate(): bool {
        // Check Basic Authentication
        if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
            return false;
        }

        $username = $_SERVER['PHP_AUTH_USER'];
        $password = $_SERVER['PHP_AUTH_PW'];

        // Verifica credenziali
        $user = Database::selectOne(
            "SELECT u.*, t.code as tenant_code
             FROM users u
             JOIN tenants t ON u.tenant_id = t.id
             WHERE u.username = :username
             AND u.status = 'active'
             AND t.status = 'active'",
            ['username' => $username]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        $this->currentUser = $user;
        $this->tenantId = $user['tenant_id'];
        return true;
    }

    /**
     * Gestisce OPTIONS
     */
    private function handleOptions(): void {
        header('Allow: OPTIONS, GET, HEAD, POST, PUT, DELETE, PROPFIND, PROPPATCH, MKCOL, COPY, MOVE, LOCK, UNLOCK');
        header('DAV: 1, 2');
        header('MS-Author-Via: DAV');
        header('Accept-Ranges: bytes');
        header('Content-Length: 0');
        http_response_code(200);
    }

    /**
     * Gestisce GET/HEAD
     */
    private function handleGet(string $path, bool $headOnly = false): void {
        $fullPath = $this->getTenantPath($path);

        if (!$this->storage->exists($fullPath)) {
            $this->sendStatus(404, 'Not Found');
            return;
        }

        $metadata = $this->storage->getMetadata($fullPath);

        if ($metadata['type'] === 'dir') {
            // Lista directory
            $this->sendDirectoryListing($fullPath, $headOnly);
        } else {
            // Invia file
            $this->sendFile($fullPath, $headOnly);
        }
    }

    /**
     * Gestisce PUT
     */
    private function handlePut(string $path): void {
        $fullPath = $this->getTenantPath($path);

        // Check locks
        if ($this->isLocked($fullPath) && !$this->hasLock($fullPath)) {
            $this->sendStatus(423, 'Locked');
            return;
        }

        // Get content
        $input = fopen('php://input', 'r');
        $temp = tmpfile();
        $size = stream_copy_to_stream($input, $temp);
        fclose($input);

        // Check quota
        if (!$this->checkQuota($size)) {
            $this->sendStatus(507, 'Insufficient Storage');
            fclose($temp);
            return;
        }

        // Save file
        rewind($temp);
        if ($this->storage->putStream($fullPath, $temp)) {
            // Update database
            $this->updateFileDatabase($path, $size);
            $this->sendStatus(201, 'Created');
        } else {
            $this->sendStatus(500, 'Internal Server Error');
        }

        fclose($temp);
    }

    /**
     * Gestisce DELETE
     */
    private function handleDelete(string $path): void {
        $fullPath = $this->getTenantPath($path);

        if (!$this->storage->exists($fullPath)) {
            $this->sendStatus(404, 'Not Found');
            return;
        }

        // Check locks
        if ($this->isLocked($fullPath) && !$this->hasLock($fullPath)) {
            $this->sendStatus(423, 'Locked');
            return;
        }

        $metadata = $this->storage->getMetadata($fullPath);

        if ($metadata['type'] === 'dir') {
            if ($this->storage->deleteDirectory($fullPath)) {
                $this->removeFromDatabase($path, true);
                $this->sendStatus(204, 'No Content');
            } else {
                $this->sendStatus(500, 'Internal Server Error');
            }
        } else {
            if ($this->storage->delete($fullPath)) {
                $this->removeFromDatabase($path, false);
                $this->sendStatus(204, 'No Content');
            } else {
                $this->sendStatus(500, 'Internal Server Error');
            }
        }
    }

    /**
     * Gestisce MKCOL (crea directory)
     */
    private function handleMkcol(string $path): void {
        $fullPath = $this->getTenantPath($path);

        if ($this->storage->exists($fullPath)) {
            $this->sendStatus(405, 'Method Not Allowed');
            return;
        }

        if ($this->storage->createDirectory($fullPath)) {
            $this->createFolderDatabase($path);
            $this->sendStatus(201, 'Created');
        } else {
            $this->sendStatus(409, 'Conflict');
        }
    }

    /**
     * Gestisce COPY/MOVE
     */
    private function handleCopyMove(string $source, bool $move = false): void {
        $destination = $_SERVER['HTTP_DESTINATION'] ?? '';
        if (!$destination) {
            $this->sendStatus(400, 'Bad Request');
            return;
        }

        // Parse destination
        $destPath = parse_url($destination, PHP_URL_PATH);
        $destPath = $this->normalizePath($destPath);

        $sourceFull = $this->getTenantPath($source);
        $destFull = $this->getTenantPath($destPath);

        if (!$this->storage->exists($sourceFull)) {
            $this->sendStatus(404, 'Not Found');
            return;
        }

        // Check locks
        if ($this->isLocked($sourceFull) && !$this->hasLock($sourceFull)) {
            $this->sendStatus(423, 'Locked');
            return;
        }

        $overwrite = $_SERVER['HTTP_OVERWRITE'] ?? 'T';
        if ($this->storage->exists($destFull) && $overwrite === 'F') {
            $this->sendStatus(412, 'Precondition Failed');
            return;
        }

        $success = $move
            ? $this->storage->move($sourceFull, $destFull)
            : $this->storage->copy($sourceFull, $destFull);

        if ($success) {
            if ($move) {
                $this->updatePathDatabase($source, $destPath);
            } else {
                $this->copyInDatabase($source, $destPath);
            }
            $this->sendStatus($this->storage->exists($destFull) ? 204 : 201);
        } else {
            $this->sendStatus(500, 'Internal Server Error');
        }
    }

    /**
     * Gestisce PROPFIND
     */
    private function handlePropfind(string $path): void {
        $fullPath = $this->getTenantPath($path);

        if (!$this->storage->exists($fullPath)) {
            $this->sendStatus(404, 'Not Found');
            return;
        }

        $depth = $_SERVER['HTTP_DEPTH'] ?? 'infinity';
        $input = file_get_contents('php://input');

        // Parse richiesta XML
        $properties = $this->parsePropertiesToFind($input);

        // Genera risposta
        $response = $this->generatePropfindResponse($fullPath, $properties, $depth);

        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Length: ' . strlen($response));
        http_response_code(207);
        echo $response;
    }

    /**
     * Gestisce PROPPATCH
     */
    private function handleProppatch(string $path): void {
        $fullPath = $this->getTenantPath($path);

        if (!$this->storage->exists($fullPath)) {
            $this->sendStatus(404, 'Not Found');
            return;
        }

        // Check locks
        if ($this->isLocked($fullPath) && !$this->hasLock($fullPath)) {
            $this->sendStatus(423, 'Locked');
            return;
        }

        $input = file_get_contents('php://input');
        $updates = $this->parsePropPatch($input);

        // Apply updates (stub - implementazione custom properties)
        $response = $this->generatePropPatchResponse($path, $updates);

        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Length: ' . strlen($response));
        http_response_code(207);
        echo $response;
    }

    /**
     * Gestisce LOCK
     */
    private function handleLock(string $path): void {
        $fullPath = $this->getTenantPath($path);

        if ($this->isLocked($fullPath) && !$this->hasLock($fullPath)) {
            $this->sendStatus(423, 'Locked');
            return;
        }

        $input = file_get_contents('php://input');
        $lockInfo = $this->parseLockRequest($input);

        // Crea lock
        $token = $this->createLock($fullPath, $lockInfo);

        $response = $this->generateLockResponse($token, $lockInfo);

        header('Content-Type: application/xml; charset=utf-8');
        header('Lock-Token: <' . $token . '>');
        http_response_code(200);
        echo $response;
    }

    /**
     * Gestisce UNLOCK
     */
    private function handleUnlock(string $path): void {
        $fullPath = $this->getTenantPath($path);

        $token = $_SERVER['HTTP_LOCK_TOKEN'] ?? '';
        $token = trim($token, '<>');

        if (!$token || !$this->removeLock($fullPath, $token)) {
            $this->sendStatus(409, 'Conflict');
            return;
        }

        $this->sendStatus(204, 'No Content');
    }

    /**
     * Genera risposta PROPFIND
     */
    private function generatePropfindResponse(string $path, array $properties, string $depth): string {
        $xml = '<?xml version="1.0" encoding="utf-8"?>';
        $xml .= '<D:multistatus xmlns:D="DAV:">';

        $metadata = $this->storage->getMetadata($path);
        $xml .= $this->generatePropfindEntry($path, $metadata, $properties);

        if ($metadata['type'] === 'dir' && $depth !== '0') {
            $contents = $this->storage->listContents($path, $depth === 'infinity');
            foreach ($contents as $item) {
                $xml .= $this->generatePropfindEntry(
                    $item['path'],
                    $item,
                    $properties
                );
            }
        }

        $xml .= '</D:multistatus>';
        return $xml;
    }

    /**
     * Genera entry PROPFIND
     */
    private function generatePropfindEntry(string $path, array $metadata, array $properties): string {
        $href = $this->getHref($path);
        $xml = '<D:response>';
        $xml .= '<D:href>' . htmlspecialchars($href) . '</D:href>';
        $xml .= '<D:propstat>';
        $xml .= '<D:prop>';

        foreach ($properties as $prop) {
            switch ($prop) {
                case 'getcontentlength':
                    if ($metadata['type'] === 'file') {
                        $xml .= '<D:getcontentlength>' . $metadata['size'] . '</D:getcontentlength>';
                    }
                    break;
                case 'getlastmodified':
                    $xml .= '<D:getlastmodified>' . gmdate('D, d M Y H:i:s', $metadata['timestamp']) . ' GMT</D:getlastmodified>';
                    break;
                case 'resourcetype':
                    $xml .= '<D:resourcetype>';
                    if ($metadata['type'] === 'dir') {
                        $xml .= '<D:collection/>';
                    }
                    $xml .= '</D:resourcetype>';
                    break;
                case 'getcontenttype':
                    if ($metadata['type'] === 'file') {
                        $xml .= '<D:getcontenttype>' . ($metadata['mimetype'] ?? 'application/octet-stream') . '</D:getcontenttype>';
                    }
                    break;
                case 'getetag':
                    $etag = md5($path . $metadata['timestamp']);
                    $xml .= '<D:getetag>"' . $etag . '"</D:getetag>';
                    break;
            }
        }

        $xml .= '</D:prop>';
        $xml .= '<D:status>HTTP/1.1 200 OK</D:status>';
        $xml .= '</D:propstat>';
        $xml .= '</D:response>';

        return $xml;
    }

    /**
     * Helper functions
     */
    private function getRequestPath(): string {
        $path = $_SERVER['PATH_INFO'] ?? '/';
        return $this->normalizePath($path);
    }

    private function normalizePath(string $path): string {
        $path = trim($path, '/');
        $path = preg_replace('#/+#', '/', $path);
        return '/' . $path;
    }

    private function getTenantPath(string $path): string {
        $tenantCode = $this->currentUser['tenant_code'] ?? 'default';
        return $tenantCode . $path;
    }

    private function getHref(string $path): string {
        $baseUrl = parse_url(APP_URL, PHP_URL_PATH);
        return $baseUrl . '/api/webdav.php' . $path;
    }

    private function sendStatus(int $code, string $message = ''): void {
        http_response_code($code);
        if ($message) {
            header('Content-Type: text/plain');
            echo $message;
        }
    }

    private function sendAuthRequired(): void {
        header('WWW-Authenticate: Basic realm="WebDAV"');
        $this->sendStatus(401, 'Unauthorized');
    }

    private function sendFile(string $path, bool $headOnly): void {
        $metadata = $this->storage->getMetadata($path);

        header('Content-Type: ' . ($metadata['mimetype'] ?? 'application/octet-stream'));
        header('Content-Length: ' . $metadata['size']);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $metadata['timestamp']) . ' GMT');
        header('ETag: "' . md5($path . $metadata['timestamp']) . '"');
        header('Accept-Ranges: bytes');

        if (!$headOnly) {
            readfile($this->storage->getMetadata($path)['path']);
        }
    }

    private function sendDirectoryListing(string $path, bool $headOnly): void {
        $html = '<!DOCTYPE html><html><head><title>Index of ' . htmlspecialchars($path) . '</title></head><body>';
        $html .= '<h1>Index of ' . htmlspecialchars($path) . '</h1><ul>';

        if (!$headOnly) {
            $contents = $this->storage->listContents($path);
            foreach ($contents as $item) {
                $name = basename($item['path']);
                $href = $this->getHref($item['path']);
                $html .= '<li><a href="' . htmlspecialchars($href) . '">' . htmlspecialchars($name) . '</a></li>';
            }
        }

        $html .= '</ul></body></html>';

        header('Content-Type: text/html; charset=utf-8');
        header('Content-Length: ' . strlen($html));

        if (!$headOnly) {
            echo $html;
        }
    }

    private function parsePropertiesToFind(string $xml): array {
        if (empty($xml)) {
            return ['getcontentlength', 'getlastmodified', 'resourcetype', 'getcontenttype', 'getetag'];
        }

        $properties = [];
        $dom = new DOMDocument();
        @$dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('D', 'DAV:');

        $nodes = $xpath->query('//D:prop/*');
        foreach ($nodes as $node) {
            $properties[] = strtolower($node->localName);
        }

        return $properties ?: ['getcontentlength', 'getlastmodified', 'resourcetype'];
    }

    private function parsePropPatch(string $xml): array {
        $updates = [];
        if (empty($xml)) {
            return $updates;
        }

        $dom = new DOMDocument();
        @$dom->loadXML($xml);

        // Parse property updates
        // Implementazione stub

        return $updates;
    }

    private function generatePropPatchResponse(string $path, array $updates): string {
        $xml = '<?xml version="1.0" encoding="utf-8"?>';
        $xml .= '<D:multistatus xmlns:D="DAV:">';
        $xml .= '<D:response>';
        $xml .= '<D:href>' . htmlspecialchars($this->getHref($path)) . '</D:href>';

        foreach ($updates as $prop => $status) {
            $xml .= '<D:propstat>';
            $xml .= '<D:prop><' . $prop . '/></D:prop>';
            $xml .= '<D:status>HTTP/1.1 ' . $status . '</D:status>';
            $xml .= '</D:propstat>';
        }

        $xml .= '</D:response>';
        $xml .= '</D:multistatus>';

        return $xml;
    }

    private function parseLockRequest(string $xml): array {
        $lockInfo = [
            'type' => 'write',
            'scope' => 'exclusive',
            'depth' => 'infinity',
            'owner' => $this->currentUser['username'],
            'timeout' => 3600
        ];

        if (!empty($xml)) {
            $dom = new DOMDocument();
            @$dom->loadXML($xml);

            // Parse lock info
            // Implementazione stub
        }

        return $lockInfo;
    }

    private function generateLockResponse(string $token, array $lockInfo): string {
        $xml = '<?xml version="1.0" encoding="utf-8"?>';
        $xml .= '<D:prop xmlns:D="DAV:">';
        $xml .= '<D:lockdiscovery>';
        $xml .= '<D:activelock>';
        $xml .= '<D:locktype><D:' . $lockInfo['type'] . '/></D:locktype>';
        $xml .= '<D:lockscope><D:' . $lockInfo['scope'] . '/></D:lockscope>';
        $xml .= '<D:depth>' . $lockInfo['depth'] . '</D:depth>';
        $xml .= '<D:owner>' . htmlspecialchars($lockInfo['owner']) . '</D:owner>';
        $xml .= '<D:timeout>Second-' . $lockInfo['timeout'] . '</D:timeout>';
        $xml .= '<D:locktoken><D:href>' . $token . '</D:href></D:locktoken>';
        $xml .= '</D:activelock>';
        $xml .= '</D:lockdiscovery>';
        $xml .= '</D:prop>';

        return $xml;
    }

    /**
     * Lock management
     */
    private function isLocked(string $path): bool {
        $this->cleanExpiredLocks();
        return isset($this->locks[$path]);
    }

    private function hasLock(string $path): bool {
        if (!isset($this->locks[$path])) {
            return false;
        }

        $lock = $this->locks[$path];
        return $lock['owner'] === $this->currentUser['username'];
    }

    private function createLock(string $path, array $lockInfo): string {
        $token = 'opaquelocktoken:' . generate_uuid();

        $this->locks[$path] = [
            'token' => $token,
            'owner' => $this->currentUser['username'],
            'expires' => time() + $lockInfo['timeout'],
            'type' => $lockInfo['type'],
            'scope' => $lockInfo['scope']
        ];

        $this->saveLocks();
        return $token;
    }

    private function removeLock(string $path, string $token): bool {
        if (!isset($this->locks[$path])) {
            return false;
        }

        if ($this->locks[$path]['token'] !== $token) {
            return false;
        }

        unset($this->locks[$path]);
        $this->saveLocks();
        return true;
    }

    private function cleanExpiredLocks(): void {
        $now = time();
        $cleaned = false;

        foreach ($this->locks as $path => $lock) {
            if ($lock['expires'] < $now) {
                unset($this->locks[$path]);
                $cleaned = true;
            }
        }

        if ($cleaned) {
            $this->saveLocks();
        }
    }

    private function loadLocks(): void {
        if (file_exists($this->locksFile)) {
            $content = file_get_contents($this->locksFile);
            $this->locks = json_decode($content, true) ?: [];
        }
    }

    private function saveLocks(): void {
        $dir = dirname($this->locksFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents(
            $this->locksFile,
            json_encode($this->locks),
            LOCK_EX
        );
    }

    /**
     * Database operations
     */
    private function checkQuota(int $size): bool {
        $tenant = Database::selectOne(
            "SELECT storage_limit, storage_used FROM tenants WHERE id = :id",
            ['id' => $this->tenantId]
        );

        return ($tenant['storage_used'] + $size) <= $tenant['storage_limit'];
    }

    private function updateFileDatabase(string $path, int $size): void {
        // Update or insert file record
        $name = basename($path);
        $folderPath = dirname($path);

        Database::query(
            "INSERT INTO files (tenant_id, name, original_name, size, path, uploaded_by, hash)
             VALUES (:tenant_id, :name, :name, :size, :path, :user_id, :hash)
             ON DUPLICATE KEY UPDATE size = :size, updated_at = NOW()",
            [
                'tenant_id' => $this->tenantId,
                'name' => $name,
                'size' => $size,
                'path' => $path,
                'user_id' => $this->currentUser['id'],
                'hash' => md5($path)
            ]
        );

        // Update tenant storage
        Database::query(
            "UPDATE tenants SET storage_used = storage_used + :size WHERE id = :id",
            ['size' => $size, 'id' => $this->tenantId]
        );
    }

    private function removeFromDatabase(string $path, bool $isDir): void {
        if ($isDir) {
            Database::delete('folders', [
                'tenant_id' => $this->tenantId,
                'path' => $path
            ]);
        } else {
            $file = Database::selectOne(
                "SELECT size FROM files WHERE tenant_id = :tenant_id AND path = :path",
                ['tenant_id' => $this->tenantId, 'path' => $path]
            );

            if ($file) {
                Database::delete('files', [
                    'tenant_id' => $this->tenantId,
                    'path' => $path
                ]);

                // Update tenant storage
                Database::query(
                    "UPDATE tenants SET storage_used = storage_used - :size WHERE id = :id",
                    ['size' => $file['size'], 'id' => $this->tenantId]
                );
            }
        }
    }

    private function createFolderDatabase(string $path): void {
        Database::insert('folders', [
            'tenant_id' => $this->tenantId,
            'name' => basename($path),
            'path' => $path,
            'created_by' => $this->currentUser['id']
        ]);
    }

    private function updatePathDatabase(string $oldPath, string $newPath): void {
        Database::update('files',
            ['path' => $newPath, 'name' => basename($newPath)],
            ['tenant_id' => $this->tenantId, 'path' => $oldPath]
        );

        Database::update('folders',
            ['path' => $newPath, 'name' => basename($newPath)],
            ['tenant_id' => $this->tenantId, 'path' => $oldPath]
        );
    }

    private function copyInDatabase(string $source, string $destination): void {
        $file = Database::selectOne(
            "SELECT * FROM files WHERE tenant_id = :tenant_id AND path = :path",
            ['tenant_id' => $this->tenantId, 'path' => $source]
        );

        if ($file) {
            unset($file['id']);
            $file['path'] = $destination;
            $file['name'] = basename($destination);
            $file['created_at'] = date('Y-m-d H:i:s');
            Database::insert('files', $file);
        }
    }

    private function logActivity(string $method, string $path): void {
        Database::insert('activity_logs', [
            'tenant_id' => $this->tenantId,
            'user_id' => $this->currentUser['id'] ?? null,
            'action' => 'webdav_' . strtolower($method),
            'entity_type' => 'file',
            'details' => json_encode(['path' => $path]),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }
}