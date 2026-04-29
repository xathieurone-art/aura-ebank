<?php
// Enable output buffering and error reporting for debugging
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';
session_start();

// Parse incoming JSON data
$raw_input = file_get_contents("php://input");
$data = json_decode($raw_input, true);

// Validate JSON format
if (!$data) {
    sendJSON([
        "success" => false,
        "message" => "No JSON received or invalid format",
        "raw" => $raw_input
    ]);
}

// Handle POST login requests only
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = $data['email'] ?? '';
    $pass  = $data['password'] ?? '';

    // Verify database connection exists
    if (!isset($conn) || $conn->connect_error) {
        sendJSON([
            "success" => false,
            "message" => "DB Connection Failed: " . ($conn->connect_error ?? 'No connection')
        ]);
    }

    // Prepare and execute user lookup by email
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    
    if (!$stmt) {
        sendJSON([
            "success" => false,
            "message" => "Prepare failed: " . $conn->error
        ]);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Verify password and set session on success
    if ($user && password_verify($pass, $user['password'])) {

        $response = [
            "success" => true,
            "user" => [
                "id"             => $user['id'],
                "first_name"     => $user['first_name'],
                "middle_name"    => $user['middle_name'],
                "last_name"      => $user['last_name'],
                "suffix"         => $user['suffix'],
                "account_number" => $user['account_number'],
                "balance"        => (float)$user['balance'],
                "role"           => $user['role'],
                "status"         => $user['status'],
                "email"          => $user['email'],
                "phone"          => $user['phone']
            ]
        ];

        // Store user session data
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['account_number'] = $user['account_number'];

        sendJSON($response);

    } else {
        sendJSON([
            "success" => false,
            "message" => "Invalid Login Credentials"
        ]);
    }
}
?>