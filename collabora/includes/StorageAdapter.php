<?php
declare(strict_types=1);

/**
 * Storage Abstraction Layer
 * Supporta multiple backend di storage (local, S3, FTP, etc.)
 */

interface StorageInterface {
    public function exists(string $path): bool;
    public function get(string $path): string|false;
    public function put(string $path, string $contents): bool;
    public function putStream(string $path, $stream): bool;
    public function delete(string $path): bool;
    public function copy(string $source, string $destination): bool;
    public function move(string $source, string $destination): bool;
    public function size(string $path): int|false;
    public function lastModified(string $path): int|false;
    public function listContents(string $directory = '', bool $recursive = false): array;
    public function createDirectory(string $path): bool;
    public function deleteDirectory(string $path): bool;
    public function getUrl(string $path): string;
    public function getMetadata(string $path): array|false;
}

class LocalStorageAdapter implements StorageInterface {
    private string $basePath;
    private string $baseUrl;
    private array $permissions = [
        'file' => 0644,
        'dir' => 0755
    ];

    public function __construct(string $basePath, string $baseUrl = '') {
        $this->basePath = rtrim($basePath, '/');
        $this->baseUrl = rtrim($baseUrl, '/');

        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, $this->permissions['dir'], true);
        }
    }

    private function getFullPath(string $path): string {
        return $this->basePath . '/' . ltrim($path, '/');
    }

    public function exists(string $path): bool {
        return file_exists($this->getFullPath($path));
    }

    public function get(string $path): string|false {
        $fullPath = $this->getFullPath($path);
        if (!file_exists($fullPath)) {
            return false;
        }
        return file_get_contents($fullPath);
    }

    public function put(string $path, string $contents): bool {
        $fullPath = $this->getFullPath($path);
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, $this->permissions['dir'], true);
        }

        $result = file_put_contents($fullPath, $contents, LOCK_EX);
        if ($result !== false) {
            chmod($fullPath, $this->permissions['file']);
            return true;
        }
        return false;
    }

    public function putStream(string $path, $stream): bool {
        $fullPath = $this->getFullPath($path);
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, $this->permissions['dir'], true);
        }

        $target = fopen($fullPath, 'wb');
        if (!$target) {
            return false;
        }

        $result = stream_copy_to_stream($stream, $target);
        fclose($target);

        if ($result !== false) {
            chmod($fullPath, $this->permissions['file']);
            return true;
        }
        return false;
    }

    public function delete(string $path): bool {
        $fullPath = $this->getFullPath($path);
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return true;
    }

    public function copy(string $source, string $destination): bool {
        $sourcePath = $this->getFullPath($source);
        $destPath = $this->getFullPath($destination);

        if (!file_exists($sourcePath)) {
            return false;
        }

        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, $this->permissions['dir'], true);
        }

        return copy($sourcePath, $destPath);
    }

    public function move(string $source, string $destination): bool {
        $sourcePath = $this->getFullPath($source);
        $destPath = $this->getFullPath($destination);

        if (!file_exists($sourcePath)) {
            return false;
        }

        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, $this->permissions['dir'], true);
        }

        return rename($sourcePath, $destPath);
    }

    public function size(string $path): int|false {
        $fullPath = $this->getFullPath($path);
        if (file_exists($fullPath)) {
            return filesize($fullPath);
        }
        return false;
    }

    public function lastModified(string $path): int|false {
        $fullPath = $this->getFullPath($path);
        if (file_exists($fullPath)) {
            return filemtime($fullPath);
        }
        return false;
    }

    public function listContents(string $directory = '', bool $recursive = false): array {
        $fullPath = $this->getFullPath($directory);
        if (!is_dir($fullPath)) {
            return [];
        }

        $contents = [];
        $iterator = $recursive
            ? new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS)
              )
            : new DirectoryIterator($fullPath);

        foreach ($iterator as $file) {
            if ($file->isFile() || ($file->isDir() && !$recursive)) {
                $relativePath = str_replace($this->basePath . '/', '', $file->getPathname());
                $contents[] = [
                    'path' => $relativePath,
                    'type' => $file->isFile() ? 'file' : 'dir',
                    'size' => $file->isFile() ? $file->getSize() : 0,
                    'timestamp' => $file->getMTime()
                ];
            }
        }

        return $contents;
    }

    public function createDirectory(string $path): bool {
        $fullPath = $this->getFullPath($path);
        if (!is_dir($fullPath)) {
            return mkdir($fullPath, $this->permissions['dir'], true);
        }
        return true;
    }

    public function deleteDirectory(string $path): bool {
        $fullPath = $this->getFullPath($path);
        if (!is_dir($fullPath)) {
            return true;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        return rmdir($fullPath);
    }

    public function getUrl(string $path): string {
        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    public function getMetadata(string $path): array|false {
        $fullPath = $this->getFullPath($path);
        if (!file_exists($fullPath)) {
            return false;
        }

        $stat = stat($fullPath);
        return [
            'path' => $path,
            'type' => is_file($fullPath) ? 'file' : 'dir',
            'size' => $stat['size'],
            'timestamp' => $stat['mtime'],
            'visibility' => is_readable($fullPath) ? 'public' : 'private',
            'mimetype' => is_file($fullPath) ? mime_content_type($fullPath) : null
        ];
    }
}

/**
 * S3-Compatible Storage Adapter
 * Ready per integrazione con AWS S3, MinIO, etc.
 */
class S3StorageAdapter implements StorageInterface {
    private array $config;
    private ?object $client = null;

    public function __construct(array $config) {
        $this->config = array_merge([
            'key' => '',
            'secret' => '',
            'region' => 'us-east-1',
            'bucket' => '',
            'endpoint' => null,
            'use_path_style' => false,
            'version' => 'latest'
        ], $config);
    }

    private function getClient(): object {
        if ($this->client === null) {
            // Stub per future integrazioni S3
            // Richiederà AWS SDK o implementazione custom
            throw new Exception('S3 client not implemented. Install AWS SDK.');
        }
        return $this->client;
    }

    public function exists(string $path): bool {
        // Implementazione stub
        return false;
    }

    public function get(string $path): string|false {
        // Implementazione stub
        return false;
    }

    public function put(string $path, string $contents): bool {
        // Implementazione stub
        return false;
    }

    public function putStream(string $path, $stream): bool {
        // Implementazione stub
        return false;
    }

    public function delete(string $path): bool {
        // Implementazione stub
        return false;
    }

    public function copy(string $source, string $destination): bool {
        // Implementazione stub
        return false;
    }

    public function move(string $source, string $destination): bool {
        // Implementazione stub
        return false;
    }

    public function size(string $path): int|false {
        // Implementazione stub
        return false;
    }

    public function lastModified(string $path): int|false {
        // Implementazione stub
        return false;
    }

    public function listContents(string $directory = '', bool $recursive = false): array {
        // Implementazione stub
        return [];
    }

    public function createDirectory(string $path): bool {
        // S3 non ha vere directory
        return true;
    }

    public function deleteDirectory(string $path): bool {
        // Implementazione stub
        return false;
    }

    public function getUrl(string $path): string {
        // Implementazione stub
        return '';
    }

    public function getMetadata(string $path): array|false {
        // Implementazione stub
        return false;
    }
}

/**
 * Storage Manager - Factory e gestione multi-adapter
 */
class StorageManager {
    private static array $adapters = [];
    private static ?string $defaultAdapter = null;

    public static function addAdapter(string $name, StorageInterface $adapter): void {
        self::$adapters[$name] = $adapter;

        if (self::$defaultAdapter === null) {
            self::$defaultAdapter = $name;
        }
    }

    public static function setDefaultAdapter(string $name): void {
        if (!isset(self::$adapters[$name])) {
            throw new Exception("Storage adapter '$name' not found");
        }
        self::$defaultAdapter = $name;
    }

    public static function getAdapter(string $name = null): StorageInterface {
        $name = $name ?? self::$defaultAdapter;

        if (!isset(self::$adapters[$name])) {
            throw new Exception("Storage adapter '$name' not found");
        }

        return self::$adapters[$name];
    }

    public static function disk(string $name = null): StorageInterface {
        return self::getAdapter($name);
    }

    /**
     * Inizializza storage predefiniti
     */
    public static function initialize(): void {
        // Storage locale principale
        $localAdapter = new LocalStorageAdapter(
            UPLOAD_PATH,
            APP_URL . '/uploads'
        );
        self::addAdapter('local', $localAdapter);

        // Storage temporaneo
        $tempAdapter = new LocalStorageAdapter(
            APP_ROOT . '/temp',
            APP_URL . '/temp'
        );
        self::addAdapter('temp', $tempAdapter);

        // Storage per backup
        $backupAdapter = new LocalStorageAdapter(
            APP_ROOT . '/backups',
            ''
        );
        self::addAdapter('backup', $backupAdapter);

        // Storage pubblico
        $publicAdapter = new LocalStorageAdapter(
            APP_ROOT . '/public',
            APP_URL . '/public'
        );
        self::addAdapter('public', $publicAdapter);
    }
}

// Inizializza storage manager
StorageManager::initialize();