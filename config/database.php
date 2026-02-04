<?php
class Database {
    public static function connect() {
        // Use environment variables if set (Docker), otherwise use XAMPP defaults
        $host = getenv('DB_HOST') ?: 'localhost';
        $dbname = getenv('DB_NAME') ?: 'parkalots';
        $user = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: '';

        return new PDO(
            "mysql:host={$host};dbname={$dbname}",
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
}
?>