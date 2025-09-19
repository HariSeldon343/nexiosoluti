<?php
declare(strict_types=1);

/**
 * Full-Text Search Service
 * Indicizzazione e ricerca contenuti file
 */

class SearchService {
    private string $indexPath;
    private array $index = [];
    private array $metadata = [];
    private string $indexFile;
    private string $metadataFile;
    private array $extractors = [];
    private int $maxIndexSize = 10485760; // 10MB per file
    private array $stopWords = [];

    public function __construct() {
        $this->indexPath = APP_ROOT . '/search_index';
        $this->indexFile = $this->indexPath . '/index.json';
        $this->metadataFile = $this->indexPath . '/metadata.json';

        if (!is_dir($this->indexPath)) {
            mkdir($this->indexPath, 0777, true);
        }

        $this->loadIndex();
        $this->loadMetadata();
        $this->initializeExtractors();
        $this->initializeStopWords();
    }

    /**
     * Indicizza un file
     */
    public function indexFile(string $filePath, array $fileInfo): bool {
        try {
            // Extract content
            $content = $this->extractContent($filePath, $fileInfo['mime_type'] ?? '');

            if (empty($content)) {
                return false;
            }

            // Tokenize and process
            $tokens = $this->tokenize($content);
            $tokens = $this->removeStopWords($tokens);
            $tokens = $this->stem($tokens);

            // Calculate term frequencies
            $termFrequencies = $this->calculateTermFrequencies($tokens);

            // Store in index
            $documentId = $fileInfo['id'] ?? md5($filePath);
            $this->index[$documentId] = [
                'terms' => $termFrequencies,
                'length' => count($tokens),
                'indexed_at' => time()
            ];

            // Store metadata
            $this->metadata[$documentId] = [
                'path' => $filePath,
                'name' => $fileInfo['name'] ?? basename($filePath),
                'size' => $fileInfo['size'] ?? 0,
                'mime_type' => $fileInfo['mime_type'] ?? '',
                'tenant_id' => $fileInfo['tenant_id'] ?? 0,
                'folder_id' => $fileInfo['folder_id'] ?? null,
                'owner_id' => $fileInfo['owner_id'] ?? 0,
                'created_at' => $fileInfo['created_at'] ?? time(),
                'updated_at' => $fileInfo['updated_at'] ?? time(),
                'checksum' => md5_file($filePath)
            ];

            $this->saveIndex();
            $this->saveMetadata();

            // Log indexing
            $this->logIndexing($documentId, 'success');

            return true;

        } catch (Exception $e) {
            $this->logIndexing($filePath, 'error', $e->getMessage());
            return false;
        }
    }

    /**
     * Rimuove file dall'indice
     */
    public function removeFromIndex(string $documentId): bool {
        if (isset($this->index[$documentId])) {
            unset($this->index[$documentId]);
            unset($this->metadata[$documentId]);
            $this->saveIndex();
            $this->saveMetadata();
            return true;
        }
        return false;
    }

    /**
     * Cerca nei documenti indicizzati
     */
    public function search(string $query, array $filters = [], int $limit = 50, int $offset = 0): array {
        // Process query
        $queryTokens = $this->tokenize($query);
        $queryTokens = $this->removeStopWords($queryTokens);
        $queryTokens = $this->stem($queryTokens);

        if (empty($queryTokens)) {
            return ['results' => [], 'total' => 0, 'query' => $query];
        }

        // Calculate scores for each document
        $scores = [];
        foreach ($this->index as $docId => $docIndex) {
            // Apply filters
            if (!$this->applyFilters($docId, $filters)) {
                continue;
            }

            // Calculate BM25 score
            $score = $this->calculateBM25Score($queryTokens, $docIndex, $docId);

            if ($score > 0) {
                $scores[$docId] = $score;
            }
        }

        // Sort by score
        arsort($scores);

        // Get total count
        $total = count($scores);

        // Apply pagination
        $results = array_slice($scores, $offset, $limit, true);

        // Build result set
        $searchResults = [];
        foreach ($results as $docId => $score) {
            $metadata = $this->metadata[$docId] ?? [];
            $highlight = $this->generateHighlight($docId, $queryTokens);

            $searchResults[] = array_merge($metadata, [
                'id' => $docId,
                'score' => $score,
                'highlight' => $highlight
            ]);
        }

        return [
            'results' => $searchResults,
            'total' => $total,
            'query' => $query,
            'took' => 0, // Placeholder for timing
            'limit' => $limit,
            'offset' => $offset
        ];
    }

    /**
     * Cerca con operatori booleani
     */
    public function advancedSearch(string $query, array $filters = []): array {
        // Parse query with AND, OR, NOT operators
        $parsed = $this->parseAdvancedQuery($query);

        $results = [];

        // Process MUST terms (AND)
        if (!empty($parsed['must'])) {
            $mustResults = null;
            foreach ($parsed['must'] as $term) {
                $termResults = $this->searchTerm($term);
                if ($mustResults === null) {
                    $mustResults = $termResults;
                } else {
                    $mustResults = array_intersect_key($mustResults, $termResults);
                }
            }
            $results = $mustResults ?: [];
        }

        // Process SHOULD terms (OR)
        if (!empty($parsed['should'])) {
            $shouldResults = [];
            foreach ($parsed['should'] as $term) {
                $termResults = $this->searchTerm($term);
                $shouldResults = array_merge($shouldResults, $termResults);
            }

            if (empty($results)) {
                $results = $shouldResults;
            } else {
                $results = array_merge($results, $shouldResults);
            }
        }

        // Process MUST_NOT terms (NOT)
        if (!empty($parsed['must_not'])) {
            foreach ($parsed['must_not'] as $term) {
                $excludeResults = $this->searchTerm($term);
                $results = array_diff_key($results, $excludeResults);
            }
        }

        // Apply filters
        $filteredResults = [];
        foreach ($results as $docId => $score) {
            if ($this->applyFilters($docId, $filters)) {
                $filteredResults[$docId] = $score;
            }
        }

        // Sort and format
        arsort($filteredResults);

        $searchResults = [];
        foreach ($filteredResults as $docId => $score) {
            $metadata = $this->metadata[$docId] ?? [];
            $searchResults[] = array_merge($metadata, [
                'id' => $docId,
                'score' => $score
            ]);
        }

        return [
            'results' => $searchResults,
            'total' => count($searchResults),
            'query' => $query
        ];
    }

    /**
     * Fuzzy search (con tolleranza errori)
     */
    public function fuzzySearch(string $query, int $maxDistance = 2): array {
        $queryTokens = $this->tokenize($query);
        $results = [];

        foreach ($this->index as $docId => $docIndex) {
            $score = 0;
            foreach ($queryTokens as $queryToken) {
                foreach (array_keys($docIndex['terms']) as $term) {
                    $distance = levenshtein($queryToken, $term);
                    if ($distance <= $maxDistance) {
                        $score += (1 - $distance / $maxDistance) * $docIndex['terms'][$term];
                    }
                }
            }

            if ($score > 0) {
                $results[$docId] = $score;
            }
        }

        arsort($results);

        $searchResults = [];
        foreach ($results as $docId => $score) {
            $metadata = $this->metadata[$docId] ?? [];
            $searchResults[] = array_merge($metadata, [
                'id' => $docId,
                'score' => $score
            ]);
        }

        return [
            'results' => $searchResults,
            'total' => count($searchResults),
            'query' => $query
        ];
    }

    /**
     * Suggerimenti di ricerca (autocomplete)
     */
    public function suggest(string $prefix, int $limit = 10): array {
        $prefix = strtolower($prefix);
        $suggestions = [];

        // Collect all terms
        $allTerms = [];
        foreach ($this->index as $docIndex) {
            foreach (array_keys($docIndex['terms']) as $term) {
                if (!isset($allTerms[$term])) {
                    $allTerms[$term] = 0;
                }
                $allTerms[$term]++;
            }
        }

        // Filter by prefix
        foreach ($allTerms as $term => $frequency) {
            if (strpos($term, $prefix) === 0) {
                $suggestions[$term] = $frequency;
            }
        }

        // Sort by frequency
        arsort($suggestions);

        // Limit results
        $suggestions = array_slice($suggestions, 0, $limit, true);

        return array_keys($suggestions);
    }

    /**
     * Content extraction
     */
    private function extractContent(string $filePath, string $mimeType): string {
        if (!file_exists($filePath)) {
            return '';
        }

        $fileSize = filesize($filePath);
        if ($fileSize > $this->maxIndexSize) {
            return ''; // Skip large files
        }

        // Get appropriate extractor
        $extractor = $this->getExtractor($mimeType);

        if ($extractor) {
            return call_user_func($extractor, $filePath);
        }

        // Default text extraction
        if (strpos($mimeType, 'text/') === 0) {
            return file_get_contents($filePath);
        }

        return '';
    }

    private function getExtractor(string $mimeType): ?callable {
        return $this->extractors[$mimeType] ?? null;
    }

    private function initializeExtractors(): void {
        // Text files
        $this->extractors['text/plain'] = function($path) {
            return file_get_contents($path);
        };

        // PDF extraction (requires external tool or library)
        $this->extractors['application/pdf'] = function($path) {
            // Simple implementation using pdftotext if available
            if (PHP_OS_FAMILY === 'Windows') {
                return ''; // Not implemented for Windows
            }

            $output = [];
            $return = 0;
            exec("pdftotext -layout '$path' -", $output, $return);

            if ($return === 0) {
                return implode("\n", $output);
            }

            return '';
        };

        // Office documents (simplified extraction)
        $this->extractors['application/vnd.openxmlformats-officedocument.wordprocessingml.document'] = function($path) {
            return $this->extractDocx($path);
        };

        $this->extractors['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'] = function($path) {
            return $this->extractXlsx($path);
        };

        // HTML extraction
        $this->extractors['text/html'] = function($path) {
            $content = file_get_contents($path);
            return strip_tags($content);
        };

        // XML extraction
        $this->extractors['application/xml'] = function($path) {
            $content = file_get_contents($path);
            return strip_tags($content);
        };

        // JSON extraction
        $this->extractors['application/json'] = function($path) {
            $content = file_get_contents($path);
            $json = json_decode($content, true);
            return $this->flattenArray($json);
        };
    }

    private function extractDocx(string $path): string {
        $zip = new ZipArchive();
        if ($zip->open($path) !== TRUE) {
            return '';
        }

        $content = '';
        if (($index = $zip->locateName('word/document.xml')) !== false) {
            $xml = $zip->getFromIndex($index);
            $content = strip_tags($xml);
        }

        $zip->close();
        return $content;
    }

    private function extractXlsx(string $path): string {
        $zip = new ZipArchive();
        if ($zip->open($path) !== TRUE) {
            return '';
        }

        $content = '';
        // Get shared strings
        $sharedStrings = [];
        if (($index = $zip->locateName('xl/sharedStrings.xml')) !== false) {
            $xml = $zip->getFromIndex($index);
            if ($xml) {
                $dom = new DOMDocument();
                @$dom->loadXML($xml);
                $nodes = $dom->getElementsByTagName('t');
                foreach ($nodes as $node) {
                    $sharedStrings[] = $node->nodeValue;
                }
            }
        }

        $content = implode(' ', $sharedStrings);
        $zip->close();
        return $content;
    }

    private function flattenArray(mixed $data): string {
        $result = '';

        if (is_array($data)) {
            foreach ($data as $value) {
                $result .= ' ' . $this->flattenArray($value);
            }
        } else {
            $result = (string)$data;
        }

        return $result;
    }

    /**
     * Text processing
     */
    private function tokenize(string $text): array {
        // Convert to lowercase
        $text = strtolower($text);

        // Remove special characters, keep alphanumeric and spaces
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);

        // Split into tokens
        $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Remove short tokens
        $tokens = array_filter($tokens, fn($token) => strlen($token) > 2);

        return array_values($tokens);
    }

    private function removeStopWords(array $tokens): array {
        return array_diff($tokens, $this->stopWords);
    }

    private function stem(array $tokens): array {
        // Simple stemming (Porter stemmer implementation would be better)
        return array_map(function($token) {
            // Remove common suffixes
            $token = preg_replace('/(ing|ed|es|s|ly|tion|ment|ness|ful|less)$/', '', $token);
            return $token;
        }, $tokens);
    }

    private function calculateTermFrequencies(array $tokens): array {
        $frequencies = array_count_values($tokens);

        // Normalize frequencies
        $totalTokens = count($tokens);
        foreach ($frequencies as &$freq) {
            $freq = $freq / $totalTokens;
        }

        return $frequencies;
    }

    /**
     * Scoring algorithms
     */
    private function calculateBM25Score(array $queryTokens, array $docIndex, string $docId): float {
        $k1 = 1.2; // Term frequency saturation parameter
        $b = 0.75; // Length normalization parameter

        $score = 0;
        $avgDocLength = $this->getAverageDocumentLength();
        $docLength = $docIndex['length'];
        $totalDocs = count($this->index);

        foreach ($queryTokens as $term) {
            if (!isset($docIndex['terms'][$term])) {
                continue;
            }

            $tf = $docIndex['terms'][$term];
            $df = $this->getDocumentFrequency($term);
            $idf = log(($totalDocs - $df + 0.5) / ($df + 0.5));

            $numerator = $tf * ($k1 + 1);
            $denominator = $tf + $k1 * (1 - $b + $b * ($docLength / $avgDocLength));

            $score += $idf * ($numerator / $denominator);
        }

        return $score;
    }

    private function getDocumentFrequency(string $term): int {
        $count = 0;
        foreach ($this->index as $docIndex) {
            if (isset($docIndex['terms'][$term])) {
                $count++;
            }
        }
        return $count;
    }

    private function getAverageDocumentLength(): float {
        if (empty($this->index)) {
            return 0;
        }

        $totalLength = 0;
        foreach ($this->index as $docIndex) {
            $totalLength += $docIndex['length'];
        }

        return $totalLength / count($this->index);
    }

    private function searchTerm(string $term): array {
        $term = strtolower($term);
        $term = $this->stem([$term])[0] ?? $term;

        $results = [];
        foreach ($this->index as $docId => $docIndex) {
            if (isset($docIndex['terms'][$term])) {
                $results[$docId] = $docIndex['terms'][$term];
            }
        }

        return $results;
    }

    /**
     * Query parsing
     */
    private function parseAdvancedQuery(string $query): array {
        $parsed = [
            'must' => [],
            'should' => [],
            'must_not' => []
        ];

        // Simple implementation of boolean operators
        $parts = preg_split('/\s+(AND|OR|NOT)\s+/i', $query, -1, PREG_SPLIT_DELIM_CAPTURE);

        $currentOperator = 'must';
        for ($i = 0; $i < count($parts); $i++) {
            if (strcasecmp($parts[$i], 'AND') === 0) {
                $currentOperator = 'must';
            } elseif (strcasecmp($parts[$i], 'OR') === 0) {
                $currentOperator = 'should';
            } elseif (strcasecmp($parts[$i], 'NOT') === 0) {
                $currentOperator = 'must_not';
            } else {
                $parsed[$currentOperator][] = $parts[$i];
            }
        }

        return $parsed;
    }

    /**
     * Filters
     */
    private function applyFilters(string $docId, array $filters): bool {
        if (empty($filters)) {
            return true;
        }

        $metadata = $this->metadata[$docId] ?? [];

        // Tenant filter
        if (isset($filters['tenant_id']) && $metadata['tenant_id'] != $filters['tenant_id']) {
            return false;
        }

        // Folder filter
        if (isset($filters['folder_id']) && $metadata['folder_id'] != $filters['folder_id']) {
            return false;
        }

        // Date range filter
        if (isset($filters['date_from']) || isset($filters['date_to'])) {
            $docDate = $metadata['created_at'] ?? 0;

            if (isset($filters['date_from']) && $docDate < strtotime($filters['date_from'])) {
                return false;
            }

            if (isset($filters['date_to']) && $docDate > strtotime($filters['date_to'])) {
                return false;
            }
        }

        // File type filter
        if (isset($filters['mime_type'])) {
            $mimeTypes = is_array($filters['mime_type']) ? $filters['mime_type'] : [$filters['mime_type']];
            if (!in_array($metadata['mime_type'], $mimeTypes)) {
                return false;
            }
        }

        // Size filter
        if (isset($filters['min_size']) && $metadata['size'] < $filters['min_size']) {
            return false;
        }

        if (isset($filters['max_size']) && $metadata['size'] > $filters['max_size']) {
            return false;
        }

        return true;
    }

    /**
     * Highlighting
     */
    private function generateHighlight(string $docId, array $queryTokens, int $fragmentSize = 150): string {
        $metadata = $this->metadata[$docId] ?? [];
        $filePath = $metadata['path'] ?? '';

        if (!file_exists($filePath)) {
            return '';
        }

        // Get file content
        $content = $this->extractContent($filePath, $metadata['mime_type'] ?? '');
        if (empty($content)) {
            return '';
        }

        // Find best fragment
        $content = substr($content, 0, 5000); // Limit for performance
        $tokens = $this->tokenize($content);

        $bestStart = 0;
        $bestScore = 0;

        for ($i = 0; $i < count($tokens) - 10; $i++) {
            $score = 0;
            for ($j = $i; $j < min($i + 10, count($tokens)); $j++) {
                if (in_array($tokens[$j], $queryTokens)) {
                    $score++;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestStart = $i;
            }
        }

        // Build fragment
        $fragment = implode(' ', array_slice($tokens, $bestStart, 30));

        // Highlight query terms
        foreach ($queryTokens as $term) {
            $fragment = preg_replace(
                '/\b(' . preg_quote($term, '/') . ')\b/i',
                '<mark>$1</mark>',
                $fragment
            );
        }

        return '...' . $fragment . '...';
    }

    /**
     * Stop words
     */
    private function initializeStopWords(): void {
        $this->stopWords = [
            'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from',
            'has', 'he', 'in', 'is', 'it', 'its', 'of', 'on', 'that', 'the',
            'to', 'was', 'will', 'with', 'the', 'this', 'these', 'those',
            'i', 'you', 'we', 'they', 'them', 'their', 'what', 'which', 'who',
            'when', 'where', 'why', 'how', 'all', 'would', 'there', 'been'
        ];
    }

    /**
     * Index management
     */
    public function rebuildIndex(int $tenantId = null): int {
        $this->index = [];
        $this->metadata = [];

        $where = $tenantId ? "WHERE tenant_id = :tenant_id" : "";
        $params = $tenantId ? ['tenant_id' => $tenantId] : [];

        $files = Database::select(
            "SELECT * FROM files $where",
            $params
        );

        $indexed = 0;
        foreach ($files as $file) {
            if ($this->indexFile($file['path'], $file)) {
                $indexed++;
            }
        }

        $this->saveIndex();
        $this->saveMetadata();

        return $indexed;
    }

    public function optimizeIndex(): void {
        // Remove deleted documents
        foreach ($this->metadata as $docId => $meta) {
            if (!file_exists($meta['path'])) {
                unset($this->index[$docId]);
                unset($this->metadata[$docId]);
            }
        }

        // Rebuild term frequencies
        // Compact index structure

        $this->saveIndex();
        $this->saveMetadata();
    }

    private function loadIndex(): void {
        if (file_exists($this->indexFile)) {
            $content = file_get_contents($this->indexFile);
            $this->index = json_decode($content, true) ?: [];
        }
    }

    private function saveIndex(): void {
        file_put_contents(
            $this->indexFile,
            json_encode($this->index),
            LOCK_EX
        );
    }

    private function loadMetadata(): void {
        if (file_exists($this->metadataFile)) {
            $content = file_get_contents($this->metadataFile);
            $this->metadata = json_decode($content, true) ?: [];
        }
    }

    private function saveMetadata(): void {
        file_put_contents(
            $this->metadataFile,
            json_encode($this->metadata),
            LOCK_EX
        );
    }

    private function logIndexing(string $identifier, string $status, string $message = ''): void {
        $logFile = LOG_PATH . '/search_' . date('Y-m-d') . '.log';
        $logEntry = date('Y-m-d H:i:s') . " [$status] $identifier $message" . PHP_EOL;

        if (!is_dir(LOG_PATH)) {
            mkdir(LOG_PATH, 0777, true);
        }

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Statistics
     */
    public function getStats(): array {
        return [
            'total_documents' => count($this->index),
            'total_terms' => $this->getTotalTerms(),
            'index_size' => filesize($this->indexFile),
            'metadata_size' => filesize($this->metadataFile),
            'average_doc_length' => $this->getAverageDocumentLength()
        ];
    }

    private function getTotalTerms(): int {
        $terms = [];
        foreach ($this->index as $docIndex) {
            foreach (array_keys($docIndex['terms']) as $term) {
                $terms[$term] = true;
            }
        }
        return count($terms);
    }
}