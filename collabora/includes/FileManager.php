<?php
declare(strict_types=1);

/**
 * Classe per gestione file e cartelle
 * Supporto multi-tenant, deduplicazione SHA256, soft delete
 */

class FileManager {
    private int $tenantId;
    private string $tenantCode;
    private string $uploadBasePath;

    public function __construct() {
        $this->tenantId = Auth::getTenantId() ?? 0;
        $tenant = Auth::getCurrentTenant();
        $this->tenantCode = $tenant['code'] ?? 'default';
        $this->uploadBasePath = UPLOAD_PATH;

        // Crea directory base se non esiste
        if (!is_dir($this->uploadBasePath)) {
            mkdir($this->uploadBasePath, 0755, true);
        }
    }

    /**
     * Upload singolo file con deduplicazione
     */
    public function uploadFile(array $file, ?int $folderId = null): array {
        // Validazione base
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($this->getUploadErrorMessage($file['error']));
        }

        // Validazione dimensione
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception('File size exceeds maximum allowed: ' . format_bytes(MAX_FILE_SIZE));
        }

        // Validazione estensione e MIME
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ALLOWED_EXTENSIONS)) {
            throw new Exception('File type not allowed: ' . $extension);
        }

        $mimeType = $this->getFileMimeType($file['tmp_name']);
        if (!$this->isValidMimeType($mimeType, $extension)) {
            throw new Exception('Invalid file type detected');
        }

        // Calcola hash per deduplicazione
        $hash = hash_file('sha256', $file['tmp_name']);

        // Verifica se file esiste già (deduplicazione)
        $existingFile = Database::selectOne(
            "SELECT * FROM files
             WHERE tenant_id = :tenant_id AND hash = :hash AND is_deleted = FALSE",
            ['tenant_id' => $this->tenantId, 'hash' => $hash]
        );

        if ($existingFile) {
            // File già esiste, crea solo riferimento
            return $this->createFileReference($existingFile, $file['name'], $folderId);
        }

        // Genera percorso file
        $filePath = $this->generateFilePath($extension);
        $fullPath = $this->uploadBasePath . '/' . $filePath;

        // Crea directory se necessaria
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Sposta file
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new Exception('Failed to save uploaded file');
        }

        // Salva nel database
        $fileId = Database::insert('files', [
            'tenant_id' => $this->tenantId,
            'folder_id' => $folderId,
            'name' => clean_filename($file['name']),
            'original_name' => $file['name'],
            'mime_type' => $mimeType,
            'extension' => $extension,
            'size' => $file['size'],
            'hash' => $hash,
            'path' => $filePath,
            'uploaded_by' => Auth::getUserId()
        ]);

        // Aggiorna spazio utilizzato
        $this->updateStorageUsage($file['size']);

        // Log attività
        Auth::logActivity('file_upload', 'file', $fileId, [
            'filename' => $file['name'],
            'size' => $file['size']
        ]);

        return $this->getFileById($fileId);
    }

    /**
     * Upload multipli file
     */
    public function uploadMultipleFiles(array $files, ?int $folderId = null): array {
        $uploaded = [];
        $errors = [];

        foreach ($files['name'] as $key => $name) {
            $file = [
                'name' => $name,
                'type' => $files['type'][$key],
                'tmp_name' => $files['tmp_name'][$key],
                'error' => $files['error'][$key],
                'size' => $files['size'][$key]
            ];

            try {
                $uploaded[] = $this->uploadFile($file, $folderId);
            } catch (Exception $e) {
                $errors[] = ['file' => $name, 'error' => $e->getMessage()];
            }
        }

        return ['uploaded' => $uploaded, 'errors' => $errors];
    }

    /**
     * Download file
     */
    public function downloadFile(int $fileId): void {
        $file = $this->getFileById($fileId);

        if (!$file) {
            throw new Exception('File not found');
        }

        $fullPath = $this->uploadBasePath . '/' . $file['path'];

        if (!file_exists($fullPath)) {
            throw new Exception('File not found on disk');
        }

        // Headers per download
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $file['mime_type']);
        header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . $file['size']);

        // Output file
        readfile($fullPath);

        // Log attività
        Auth::logActivity('file_download', 'file', $fileId);
        exit;
    }

    /**
     * Download multipli file come ZIP
     */
    public function downloadAsZip(array $fileIds): void {
        if (empty($fileIds)) {
            throw new Exception('No files selected');
        }

        $files = $this->getFilesByIds($fileIds);

        if (empty($files)) {
            throw new Exception('No valid files found');
        }

        // Crea ZIP temporaneo
        $zipPath = sys_get_temp_dir() . '/' . uniqid('download_') . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new Exception('Cannot create ZIP file');
        }

        $totalSize = 0;
        foreach ($files as $file) {
            $fullPath = $this->uploadBasePath . '/' . $file['path'];
            if (file_exists($fullPath)) {
                $zip->addFile($fullPath, $file['original_name']);
                $totalSize += $file['size'];
            }
        }

        $zip->close();

        // Verifica dimensione ZIP
        if ($totalSize > MAX_ZIP_SIZE) {
            unlink($zipPath);
            throw new Exception('ZIP file too large: ' . format_bytes($totalSize));
        }

        // Headers per download
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="files_' . date('Y-m-d_His') . '.zip"');
        header('Content-Length: ' . filesize($zipPath));

        // Output e cleanup
        readfile($zipPath);
        unlink($zipPath);

        // Log attività
        Auth::logActivity('files_download_zip', 'file', null, [
            'file_ids' => $fileIds,
            'count' => count($files)
        ]);
        exit;
    }

    /**
     * Elimina file (soft delete)
     */
    public function deleteFile(int $fileId): bool {
        $file = $this->getFileById($fileId);

        if (!$file) {
            throw new Exception('File not found');
        }

        // Soft delete
        Database::update('files', [
            'is_deleted' => true,
            'deleted_at' => date('Y-m-d H:i:s')
        ], ['id' => $fileId]);

        // Log attività
        Auth::logActivity('file_delete', 'file', $fileId);

        return true;
    }

    /**
     * Ripristina file dal cestino
     */
    public function restoreFile(int $fileId): bool {
        $file = Database::selectOne(
            "SELECT * FROM files
             WHERE id = :id AND tenant_id = :tenant_id AND is_deleted = TRUE",
            ['id' => $fileId, 'tenant_id' => $this->tenantId]
        );

        if (!$file) {
            throw new Exception('File not found in trash');
        }

        Database::update('files', [
            'is_deleted' => false,
            'deleted_at' => null
        ], ['id' => $fileId]);

        // Log attività
        Auth::logActivity('file_restore', 'file', $fileId);

        return true;
    }

    /**
     * Elimina permanentemente file dal cestino
     */
    public function permanentlyDeleteFile(int $fileId): bool {
        $file = Database::selectOne(
            "SELECT * FROM files
             WHERE id = :id AND tenant_id = :tenant_id AND is_deleted = TRUE",
            ['id' => $fileId, 'tenant_id' => $this->tenantId]
        );

        if (!$file) {
            throw new Exception('File not found in trash');
        }

        // Verifica se altri file usano stesso hash
        $sharedCount = Database::count('files', [
            'tenant_id' => $this->tenantId,
            'hash' => $file['hash']
        ]);

        // Elimina file fisico solo se non condiviso
        if ($sharedCount <= 1) {
            $fullPath = $this->uploadBasePath . '/' . $file['path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        // Elimina record
        Database::delete('files', ['id' => $fileId]);

        // Aggiorna spazio utilizzato
        $this->updateStorageUsage(-$file['size']);

        // Log attività
        Auth::logActivity('file_permanent_delete', 'file', $fileId);

        return true;
    }

    /**
     * Rinomina file
     */
    public function renameFile(int $fileId, string $newName): array {
        $file = $this->getFileById($fileId);

        if (!$file) {
            throw new Exception('File not found');
        }

        $cleanName = clean_filename($newName);

        // Mantieni estensione originale
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (!str_ends_with(strtolower($cleanName), '.' . $extension)) {
            $cleanName .= '.' . $extension;
        }

        Database::update('files', ['name' => $cleanName], ['id' => $fileId]);

        // Log attività
        Auth::logActivity('file_rename', 'file', $fileId, [
            'old_name' => $file['name'],
            'new_name' => $cleanName
        ]);

        return $this->getFileById($fileId);
    }

    /**
     * Sposta file in altra cartella
     */
    public function moveFile(int $fileId, ?int $newFolderId): array {
        $file = $this->getFileById($fileId);

        if (!$file) {
            throw new Exception('File not found');
        }

        // Verifica cartella destinazione
        if ($newFolderId !== null) {
            $folder = $this->getFolderById($newFolderId);
            if (!$folder) {
                throw new Exception('Destination folder not found');
            }
        }

        Database::update('files', ['folder_id' => $newFolderId], ['id' => $fileId]);

        // Log attività
        Auth::logActivity('file_move', 'file', $fileId, [
            'from_folder' => $file['folder_id'],
            'to_folder' => $newFolderId
        ]);

        return $this->getFileById($fileId);
    }

    /**
     * Crea nuova cartella
     */
    public function createFolder(string $name, ?int $parentId = null): array {
        $cleanName = clean_filename($name);

        // Verifica unicità nome nella stessa directory
        $existing = Database::count('folders', [
            'tenant_id' => $this->tenantId,
            'parent_id' => $parentId,
            'name' => $cleanName
        ]);

        if ($existing > 0) {
            throw new Exception('Folder with this name already exists');
        }

        // Calcola path
        $path = '/';
        if ($parentId) {
            $parent = $this->getFolderById($parentId);
            if (!$parent) {
                throw new Exception('Parent folder not found');
            }
            $path = $parent['path'] . $cleanName . '/';
        } else {
            $path = '/' . $cleanName . '/';
        }

        $folderId = Database::insert('folders', [
            'tenant_id' => $this->tenantId,
            'parent_id' => $parentId,
            'name' => $cleanName,
            'path' => $path,
            'created_by' => Auth::getUserId()
        ]);

        // Log attività
        Auth::logActivity('folder_create', 'folder', $folderId, ['name' => $cleanName]);

        return $this->getFolderById($folderId);
    }

    /**
     * Elimina cartella e contenuto
     */
    public function deleteFolder(int $folderId): bool {
        $folder = $this->getFolderById($folderId);

        if (!$folder) {
            throw new Exception('Folder not found');
        }

        // Soft delete file nella cartella
        Database::query(
            "UPDATE files SET is_deleted = TRUE, deleted_at = NOW()
             WHERE tenant_id = :tenant_id AND folder_id = :folder_id",
            ['tenant_id' => $this->tenantId, 'folder_id' => $folderId]
        );

        // Elimina sottocartelle ricorsivamente
        $subfolders = Database::select(
            "SELECT id FROM folders WHERE tenant_id = :tenant_id AND parent_id = :parent_id",
            ['tenant_id' => $this->tenantId, 'parent_id' => $folderId]
        );

        foreach ($subfolders as $subfolder) {
            $this->deleteFolder($subfolder['id']);
        }

        // Elimina cartella
        Database::delete('folders', ['id' => $folderId]);

        // Log attività
        Auth::logActivity('folder_delete', 'folder', $folderId);

        return true;
    }

    /**
     * Lista file in una cartella
     */
    public function listFiles(?int $folderId = null, array $options = []): array {
        $where = [
            'tenant_id = :tenant_id',
            'is_deleted = FALSE'
        ];
        $params = ['tenant_id' => $this->tenantId];

        if ($folderId !== null) {
            $where[] = 'folder_id = :folder_id';
            $params['folder_id'] = $folderId;
        } else {
            $where[] = 'folder_id IS NULL';
        }

        // Filtro ricerca
        if (!empty($options['search'])) {
            $where[] = 'name LIKE :search';
            $params['search'] = '%' . Database::escapeLike($options['search']) . '%';
        }

        // Filtro estensione
        if (!empty($options['extension'])) {
            $where[] = 'extension = :extension';
            $params['extension'] = $options['extension'];
        }

        // Costruisci query
        $sql = "SELECT f.*, u.full_name as uploaded_by_name
                FROM files f
                LEFT JOIN users u ON f.uploaded_by = u.id
                WHERE " . implode(' AND ', $where);

        // Ordinamento
        $orderBy = $options['order_by'] ?? 'name';
        $orderDir = $options['order_dir'] ?? 'ASC';
        $allowedOrderBy = ['name', 'size', 'created_at', 'updated_at'];

        if (in_array($orderBy, $allowedOrderBy)) {
            $sql .= " ORDER BY {$orderBy} {$orderDir}";
        }

        // Paginazione
        if (!empty($options['limit'])) {
            $sql .= " LIMIT :limit";
            $params['limit'] = (int)$options['limit'];

            if (!empty($options['offset'])) {
                $sql .= " OFFSET :offset";
                $params['offset'] = (int)$options['offset'];
            }
        }

        return Database::select($sql, $params);
    }

    /**
     * Lista cartelle
     */
    public function listFolders(?int $parentId = null): array {
        $where = ['tenant_id = :tenant_id'];
        $params = ['tenant_id' => $this->tenantId];

        if ($parentId !== null) {
            $where[] = 'parent_id = :parent_id';
            $params['parent_id'] = $parentId;
        } else {
            $where[] = 'parent_id IS NULL';
        }

        $sql = "SELECT f.*, u.full_name as created_by_name,
                (SELECT COUNT(*) FROM files WHERE folder_id = f.id AND is_deleted = FALSE) as file_count,
                (SELECT COUNT(*) FROM folders WHERE parent_id = f.id) as folder_count
                FROM folders f
                LEFT JOIN users u ON f.created_by = u.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY name ASC";

        return Database::select($sql, $params);
    }

    /**
     * Ottiene file nel cestino
     */
    public function getTrashFiles(): array {
        $sql = "SELECT f.*, u.full_name as uploaded_by_name
                FROM files f
                LEFT JOIN users u ON f.uploaded_by = u.id
                WHERE f.tenant_id = :tenant_id AND f.is_deleted = TRUE
                ORDER BY f.deleted_at DESC";

        return Database::select($sql, ['tenant_id' => $this->tenantId]);
    }

    /**
     * Pulisce cestino (file più vecchi di 30 giorni)
     */
    public function cleanTrash(): int {
        $files = Database::select(
            "SELECT * FROM files
             WHERE tenant_id = :tenant_id
             AND is_deleted = TRUE
             AND deleted_at < DATE_SUB(NOW(), INTERVAL :days DAY)",
            ['tenant_id' => $this->tenantId, 'days' => TRASH_RETENTION_DAYS]
        );

        $count = 0;
        foreach ($files as $file) {
            try {
                $this->permanentlyDeleteFile($file['id']);
                $count++;
            } catch (Exception $e) {
                // Log errore e continua
                error_log('Error cleaning trash file ' . $file['id'] . ': ' . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Ottiene statistiche storage
     */
    public function getStorageStats(): array {
        $tenant = Auth::getCurrentTenant();

        $stats = [
            'total_limit' => $tenant['storage_limit'] ?? 0,
            'total_used' => $tenant['storage_used'] ?? 0,
            'percentage_used' => 0,
            'files_count' => 0,
            'folders_count' => 0,
            'trash_count' => 0,
            'trash_size' => 0
        ];

        // Conta file attivi
        $stats['files_count'] = Database::count('files', [
            'tenant_id' => $this->tenantId,
            'is_deleted' => false
        ]);

        // Conta cartelle
        $stats['folders_count'] = Database::count('folders', [
            'tenant_id' => $this->tenantId
        ]);

        // Statistiche cestino
        $trashStats = Database::selectOne(
            "SELECT COUNT(*) as count, COALESCE(SUM(size), 0) as size
             FROM files
             WHERE tenant_id = :tenant_id AND is_deleted = TRUE",
            ['tenant_id' => $this->tenantId]
        );

        $stats['trash_count'] = (int)$trashStats['count'];
        $stats['trash_size'] = (int)$trashStats['size'];

        // Calcola percentuale
        if ($stats['total_limit'] > 0) {
            $stats['percentage_used'] = round(($stats['total_used'] / $stats['total_limit']) * 100, 2);
        }

        return $stats;
    }

    /**
     * Helper: ottiene file per ID
     */
    private function getFileById(int $fileId): ?array {
        return Database::selectOne(
            "SELECT * FROM files
             WHERE id = :id AND tenant_id = :tenant_id AND is_deleted = FALSE",
            ['id' => $fileId, 'tenant_id' => $this->tenantId]
        );
    }

    /**
     * Helper: ottiene file multipli per IDs
     */
    private function getFilesByIds(array $fileIds): array {
        if (empty($fileIds)) {
            return [];
        }

        $placeholders = array_map(fn($i) => ":id_{$i}", array_keys($fileIds));
        $params = ['tenant_id' => $this->tenantId];

        foreach ($fileIds as $i => $id) {
            $params["id_{$i}"] = $id;
        }

        $sql = "SELECT * FROM files
                WHERE tenant_id = :tenant_id
                AND id IN (" . implode(',', $placeholders) . ")
                AND is_deleted = FALSE";

        return Database::select($sql, $params);
    }

    /**
     * Helper: ottiene cartella per ID
     */
    private function getFolderById(int $folderId): ?array {
        return Database::selectOne(
            "SELECT * FROM folders WHERE id = :id AND tenant_id = :tenant_id",
            ['id' => $folderId, 'tenant_id' => $this->tenantId]
        );
    }

    /**
     * Helper: genera percorso file univoco
     */
    private function generateFilePath(string $extension): string {
        $year = date('Y');
        $month = date('m');
        $filename = uniqid() . '_' . time() . '.' . $extension;

        return $this->tenantCode . '/' . $year . '/' . $month . '/' . $filename;
    }

    /**
     * Helper: crea riferimento a file esistente (deduplicazione)
     */
    private function createFileReference(array $existingFile, string $newName, ?int $folderId): array {
        $fileId = Database::insert('files', [
            'tenant_id' => $this->tenantId,
            'folder_id' => $folderId,
            'name' => clean_filename($newName),
            'original_name' => $newName,
            'mime_type' => $existingFile['mime_type'],
            'extension' => $existingFile['extension'],
            'size' => $existingFile['size'],
            'hash' => $existingFile['hash'],
            'path' => $existingFile['path'],
            'uploaded_by' => Auth::getUserId()
        ]);

        // Log attività
        Auth::logActivity('file_upload_deduplicated', 'file', $fileId, [
            'original_file_id' => $existingFile['id']
        ]);

        return $this->getFileById($fileId);
    }

    /**
     * Helper: aggiorna spazio storage utilizzato
     */
    private function updateStorageUsage(int $sizeChange): void {
        Database::query(
            "UPDATE tenants SET storage_used = storage_used + :size WHERE id = :id",
            ['size' => $sizeChange, 'id' => $this->tenantId]
        );
    }

    /**
     * Helper: ottiene MIME type sicuro
     */
    private function getFileMimeType(string $filePath): string {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        return $mimeType ?: 'application/octet-stream';
    }

    /**
     * Helper: valida MIME type
     */
    private function isValidMimeType(string $mimeType, string $extension): bool {
        // Permetti sempre file di testo generico
        if (str_starts_with($mimeType, 'text/')) {
            return true;
        }

        // Verifica corrispondenza con estensione
        $expectedMime = MIME_TYPES[$extension] ?? null;

        if ($expectedMime) {
            return $mimeType === $expectedMime;
        }

        // Default: permetti application/octet-stream per file binari
        return $mimeType === 'application/octet-stream';
    }

    /**
     * Helper: messaggio errore upload
     */
    private function getUploadErrorMessage(int $errorCode): string {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];

        return $errors[$errorCode] ?? 'Unknown upload error';
    }
}