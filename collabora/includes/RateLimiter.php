<?php
declare(strict_types=1);

/**
 * Rate Limiter Service
 * API rate limiting and throttling
 */

class RateLimiter {
    private string $storagePath;
    private array $config;
    private array $cache = [];

    // Rate limit strategies
    const STRATEGY_FIXED_WINDOW = 'fixed_window';
    const STRATEGY_SLIDING_WINDOW = 'sliding_window';
    const STRATEGY_TOKEN_BUCKET = 'token_bucket';
    const STRATEGY_LEAKY_BUCKET = 'leaky_bucket';

    public function __construct() {
        $this->storagePath = APP_ROOT . '/temp/rate_limits';

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0777, true);
        }

        $this->config = [
            'default_limit' => 100,
            'default_window' => 3600, // 1 hour
            'strategy' => self::STRATEGY_SLIDING_WINDOW,
            'storage' => 'file', // file, redis, memcached, database
            'cleanup_probability' => 1, // 1% chance
            'limits' => [
                'api' => [
                    'default' => ['limit' => 1000, 'window' => 3600],
                    'auth' => ['limit' => 10, 'window' => 900], // 10 attempts per 15 min
                    'upload' => ['limit' => 100, 'window' => 3600],
                    'download' => ['limit' => 500, 'window' => 3600],
                    'search' => ['limit' => 200, 'window' => 3600],
                    'webhook' => ['limit' => 50, 'window' => 60]
                ],
                'user' => [
                    'free' => ['limit' => 100, 'window' => 3600],
                    'basic' => ['limit' => 500, 'window' => 3600],
                    'premium' => ['limit' => 2000, 'window' => 3600],
                    'unlimited' => ['limit' => PHP_INT_MAX, 'window' => 3600]
                ],
                'ip' => [
                    'default' => ['limit' => 1000, 'window' => 3600],
                    'burst' => ['limit' => 50, 'window' => 60]
                ]
            ]
        ];

        // Cleanup old records
        if (mt_rand(1, 100) <= $this->config['cleanup_probability']) {
            $this->cleanup();
        }
    }

    /**
     * Check rate limit
     */
    public function check(string $key, string $type = 'api', string $action = 'default'): array {
        $identifier = $this->getIdentifier($key, $type, $action);
        $limit = $this->getLimit($type, $action);

        switch ($this->config['strategy']) {
            case self::STRATEGY_FIXED_WINDOW:
                return $this->checkFixedWindow($identifier, $limit);

            case self::STRATEGY_SLIDING_WINDOW:
                return $this->checkSlidingWindow($identifier, $limit);

            case self::STRATEGY_TOKEN_BUCKET:
                return $this->checkTokenBucket($identifier, $limit);

            case self::STRATEGY_LEAKY_BUCKET:
                return $this->checkLeakyBucket($identifier, $limit);

            default:
                return $this->checkSlidingWindow($identifier, $limit);
        }
    }

    /**
     * Consume rate limit
     */
    public function consume(string $key, string $type = 'api', string $action = 'default', int $tokens = 1): bool {
        $result = $this->check($key, $type, $action);

        if ($result['remaining'] >= $tokens) {
            $identifier = $this->getIdentifier($key, $type, $action);
            $this->recordRequest($identifier, $tokens);
            return true;
        }

        return false;
    }

    /**
     * Fixed window strategy
     */
    private function checkFixedWindow(string $identifier, array $limit): array {
        $window = $this->getCurrentWindow($limit['window']);
        $count = $this->getRequestCount($identifier, $window);

        $remaining = max(0, $limit['limit'] - $count);
        $resetTime = $window + $limit['window'];

        return [
            'allowed' => $remaining > 0,
            'limit' => $limit['limit'],
            'remaining' => $remaining,
            'reset' => $resetTime,
            'retry_after' => $remaining > 0 ? null : ($resetTime - time())
        ];
    }

    /**
     * Sliding window strategy
     */
    private function checkSlidingWindow(string $identifier, array $limit): array {
        $now = time();
        $windowStart = $now - $limit['window'];

        // Get requests in current window
        $requests = $this->getRequests($identifier);
        $requests = array_filter($requests, fn($timestamp) => $timestamp >= $windowStart);

        $count = count($requests);
        $remaining = max(0, $limit['limit'] - $count);

        // Calculate reset time (when oldest request expires)
        $resetTime = $remaining > 0 ? null : (min($requests) + $limit['window']);

        return [
            'allowed' => $remaining > 0,
            'limit' => $limit['limit'],
            'remaining' => $remaining,
            'reset' => $resetTime,
            'retry_after' => $remaining > 0 ? null : ($resetTime - time())
        ];
    }

    /**
     * Token bucket strategy
     */
    private function checkTokenBucket(string $identifier, array $limit): array {
        $bucket = $this->getTokenBucket($identifier);
        $now = time();

        // Initialize bucket if needed
        if (!$bucket) {
            $bucket = [
                'tokens' => $limit['limit'],
                'last_refill' => $now
            ];
        }

        // Refill tokens
        $timePassed = $now - $bucket['last_refill'];
        $refillRate = $limit['limit'] / $limit['window'];
        $tokensToAdd = $timePassed * $refillRate;

        $bucket['tokens'] = min($limit['limit'], $bucket['tokens'] + $tokensToAdd);
        $bucket['last_refill'] = $now;

        // Save updated bucket
        $this->saveTokenBucket($identifier, $bucket);

        $remaining = floor($bucket['tokens']);
        $resetTime = $remaining >= $limit['limit'] ? null : $now + ceil((1 - $bucket['tokens']) / $refillRate);

        return [
            'allowed' => $remaining > 0,
            'limit' => $limit['limit'],
            'remaining' => $remaining,
            'reset' => $resetTime,
            'retry_after' => $remaining > 0 ? null : ceil(1 / $refillRate)
        ];
    }

    /**
     * Leaky bucket strategy
     */
    private function checkLeakyBucket(string $identifier, array $limit): array {
        $bucket = $this->getLeakyBucket($identifier);
        $now = time();

        // Initialize bucket if needed
        if (!$bucket) {
            $bucket = [
                'volume' => 0,
                'last_leak' => $now
            ];
        }

        // Leak water from bucket
        $timePassed = $now - $bucket['last_leak'];
        $leakRate = $limit['limit'] / $limit['window'];
        $leaked = $timePassed * $leakRate;

        $bucket['volume'] = max(0, $bucket['volume'] - $leaked);
        $bucket['last_leak'] = $now;

        // Check if bucket can accept more
        $canAccept = $bucket['volume'] < $limit['limit'];
        $remaining = floor($limit['limit'] - $bucket['volume']);

        // Save updated bucket
        $this->saveLeakyBucket($identifier, $bucket);

        $resetTime = $bucket['volume'] > 0 ? $now + ceil($bucket['volume'] / $leakRate) : null;

        return [
            'allowed' => $canAccept,
            'limit' => $limit['limit'],
            'remaining' => $remaining,
            'reset' => $resetTime,
            'retry_after' => $canAccept ? null : ceil(1 / $leakRate)
        ];
    }

    /**
     * Distributed rate limiting
     */
    public function checkDistributed(string $key, array $servers, string $type = 'api', string $action = 'default'): array {
        $totalLimit = $this->getLimit($type, $action);
        $perServerLimit = [
            'limit' => ceil($totalLimit['limit'] / count($servers)),
            'window' => $totalLimit['window']
        ];

        // Check local limit
        $localResult = $this->check($key, $type, $action);

        // Sync with other servers (simplified)
        $globalCount = $localResult['limit'] - $localResult['remaining'];

        foreach ($servers as $server) {
            $remoteCount = $this->getRemoteCount($server, $key, $type, $action);
            $globalCount += $remoteCount;
        }

        $globalRemaining = max(0, $totalLimit['limit'] - $globalCount);

        return [
            'allowed' => $globalRemaining > 0,
            'limit' => $totalLimit['limit'],
            'remaining' => $globalRemaining,
            'reset' => $localResult['reset'],
            'retry_after' => $localResult['retry_after']
        ];
    }

    /**
     * Adaptive rate limiting
     */
    public function checkAdaptive(string $key, string $type = 'api', string $action = 'default'): array {
        $baseLimit = $this->getLimit($type, $action);

        // Get user behavior metrics
        $metrics = $this->getUserMetrics($key);

        // Adjust limit based on behavior
        $adjustedLimit = $this->adjustLimitBasedOnBehavior($baseLimit, $metrics);

        return $this->checkSlidingWindow(
            $this->getIdentifier($key, $type, $action),
            $adjustedLimit
        );
    }

    private function adjustLimitBasedOnBehavior(array $baseLimit, array $metrics): array {
        $multiplier = 1.0;

        // Good behavior increases limit
        if ($metrics['error_rate'] < 0.01) {
            $multiplier *= 1.5;
        }

        // Consistent usage pattern
        if ($metrics['variance'] < 0.2) {
            $multiplier *= 1.2;
        }

        // Bad behavior decreases limit
        if ($metrics['burst_count'] > 5) {
            $multiplier *= 0.5;
        }

        if ($metrics['violation_count'] > 0) {
            $multiplier *= max(0.1, 1 - ($metrics['violation_count'] * 0.1));
        }

        return [
            'limit' => (int)($baseLimit['limit'] * $multiplier),
            'window' => $baseLimit['window']
        ];
    }

    /**
     * Cost-based rate limiting
     */
    public function checkCostBased(string $key, int $cost, string $type = 'api'): array {
        $budget = $this->getBudget($key, $type);
        $remaining = $budget['remaining'] - $cost;

        if ($remaining >= 0) {
            $this->updateBudget($key, $type, $remaining);
        }

        return [
            'allowed' => $remaining >= 0,
            'limit' => $budget['limit'],
            'remaining' => max(0, $remaining),
            'reset' => $budget['reset'],
            'retry_after' => $remaining >= 0 ? null : ($budget['reset'] - time())
        ];
    }

    /**
     * Hierarchical rate limiting
     */
    public function checkHierarchical(array $hierarchy): array {
        $results = [];
        $allowed = true;

        foreach ($hierarchy as $level) {
            $result = $this->check($level['key'], $level['type'], $level['action']);
            $results[$level['name']] = $result;

            if (!$result['allowed']) {
                $allowed = false;
            }
        }

        // Return most restrictive limit
        $mostRestrictive = null;
        $minRemaining = PHP_INT_MAX;

        foreach ($results as $name => $result) {
            if ($result['remaining'] < $minRemaining) {
                $minRemaining = $result['remaining'];
                $mostRestrictive = $result;
                $mostRestrictive['level'] = $name;
            }
        }

        return $mostRestrictive ?: [
            'allowed' => true,
            'limit' => 0,
            'remaining' => PHP_INT_MAX,
            'reset' => null,
            'retry_after' => null
        ];
    }

    /**
     * Whitelist/Blacklist management
     */
    public function isWhitelisted(string $key): bool {
        $whitelist = $this->loadList('whitelist');
        return in_array($key, $whitelist);
    }

    public function isBlacklisted(string $key): bool {
        $blacklist = $this->loadList('blacklist');
        return in_array($key, $blacklist);
    }

    public function addToWhitelist(string $key): void {
        $whitelist = $this->loadList('whitelist');
        if (!in_array($key, $whitelist)) {
            $whitelist[] = $key;
            $this->saveList('whitelist', $whitelist);
        }
    }

    public function addToBlacklist(string $key, int $duration = 0): void {
        $blacklist = $this->loadList('blacklist');
        $blacklist[$key] = $duration > 0 ? time() + $duration : 0;
        $this->saveList('blacklist', $blacklist);
    }

    public function removeFromWhitelist(string $key): void {
        $whitelist = $this->loadList('whitelist');
        $whitelist = array_diff($whitelist, [$key]);
        $this->saveList('whitelist', $whitelist);
    }

    public function removeFromBlacklist(string $key): void {
        $blacklist = $this->loadList('blacklist');
        unset($blacklist[$key]);
        $this->saveList('blacklist', $blacklist);
    }

    /**
     * Storage methods
     */
    private function recordRequest(string $identifier, int $tokens = 1): void {
        $requests = $this->getRequests($identifier);
        $now = time();

        for ($i = 0; $i < $tokens; $i++) {
            $requests[] = $now;
        }

        $this->saveRequests($identifier, $requests);
    }

    private function getRequests(string $identifier): array {
        $file = $this->storagePath . '/' . md5($identifier) . '.json';

        if (file_exists($file)) {
            $content = file_get_contents($file);
            return json_decode($content, true) ?: [];
        }

        return [];
    }

    private function saveRequests(string $identifier, array $requests): void {
        $file = $this->storagePath . '/' . md5($identifier) . '.json';

        // Keep only recent requests
        $maxAge = max(array_column($this->config['limits']['api'], 'window'));
        $cutoff = time() - $maxAge;
        $requests = array_filter($requests, fn($timestamp) => $timestamp >= $cutoff);

        file_put_contents($file, json_encode(array_values($requests)), LOCK_EX);
    }

    private function getRequestCount(string $identifier, int $window): int {
        $file = $this->storagePath . '/' . md5($identifier) . '_' . $window . '.count';

        if (file_exists($file)) {
            return (int)file_get_contents($file);
        }

        return 0;
    }

    private function getCurrentWindow(int $windowSize): int {
        return floor(time() / $windowSize) * $windowSize;
    }

    private function getTokenBucket(string $identifier): ?array {
        $file = $this->storagePath . '/' . md5($identifier) . '.bucket';

        if (file_exists($file)) {
            $content = file_get_contents($file);
            return json_decode($content, true);
        }

        return null;
    }

    private function saveTokenBucket(string $identifier, array $bucket): void {
        $file = $this->storagePath . '/' . md5($identifier) . '.bucket';
        file_put_contents($file, json_encode($bucket), LOCK_EX);
    }

    private function getLeakyBucket(string $identifier): ?array {
        return $this->getTokenBucket($identifier); // Same structure
    }

    private function saveLeakyBucket(string $identifier, array $bucket): void {
        $this->saveTokenBucket($identifier, $bucket); // Same structure
    }

    private function getBudget(string $key, string $type): array {
        $file = $this->storagePath . '/' . md5("budget_$key_$type") . '.json';

        if (file_exists($file)) {
            $budget = json_decode(file_get_contents($file), true);

            // Reset if window expired
            if ($budget['reset'] <= time()) {
                $budget = $this->resetBudget($key, $type);
            }

            return $budget;
        }

        return $this->resetBudget($key, $type);
    }

    private function updateBudget(string $key, string $type, int $remaining): void {
        $budget = $this->getBudget($key, $type);
        $budget['remaining'] = $remaining;

        $file = $this->storagePath . '/' . md5("budget_$key_$type") . '.json';
        file_put_contents($file, json_encode($budget), LOCK_EX);
    }

    private function resetBudget(string $key, string $type): array {
        $limit = $this->getLimit($type, 'default');

        return [
            'limit' => $limit['limit'] * 100, // Cost units
            'remaining' => $limit['limit'] * 100,
            'reset' => time() + $limit['window']
        ];
    }

    /**
     * Helper methods
     */
    private function getIdentifier(string $key, string $type, string $action): string {
        return "$type:$action:$key";
    }

    private function getLimit(string $type, string $action): array {
        if (isset($this->config['limits'][$type][$action])) {
            return $this->config['limits'][$type][$action];
        }

        if (isset($this->config['limits'][$type]['default'])) {
            return $this->config['limits'][$type]['default'];
        }

        return [
            'limit' => $this->config['default_limit'],
            'window' => $this->config['default_window']
        ];
    }

    private function getUserMetrics(string $key): array {
        // Simplified metrics calculation
        $requests = $this->getRequests($key);
        $now = time();
        $hourAgo = $now - 3600;

        $recentRequests = array_filter($requests, fn($t) => $t >= $hourAgo);

        return [
            'total_requests' => count($recentRequests),
            'error_rate' => 0.01, // Would track actual errors
            'variance' => $this->calculateVariance($recentRequests),
            'burst_count' => $this->countBursts($recentRequests),
            'violation_count' => 0 // Would track actual violations
        ];
    }

    private function calculateVariance(array $timestamps): float {
        if (count($timestamps) < 2) {
            return 0;
        }

        $intervals = [];
        for ($i = 1; $i < count($timestamps); $i++) {
            $intervals[] = $timestamps[$i] - $timestamps[$i - 1];
        }

        $mean = array_sum($intervals) / count($intervals);
        $variance = 0;

        foreach ($intervals as $interval) {
            $variance += pow($interval - $mean, 2);
        }

        return $variance / count($intervals);
    }

    private function countBursts(array $timestamps, int $burstWindow = 10): int {
        $bursts = 0;
        $windowCounts = [];

        foreach ($timestamps as $timestamp) {
            $window = floor($timestamp / $burstWindow);
            $windowCounts[$window] = ($windowCounts[$window] ?? 0) + 1;
        }

        foreach ($windowCounts as $count) {
            if ($count > 10) {
                $bursts++;
            }
        }

        return $bursts;
    }

    private function getRemoteCount(string $server, string $key, string $type, string $action): int {
        // Simplified remote count retrieval
        // In production, would use Redis, Memcached, or API calls
        return 0;
    }

    private function loadList(string $name): array {
        $file = $this->storagePath . "/$name.json";

        if (file_exists($file)) {
            $content = file_get_contents($file);
            $list = json_decode($content, true) ?: [];

            // Clean expired blacklist entries
            if ($name === 'blacklist') {
                $now = time();
                $list = array_filter($list, fn($expiry) => $expiry === 0 || $expiry > $now);
            }

            return $list;
        }

        return [];
    }

    private function saveList(string $name, array $list): void {
        $file = $this->storagePath . "/$name.json";
        file_put_contents($file, json_encode($list), LOCK_EX);
    }

    /**
     * Cleanup old records
     */
    private function cleanup(): void {
        $files = glob($this->storagePath . '/*.json');
        $now = time();

        foreach ($files as $file) {
            $mtime = filemtime($file);
            if (($now - $mtime) > 86400) { // 24 hours old
                unlink($file);
            }
        }
    }

    /**
     * Response headers
     */
    public function getHeaders(array $result): array {
        $headers = [
            'X-RateLimit-Limit' => $result['limit'],
            'X-RateLimit-Remaining' => $result['remaining'],
        ];

        if ($result['reset']) {
            $headers['X-RateLimit-Reset'] = $result['reset'];
        }

        if ($result['retry_after']) {
            $headers['Retry-After'] = $result['retry_after'];
        }

        return $headers;
    }

    /**
     * Statistics
     */
    public function getStats(): array {
        $files = glob($this->storagePath . '/*.json');

        $totalRequests = 0;
        $activeKeys = 0;

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $data = json_decode($content, true);

            if (is_array($data) && isset($data[0]) && is_numeric($data[0])) {
                $totalRequests += count($data);
                $activeKeys++;
            }
        }

        return [
            'active_keys' => $activeKeys,
            'total_requests' => $totalRequests,
            'storage_files' => count($files),
            'whitelist_size' => count($this->loadList('whitelist')),
            'blacklist_size' => count($this->loadList('blacklist'))
        ];
    }
}