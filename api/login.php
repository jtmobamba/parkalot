<?php
header("Content-Type: application/json");
require "../config/db.php";
require "../app/controllers/AuthController.php";

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data["email"]) || empty($data["password"])) {
    echo json_encode(["success" => false, "error" => "empty_fields"]);
    exit;
}

$auth = new AuthController($db);
echo json_encode($auth->login($data["email"], $data["password"]));

