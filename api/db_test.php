<?php
require '../config/database.php';

try {
    $db = Database::connect();
    echo json_encode([
        "status" => "success",
        "message" => "Secure database connection established"
    ]);
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>