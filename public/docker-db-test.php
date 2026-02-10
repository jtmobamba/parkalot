<?php
/**
 * Docker Database Connection Test
 *
 * This script tests the connection from Docker to your XAMPP MySQL.
 * Access it at: http://localhost:8080/docker-db-test.php
 *
 * DELETE THIS FILE IN PRODUCTION!
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Docker DB Connection Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .success { color: #27ae60; background: #d4edda; padding: 15px; border-radius: 5px; }
        .error { color: #c0392b; background: #f8d7da; padding: 15px; border-radius: 5px; }
        .info { color: #2980b9; background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0; }
        code { background: #f1f1f1; padding: 2px 6px; border-radius: 3px; }
        pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Docker Database Connection Test</h1>

        <div class="info">
            <strong>Environment Variables:</strong><br>
            DB_HOST: <code><?= getenv('DB_HOST') ?: 'not set (using localhost)' ?></code><br>
            DB_PORT: <code><?= getenv('DB_PORT') ?: 'not set (using 3306)' ?></code><br>
            DB_NAME: <code><?= getenv('DB_NAME') ?: 'not set (using parkalots)' ?></code><br>
            DB_USER: <code><?= getenv('DB_USER') ?: 'not set (using root)' ?></code>
        </div>

        <?php
        // Load database config
        require_once __DIR__ . '/../config/database.php';

        try {
            $db = Database::connect();
            echo '<div class="success">';
            echo '<strong>SUCCESS!</strong> Connected to MySQL database.<br><br>';

            // Get MySQL version
            $version = $db->query('SELECT VERSION()')->fetchColumn();
            echo "MySQL Version: <code>{$version}</code><br>";

            // List tables
            $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            echo "Tables in database: <code>" . count($tables) . "</code><br><br>";

            if (count($tables) > 0) {
                echo "<strong>Tables:</strong><br>";
                echo "<ul>";
                foreach ($tables as $table) {
                    echo "<li><code>{$table}</code></li>";
                }
                echo "</ul>";
            }
            echo '</div>';

        } catch (PDOException $e) {
            echo '<div class="error">';
            echo '<strong>CONNECTION FAILED!</strong><br><br>';
            echo 'Error: ' . htmlspecialchars($e->getMessage()) . '<br><br>';
            echo '</div>';

            echo '<div class="info">';
            echo '<strong>Troubleshooting Steps:</strong><br><br>';
            echo '1. <strong>Is XAMPP MySQL running?</strong><br>';
            echo '   - Open XAMPP Control Panel and ensure MySQL is started.<br><br>';
            echo '2. <strong>Check MySQL port:</strong><br>';
            echo '   - Default is 3306. If different, update <code>docker-compose.host-db.yml</code><br><br>';
            echo '3. <strong>Grant Docker access to MySQL:</strong><br>';
            echo '   - Open phpMyAdmin or MySQL console and run:<br>';
            echo '<pre>GRANT ALL PRIVILEGES ON parkalots.* TO \'root\'@\'%\' IDENTIFIED BY \'\';
FLUSH PRIVILEGES;</pre>';
            echo '4. <strong>Check MySQL bind-address:</strong><br>';
            echo '   - Edit <code>C:\xampp\mysql\bin\my.ini</code><br>';
            echo '   - Find <code>bind-address</code> and change to <code>0.0.0.0</code><br>';
            echo '   - Restart MySQL in XAMPP<br>';
            echo '</div>';
        }
        ?>

        <p style="margin-top: 20px; color: #666; font-size: 12px;">
            <strong>Warning:</strong> Delete this file (<code>docker-db-test.php</code>) in production!
        </p>
    </div>
</body>
</html>
