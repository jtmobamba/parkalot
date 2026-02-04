<?php
if (!$user || !password_verify($password, $user['password_hash'])) {
    echo json_encode([
        "success" => false,
        "error" => "invalid"
    ]);
    exit;
}
?>