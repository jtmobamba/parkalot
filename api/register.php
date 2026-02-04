<?php
header("Content-Type: application/json");
require "../config/db.php";
require "../app/controllers/AuthController.php";

$data = json_decode(file_get_contents("php://input"), true);

if (in_array("", $data)) {
    echo json_encode(["success" => false, "error" => "empty_fields"]);
    exit;
}

$auth = new AuthController($db);
echo json_encode($auth->register(
    $data["name"], $data["email"], $data["password"]
));
