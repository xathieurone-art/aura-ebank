<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once 'db.php';
session_start();

$raw_input = file_get_contents("php://input");
$data = json_decode($raw_input, true);

if (!$data) {
    sendJSON([
        "success" => false,
        "message" => "No JSON received or invalid format",
        "raw" => $raw_input
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = $data['email'] ?? '';
    $pass  = $data['password'] ?? '';

    if ($conn->connect_error) {
        sendJSON([
            "success" => false,
            "message" => "DB Connection Failed: " . $conn->connect_error
        ]);
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

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
                "status"         => $user['status']
            ]
        ];

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