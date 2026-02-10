<?php
/**
 * Azure Blob Storage Service
 *
 * Provides methods for interacting with Azure Blob Storage:
 * - Upload files to blob storage
 * - Delete blobs
 * - Generate public/SAS URLs
 * - List blobs in a container
 *
 * Uses Azure REST API directly (no SDK dependency).
 */

require_once __DIR__ . '/../../config/azure.php';

class AzureBlobService
{
    private string $accountName;
    private string $accountKey;
    private string $containerName;
    private bool $enabled;

    /**
     * Constructor
     *
     * @param string|null $accountName Azure Storage account name (uses config if null)
     * @param string|null $accountKey Azure Storage account key (uses config if null)
     * @param string|null $containerName Container name (uses config if null)
     */
    public function __construct(
        ?string $accountName = null,
        ?string $accountKey = null,
        ?string $containerName = null
    ) {
        $this->accountName = $accountName ?? AZURE_STORAGE_ACCOUNT_NAME;
        $this->accountKey = $accountKey ?? AZURE_STORAGE_ACCOUNT_KEY;
        $this->containerName = $containerName ?? AZURE_STORAGE_CONTAINER_NAME;
        $this->enabled = isAzureStorageConfigured();
    }

    /**
     * Check if Azure Blob Storage is enabled and configured
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Upload a file to Azure Blob Storage
     *
     * @param string $localPath Local file path to upload
     * @param string $blobName Blob name (path in container)
     * @param string|null $container Container subfolder (e.g., 'cv-files', 'space-photos')
     * @param string|null $contentType MIME type (auto-detected if null)
     * @return array Result with 'success', 'url', or 'error'
     */
    public function uploadFile(
        string $localPath,
        string $blobName,
        ?string $container = null,
        ?string $contentType = null
    ): array {
        if (!$this->enabled) {
            return ['success' => false, 'error' => 'Azure Blob Storage is not configured'];
        }

        if (!file_exists($localPath)) {
            return ['success' => false, 'error' => 'Local file not found: ' . $localPath];
        }

        // Build the full blob path
        $fullBlobName = $container ? $container . '/' . $blobName : $blobName;

        // Detect content type if not provided
        if (!$contentType) {
            $contentType = $this->detectContentType($localPath);
        }

        // Read file content
        $fileContent = file_get_contents($localPath);
        if ($fileContent === false) {
            return ['success' => false, 'error' => 'Failed to read local file'];
        }

        // Upload to Azure
        $result = $this->putBlob($fullBlobName, $fileContent, $contentType);

        if ($result['success']) {
            return [
                'success' => true,
                'url' => $this->getFileUrl($fullBlobName),
                'blob_name' => $fullBlobName
            ];
        }

        return $result;
    }

    /**
     * Upload file content directly (for stream uploads)
     *
     * @param string $content File content
     * @param string $blobName Blob name
     * @param string $contentType MIME type
     * @param string|null $container Container subfolder
     * @return array Result
     */
    public function uploadContent(
        string $content,
        string $blobName,
        string $contentType,
        ?string $container = null
    ): array {
        if (!$this->enabled) {
            return ['success' => false, 'error' => 'Azure Blob Storage is not configured'];
        }

        $fullBlobName = $container ? $container . '/' . $blobName : $blobName;

        $result = $this->putBlob($fullBlobName, $content, $contentType);

        if ($result['success']) {
            return [
                'success' => true,
                'url' => $this->getFileUrl($fullBlobName),
                'blob_name' => $fullBlobName
            ];
        }

        return $result;
    }

    /**
     * Delete a blob from Azure Storage
     *
     * @param string $blobName Blob name (full path including container subfolder)
     * @return array Result with 'success' or 'error'
     */
    public function deleteFile(string $blobName): array
    {
        if (!$this->enabled) {
            return ['success' => false, 'error' => 'Azure Blob Storage is not configured'];
        }

        $url = $this->getBlobUrl($blobName);
        $date = gmdate('D, d M Y H:i:s T');

        $headers = [
            'x-ms-date' => $date,
            'x-ms-version' => '2020-10-02',
        ];

        $authHeader = $this->generateAuthorizationHeader('DELETE', $blobName, $headers);
        $headers['Authorization'] = $authHeader;

        $result = $this->makeRequest('DELETE', $url, $headers);

        if ($result['http_code'] === 202 || $result['http_code'] === 404) {
            // 202 = deleted, 404 = already doesn't exist
            return ['success' => true];
        }

        return [
            'success' => false,
            'error' => 'Failed to delete blob: HTTP ' . $result['http_code']
        ];
    }

    /**
     * Get the public URL for a blob
     *
     * @param string $blobName Blob name
     * @return string Public URL
     */
    public function getFileUrl(string $blobName): string
    {
        return $this->getBlobUrl($blobName);
    }

    /**
     * Generate a Shared Access Signature (SAS) URL for temporary access
     *
     * @param string $blobName Blob name
     * @param int $expirySeconds Seconds until expiry (default from config)
     * @param string $permissions Permissions string (r=read, w=write, d=delete)
     * @return string SAS URL
     */
    public function generateSasUrl(
        string $blobName,
        int $expirySeconds = 0,
        string $permissions = 'r'
    ): string {
        if ($expirySeconds <= 0) {
            $expirySeconds = AZURE_SAS_TOKEN_EXPIRY;
        }

        $start = gmdate('Y-m-d\TH:i:s\Z', time() - 300); // 5 min before now
        $expiry = gmdate('Y-m-d\TH:i:s\Z', time() + $expirySeconds);

        $canonicalizedResource = "/blob/{$this->accountName}/{$this->containerName}/{$blobName}";

        // Build string to sign for SAS
        $stringToSign = implode("\n", [
            $permissions,           // Signed permissions
            $start,                 // Signed start
            $expiry,                // Signed expiry
            $canonicalizedResource, // Canonicalized resource
            '',                     // Signed identifier
            '',                     // Signed IP
            'https',                // Signed protocol
            '2020-10-02',           // Signed version
            'b',                    // Signed resource (blob)
            '',                     // Signed snapshot time
            '',                     // Signed encryption scope
            '',                     // Rscc (Cache-Control)
            '',                     // Rscd (Content-Disposition)
            '',                     // Rsce (Content-Encoding)
            '',                     // Rscl (Content-Language)
            '',                     // Rsct (Content-Type)
        ]);

        $signature = base64_encode(
            hash_hmac('sha256', $stringToSign, base64_decode($this->accountKey), true)
        );

        $sasParams = http_build_query([
            'sv' => '2020-10-02',
            'ss' => 'b',
            'srt' => 'o',
            'sp' => $permissions,
            'st' => $start,
            'se' => $expiry,
            'spr' => 'https',
            'sig' => $signature,
        ]);

        return $this->getBlobUrl($blobName) . '?' . $sasParams;
    }

    /**
     * List blobs in a container with optional prefix
     *
     * @param string|null $prefix Prefix to filter blobs
     * @param int $maxResults Maximum results to return
     * @return array Result with 'success', 'blobs', or 'error'
     */
    public function listFiles(?string $prefix = null, int $maxResults = 100): array
    {
        if (!$this->enabled) {
            return ['success' => false, 'error' => 'Azure Blob Storage is not configured'];
        }

        $url = "https://{$this->accountName}.blob.core.windows.net/{$this->containerName}";
        $url .= "?restype=container&comp=list&maxresults={$maxResults}";

        if ($prefix) {
            $url .= "&prefix=" . urlencode($prefix);
        }

        $date = gmdate('D, d M Y H:i:s T');

        $headers = [
            'x-ms-date' => $date,
            'x-ms-version' => '2020-10-02',
        ];

        // For list operations, we need to sign differently
        $authHeader = $this->generateListAuthorizationHeader($headers, $prefix, $maxResults);
        $headers['Authorization'] = $authHeader;

        $result = $this->makeRequest('GET', $url, $headers);

        if ($result['http_code'] === 200) {
            $blobs = $this->parseListBlobsResponse($result['body']);
            return [
                'success' => true,
                'blobs' => $blobs
            ];
        }

        return [
            'success' => false,
            'error' => 'Failed to list blobs: HTTP ' . $result['http_code']
        ];
    }

    /**
     * Check if a blob exists
     *
     * @param string $blobName Blob name
     * @return bool True if blob exists
     */
    public function exists(string $blobName): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $url = $this->getBlobUrl($blobName);
        $date = gmdate('D, d M Y H:i:s T');

        $headers = [
            'x-ms-date' => $date,
            'x-ms-version' => '2020-10-02',
        ];

        $authHeader = $this->generateAuthorizationHeader('HEAD', $blobName, $headers);
        $headers['Authorization'] = $authHeader;

        $result = $this->makeRequest('HEAD', $url, $headers);

        return $result['http_code'] === 200;
    }

    /**
     * Copy a blob within the same storage account
     *
     * @param string $sourceBlobName Source blob name
     * @param string $destBlobName Destination blob name
     * @return array Result
     */
    public function copyFile(string $sourceBlobName, string $destBlobName): array
    {
        if (!$this->enabled) {
            return ['success' => false, 'error' => 'Azure Blob Storage is not configured'];
        }

        $sourceUrl = $this->getBlobUrl($sourceBlobName);
        $destUrl = $this->getBlobUrl($destBlobName);
        $date = gmdate('D, d M Y H:i:s T');

        $headers = [
            'x-ms-date' => $date,
            'x-ms-version' => '2020-10-02',
            'x-ms-copy-source' => $sourceUrl,
        ];

        $authHeader = $this->generateAuthorizationHeader('PUT', $destBlobName, $headers);
        $headers['Authorization'] = $authHeader;

        $result = $this->makeRequest('PUT', $destUrl, $headers);

        if ($result['http_code'] === 202) {
            return [
                'success' => true,
                'url' => $destUrl
            ];
        }

        return [
            'success' => false,
            'error' => 'Failed to copy blob: HTTP ' . $result['http_code']
        ];
    }

    // ==================== Private Methods ====================

    /**
     * Put a blob to Azure Storage
     *
     * @param string $blobName Blob name
     * @param string $content File content
     * @param string $contentType MIME type
     * @return array Result
     */
    private function putBlob(string $blobName, string $content, string $contentType): array
    {
        $url = $this->getBlobUrl($blobName);
        $date = gmdate('D, d M Y H:i:s T');
        $contentLength = strlen($content);

        $headers = [
            'Content-Type' => $contentType,
            'Content-Length' => (string)$contentLength,
            'x-ms-blob-type' => 'BlockBlob',
            'x-ms-date' => $date,
            'x-ms-version' => '2020-10-02',
        ];

        $authHeader = $this->generateAuthorizationHeader('PUT', $blobName, $headers, $contentLength);
        $headers['Authorization'] = $authHeader;

        $result = $this->makeRequest('PUT', $url, $headers, $content);

        if ($result['http_code'] === 201) {
            return ['success' => true];
        }

        return [
            'success' => false,
            'error' => 'Failed to upload blob: HTTP ' . $result['http_code'],
            'details' => $result['body'] ?? ''
        ];
    }

    /**
     * Generate Authorization header for Azure Storage REST API
     *
     * @param string $method HTTP method
     * @param string $blobName Blob name
     * @param array $headers Request headers
     * @param int $contentLength Content length
     * @return string Authorization header value
     */
    private function generateAuthorizationHeader(
        string $method,
        string $blobName,
        array $headers,
        int $contentLength = 0
    ): string {
        $contentType = $headers['Content-Type'] ?? '';

        // Build canonicalized headers
        $canonicalizedHeaders = $this->buildCanonicalizedHeaders($headers);

        // Build canonicalized resource
        $canonicalizedResource = "/{$this->accountName}/{$this->containerName}/{$blobName}";

        // Build string to sign
        $stringToSign = implode("\n", [
            $method,                                    // HTTP verb
            '',                                         // Content-Encoding
            '',                                         // Content-Language
            $contentLength > 0 ? $contentLength : '',   // Content-Length
            '',                                         // Content-MD5
            $contentType,                               // Content-Type
            '',                                         // Date
            '',                                         // If-Modified-Since
            '',                                         // If-Match
            '',                                         // If-None-Match
            '',                                         // If-Unmodified-Since
            '',                                         // Range
            $canonicalizedHeaders,                      // Canonicalized headers
            $canonicalizedResource,                     // Canonicalized resource
        ]);

        $signature = base64_encode(
            hash_hmac('sha256', $stringToSign, base64_decode($this->accountKey), true)
        );

        return "SharedKey {$this->accountName}:{$signature}";
    }

    /**
     * Generate Authorization header for list operations
     *
     * @param array $headers Request headers
     * @param string|null $prefix Blob prefix
     * @param int $maxResults Max results
     * @return string Authorization header value
     */
    private function generateListAuthorizationHeader(
        array $headers,
        ?string $prefix,
        int $maxResults
    ): string {
        $canonicalizedHeaders = $this->buildCanonicalizedHeaders($headers);

        $canonicalizedResource = "/{$this->accountName}/{$this->containerName}";
        $canonicalizedResource .= "\ncomp:list";
        $canonicalizedResource .= "\nmaxresults:{$maxResults}";
        if ($prefix) {
            $canonicalizedResource .= "\nprefix:{$prefix}";
        }
        $canonicalizedResource .= "\nrestype:container";

        $stringToSign = implode("\n", [
            'GET',                      // HTTP verb
            '',                         // Content-Encoding
            '',                         // Content-Language
            '',                         // Content-Length
            '',                         // Content-MD5
            '',                         // Content-Type
            '',                         // Date
            '',                         // If-Modified-Since
            '',                         // If-Match
            '',                         // If-None-Match
            '',                         // If-Unmodified-Since
            '',                         // Range
            $canonicalizedHeaders,      // Canonicalized headers
            $canonicalizedResource,     // Canonicalized resource
        ]);

        $signature = base64_encode(
            hash_hmac('sha256', $stringToSign, base64_decode($this->accountKey), true)
        );

        return "SharedKey {$this->accountName}:{$signature}";
    }

    /**
     * Build canonicalized headers string
     *
     * @param array $headers Request headers
     * @return string Canonicalized headers
     */
    private function buildCanonicalizedHeaders(array $headers): string
    {
        $msHeaders = [];

        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);
            if (strpos($lowerKey, 'x-ms-') === 0) {
                $msHeaders[$lowerKey] = $value;
            }
        }

        ksort($msHeaders);

        $parts = [];
        foreach ($msHeaders as $key => $value) {
            $parts[] = $key . ':' . $value;
        }

        return implode("\n", $parts);
    }

    /**
     * Get the full blob URL
     *
     * @param string $blobName Blob name
     * @return string Full URL
     */
    private function getBlobUrl(string $blobName): string
    {
        return "https://{$this->accountName}.blob.core.windows.net/{$this->containerName}/{$blobName}";
    }

    /**
     * Make HTTP request using cURL
     *
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param array $headers Request headers
     * @param string|null $body Request body
     * @return array Response with 'http_code' and 'body'
     */
    private function makeRequest(
        string $method,
        string $url,
        array $headers,
        ?string $body = null
    ): array {
        $ch = curl_init();

        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = "{$key}: {$value}";
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        if ($method === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Azure Blob Storage cURL error: {$error}");
        }

        return [
            'http_code' => $httpCode,
            'body' => $response,
            'error' => $error ?: null
        ];
    }

    /**
     * Detect content type from file
     *
     * @param string $filePath File path
     * @return string MIME type
     */
    private function detectContentType(string $filePath): string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        if ($mimeType) {
            return $mimeType;
        }

        // Fallback based on extension
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'json' => 'application/json',
            'log' => 'text/plain',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Parse list blobs XML response
     *
     * @param string $xmlResponse XML response body
     * @return array Array of blob info
     */
    private function parseListBlobsResponse(string $xmlResponse): array
    {
        $blobs = [];

        try {
            $xml = new SimpleXMLElement($xmlResponse);

            if (isset($xml->Blobs->Blob)) {
                foreach ($xml->Blobs->Blob as $blob) {
                    $blobs[] = [
                        'name' => (string)$blob->Name,
                        'url' => $this->getBlobUrl((string)$blob->Name),
                        'content_type' => (string)($blob->Properties->{'Content-Type'} ?? ''),
                        'content_length' => (int)($blob->Properties->{'Content-Length'} ?? 0),
                        'last_modified' => (string)($blob->Properties->{'Last-Modified'} ?? ''),
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("Failed to parse Azure list blobs response: " . $e->getMessage());
        }

        return $blobs;
    }

    /**
     * Extract blob name from Azure URL
     *
     * @param string $url Azure blob URL
     * @return string|null Blob name or null if not an Azure URL
     */
    public static function extractBlobNameFromUrl(string $url): ?string
    {
        $pattern = '/https:\/\/[^.]+\.blob\.core\.windows\.net\/[^\/]+\/(.+)$/';

        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Check if URL is an Azure Blob Storage URL
     *
     * @param string $url URL to check
     * @return bool True if Azure blob URL
     */
    public static function isAzureUrl(string $url): bool
    {
        return strpos($url, '.blob.core.windows.net/') !== false;
    }
}
