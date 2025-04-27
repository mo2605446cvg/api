<?php
header('Content-Type: application/json');

// إضافة رؤوس CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

// التعامل مع طلبات OPTIONS لـ CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];

// تسجيل الطلبات للتصحيح
$log_message = "[" . date('Y-m-d H:i:s') . "] Method: $method, URI: $request_uri";
if (isset($_FILES['file'])) {
    $log_message .= ", File: " . $_FILES['file']['name'];
}
$log_message .= "\n";
file_put_contents('api.log', $log_message, FILE_APPEND);

// التحقق من أن الطريقة هي POST
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "error" => "Method not allowed",
        "expected_method" => "POST",
        "received_method" => $method,
        "request_uri" => $request_uri
    ]);
    exit();
}

// التحقق من وجود ملف مرفوع
if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    echo json_encode([
        "error" => "No file uploaded or upload error",
        "request_uri" => $request_uri
    ]);
    exit();
}

$file = $_FILES['file'];
$upload_dir = 'Uploads/';
$allowed_types = ['pdf', 'xlsx', 'xls', 'jpg', 'png', 'jpeg'];

// التحقق من نوع الملف
$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($file_ext, $allowed_types)) {
    http_response_code(400);
    echo json_encode([
        "error" => "Invalid file type. Allowed types: " . implode(', ', $allowed_types),
        "request_uri" => $request_uri
    ]);
    exit();
}

// التحقق من حجم الملف (حد أقصى 10 ميجابايت)
if ($file['size'] > 10 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode([
        "error" => "File size exceeds 10MB limit",
        "request_uri" => $request_uri
    ]);
    exit();
}

// التأكد من وجود دليل Uploads
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        http_response_code(500);
        echo json_encode([
            "error" => "Failed to create upload directory",
            "request_uri" => $request_uri
        ]);
        exit();
    }
}

// إنشاء اسم ملف فريد
$file_name = uniqid() . '.' . $file_ext;
$destination = $upload_dir . $file_name;

// نقل الملف إلى الوجهة
if (move_uploaded_file($file['tmp_name'], $destination)) {
    $file_path = $upload_dir . $file_name;
    echo json_encode([
        "message" => "File uploaded successfully",
        "file_path" => $file_path
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "error" => "Failed to upload file",
        "request_uri" => $request_uri
    ]);
}
?>