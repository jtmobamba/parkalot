<?php
/**
 * Azure Configuration
 *
 * Configuration for Azure Blob Storage and Azure Database for MySQL.
 * Values are loaded from environment variables with sensible defaults.
 */

// Prevent direct access
if (!defined('PARKALOT_APP')) {
    define('PARKALOT_APP', true);
}

/**
 * Azure Blob Storage Configuration
 */
define('AZURE_STORAGE_ENABLED', getenv('AZURE_STORAGE_ENABLED') === 'true');
define('AZURE_STORAGE_ACCOUNT_NAME', getenv('AZURE_STORAGE_ACCOUNT_NAME') ?: '');
define('AZURE_STORAGE_ACCOUNT_KEY', getenv('AZURE_STORAGE_ACCOUNT_KEY') ?: '');
define('AZURE_STORAGE_CONTAINER_NAME', getenv('AZURE_STORAGE_CONTAINER_NAME') ?: 'parkalot-files');

// Container paths for different file types
define('AZURE_CONTAINER_CV_FILES', 'cv-files');
define('AZURE_CONTAINER_SPACE_PHOTOS', 'space-photos');
define('AZURE_CONTAINER_LOGS', 'logs');

// SAS Token settings
define('AZURE_SAS_TOKEN_EXPIRY', (int)(getenv('AZURE_SAS_TOKEN_EXPIRY') ?: 3600)); // 1 hour default

/**
 * Azure Database for MySQL Configuration
 */
define('AZURE_MYSQL_ENABLED', getenv('AZURE_MYSQL_ENABLED') === 'true');
define('AZURE_MYSQL_HOST', getenv('AZURE_MYSQL_HOST') ?: '');
define('AZURE_MYSQL_PORT', getenv('AZURE_MYSQL_PORT') ?: '3306');
define('AZURE_MYSQL_DATABASE', getenv('AZURE_MYSQL_DATABASE') ?: '');
define('AZURE_MYSQL_USERNAME', getenv('AZURE_MYSQL_USERNAME') ?: '');
define('AZURE_MYSQL_PASSWORD', getenv('AZURE_MYSQL_PASSWORD') ?: '');

// SSL Certificate for secure connection (required for Azure MySQL)
define('AZURE_MYSQL_SSL_CA', getenv('AZURE_MYSQL_SSL_CA') ?: __DIR__ . '/ssl/DigiCertGlobalRootCA.crt.pem');
define('AZURE_MYSQL_SSL_VERIFY', getenv('AZURE_MYSQL_SSL_VERIFY') !== 'false'); // Default true

/**
 * Helper function to get Azure Blob Storage base URL
 *
 * @return string The base URL for blob storage
 */
function getAzureBlobBaseUrl(): string
{
    $accountName = AZURE_STORAGE_ACCOUNT_NAME;
    $containerName = AZURE_STORAGE_CONTAINER_NAME;

    if (empty($accountName)) {
        return '';
    }

    return "https://{$accountName}.blob.core.windows.net/{$containerName}";
}

/**
 * Check if Azure storage is properly configured
 *
 * @return bool True if Azure storage is configured and enabled
 */
function isAzureStorageConfigured(): bool
{
    return AZURE_STORAGE_ENABLED
        && !empty(AZURE_STORAGE_ACCOUNT_NAME)
        && !empty(AZURE_STORAGE_ACCOUNT_KEY);
}

/**
 * Check if Azure MySQL is properly configured
 *
 * @return bool True if Azure MySQL is configured and enabled
 */
function isAzureMySQLConfigured(): bool
{
    return AZURE_MYSQL_ENABLED
        && !empty(AZURE_MYSQL_HOST)
        && !empty(AZURE_MYSQL_DATABASE)
        && !empty(AZURE_MYSQL_USERNAME);
}

/**
 * Get Azure MySQL connection options for PDO
 *
 * @return array PDO options array with SSL configuration
 */
function getAzureMySQLOptions(): array
{
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    // Add SSL options if certificate path exists
    if (AZURE_MYSQL_SSL_VERIFY && file_exists(AZURE_MYSQL_SSL_CA)) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = AZURE_MYSQL_SSL_CA;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }

    return $options;
}
