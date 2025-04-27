<?php
header('Content-Type: application/json');

// إضافة رؤوس CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

require_once 'config.php';

// التعامل مع طلبات OPTIONS لـ CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$table = isset($_GET['table']) ? trim($_GET['table']) : '';
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$request_uri = $_SERVER['REQUEST_URI'];

// تسجيل الطلبات للتصحيح
$log_message = "[" . date('Y-m-d H:i:s') . "] Method: $method, Table: $table, Action: $action, URI: $request_uri";
if ($method === 'POST') {
    $post_data = file_get_contents("php://input");
    $log_message .= ", POST Data: $post_data";
}
$log_message .= "\n";
file_put_contents('api.log', $log_message, FILE_APPEND);

// التحقق من وجود table
if (empty($table)) {
    http_response_code(400);
    echo json_encode([
        "error" => "Table parameter is required",
        "request_uri" => $request_uri,
        "method" => $method
    ]);
    exit();
}

switch ($table) {
    case 'users':
        if ($action === 'login') {
            handleLogin($conn, $method, $request_uri);
        } elseif ($action === 'generate_password') {
            generatePassword($conn, $method, $request_uri);
        } else {
            handleUsers($conn, $method, $action, $request_uri);
        }
        break;
    case 'content':
        handleContent($conn, $method, $action, $request_uri);
        break;
    case 'messages':
        handleMessages($conn, $method, $action, $request_uri);
        break;
    default:
        http_response_code(400);
        echo json_encode([
            "error" => "Invalid table: $table",
            "request_uri" => $request_uri,
            "method" => $method
        ]);
        break;
}

function generatePassword($conn, $method, $request_uri) {
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
    $data = json_decode(file_get_contents("php://input"), true);
    if (!is_array($data) || !isset($data['password']) || empty(trim($data['password']))) {
        http_response_code(400);
        echo json_encode([
            "error" => "Valid password is required",
            "request_uri" => $request_uri
        ]);
        exit();
    }
    $hash = password_hash(trim($data['password']), PASSWORD_DEFAULT);
    if ($hash === false) {
        http_response_code(500);
        echo json_encode([
            "error" => "Failed to generate password hash",
            "request_uri" => $request_uri
        ]);
        exit();
    }
    echo json_encode(["hash" => $hash]);
}

function handleLogin($conn, $method, $request_uri) {
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
    $data = json_decode(file_get_contents("php://input"), true);
    if (!is_array($data) || !isset($data['code']) || !isset($data['password']) || empty(trim($data['code'])) || empty(trim($data['password']))) {
        http_response_code(400);
        echo json_encode([
            "error" => "Valid code and password are required",
            "request_uri" => $request_uri
        ]);
        exit();
    }
    $code = $conn->real_escape_string(trim($data['code']));
    $password = trim($data['password']);

    $result = $conn->query("SELECT * FROM users WHERE code = '$code'");
    if (!$result) {
        http_response_code(500);
        echo json_encode([
            "error" => "Database error: " . $conn->error,
            "request_uri" => $request_uri
        ]);
        exit();
    }
    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode([
            "error" => "User not found",
            "request_uri" => $request_uri
        ]);
        exit();
    }

    $user = $result->fetch_assoc();
    if (password_verify($password, $user['password'])) {
        echo json_encode($user);
    } else {
        http_response_code(401);
        echo json_encode([
            "error" => "Invalid password",
            "request_uri" => $request_uri
        ]);
    }
}

function handleUsers($conn, $method, $action, $request_uri) {
    if ($method === 'GET') {
        if ($action === 'all') {
            $result = $conn->query("SELECT * FROM users");
            if (!$result) {
                http_response_code(500);
                echo json_encode([
                    "error" => "Database error: " . $conn->error,
                    "request_uri" => $request_uri
                ]);
                exit();
            }
            $users = [];
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            echo json_encode($users);
        } elseif ($action === 'single') {
            if (!isset($_GET['code']) || empty(trim($_GET['code']))) {
                http_response_code(400);
                echo json_encode([
                    "error" => "Valid code parameter is required",
                    "request_uri" => $request_uri
                ]);
                exit();
            }
            $code = $conn->real_escape_string(trim($_GET['code']));
            $result = $conn->query("SELECT * FROM users WHERE code = '$code'");
            if (!$result) {
                http_response_code(500);
                echo json_encode([
                    "error" => "Database error: " . $conn->error,
                    "request_uri" => $request_uri
                ]);
                exit();
            }
            $user = $result->fetch_assoc();
            if ($user) {
                echo json_encode($user);
            } else {
                http_response_code(404);
                echo json_encode([
                    "error" => "User not found",
                    "request_uri" => $request_uri
                ]);
            }
        } else {
            http_response_code(400);
            echo json_encode([
                "error" => "Invalid action: " . ($action ?: 'none'),
                "request_uri" => $request_uri
            ]);
        }
    } elseif ($method === 'POST' && empty($action)) {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!is_array($data) || !isset($data['code']) || !isset($data['username']) || !isset($data['department']) ||
            !isset($data['division']) || !isset($data['role']) || !isset($data['password']) ||
            empty(trim($data['code'])) || empty(trim($data['username'])) || empty(trim($data['department'])) ||
            empty(trim($data['division'])) || empty(trim($data['role'])) || empty(trim($data['password']))) {
            http_response_code(400);
            echo json_encode([
                "error" => "All user fields are required and must be non-empty",
                "request_uri" => $request_uri
            ]);
            exit();
        }
        $code = $conn->real_escape_string(trim($data['code']));
        $username = $conn->real_escape_string(trim($data['username']));
        $department = $conn->real_escape_string(trim($data['department']));
        $division = $conn->real_escape_string(trim($data['division']));
        $role = $conn->real_escape_string(trim($data['role']));
        $password = $conn->real_escape_string(trim($data['password']));
        $sql = "INSERT INTO users (code, username, department, division, role, password) 
                VALUES ('$code', '$username', '$department', '$division', '$role', '$password')";
        if ($conn->query($sql)) {
            echo json_encode(["message" => "User added successfully"]);
        } else {
            http_response_code(500);
            echo json_encode([
                "error" => "Database error: " . $conn->error,
                "request_uri" => $request_uri
            ]);
        }
    } else {
        http_response_code(405);
        echo json_encode([
            "error" => "Method not allowed",
            "expected_method" => empty($action) ? "POST" : "GET",
            "received_method" => $method,
            "request_uri" => $request_uri
        ]);
    }
}

function handleContent($conn, $method, $action, $request_uri) {
    if ($method === 'GET') {
        if ($action !== 'all') {
            http_response_code(400);
            echo json_encode([
                "error" => "Invalid action: " . ($action ?: 'none'),
                "request_uri" => $request_uri
            ]);
            exit();
        }
        if (!isset($_GET['department']) || !isset($_GET['division']) || empty(trim($_GET['department'])) || empty(trim($_GET['division']))) {
            http_response_code(400);
            echo json_encode([
                "error" => "Valid department and division parameters are required",
                "request_uri" => $request_uri
            ]);
            exit();
        }
        $department = $conn->real_escape_string(trim($_GET['department']));
        $division = $conn->real_escape_string(trim($_GET['division']));
        $sql = "SELECT * FROM content WHERE department = '$department' AND division = '$division'";
        $result = $conn->query($sql);
        if (!$result) {
            http_response_code(500);
            echo json_encode([
                "error" => "Database error: " . $conn->error,
                "request_uri" => $request_uri
            ]);
            exit();
        }
        $content = [];
        while ($row = $result->fetch_assoc()) {
            $content[] = $row;
        }
        echo json_encode($content);
    } elseif ($method === 'POST' && empty($action)) {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!is_array($data) || !isset($data['id']) || !isset($data['title']) || !isset($data['file_path']) ||
            !isset($data['file_type']) || !isset($data['uploaded_by']) || !isset($data['upload_date']) ||
            !isset($data['department']) || !isset($data['division']) ||
            empty(trim($data['id'])) || empty(trim($data['title'])) || empty(trim($data['file_path'])) ||
            empty(trim($data['file_type'])) || empty(trim($data['uploaded_by'])) || empty(trim($data['upload_date'])) ||
            empty(trim($data['department'])) || empty(trim($data['division']))) {
            http_response_code(400);
            echo json_encode([
                "error" => "All content fields are required and must be non-empty",
                "request_uri" => $request_uri
            ]);
            exit();
        }
        $id = $conn->real_escape_string(trim($data['id']));
        $title = $conn->real_escape_string(trim($data['title']));
        $file_path = $conn->real_escape_string(trim($data['file_path']));
        $poster_path = isset($data['poster_path']) ? $conn->real_escape_string(trim($data['poster_path'])) : '';
        $file_type = $conn->real_escape_string(trim($data['file_type']));
        $uploaded_by = $conn->real_escape_string(trim($data['uploaded_by']));
        $upload_date = $conn->real_escape_string(trim($data['upload_date']));
        $department = $conn->real_escape_string(trim($data['department']));
        $division = $conn->real_escape_string(trim($data['division']));
        $sql = "INSERT INTO content (id, title, file_path, poster_path, file_type, uploaded_by, upload_date, department, division) 
                VALUES ('$id', '$title', '$file_path', '$poster_path', '$file_type', '$uploaded_by', '$upload_date', '$department', '$division')";
        if ($conn->query($sql)) {
            echo json_encode(["message" => "Content added successfully"]);
        } else {
            http_response_code(500);
            echo json_encode([
                "error" => "Database error: " . $conn->error,
                "request_uri" => $request_uri
            ]);
        }
    } else {
        http_response_code(405);
        echo json_encode([
            "error" => "Method not allowed",
            "expected_method" => empty($action) ? "POST" : "GET",
            "received_method" => $method,
            "request_uri" => $request_uri
        ]);
    }
}

function handleMessages($conn, $method, $action, $request_uri) {
    if ($method === 'GET') {
        if (!isset($_GET['department']) || !isset($_GET['division']) || empty(trim($_GET['department'])) || empty(trim($_GET['division']))) {
            http_response_code(400);
            echo json_encode([
                "error" => "Valid department and division parameters are required",
                "request_uri" => $request_uri
            ]);
            exit();
        }
        $department = $conn->real_escape_string(trim($_GET['department']));
        $division = $conn->real_escape_string(trim($_GET['division']));
        $sql = "SELECT * FROM messages WHERE department = '$department' AND division = '$division' ORDER BY timestamp DESC";
        $result = $conn->query($sql);
        if (!$result) {
            http_response_code(500);
            echo json_encode([
                "error" => "Database error: " . $conn->error,
                "request_uri" => $request_uri
            ]);
            exit();
        }
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        echo json_encode($messages);
    } elseif ($method === 'POST' && empty($action)) {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!is_array($data) || !isset($data['id']) || !isset($data['content']) || !isset($data['sender_id']) ||
            !isset($data['department']) || !isset($data['division']) || !isset($data['timestamp']) ||
            empty(trim($data['id'])) || empty(trim($data['content'])) || empty(trim($data['sender_id'])) ||
            empty(trim($data['department'])) || empty(trim($data['division'])) || empty(trim($data['timestamp']))) {
            http_response_code(400);
            echo json_encode([
                "error" => "All message fields are required and must be non-empty",
                "request_uri" => $request_uri
            ]);
            exit();
        }
        $id = $conn->real_escape_string(trim($data['id']));
        $content = $conn->real_escape_string(trim($data['content']));
        $sender_id = $conn->real_escape_string(trim($data['sender_id']));
        $department = $conn->real_escape_string(trim($data['department']));
        $division = $conn->real_escape_string(trim($data['division']));
        $timestamp = $conn->real_escape_string(trim($data['timestamp']));
        $sql = "INSERT INTO messages (id, content, sender_id, department, division, timestamp) 
                VALUES ('$id', '$content', '$sender_id', '$department', '$division', '$timestamp')";
        if ($conn->query($sql)) {
            echo json_encode(["message" => "Message sent successfully"]);
        } else {
            http_response_code(500);
            echo json_encode([
                "error" => "Database error: " . $conn->error,
                "request_uri" => $request_uri
            ]);
        }
    } else {
        http_response_code(405);
        echo json_encode([
            "error" => "Method not allowed",
            "expected_method" => empty($action) ? "POST" : "GET",
            "received_method" => $method,
            "request_uri" => $request_uri
        ]);
    }
}

$conn->close();
?>