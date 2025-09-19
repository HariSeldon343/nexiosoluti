<?php
declare(strict_types=1);

/**
 * Security Scanner Service
 * Virus scanning, malware detection, and security checks
 */

class SecurityScanner {
    private array $config;
    private array $signatures = [];
    private array $quarantinePath;
    private array $scanLog = [];
    private string $signaturesFile;

    // Known malicious patterns
    private array $maliciousPatterns = [
        'php' => [
            '/eval\s*\(/i',
            '/base64_decode\s*\(/i',
            '/system\s*\(/i',
            '/exec\s*\(/i',
            '/passthru\s*\(/i',
            '/shell_exec\s*\(/i',
            '/\$_GET\s*\[\s*[\'"]cmd[\'"]\s*\]/i',
            '/preg_replace.*\/e/i',
            '/assert\s*\(/i',
            '/create_function\s*\(/i'
        ],
        'javascript' => [
            '/document\.write\s*\(.*unescape/i',
            '/eval\s*\(.*fromCharCode/i',
            '/new\s+ActiveXObject/i',
            '/onclick\s*=\s*[\'"].*javascript:/i',
            '/<script.*src\s*=\s*[\'"]https?:\/\/[^\'"]*/i'
        ],
        'general' => [
            '/\x00/', // Null bytes
            '/<\?php.*eval.*\?>/is',
            '/<iframe.*src\s*=\s*[\'"]https?:\/\/[^\'"]*/i'
        ]
    ];

    // Suspicious file extensions
    private array $suspiciousExtensions = [
        'exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js',
        'jar', 'app', 'deb', 'rpm', 'dmg', 'pkg', 'msi'
    ];

    // Known virus signatures (simplified)
    private array $virusSignatures = [
        'eicar' => 'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*',
        'php_shell' => 'c99shell',
        'web_shell' => 'r57shell',
        'backdoor' => 'backdoor'
    ];

    public function __construct() {
        $this->quarantinePath = APP_ROOT . '/quarantine';
        $this->signaturesFile = APP_ROOT . '/config/virus_signatures.json';

        $this->config = [
            'enabled' => true,
            'real_time_scan' => false,
            'max_file_size' => 104857600, // 100MB
            'scan_archives' => true,
            'scan_depth' => 3,
            'quarantine_infected' => true,
            'delete_infected' => false,
            'scan_timeout' => 300, // 5 minutes
            'clamav' => [
                'enabled' => false,
                'socket' => '/var/run/clamav/clamd.ctl',
                'host' => '127.0.0.1',
                'port' => 3310
            ]
        ];

        if (!is_dir($this->quarantinePath)) {
            mkdir($this->quarantinePath, 0700, true);
        }

        $this->loadSignatures();
    }

    /**
     * Scansiona un file
     */
    public function scanFile(string $filePath, array $options = []): array {
        $startTime = microtime(true);

        $result = [
            'file' => $filePath,
            'status' => 'clean',
            'threats' => [],
            'scan_time' => 0,
            'size' => filesize($filePath),
            'hash' => hash_file('sha256', $filePath)
        ];

        if (!file_exists($filePath)) {
            $result['status'] = 'error';
            $result['error'] = 'File not found';
            return $result;
        }

        // Check file size
        if ($result['size'] > $this->config['max_file_size']) {
            $result['status'] = 'skipped';
            $result['reason'] = 'File too large';
            return $result;
        }

        try {
            // External scanner (ClamAV)
            if ($this->config['clamav']['enabled']) {
                $clamResult = $this->scanWithClamAV($filePath);
                if ($clamResult['infected']) {
                    $result['status'] = 'infected';
                    $result['threats'][] = $clamResult;
                }
            }

            // Pattern-based scanning
            $patternResult = $this->scanForPatterns($filePath);
            if (!empty($patternResult)) {
                $result['status'] = 'suspicious';
                $result['threats'] = array_merge($result['threats'], $patternResult);
            }

            // Signature-based scanning
            $signatureResult = $this->scanForSignatures($filePath);
            if (!empty($signatureResult)) {
                $result['status'] = 'infected';
                $result['threats'] = array_merge($result['threats'], $signatureResult);
            }

            // Check file extension
            $extensionCheck = $this->checkFileExtension($filePath);
            if ($extensionCheck) {
                $result['warnings'][] = $extensionCheck;
            }

            // Check file headers (magic bytes)
            $headerCheck = $this->checkFileHeaders($filePath);
            if ($headerCheck) {
                $result['warnings'][] = $headerCheck;
            }

            // Handle infected file
            if ($result['status'] === 'infected') {
                if ($this->config['quarantine_infected']) {
                    $this->quarantineFile($filePath);
                    $result['action'] = 'quarantined';
                } elseif ($this->config['delete_infected']) {
                    unlink($filePath);
                    $result['action'] = 'deleted';
                }
            }

        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['error'] = $e->getMessage();
        }

        $result['scan_time'] = microtime(true) - $startTime;

        // Log scan result
        $this->logScan($result);

        return $result;
    }

    /**
     * Scansiona una directory
     */
    public function scanDirectory(string $directory, bool $recursive = true): array {
        $results = [
            'directory' => $directory,
            'total_files' => 0,
            'scanned' => 0,
            'clean' => 0,
            'infected' => 0,
            'suspicious' => 0,
            'errors' => 0,
            'files' => [],
            'summary' => []
        ];

        if (!is_dir($directory)) {
            $results['error'] = 'Directory not found';
            return $results;
        }

        $iterator = $recursive
            ? new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
              )
            : new DirectoryIterator($directory);

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $results['total_files']++;

                $scanResult = $this->scanFile($file->getPathname());
                $results['scanned']++;

                switch ($scanResult['status']) {
                    case 'clean':
                        $results['clean']++;
                        break;
                    case 'infected':
                        $results['infected']++;
                        $results['files'][] = $scanResult;
                        break;
                    case 'suspicious':
                        $results['suspicious']++;
                        $results['files'][] = $scanResult;
                        break;
                    case 'error':
                        $results['errors']++;
                        break;
                }
            }
        }

        $results['summary'] = $this->generateScanSummary($results);

        return $results;
    }

    /**
     * Real-time scanning
     */
    public function enableRealTimeScanning(): void {
        $this->config['real_time_scan'] = true;
        // Implementation would require system hooks or file system monitoring
    }

    /**
     * Pattern-based scanning
     */
    private function scanForPatterns(string $filePath): array {
        $threats = [];
        $content = file_get_contents($filePath);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // Determine file type
        $fileType = 'general';
        if (in_array($extension, ['php', 'phtml', 'php3', 'php4', 'php5'])) {
            $fileType = 'php';
        } elseif (in_array($extension, ['js', 'javascript'])) {
            $fileType = 'javascript';
        }

        // Check patterns
        $patterns = array_merge(
            $this->maliciousPatterns['general'],
            $this->maliciousPatterns[$fileType] ?? []
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $threats[] = [
                    'type' => 'pattern',
                    'severity' => 'high',
                    'pattern' => $pattern,
                    'match' => substr($matches[0], 0, 100),
                    'description' => $this->getPatternDescription($pattern)
                ];
            }
        }

        return $threats;
    }

    /**
     * Signature-based scanning
     */
    private function scanForSignatures(string $filePath): array {
        $threats = [];
        $content = file_get_contents($filePath);

        foreach ($this->virusSignatures as $name => $signature) {
            if (strpos($content, $signature) !== false) {
                $threats[] = [
                    'type' => 'signature',
                    'severity' => 'critical',
                    'virus' => $name,
                    'description' => $this->getVirusDescription($name)
                ];
            }
        }

        // Check loaded signatures
        foreach ($this->signatures as $sig) {
            if ($this->matchSignature($content, $sig)) {
                $threats[] = [
                    'type' => 'signature',
                    'severity' => $sig['severity'] ?? 'high',
                    'virus' => $sig['name'],
                    'description' => $sig['description'] ?? 'Known malware'
                ];
            }
        }

        return $threats;
    }

    /**
     * ClamAV integration
     */
    private function scanWithClamAV(string $filePath): array {
        if (!$this->config['clamav']['enabled']) {
            return ['infected' => false];
        }

        try {
            if (file_exists($this->config['clamav']['socket'])) {
                // Unix socket
                $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
                socket_connect($socket, $this->config['clamav']['socket']);
            } else {
                // TCP socket
                $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                socket_connect($socket, $this->config['clamav']['host'], $this->config['clamav']['port']);
            }

            // Send SCAN command
            $command = "SCAN $filePath\n";
            socket_write($socket, $command, strlen($command));

            // Read response
            $response = '';
            while ($buffer = socket_read($socket, 1024)) {
                $response .= $buffer;
                if (strpos($buffer, "\n") !== false) {
                    break;
                }
            }

            socket_close($socket);

            // Parse response
            if (strpos($response, 'FOUND') !== false) {
                preg_match('/: (.+) FOUND/', $response, $matches);
                return [
                    'infected' => true,
                    'type' => 'clamav',
                    'virus' => $matches[1] ?? 'Unknown',
                    'severity' => 'critical'
                ];
            }

            return ['infected' => false];

        } catch (Exception $e) {
            // ClamAV not available or error
            return ['infected' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * File validation
     */
    private function checkFileExtension(string $filePath): ?array {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($extension, $this->suspiciousExtensions)) {
            return [
                'type' => 'extension',
                'extension' => $extension,
                'severity' => 'medium',
                'description' => "Potentially dangerous file extension: $extension"
            ];
        }

        // Check double extension
        $filename = basename($filePath);
        if (preg_match('/\.(jpg|png|gif|pdf|doc|txt)\.(php|asp|jsp|exe)$/i', $filename)) {
            return [
                'type' => 'double_extension',
                'filename' => $filename,
                'severity' => 'high',
                'description' => 'Suspicious double extension detected'
            ];
        }

        return null;
    }

    private function checkFileHeaders(string $filePath): ?array {
        $handle = fopen($filePath, 'rb');
        $header = fread($handle, 512);
        fclose($handle);

        // Check for executable headers
        $executableHeaders = [
            'MZ' => 'Windows executable',
            "\x7FELF" => 'Linux executable',
            "\xCA\xFE\xBA\xBE" => 'Mach-O executable',
            "\xFE\xED\xFA\xCE" => 'Mach-O executable'
        ];

        foreach ($executableHeaders as $signature => $type) {
            if (strpos($header, $signature) === 0) {
                $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                if (!in_array($extension, ['exe', 'dll', 'so', 'dylib'])) {
                    return [
                        'type' => 'header_mismatch',
                        'expected' => $extension,
                        'detected' => $type,
                        'severity' => 'high',
                        'description' => "File header indicates $type but extension is .$extension"
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Quarantine management
     */
    private function quarantineFile(string $filePath): bool {
        $quarantineFile = $this->quarantinePath . '/' . hash('sha256', $filePath) . '.quarantine';

        // Create quarantine metadata
        $metadata = [
            'original_path' => $filePath,
            'quarantine_date' => time(),
            'file_hash' => hash_file('sha256', $filePath),
            'reason' => 'Virus detected'
        ];

        // Encrypt file (simple XOR for demonstration)
        $content = file_get_contents($filePath);
        $encrypted = $this->encryptQuarantine($content);

        // Save to quarantine
        file_put_contents($quarantineFile, $encrypted);
        file_put_contents($quarantineFile . '.meta', json_encode($metadata));

        // Remove original
        unlink($filePath);

        return true;
    }

    public function restoreFromQuarantine(string $quarantineId): bool {
        $quarantineFile = $this->quarantinePath . '/' . $quarantineId . '.quarantine';
        $metadataFile = $quarantineFile . '.meta';

        if (!file_exists($quarantineFile) || !file_exists($metadataFile)) {
            return false;
        }

        $metadata = json_decode(file_get_contents($metadataFile), true);
        $encrypted = file_get_contents($quarantineFile);
        $content = $this->decryptQuarantine($encrypted);

        // Restore to original location
        file_put_contents($metadata['original_path'], $content);

        // Remove from quarantine
        unlink($quarantineFile);
        unlink($metadataFile);

        return true;
    }

    private function encryptQuarantine(string $content): string {
        // Simple XOR encryption (for demonstration)
        $key = 'SecureQuarantineKey';
        $result = '';

        for ($i = 0; $i < strlen($content); $i++) {
            $result .= chr(ord($content[$i]) ^ ord($key[$i % strlen($key)]));
        }

        return base64_encode($result);
    }

    private function decryptQuarantine(string $encrypted): string {
        $encrypted = base64_decode($encrypted);
        return $this->encryptQuarantine($encrypted); // XOR is reversible
    }

    /**
     * Signature management
     */
    private function loadSignatures(): void {
        if (file_exists($this->signaturesFile)) {
            $content = file_get_contents($this->signaturesFile);
            $this->signatures = json_decode($content, true) ?: [];
        }
    }

    public function updateSignatures(string $source = null): bool {
        if ($source) {
            // Download signatures from remote source
            $signatures = $this->downloadSignatures($source);
            if ($signatures) {
                $this->signatures = array_merge($this->signatures, $signatures);
                $this->saveSignatures();
                return true;
            }
        }

        return false;
    }

    private function downloadSignatures(string $source): ?array {
        // Implementation for downloading signatures
        $ch = curl_init($source);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return json_decode($response, true);
        }

        return null;
    }

    private function saveSignatures(): void {
        file_put_contents(
            $this->signaturesFile,
            json_encode($this->signatures, JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    private function matchSignature(string $content, array $signature): bool {
        if (isset($signature['hex'])) {
            $hex = bin2hex($content);
            return strpos($hex, $signature['hex']) !== false;
        }

        if (isset($signature['string'])) {
            return strpos($content, $signature['string']) !== false;
        }

        if (isset($signature['regex'])) {
            return preg_match($signature['regex'], $content);
        }

        return false;
    }

    /**
     * Heuristic analysis
     */
    public function performHeuristicAnalysis(string $filePath): array {
        $analysis = [
            'entropy' => $this->calculateEntropy($filePath),
            'compression_ratio' => $this->getCompressionRatio($filePath),
            'suspicious_strings' => $this->findSuspiciousStrings($filePath),
            'risk_score' => 0
        ];

        // High entropy might indicate encryption/packing
        if ($analysis['entropy'] > 7.5) {
            $analysis['risk_score'] += 30;
            $analysis['warnings'][] = 'High entropy detected (possible encryption/packing)';
        }

        // Low compression ratio might indicate already compressed/encrypted
        if ($analysis['compression_ratio'] < 1.1) {
            $analysis['risk_score'] += 20;
            $analysis['warnings'][] = 'Low compression ratio (possible packed file)';
        }

        // Suspicious strings
        if (count($analysis['suspicious_strings']) > 5) {
            $analysis['risk_score'] += 10 * count($analysis['suspicious_strings']);
        }

        $analysis['risk_level'] = $this->getRiskLevel($analysis['risk_score']);

        return $analysis;
    }

    private function calculateEntropy(string $filePath): float {
        $content = file_get_contents($filePath);
        $entropy = 0.0;
        $size = strlen($content);

        if ($size === 0) {
            return 0.0;
        }

        $frequency = array_count_values(str_split($content));

        foreach ($frequency as $count) {
            $probability = $count / $size;
            $entropy -= $probability * log($probability, 2);
        }

        return $entropy;
    }

    private function getCompressionRatio(string $filePath): float {
        $original = file_get_contents($filePath);
        $compressed = gzcompress($original, 9);

        if (strlen($compressed) === 0) {
            return 0.0;
        }

        return strlen($original) / strlen($compressed);
    }

    private function findSuspiciousStrings(string $filePath): array {
        $content = file_get_contents($filePath);
        $suspicious = [];

        $keywords = [
            'hack', 'crack', 'exploit', 'backdoor', 'trojan',
            'keylogger', 'rootkit', 'malware', 'virus', 'worm',
            'cmd.exe', 'powershell', 'wget', 'curl', 'nc.exe'
        ];

        foreach ($keywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                $suspicious[] = $keyword;
            }
        }

        return $suspicious;
    }

    private function getRiskLevel(int $score): string {
        if ($score >= 80) return 'critical';
        if ($score >= 60) return 'high';
        if ($score >= 40) return 'medium';
        if ($score >= 20) return 'low';
        return 'minimal';
    }

    /**
     * Helper methods
     */
    private function getPatternDescription(string $pattern): string {
        $descriptions = [
            '/eval\s*\(/i' => 'Potentially dangerous eval() function',
            '/base64_decode\s*\(/i' => 'Base64 decoding (often used to hide malicious code)',
            '/system\s*\(/i' => 'System command execution',
            '/exec\s*\(/i' => 'Command execution function',
            '/<iframe/i' => 'Hidden iframe (possible clickjacking)'
        ];

        return $descriptions[$pattern] ?? 'Suspicious pattern detected';
    }

    private function getVirusDescription(string $virus): string {
        $descriptions = [
            'eicar' => 'EICAR test virus',
            'php_shell' => 'PHP web shell',
            'web_shell' => 'Web shell backdoor',
            'backdoor' => 'Backdoor trojan'
        ];

        return $descriptions[$virus] ?? 'Unknown virus';
    }

    private function generateScanSummary(array $results): array {
        return [
            'threat_level' => $this->calculateThreatLevel($results),
            'recommendations' => $this->getRecommendations($results),
            'scan_date' => date('Y-m-d H:i:s'),
            'scan_duration' => $results['scan_time'] ?? 0
        ];
    }

    private function calculateThreatLevel(array $results): string {
        if ($results['infected'] > 0) return 'critical';
        if ($results['suspicious'] > 5) return 'high';
        if ($results['suspicious'] > 0) return 'medium';
        if ($results['errors'] > 0) return 'low';
        return 'safe';
    }

    private function getRecommendations(array $results): array {
        $recommendations = [];

        if ($results['infected'] > 0) {
            $recommendations[] = 'Infected files detected. Review quarantine and take action.';
        }

        if ($results['suspicious'] > 0) {
            $recommendations[] = 'Suspicious files found. Manual review recommended.';
        }

        if ($results['errors'] > 0) {
            $recommendations[] = 'Some files could not be scanned. Check permissions.';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'No threats detected. System appears clean.';
        }

        return $recommendations;
    }

    /**
     * Logging
     */
    private function logScan(array $result): void {
        $this->scanLog[] = $result;

        $logFile = LOG_PATH . '/security_' . date('Y-m-d') . '.log';
        $logEntry = date('Y-m-d H:i:s') . ' ' . json_encode($result) . PHP_EOL;

        if (!is_dir(LOG_PATH)) {
            mkdir(LOG_PATH, 0777, true);
        }

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Statistics
     */
    public function getStats(): array {
        $quarantineFiles = glob($this->quarantinePath . '/*.quarantine');

        return [
            'scans_performed' => count($this->scanLog),
            'quarantined_files' => count($quarantineFiles),
            'signatures_loaded' => count($this->signatures) + count($this->virusSignatures),
            'clamav_enabled' => $this->config['clamav']['enabled'],
            'real_time_scanning' => $this->config['real_time_scan']
        ];
    }
}