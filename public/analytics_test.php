<?php
/**
 * Analytics Data Test & Debug Script
 *
 * This script tests the analytics data and can insert sample data.
 * Access: http://localhost:8081/ParkaLot_System/public/analytics_test.php
 *
 * DELETE THIS FILE IN PRODUCTION!
 */

session_start();
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

$db = Database::connect();
$action = $_GET['action'] ?? 'check';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Analytics Data Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; }
        .success { color: #27ae60; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #c0392b; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #2980b9; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f8f9fa; }
        .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
        .btn:hover { background: #2980b9; }
        .btn-success { background: #27ae60; }
        .btn-danger { background: #e74c3c; }
        pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; overflow-x: auto; }
        code { background: #f1f1f1; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Analytics Data Test & Debug</h1>

    <p>
        <a href="?action=check" class="btn">Check Data</a>
        <a href="?action=insert" class="btn btn-success">Insert Sample Data</a>
        <a href="?action=api" class="btn">Test API Response</a>
    </p>

    <?php if ($action === 'check'): ?>
        <h2>Database Tables Check</h2>

        <?php
        $tables = [
            'payments' => 'SELECT COUNT(*) as count, SUM(amount) as total FROM payments WHERE payment_status = "succeeded"',
            'reservations' => 'SELECT status, COUNT(*) as count FROM reservations GROUP BY status',
            'vehicle_inspections' => 'SELECT inspection_type, COUNT(*) as count FROM vehicle_inspections GROUP BY inspection_type',
            'employee_contracts' => 'SELECT department, COUNT(*) as count FROM employee_contracts WHERE department IS NOT NULL GROUP BY department',
            'reports' => 'SELECT report_type, COUNT(*) as count FROM reports GROUP BY report_type'
        ];

        foreach ($tables as $table => $query):
            echo "<h3>Table: <code>{$table}</code></h3>";
            try {
                // Check if table exists
                $checkTable = $db->query("SHOW TABLES LIKE '{$table}'");
                if ($checkTable->rowCount() === 0) {
                    echo '<div class="error">Table does not exist!</div>';
                    continue;
                }

                $stmt = $db->query($query);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($results) || (isset($results[0]['count']) && $results[0]['count'] == 0)) {
                    echo '<div class="info">Table exists but has no data.</div>';
                } else {
                    echo '<div class="success">Table has data:</div>';
                    echo '<table><tr>';
                    foreach (array_keys($results[0]) as $col) {
                        echo "<th>{$col}</th>";
                    }
                    echo '</tr>';
                    foreach ($results as $row) {
                        echo '<tr>';
                        foreach ($row as $val) {
                            echo "<td>" . htmlspecialchars($val ?? 'NULL') . "</td>";
                        }
                        echo '</tr>';
                    }
                    echo '</table>';
                }
            } catch (Exception $e) {
                echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        endforeach;
        ?>

    <?php elseif ($action === 'insert'): ?>
        <h2>Inserting Sample Data</h2>

        <?php
        try {
            // Insert sample payments
            $db->exec("
                INSERT INTO payments (user_id, reservation_id, amount, payment_status, payment_method, created_at) VALUES
                (1, 1, 25.50, 'succeeded', 'card', DATE_SUB(NOW(), INTERVAL 1 MONTH)),
                (1, 2, 45.00, 'succeeded', 'card', DATE_SUB(NOW(), INTERVAL 2 MONTH)),
                (1, 3, 30.00, 'succeeded', 'card', DATE_SUB(NOW(), INTERVAL 3 MONTH)),
                (1, 4, 55.00, 'succeeded', 'card', DATE_SUB(NOW(), INTERVAL 4 MONTH)),
                (1, 5, 40.00, 'succeeded', 'card', DATE_SUB(NOW(), INTERVAL 5 MONTH)),
                (1, 6, 35.00, 'succeeded', 'card', NOW())
                ON DUPLICATE KEY UPDATE amount = VALUES(amount)
            ");
            echo '<div class="success">Inserted sample payments data.</div>';
        } catch (Exception $e) {
            echo '<div class="error">Payments: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        try {
            // Insert sample reservations
            $db->exec("
                INSERT INTO reservations (user_id, garage_id, spot_number, start_time, end_time, status) VALUES
                (1, 1, 'A1', NOW(), DATE_ADD(NOW(), INTERVAL 2 HOUR), 'active'),
                (1, 1, 'A2', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 22 HOUR), 'completed'),
                (1, 1, 'A3', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 46 HOUR), 'completed'),
                (1, 1, 'B1', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 70 HOUR), 'cancelled'),
                (1, 1, 'B2', NOW(), DATE_ADD(NOW(), INTERVAL 3 HOUR), 'active')
                ON DUPLICATE KEY UPDATE status = VALUES(status)
            ");
            echo '<div class="success">Inserted sample reservations data.</div>';
        } catch (Exception $e) {
            echo '<div class="error">Reservations: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        try {
            // Insert sample vehicle inspections
            $db->exec("
                INSERT INTO vehicle_inspections (vehicle_id, inspector_id, inspection_type, inspection_result, notes) VALUES
                (1, 1, 'routine', 'pass', 'All good'),
                (2, 1, 'entry', 'pass', 'Minor scratch noted'),
                (3, 1, 'exit', 'pass', 'No new damage'),
                (4, 1, 'damage', 'fail', 'Dent on rear bumper'),
                (5, 1, 'random', 'pass', 'Spot check passed'),
                (6, 1, 'routine', 'pass', 'Clean vehicle'),
                (7, 1, 'entry', 'pass', 'Logged entry')
                ON DUPLICATE KEY UPDATE inspection_result = VALUES(inspection_result)
            ");
            echo '<div class="success">Inserted sample vehicle inspections data.</div>';
        } catch (Exception $e) {
            echo '<div class="error">Vehicle inspections: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        try {
            // Insert sample employee contracts
            $db->exec("
                INSERT INTO employee_contracts (user_id, department, position, salary, contract_status, start_date) VALUES
                (1, 'management', 'Manager', 50000, 'active', CURDATE()),
                (2, 'operations', 'Parking Attendant', 25000, 'active', CURDATE()),
                (3, 'operations', 'Parking Attendant', 25000, 'active', CURDATE()),
                (4, 'customer_service', 'Customer Service Rep', 28000, 'active', CURDATE()),
                (5, 'software_systems', 'Software Developer', 45000, 'active', CURDATE()),
                (6, 'software_systems', 'Senior Developer', 55000, 'active', CURDATE())
                ON DUPLICATE KEY UPDATE department = VALUES(department)
            ");
            echo '<div class="success">Inserted sample employee contracts data.</div>';
        } catch (Exception $e) {
            echo '<div class="error">Employee contracts: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        try {
            // Insert sample reports
            $db->exec("
                INSERT INTO reports (generated_by, report_type, report_data, generated_at) VALUES
                (1, 'daily', '{\"summary\": \"Daily report\"}', NOW()),
                (1, 'daily', '{\"summary\": \"Daily report 2\"}', DATE_SUB(NOW(), INTERVAL 1 DAY)),
                (1, 'weekly', '{\"summary\": \"Weekly report\"}', DATE_SUB(NOW(), INTERVAL 1 WEEK)),
                (1, 'weekly', '{\"summary\": \"Weekly report 2\"}', DATE_SUB(NOW(), INTERVAL 2 WEEK)),
                (1, 'monthly', '{\"summary\": \"Monthly report\"}', DATE_SUB(NOW(), INTERVAL 1 MONTH))
                ON DUPLICATE KEY UPDATE report_data = VALUES(report_data)
            ");
            echo '<div class="success">Inserted sample reports data.</div>';
        } catch (Exception $e) {
            echo '<div class="error">Reports: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        echo '<div class="info">Sample data inserted. <a href="?action=check">Check data now</a></div>';
        ?>

    <?php elseif ($action === 'api'): ?>
        <h2>API Response Test</h2>

        <?php
        // Simulate logged in manager
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'manager';

        echo '<div class="info">Session set: user_id=1, role=manager</div>';

        // Make internal API call
        $apiUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/../api/index.php?route=manager/analytics';
        echo '<p>API URL: <code>' . htmlspecialchars($apiUrl) . '</code></p>';

        // Use cURL to make request with session
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        echo "<p>HTTP Status: <code>{$httpCode}</code></p>";
        echo '<h3>Raw Response:</h3>';
        echo '<pre>' . htmlspecialchars($response) . '</pre>';

        $data = json_decode($response, true);
        if ($data) {
            echo '<h3>Parsed JSON:</h3>';
            echo '<pre>' . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . '</pre>';
        } else {
            echo '<div class="error">Failed to parse JSON response</div>';
        }
        ?>

    <?php endif; ?>

    <hr>
    <p style="color: #666; font-size: 12px;">
        <strong>Warning:</strong> Delete this file (<code>analytics_test.php</code>) in production!
    </p>
</div>
</body>
</html>
