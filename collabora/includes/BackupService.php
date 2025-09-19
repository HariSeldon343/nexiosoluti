<?php
declare(strict_types=1);

/**
 * Backup and Restore Service
 * Gestione backup incrementali e completi
 */

class BackupService {
    private string $backupPath;
    private StorageInterface $storage;
    private array $config;
    private string $manifestFile;
    private array $excludePatterns = [];

    public function __construct() {
        $this->backupPath = APP_ROOT . '/backups';
        $this->storage = StorageManager::disk('backup');
        $this->manifestFile = $this->backupPath . '/manifest.json';

        $this->config = [
            'compression' => 'gzip', // none, gzip, bzip2
            'encryption' => false,
            'max_backups' => 10,
            'retention_days' => 30,
            'chunk_size' => 52428800, // 50MB chunks
            'verify_after_backup' => true,
            'include_database' => true,
            'incremental' => true
        ];

        $this->excludePatterns = [
            '*/cache/*',
            '*/temp/*',
            '*/logs/*',
            '*.tmp',
            '.DS_Store',
            'Thumbs.db'
        ];

        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0777, true);
        }

        $this->loadManifest();
    }

    /**
     * Crea backup completo
     */
    public function createFullBackup(int $tenantId = null, array $options = []): array {
        $startTime = microtime(true);
        $backupId = $this->generateBackupId();
        $backupDir = $this->backupPath . '/' . $backupId;

        try {
            // Create backup directory
            mkdir($backupDir, 0777, true);

            // Initialize backup info
            $backupInfo = [
                'id' => $backupId,
                'type' => 'full',
                'tenant_id' => $tenantId,
                'created_at' => time(),
                'status' => 'in_progress',
                'files' => [],
                'database' => [],
                'size' => 0,
                'file_count' => 0,
                'options' => $options
            ];

            // Backup files
            $this->logBackup($backupId, 'Starting file backup');
            $fileBackup = $this->backupFiles($backupDir, $tenantId);
            $backupInfo['files'] = $fileBackup;
            $backupInfo['file_count'] = $fileBackup['count'];

            // Backup database
            if ($this->config['include_database']) {
                $this->logBackup($backupId, 'Starting database backup');
                $dbBackup = $this->backupDatabase($backupDir, $tenantId);
                $backupInfo['database'] = $dbBackup;
            }

            // Create archive if requested
            if ($options['archive'] ?? true) {
                $this->logBackup($backupId, 'Creating archive');
                $archivePath = $this->createArchive($backupDir, $backupId);
                $backupInfo['archive'] = $archivePath;
                $backupInfo['size'] = filesize($archivePath);

                // Remove uncompressed files
                if ($options['remove_source'] ?? true) {
                    $this->removeDirectory($backupDir);
                }
            } else {
                $backupInfo['size'] = $this->getDirectorySize($backupDir);
            }

            // Verify backup
            if ($this->config['verify_after_backup']) {
                $this->logBackup($backupId, 'Verifying backup');
                $verification = $this->verifyBackup($backupId);
                $backupInfo['verified'] = $verification['valid'];
                $backupInfo['verification'] = $verification;
            }

            // Update status
            $backupInfo['status'] = 'completed';
            $backupInfo['duration'] = microtime(true) - $startTime;

            // Save to manifest
            $this->addToManifest($backupInfo);

            // Clean old backups
            $this->cleanOldBackups();

            $this->logBackup($backupId, 'Backup completed successfully');

            return $backupInfo;

        } catch (Exception $e) {
            $backupInfo['status'] = 'failed';
            $backupInfo['error'] = $e->getMessage();
            $this->addToManifest($backupInfo);

            $this->logBackup($backupId, 'Backup failed: ' . $e->getMessage());

            // Clean up partial backup
            if (is_dir($backupDir)) {
                $this->removeDirectory($backupDir);
            }

            throw $e;
        }
    }

    /**
     * Crea backup incrementale
     */
    public function createIncrementalBackup(int $tenantId = null): array {
        $lastBackup = $this->getLastBackup($tenantId, 'full');

        if (!$lastBackup) {
            // No full backup exists, create one
            return $this->createFullBackup($tenantId);
        }

        $startTime = microtime(true);
        $backupId = $this->generateBackupId();
        $backupDir = $this->backupPath . '/' . $backupId;

        try {
            mkdir($backupDir, 0777, true);

            $backupInfo = [
                'id' => $backupId,
                'type' => 'incremental',
                'parent_id' => $lastBackup['id'],
                'tenant_id' => $tenantId,
                'created_at' => time(),
                'status' => 'in_progress',
                'changes' => [],
                'size' => 0
            ];

            // Find changed files since last backup
            $this->logBackup($backupId, 'Finding changed files');
            $changes = $this->findChangedFiles($lastBackup['created_at'], $tenantId);

            // Backup changed files
            foreach ($changes['added'] as $file) {
                $this->backupFile($file, $backupDir);
            }

            foreach ($changes['modified'] as $file) {
                $this->backupFile($file, $backupDir);
            }

            $backupInfo['changes'] = $changes;

            // Backup database changes
            if ($this->config['include_database']) {
                $dbChanges = $this->backupDatabaseChanges($backupDir, $lastBackup['created_at'], $tenantId);
                $backupInfo['database_changes'] = $dbChanges;
            }

            // Create archive
            $archivePath = $this->createArchive($backupDir, $backupId);
            $backupInfo['archive'] = $archivePath;
            $backupInfo['size'] = filesize($archivePath);

            // Clean up
            $this->removeDirectory($backupDir);

            $backupInfo['status'] = 'completed';
            $backupInfo['duration'] = microtime(true) - $startTime;

            $this->addToManifest($backupInfo);
            $this->logBackup($backupId, 'Incremental backup completed');

            return $backupInfo;

        } catch (Exception $e) {
            $backupInfo['status'] = 'failed';
            $backupInfo['error'] = $e->getMessage();
            $this->addToManifest($backupInfo);

            if (is_dir($backupDir)) {
                $this->removeDirectory($backupDir);
            }

            throw $e;
        }
    }

    /**
     * Ripristina backup
     */
    public function restore(string $backupId, array $options = []): bool {
        $backup = $this->getBackup($backupId);

        if (!$backup) {
            throw new Exception("Backup $backupId not found");
        }

        $this->logRestore($backupId, 'Starting restore');

        try {
            // Verify backup before restore
            if ($options['verify'] ?? true) {
                $verification = $this->verifyBackup($backupId);
                if (!$verification['valid']) {
                    throw new Exception('Backup verification failed');
                }
            }

            // Create restore point
            if ($options['create_restore_point'] ?? true) {
                $this->logRestore($backupId, 'Creating restore point');
                $restorePoint = $this->createRestorePoint();
            }

            // Extract backup
            $tempDir = $this->extractBackup($backupId);

            // Restore files
            if ($options['restore_files'] ?? true) {
                $this->logRestore($backupId, 'Restoring files');
                $this->restoreFiles($tempDir, $options);
            }

            // Restore database
            if ($options['restore_database'] ?? true && isset($backup['database'])) {
                $this->logRestore($backupId, 'Restoring database');
                $this->restoreDatabase($tempDir, $options);
            }

            // Clean up
            $this->removeDirectory($tempDir);

            $this->logRestore($backupId, 'Restore completed successfully');

            return true;

        } catch (Exception $e) {
            $this->logRestore($backupId, 'Restore failed: ' . $e->getMessage());

            // Attempt to restore from restore point
            if (isset($restorePoint)) {
                $this->logRestore($backupId, 'Attempting to restore from restore point');
                $this->restore($restorePoint['id'], ['create_restore_point' => false]);
            }

            throw $e;
        }
    }

    /**
     * File backup methods
     */
    private function backupFiles(string $backupDir, int $tenantId = null): array {
        $filesDir = $backupDir . '/files';
        mkdir($filesDir, 0777, true);

        $fileList = [];
        $totalSize = 0;
        $count = 0;

        // Get files from database
        $where = $tenantId ? "WHERE tenant_id = :tenant_id" : "";
        $params = $tenantId ? ['tenant_id' => $tenantId] : [];

        $files = Database::select(
            "SELECT * FROM files $where",
            $params
        );

        foreach ($files as $file) {
            $sourcePath = UPLOAD_PATH . '/' . $file['path'];
            if (file_exists($sourcePath)) {
                $destPath = $filesDir . '/' . $file['path'];
                $destDir = dirname($destPath);

                if (!is_dir($destDir)) {
                    mkdir($destDir, 0777, true);
                }

                if (copy($sourcePath, $destPath)) {
                    $fileList[] = $file['path'];
                    $totalSize += $file['size'];
                    $count++;
                }
            }
        }

        return [
            'count' => $count,
            'size' => $totalSize,
            'list' => $fileList
        ];
    }

    private function backupFile(string $filePath, string $backupDir): bool {
        $sourcePath = UPLOAD_PATH . '/' . $filePath;
        $destPath = $backupDir . '/files/' . $filePath;

        if (!file_exists($sourcePath)) {
            return false;
        }

        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0777, true);
        }

        return copy($sourcePath, $destPath);
    }

    /**
     * Database backup methods
     */
    private function backupDatabase(string $backupDir, int $tenantId = null): array {
        $dbDir = $backupDir . '/database';
        mkdir($dbDir, 0777, true);

        $tables = ['users', 'tenants', 'files', 'folders', 'activity_logs'];
        $backupInfo = [];

        foreach ($tables as $table) {
            if (!Database::tableExists($table)) {
                continue;
            }

            $where = "";
            $params = [];

            if ($tenantId && in_array($table, ['users', 'files', 'folders', 'activity_logs'])) {
                $where = "WHERE tenant_id = :tenant_id";
                $params = ['tenant_id' => $tenantId];
            }

            $data = Database::select("SELECT * FROM $table $where", $params);

            $filename = $dbDir . '/' . $table . '.json';
            file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));

            $backupInfo[$table] = [
                'count' => count($data),
                'size' => filesize($filename)
            ];
        }

        // Backup database structure
        $this->backupDatabaseStructure($dbDir);

        return $backupInfo;
    }

    private function backupDatabaseStructure(string $dbDir): void {
        $structure = [];

        $tables = Database::select("SHOW TABLES");
        foreach ($tables as $table) {
            $tableName = array_values($table)[0];
            $createTable = Database::selectOne("SHOW CREATE TABLE `$tableName`");
            $structure[$tableName] = $createTable['Create Table'] ?? '';
        }

        file_put_contents(
            $dbDir . '/structure.sql',
            implode(";\n\n", $structure) . ';'
        );
    }

    private function backupDatabaseChanges(string $backupDir, int $since, int $tenantId = null): array {
        $dbDir = $backupDir . '/database';
        mkdir($dbDir, 0777, true);

        $changes = [];

        // Backup changed records
        $tables = ['files', 'folders', 'activity_logs'];

        foreach ($tables as $table) {
            if (!Database::tableExists($table)) {
                continue;
            }

            $where = "WHERE updated_at > FROM_UNIXTIME(:since)";
            $params = ['since' => $since];

            if ($tenantId) {
                $where .= " AND tenant_id = :tenant_id";
                $params['tenant_id'] = $tenantId;
            }

            $data = Database::select("SELECT * FROM $table $where", $params);

            if (!empty($data)) {
                $filename = $dbDir . '/' . $table . '_changes.json';
                file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));

                $changes[$table] = [
                    'count' => count($data),
                    'size' => filesize($filename)
                ];
            }
        }

        return $changes;
    }

    /**
     * Archive methods
     */
    private function createArchive(string $sourceDir, string $backupId): string {
        $archivePath = $this->backupPath . '/' . $backupId . '.tar.gz';

        if (PHP_OS_FAMILY === 'Windows') {
            // Use PHP's PharData for Windows
            $phar = new PharData($this->backupPath . '/' . $backupId . '.tar');
            $phar->buildFromDirectory($sourceDir);
            $phar->compress(Phar::GZ);

            // Remove uncompressed tar
            unlink($this->backupPath . '/' . $backupId . '.tar');
        } else {
            // Use system tar for Unix/Linux
            $command = sprintf(
                'tar -czf %s -C %s .',
                escapeshellarg($archivePath),
                escapeshellarg($sourceDir)
            );
            exec($command, $output, $return);

            if ($return !== 0) {
                throw new Exception('Failed to create archive');
            }
        }

        return $archivePath;
    }

    private function extractBackup(string $backupId): string {
        $backup = $this->getBackup($backupId);
        $archivePath = $backup['archive'] ?? $this->backupPath . '/' . $backupId . '.tar.gz';

        if (!file_exists($archivePath)) {
            throw new Exception('Backup archive not found');
        }

        $tempDir = sys_get_temp_dir() . '/restore_' . $backupId;
        mkdir($tempDir, 0777, true);

        if (PHP_OS_FAMILY === 'Windows') {
            $phar = new PharData($archivePath);
            $phar->extractTo($tempDir);
        } else {
            $command = sprintf(
                'tar -xzf %s -C %s',
                escapeshellarg($archivePath),
                escapeshellarg($tempDir)
            );
            exec($command, $output, $return);

            if ($return !== 0) {
                throw new Exception('Failed to extract archive');
            }
        }

        return $tempDir;
    }

    /**
     * Restore methods
     */
    private function restoreFiles(string $sourceDir, array $options): bool {
        $filesDir = $sourceDir . '/files';

        if (!is_dir($filesDir)) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($filesDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($filesDir . '/', '', $file->getPathname());
                $destPath = UPLOAD_PATH . '/' . $relativePath;

                // Backup existing file if requested
                if ($options['backup_existing'] ?? true && file_exists($destPath)) {
                    $backupPath = $destPath . '.backup_' . time();
                    rename($destPath, $backupPath);
                }

                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0777, true);
                }

                copy($file->getPathname(), $destPath);
            }
        }

        return true;
    }

    private function restoreDatabase(string $sourceDir, array $options): bool {
        $dbDir = $sourceDir . '/database';

        if (!is_dir($dbDir)) {
            return false;
        }

        // Restore structure if requested
        if ($options['restore_structure'] ?? false) {
            $structureFile = $dbDir . '/structure.sql';
            if (file_exists($structureFile)) {
                $sql = file_get_contents($structureFile);
                // Execute SQL statements
                // Note: This requires careful handling of multiple statements
            }
        }

        // Restore data
        $tables = ['tenants', 'users', 'folders', 'files', 'activity_logs'];

        foreach ($tables as $table) {
            $dataFile = $dbDir . '/' . $table . '.json';

            if (!file_exists($dataFile)) {
                continue;
            }

            $data = json_decode(file_get_contents($dataFile), true);

            if ($options['clear_existing'] ?? false) {
                Database::query("TRUNCATE TABLE $table");
            }

            foreach ($data as $row) {
                try {
                    Database::insert($table, $row);
                } catch (Exception $e) {
                    // Handle duplicate key errors
                    if ($options['skip_errors'] ?? false) {
                        continue;
                    }
                    throw $e;
                }
            }
        }

        return true;
    }

    /**
     * Verification methods
     */
    public function verifyBackup(string $backupId): array {
        $backup = $this->getBackup($backupId);

        if (!$backup) {
            return ['valid' => false, 'error' => 'Backup not found'];
        }

        $verification = [
            'valid' => true,
            'checks' => []
        ];

        // Check archive exists
        $archivePath = $backup['archive'] ?? $this->backupPath . '/' . $backupId . '.tar.gz';
        if (!file_exists($archivePath)) {
            $verification['valid'] = false;
            $verification['checks']['archive'] = 'Archive file not found';
        } else {
            $verification['checks']['archive'] = 'OK';

            // Check archive integrity
            if (PHP_OS_FAMILY !== 'Windows') {
                exec("tar -tzf " . escapeshellarg($archivePath) . " 2>&1", $output, $return);
                if ($return !== 0) {
                    $verification['valid'] = false;
                    $verification['checks']['integrity'] = 'Archive corrupted';
                } else {
                    $verification['checks']['integrity'] = 'OK';
                }
            }
        }

        // Check size
        if (filesize($archivePath) !== $backup['size']) {
            $verification['valid'] = false;
            $verification['checks']['size'] = 'Size mismatch';
        } else {
            $verification['checks']['size'] = 'OK';
        }

        return $verification;
    }

    /**
     * Utility methods
     */
    private function findChangedFiles(int $since, int $tenantId = null): array {
        $changes = [
            'added' => [],
            'modified' => [],
            'deleted' => []
        ];

        $where = "WHERE created_at > FROM_UNIXTIME(:since)";
        $params = ['since' => $since];

        if ($tenantId) {
            $where .= " AND tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        }

        // Find added files
        $added = Database::select(
            "SELECT path FROM files $where",
            $params
        );

        foreach ($added as $file) {
            $changes['added'][] = $file['path'];
        }

        // Find modified files
        $where = "WHERE updated_at > FROM_UNIXTIME(:since) AND created_at <= FROM_UNIXTIME(:since)";
        $modified = Database::select(
            "SELECT path FROM files $where",
            $params
        );

        foreach ($modified as $file) {
            $changes['modified'][] = $file['path'];
        }

        // Find deleted files
        $where = "WHERE is_deleted = 1 AND deleted_at > FROM_UNIXTIME(:since)";
        if ($tenantId) {
            $where .= " AND tenant_id = :tenant_id";
        }

        $deleted = Database::select(
            "SELECT path FROM files $where",
            $params
        );

        foreach ($deleted as $file) {
            $changes['deleted'][] = $file['path'];
        }

        return $changes;
    }

    private function createRestorePoint(): array {
        return $this->createFullBackup(null, [
            'type' => 'restore_point',
            'archive' => true,
            'remove_source' => true
        ]);
    }

    private function generateBackupId(): string {
        return date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8);
    }

    private function getDirectorySize(string $dir): int {
        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    private function removeDirectory(string $dir): bool {
        if (!is_dir($dir)) {
            return false;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        return rmdir($dir);
    }

    /**
     * Manifest management
     */
    private function loadManifest(): void {
        if (file_exists($this->manifestFile)) {
            $content = file_get_contents($this->manifestFile);
            $this->manifest = json_decode($content, true) ?: [];
        } else {
            $this->manifest = [];
        }
    }

    private function saveManifest(): void {
        file_put_contents(
            $this->manifestFile,
            json_encode($this->manifest, JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    private function addToManifest(array $backupInfo): void {
        $this->manifest[$backupInfo['id']] = $backupInfo;
        $this->saveManifest();
    }

    public function getBackup(string $backupId): ?array {
        return $this->manifest[$backupId] ?? null;
    }

    public function getLastBackup(int $tenantId = null, string $type = null): ?array {
        $backups = array_filter($this->manifest, function($backup) use ($tenantId, $type) {
            if ($tenantId && $backup['tenant_id'] != $tenantId) {
                return false;
            }
            if ($type && $backup['type'] != $type) {
                return false;
            }
            return $backup['status'] === 'completed';
        });

        if (empty($backups)) {
            return null;
        }

        uasort($backups, fn($a, $b) => $b['created_at'] - $a['created_at']);
        return reset($backups);
    }

    public function listBackups(int $tenantId = null): array {
        if ($tenantId === null) {
            return $this->manifest;
        }

        return array_filter($this->manifest, fn($b) => $b['tenant_id'] == $tenantId);
    }

    /**
     * Cleanup methods
     */
    private function cleanOldBackups(): void {
        $now = time();
        $maxAge = $this->config['retention_days'] * 86400;

        foreach ($this->manifest as $id => $backup) {
            if (($now - $backup['created_at']) > $maxAge) {
                $this->deleteBackup($id);
            }
        }

        // Keep only max_backups
        $backups = array_values($this->manifest);
        usort($backups, fn($a, $b) => $b['created_at'] - $a['created_at']);

        if (count($backups) > $this->config['max_backups']) {
            $toDelete = array_slice($backups, $this->config['max_backups']);
            foreach ($toDelete as $backup) {
                $this->deleteBackup($backup['id']);
            }
        }
    }

    public function deleteBackup(string $backupId): bool {
        $backup = $this->getBackup($backupId);

        if (!$backup) {
            return false;
        }

        // Delete archive file
        $archivePath = $backup['archive'] ?? $this->backupPath . '/' . $backupId . '.tar.gz';
        if (file_exists($archivePath)) {
            unlink($archivePath);
        }

        // Delete directory if exists
        $backupDir = $this->backupPath . '/' . $backupId;
        if (is_dir($backupDir)) {
            $this->removeDirectory($backupDir);
        }

        // Remove from manifest
        unset($this->manifest[$backupId]);
        $this->saveManifest();

        return true;
    }

    /**
     * Logging
     */
    private function logBackup(string $backupId, string $message): void {
        $this->log('backup', $backupId, $message);
    }

    private function logRestore(string $backupId, string $message): void {
        $this->log('restore', $backupId, $message);
    }

    private function log(string $type, string $id, string $message): void {
        $logFile = LOG_PATH . '/backup_' . date('Y-m-d') . '.log';
        $logEntry = date('Y-m-d H:i:s') . " [$type] [$id] $message" . PHP_EOL;

        if (!is_dir(LOG_PATH)) {
            mkdir(LOG_PATH, 0777, true);
        }

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Statistics
     */
    public function getStats(): array {
        $totalSize = 0;
        $totalCount = count($this->manifest);
        $byType = ['full' => 0, 'incremental' => 0];
        $byStatus = ['completed' => 0, 'failed' => 0, 'in_progress' => 0];

        foreach ($this->manifest as $backup) {
            $totalSize += $backup['size'] ?? 0;
            $byType[$backup['type']] = ($byType[$backup['type']] ?? 0) + 1;
            $byStatus[$backup['status']] = ($byStatus[$backup['status']] ?? 0) + 1;
        }

        return [
            'total_backups' => $totalCount,
            'total_size' => format_bytes($totalSize),
            'by_type' => $byType,
            'by_status' => $byStatus,
            'last_backup' => $this->getLastBackup(),
            'storage_path' => $this->backupPath
        ];
    }
}