<?php
require_once __DIR__ . '/azure.php';

class Database {
    private static ?PDO $connection = null;

    /**
     * Get database connection
     *
     * Automatically selects Azure MySQL or local MySQL based on configuration.
     *
     * @return PDO Database connection
     */
    public static function connect(): PDO {
        if (self::$connection !== null) {
            return self::$connection;
        }

        // Check if Azure MySQL is configured and enabled
        if (isAzureMySQLConfigured()) {
            self::$connection = self::connectAzure();
        } else {
            self::$connection = self::connectLocal();
        }

        return self::$connection;
    }

    /**
     * Connect to Azure Database for MySQL
     *
     * @return PDO Database connection
     */
    private static function connectAzure(): PDO {
        $host = AZURE_MYSQL_HOST;
        $port = AZURE_MYSQL_PORT;
        $dbname = AZURE_MYSQL_DATABASE;
        $user = AZURE_MYSQL_USERNAME;
        $password = AZURE_MYSQL_PASSWORD;

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

        $options = getAzureMySQLOptions();

        try {
            $pdo = new PDO($dsn, $user, $password, $options);

            // Set additional connection attributes for Azure
            $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);

            return $pdo;
        } catch (PDOException $e) {
            error_log("Azure MySQL connection failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Connect to local MySQL (XAMPP or Docker)
     *
     * @return PDO Database connection
     */
    private static function connectLocal(): PDO {
        // Use environment variables if set (Docker), otherwise use XAMPP defaults
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_NAME') ?: 'parkalotdb';
        $user = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: '';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

        return new PDO(
            $dsn,
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    /**
     * Check if using Azure MySQL
     *
     * @return bool True if connected to Azure
     */
    public static function isAzure(): bool {
        return isAzureMySQLConfigured();
    }

    /**
     * Get connection info (for debugging)
     *
     * @return array Connection details (without password)
     */
    public static function getConnectionInfo(): array {
        if (isAzureMySQLConfigured()) {
            return [
                'type' => 'azure',
                'host' => AZURE_MYSQL_HOST,
                'port' => AZURE_MYSQL_PORT,
                'database' => AZURE_MYSQL_DATABASE,
                'user' => AZURE_MYSQL_USERNAME,
                'ssl_enabled' => AZURE_MYSQL_SSL_VERIFY,
            ];
        }

        return [
            'type' => 'local',
            'host' => getenv('DB_HOST') ?: 'localhost',
            'port' => getenv('DB_PORT') ?: '3306',
            'database' => getenv('DB_NAME') ?: 'parkalotdb',
            'user' => getenv('DB_USER') ?: 'root',
        ];
    }

    /**
     * Reset connection (useful for testing or reconnection)
     */
    public static function resetConnection(): void {
        self::$connection = null;
    }
}
?>