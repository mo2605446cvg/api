<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$host = 'alalsunacademy.com';
$dbname = 'u343039681_alalsun';
$username = 'u343039681_NSAR11';
$password = 'KaReeM@K74';
$conn = new mysqli($host, $username, $password, $db_name);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Connection failed: " . $conn->connect_error]);
    exit();
}
?>