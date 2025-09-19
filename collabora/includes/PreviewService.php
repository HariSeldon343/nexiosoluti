<?php
declare(strict_types=1);

/**
 * File Preview Service
 * Genera anteprime per diversi tipi di file
 */

class PreviewService {
    private string $cachePath;
    private array $thumbnailSizes = [
        'small' => [150, 150],
        'medium' => [300, 300],
        'large' => [600, 600]
    ];
    private array $supportedFormats = [];
    private int $cacheLifetime = 86400 * 7; // 7 days
    private int $maxProcessingSize = 52428800; // 50MB

    public function __construct() {
        $this->cachePath = APP_ROOT . '/cache/previews';

        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0777, true);
        }

        $this->detectSupportedFormats();
    }

    /**
     * Genera anteprima per un file
     */
    public function generatePreview(string $filePath, string $size = 'medium', array $options = []): ?string {
        if (!file_exists($filePath)) {
            return null;
        }

        $fileSize = filesize($filePath);
        if ($fileSize > $this->maxProcessingSize) {
            return null;
        }

        $mimeType = mime_content_type($filePath);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        // Check cache
        $cacheKey = $this->getCacheKey($filePath, $size, $options);
        $cachedPreview = $this->getFromCache($cacheKey);

        if ($cachedPreview) {
            return $cachedPreview;
        }

        // Generate preview based on file type
        $preview = null;

        if ($this->isImage($mimeType)) {
            $preview = $this->generateImagePreview($filePath, $size);
        } elseif ($this->isPdf($mimeType)) {
            $preview = $this->generatePdfPreview($filePath, $size, $options['page'] ?? 1);
        } elseif ($this->isVideo($mimeType)) {
            $preview = $this->generateVideoThumbnail($filePath, $size, $options['time'] ?? 1);
        } elseif ($this->isOfficeDocument($mimeType, $extension)) {
            $preview = $this->generateOfficePreview($filePath, $size);
        } elseif ($this->isTextFile($mimeType)) {
            $preview = $this->generateTextPreview($filePath);
        } elseif ($this->isArchive($mimeType, $extension)) {
            $preview = $this->generateArchivePreview($filePath);
        } else {
            $preview = $this->generateGenericPreview($mimeType, $extension);
        }

        if ($preview) {
            $this->saveToCache($cacheKey, $preview);
        }

        return $preview;
    }

    /**
     * Image preview generation
     */
    private function generateImagePreview(string $filePath, string $size): ?string {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $imageData = file_get_contents($filePath);
            $sourceImage = imagecreatefromstring($imageData);

            if (!$sourceImage) {
                return null;
            }

            $sourceWidth = imagesx($sourceImage);
            $sourceHeight = imagesy($sourceImage);

            [$targetWidth, $targetHeight] = $this->thumbnailSizes[$size] ?? $this->thumbnailSizes['medium'];

            // Calculate dimensions maintaining aspect ratio
            $ratio = min($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
            $newWidth = (int)($sourceWidth * $ratio);
            $newHeight = (int)($sourceHeight * $ratio);

            // Create thumbnail
            $thumbnail = imagecreatetruecolor($newWidth, $newHeight);

            // Preserve transparency for PNG and GIF
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
            imagefilledrectangle($thumbnail, 0, 0, $newWidth, $newHeight, $transparent);

            // Resize
            imagecopyresampled(
                $thumbnail, $sourceImage,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $sourceWidth, $sourceHeight
            );

            // Convert to base64
            ob_start();
            imagepng($thumbnail);
            $imageData = ob_get_clean();

            imagedestroy($sourceImage);
            imagedestroy($thumbnail);

            return 'data:image/png;base64,' . base64_encode($imageData);

        } catch (Exception $e) {
            $this->logError('Image preview generation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * PDF preview generation
     */
    private function generatePdfPreview(string $filePath, string $size, int $page = 1): ?string {
        // Check if Imagick is available
        if (class_exists('Imagick')) {
            try {
                $imagick = new Imagick();
                $imagick->setResolution(150, 150);
                $imagick->readImage($filePath . '[' . ($page - 1) . ']');
                $imagick->setImageFormat('png');

                [$width, $height] = $this->thumbnailSizes[$size] ?? $this->thumbnailSizes['medium'];
                $imagick->thumbnailImage($width, $height, true);

                $imageData = $imagick->getImageBlob();
                $imagick->clear();

                return 'data:image/png;base64,' . base64_encode($imageData);

            } catch (Exception $e) {
                $this->logError('PDF preview with Imagick failed: ' . $e->getMessage());
            }
        }

        // Fallback to Ghostscript if available
        if (PHP_OS_FAMILY !== 'Windows') {
            $tempFile = tempnam(sys_get_temp_dir(), 'pdf_preview');
            $command = sprintf(
                'gs -dNOPAUSE -dBATCH -sDEVICE=png16m -dFirstPage=%d -dLastPage=%d -r150 -sOutputFile=%s %s 2>&1',
                $page,
                $page,
                escapeshellarg($tempFile),
                escapeshellarg($filePath)
            );

            exec($command, $output, $return);

            if ($return === 0 && file_exists($tempFile)) {
                $imageData = file_get_contents($tempFile);
                unlink($tempFile);

                // Resize if needed
                return $this->resizeImage($imageData, $size);
            }
        }

        return $this->generateGenericPreview('application/pdf', 'pdf');
    }

    /**
     * Video thumbnail generation
     */
    private function generateVideoThumbnail(string $filePath, string $size, int $time = 1): ?string {
        // Check if FFmpeg is available
        if (PHP_OS_FAMILY !== 'Windows') {
            $tempFile = tempnam(sys_get_temp_dir(), 'video_thumb') . '.png';
            $command = sprintf(
                'ffmpeg -i %s -ss %d -vframes 1 -vf scale=640:-1 %s 2>&1',
                escapeshellarg($filePath),
                $time,
                escapeshellarg($tempFile)
            );

            exec($command, $output, $return);

            if ($return === 0 && file_exists($tempFile)) {
                $imageData = file_get_contents($tempFile);
                unlink($tempFile);

                return $this->resizeImage($imageData, $size);
            }
        }

        return $this->generateGenericPreview('video/*', 'mp4');
    }

    /**
     * Office document preview
     */
    private function generateOfficePreview(string $filePath, string $size): ?string {
        // For DOCX files, try to extract thumbnail from the package
        if (pathinfo($filePath, PATHINFO_EXTENSION) === 'docx') {
            $zip = new ZipArchive();
            if ($zip->open($filePath) === TRUE) {
                // Check for embedded thumbnail
                if ($zip->locateName('docProps/thumbnail.jpeg') !== false) {
                    $thumbnail = $zip->getFromName('docProps/thumbnail.jpeg');
                    $zip->close();

                    return $this->resizeImage($thumbnail, $size);
                }
                $zip->close();
            }
        }

        // LibreOffice conversion (if available)
        if (PHP_OS_FAMILY !== 'Windows') {
            $tempDir = sys_get_temp_dir() . '/office_preview_' . uniqid();
            mkdir($tempDir);

            $command = sprintf(
                'libreoffice --headless --convert-to pdf --outdir %s %s 2>&1',
                escapeshellarg($tempDir),
                escapeshellarg($filePath)
            );

            exec($command, $output, $return);

            if ($return === 0) {
                $pdfFile = $tempDir . '/' . pathinfo($filePath, PATHINFO_FILENAME) . '.pdf';
                if (file_exists($pdfFile)) {
                    $preview = $this->generatePdfPreview($pdfFile, $size);
                    unlink($pdfFile);
                    rmdir($tempDir);
                    return $preview;
                }
            }

            if (is_dir($tempDir)) {
                rmdir($tempDir);
            }
        }

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        return $this->generateGenericPreview('application/vnd.ms-office', $extension);
    }

    /**
     * Text file preview
     */
    private function generateTextPreview(string $filePath): ?string {
        $content = file_get_contents($filePath, false, null, 0, 5000); // Read first 5KB

        // Clean and truncate content
        $content = htmlspecialchars($content);
        $content = nl2br($content);

        if (strlen($content) > 1000) {
            $content = substr($content, 0, 1000) . '...';
        }

        // Generate HTML preview
        $html = '
        <div style="
            font-family: monospace;
            font-size: 12px;
            padding: 10px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            overflow: auto;
            max-height: 400px;
        ">' . $content . '</div>';

        return 'data:text/html;base64,' . base64_encode($html);
    }

    /**
     * Archive preview
     */
    private function generateArchivePreview(string $filePath): ?string {
        $contents = [];
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'zip') {
            $zip = new ZipArchive();
            if ($zip->open($filePath) === TRUE) {
                for ($i = 0; $i < min($zip->numFiles, 50); $i++) {
                    $stat = $zip->statIndex($i);
                    $contents[] = [
                        'name' => $stat['name'],
                        'size' => format_bytes($stat['size']),
                        'compressed' => format_bytes($stat['comp_size'])
                    ];
                }
                $zip->close();
            }
        }

        // Generate HTML table
        $html = '<style>
            table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; }
            th { background: #f0f0f0; padding: 8px; text-align: left; }
            td { padding: 6px; border-bottom: 1px solid #eee; }
        </style>';
        $html .= '<table><thead><tr><th>File</th><th>Size</th><th>Compressed</th></tr></thead><tbody>';

        foreach ($contents as $file) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($file['name']) . '</td>';
            $html .= '<td>' . $file['size'] . '</td>';
            $html .= '<td>' . $file['compressed'] . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        if (count($contents) === 50) {
            $html .= '<p style="text-align: center; color: #666;">... and more files</p>';
        }

        return 'data:text/html;base64,' . base64_encode($html);
    }

    /**
     * Generic preview (icon-based)
     */
    private function generateGenericPreview(string $mimeType, string $extension): string {
        $icon = $this->getFileIcon($mimeType, $extension);
        $color = $this->getFileColor($mimeType, $extension);

        $svg = '
        <svg width="200" height="200" xmlns="http://www.w3.org/2000/svg">
            <rect width="200" height="200" fill="' . $color . '" opacity="0.1"/>
            <text x="100" y="100" font-family="Arial" font-size="60" fill="' . $color . '" text-anchor="middle" dominant-baseline="middle">
                ' . strtoupper($extension) . '
            </text>
        </svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Helper methods
     */
    private function resizeImage(string $imageData, string $size): ?string {
        $sourceImage = imagecreatefromstring($imageData);
        if (!$sourceImage) {
            return null;
        }

        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);

        [$targetWidth, $targetHeight] = $this->thumbnailSizes[$size] ?? $this->thumbnailSizes['medium'];

        $ratio = min($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
        $newWidth = (int)($sourceWidth * $ratio);
        $newHeight = (int)($sourceHeight * $ratio);

        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled(
            $thumbnail, $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $sourceWidth, $sourceHeight
        );

        ob_start();
        imagepng($thumbnail);
        $resizedData = ob_get_clean();

        imagedestroy($sourceImage);
        imagedestroy($thumbnail);

        return 'data:image/png;base64,' . base64_encode($resizedData);
    }

    private function isImage(string $mimeType): bool {
        return strpos($mimeType, 'image/') === 0;
    }

    private function isPdf(string $mimeType): bool {
        return $mimeType === 'application/pdf';
    }

    private function isVideo(string $mimeType): bool {
        return strpos($mimeType, 'video/') === 0;
    }

    private function isTextFile(string $mimeType): bool {
        return strpos($mimeType, 'text/') === 0 || in_array($mimeType, [
            'application/json',
            'application/xml',
            'application/javascript'
        ]);
    }

    private function isOfficeDocument(string $mimeType, string $extension): bool {
        $officeTypes = [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];

        $officeExtensions = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp'];

        return in_array($mimeType, $officeTypes) || in_array($extension, $officeExtensions);
    }

    private function isArchive(string $mimeType, string $extension): bool {
        $archiveTypes = [
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'application/x-tar',
            'application/gzip'
        ];

        $archiveExtensions = ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'];

        return in_array($mimeType, $archiveTypes) || in_array($extension, $archiveExtensions);
    }

    private function getFileIcon(string $mimeType, string $extension): string {
        $icons = [
            'pdf' => '📄',
            'doc' => '📝', 'docx' => '📝',
            'xls' => '📊', 'xlsx' => '📊',
            'ppt' => '📽️', 'pptx' => '📽️',
            'zip' => '🗜️', 'rar' => '🗜️',
            'jpg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️',
            'mp3' => '🎵', 'mp4' => '🎬',
            'txt' => '📃', 'csv' => '📋'
        ];

        return $icons[$extension] ?? '📎';
    }

    private function getFileColor(string $mimeType, string $extension): string {
        $colors = [
            'pdf' => '#e74c3c',
            'doc' => '#3498db', 'docx' => '#3498db',
            'xls' => '#2ecc71', 'xlsx' => '#2ecc71',
            'ppt' => '#e67e22', 'pptx' => '#e67e22',
            'zip' => '#95a5a6', 'rar' => '#95a5a6',
            'jpg' => '#9b59b6', 'png' => '#9b59b6', 'gif' => '#9b59b6',
            'mp3' => '#1abc9c', 'mp4' => '#34495e'
        ];

        return $colors[$extension] ?? '#7f8c8d';
    }

    /**
     * Format detection
     */
    private function detectSupportedFormats(): void {
        $this->supportedFormats = [];

        // Check GD library
        if (function_exists('imagecreate')) {
            $this->supportedFormats[] = 'gd';

            if (function_exists('imagecreatefromjpeg')) {
                $this->supportedFormats[] = 'jpeg';
            }
            if (function_exists('imagecreatefrompng')) {
                $this->supportedFormats[] = 'png';
            }
            if (function_exists('imagecreatefromgif')) {
                $this->supportedFormats[] = 'gif';
            }
            if (function_exists('imagecreatefromwebp')) {
                $this->supportedFormats[] = 'webp';
            }
        }

        // Check Imagick
        if (class_exists('Imagick')) {
            $this->supportedFormats[] = 'imagick';
        }

        // Check external tools
        if (PHP_OS_FAMILY !== 'Windows') {
            // FFmpeg
            exec('which ffmpeg 2>&1', $output, $return);
            if ($return === 0) {
                $this->supportedFormats[] = 'ffmpeg';
            }

            // LibreOffice
            exec('which libreoffice 2>&1', $output, $return);
            if ($return === 0) {
                $this->supportedFormats[] = 'libreoffice';
            }

            // Ghostscript
            exec('which gs 2>&1', $output, $return);
            if ($return === 0) {
                $this->supportedFormats[] = 'ghostscript';
            }
        }
    }

    /**
     * Cache management
     */
    private function getCacheKey(string $filePath, string $size, array $options): string {
        $stat = stat($filePath);
        return md5($filePath . $size . json_encode($options) . $stat['mtime'] . $stat['size']);
    }

    private function getFromCache(string $key): ?string {
        $cacheFile = $this->cachePath . '/' . $key;

        if (file_exists($cacheFile)) {
            $age = time() - filemtime($cacheFile);
            if ($age < $this->cacheLifetime) {
                return file_get_contents($cacheFile);
            }
            unlink($cacheFile);
        }

        return null;
    }

    private function saveToCache(string $key, string $data): void {
        $cacheFile = $this->cachePath . '/' . $key;
        file_put_contents($cacheFile, $data, LOCK_EX);
    }

    public function clearCache(int $olderThan = null): int {
        $cleared = 0;
        $time = $olderThan ?? (time() - $this->cacheLifetime);

        $files = glob($this->cachePath . '/*');
        foreach ($files as $file) {
            if (filemtime($file) < $time) {
                unlink($file);
                $cleared++;
            }
        }

        return $cleared;
    }

    /**
     * Batch processing
     */
    public function generateBatchPreviews(array $files, string $size = 'medium'): array {
        $results = [];

        foreach ($files as $file) {
            $results[$file] = $this->generatePreview($file, $size);
        }

        return $results;
    }

    /**
     * Statistics
     */
    public function getStats(): array {
        $cacheFiles = glob($this->cachePath . '/*');

        $totalSize = 0;
        foreach ($cacheFiles as $file) {
            $totalSize += filesize($file);
        }

        return [
            'cache_files' => count($cacheFiles),
            'cache_size' => format_bytes($totalSize),
            'supported_formats' => $this->supportedFormats
        ];
    }

    private function logError(string $message): void {
        $logFile = LOG_PATH . '/preview_' . date('Y-m-d') . '.log';
        $logEntry = date('Y-m-d H:i:s') . ' [ERROR] ' . $message . PHP_EOL;

        if (!is_dir(LOG_PATH)) {
            mkdir(LOG_PATH, 0777, true);
        }

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}