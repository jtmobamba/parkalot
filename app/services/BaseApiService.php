<?php
/**
 * Base API Service
 *
 * Abstract base class for external API integrations.
 * Provides common functionality for HTTP requests, caching, and error handling.
 */

abstract class BaseApiService
{
    protected string $baseUrl;
    protected ?string $apiKey;
    protected int $timeout;
    protected $db;
    protected int $cacheExpiry = 3600; // 1 hour default
    protected string $apiSource;
    protected bool $useMockData = true;
    protected bool $debugMode = false;

    /**
     * Constructor
     *
     * @param PDO|null $db Database connection
     * @param string|null $apiKey API key
     * @param int $timeout Request timeout in seconds
     */
    public function __construct(?PDO $db = null, ?string $apiKey = null, int $timeout = 10)
    {
        $this->db = $db;
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;

        // Check if mock data should be used
        if (defined('USE_MOCK_DATA')) {
            $this->useMockData = USE_MOCK_DATA || empty($this->apiKey);
        }

        if (defined('API_DEBUG_MODE')) {
            $this->debugMode = API_DEBUG_MODE;
        }
    }

    /**
     * Make HTTP request to API
     *
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @param string $method HTTP method
     * @param array $body Request body for POST/PUT
     * @return array|null Response data or null on failure
     */
    protected function makeRequest(
        string $endpoint,
        array $params = [],
        string $method = 'GET',
        array $body = []
    ): ?array {
        $url = $this->baseUrl . $endpoint;

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $headers = $this->getHeaders();

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => !empty($body) ? json_encode($body) : null,
                'timeout' => $this->timeout,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ]);

        try {
            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                $this->logError("API request failed: {$url}");
                return null;
            }

            // Get HTTP response code
            $httpCode = $this->getHttpCode($http_response_header ?? []);

            if ($httpCode >= 400) {
                $this->logError("API returned error code {$httpCode}: {$url}");
                return null;
            }

            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logError("JSON decode error: " . json_last_error_msg());
                return null;
            }

            return $data;
        } catch (Exception $e) {
            $this->logError("API exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Make HTTP request using cURL (alternative method)
     *
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @param string $method HTTP method
     * @param array $body Request body
     * @return array|null Response data
     */
    protected function makeRequestCurl(
        string $endpoint,
        array $params = [],
        string $method = 'GET',
        array $body = []
    ): ?array {
        if (!function_exists('curl_init')) {
            return $this->makeRequest($endpoint, $params, $method, $body);
        }

        $url = $this->baseUrl . $endpoint;

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $this->getHeaders(),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'ParkaLot-System/1.0'
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if (!empty($body)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logError("cURL error: {$error}");
            return null;
        }

        if ($httpCode >= 400) {
            $this->logError("API returned error code {$httpCode}: {$url}");
            return null;
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logError("JSON decode error: " . json_last_error_msg());
            return null;
        }

        return $data;
    }

    /**
     * Get cached data
     *
     * @param string $key Cache key
     * @return array|null Cached data or null if not found/expired
     */
    protected function cacheGet(string $key): ?array
    {
        if (!$this->db) {
            return null;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT data, hit_count
                FROM api_cache
                WHERE cache_key = :key
                  AND api_source = :source
                  AND expires_at > NOW()
            ");
            $stmt->execute([
                ':key' => $key,
                ':source' => $this->apiSource
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                // Update hit count and last accessed
                $updateStmt = $this->db->prepare("
                    UPDATE api_cache
                    SET hit_count = hit_count + 1, last_accessed = NOW()
                    WHERE cache_key = :key AND api_source = :source
                ");
                $updateStmt->execute([':key' => $key, ':source' => $this->apiSource]);

                return json_decode($row['data'], true);
            }
        } catch (PDOException $e) {
            $this->logError("Cache get error: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Set cached data
     *
     * @param string $key Cache key
     * @param array $data Data to cache
     * @param int|null $expiry Custom expiry time in seconds
     * @return bool Success status
     */
    protected function cacheSet(string $key, array $data, ?int $expiry = null): bool
    {
        if (!$this->db) {
            return false;
        }

        $expiry = $expiry ?? $this->cacheExpiry;
        $jsonData = json_encode($data);
        $dataHash = hash('sha256', $jsonData);

        try {
            $stmt = $this->db->prepare("
                INSERT INTO api_cache (cache_key, api_source, data, data_hash, expires_at, hit_count)
                VALUES (:key, :source, :data, :hash, DATE_ADD(NOW(), INTERVAL :expiry SECOND), 0)
                ON DUPLICATE KEY UPDATE
                    data = VALUES(data),
                    data_hash = VALUES(data_hash),
                    expires_at = VALUES(expires_at),
                    cached_at = NOW()
            ");

            return $stmt->execute([
                ':key' => $key,
                ':source' => $this->apiSource,
                ':data' => $jsonData,
                ':hash' => $dataHash,
                ':expiry' => $expiry
            ]);
        } catch (PDOException $e) {
            $this->logError("Cache set error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete cached data
     *
     * @param string $key Cache key
     * @return bool Success status
     */
    protected function cacheDelete(string $key): bool
    {
        if (!$this->db) {
            return false;
        }

        try {
            $stmt = $this->db->prepare("
                DELETE FROM api_cache
                WHERE cache_key = :key AND api_source = :source
            ");
            return $stmt->execute([':key' => $key, ':source' => $this->apiSource]);
        } catch (PDOException $e) {
            $this->logError("Cache delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear all cached data for this API source
     *
     * @return bool Success status
     */
    protected function cacheClearAll(): bool
    {
        if (!$this->db) {
            return false;
        }

        try {
            $stmt = $this->db->prepare("
                DELETE FROM api_cache WHERE api_source = :source
            ");
            return $stmt->execute([':source' => $this->apiSource]);
        } catch (PDOException $e) {
            $this->logError("Cache clear error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get HTTP response code from headers
     *
     * @param array $headers Response headers
     * @return int HTTP status code
     */
    protected function getHttpCode(array $headers): int
    {
        if (empty($headers)) {
            return 0;
        }

        if (preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $headers[0], $matches)) {
            return (int)$matches[1];
        }

        return 0;
    }

    /**
     * Log error message
     *
     * @param string $message Error message
     */
    protected function logError(string $message): void
    {
        $logMessage = "[{$this->apiSource}] {$message}";

        if ($this->debugMode) {
            error_log($logMessage);
        }

        // Optionally log to database activity_logs
        if ($this->db) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO activity_logs (role, action, description, ip_address)
                    VALUES ('system', 'api_error', :message, :ip)
                ");
                $stmt->execute([
                    ':message' => substr($logMessage, 0, 500),
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI'
                ]);
            } catch (PDOException $e) {
                error_log("Failed to log API error: " . $e->getMessage());
            }
        }
    }

    /**
     * Get HTTP headers for API requests
     *
     * @return array Headers array
     */
    abstract protected function getHeaders(): array;

    /**
     * Format response data into consistent structure
     *
     * @param array $data Raw API response
     * @return array Formatted data
     */
    abstract protected function formatResponse(array $data): array;

    /**
     * Get fallback/mock data when API is unavailable
     *
     * @return array Fallback data
     */
    abstract protected function getFallbackData(): array;

    /**
     * Check if API is available
     *
     * @return bool True if API is configured and accessible
     */
    public function isAvailable(): bool
    {
        return !empty($this->apiKey) && !$this->useMockData;
    }

    /**
     * Check if using mock data
     *
     * @return bool True if using mock data
     */
    public function isUsingMockData(): bool
    {
        return $this->useMockData;
    }
}
