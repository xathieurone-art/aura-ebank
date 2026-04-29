<?php
require_once 'db.php';
session_start();

// Helper function to send JSON responses
if (!function_exists('sendJSON')) {
    function sendJSON($data) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

// Check if user has admin or staff privileges
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    sendJSON(["success" => false, "message" => "Unauthorized access."]);
}

// Fetch all non-admin users with their profile data
$sql = "SELECT 
            first_name, 
            middle_name, 
            last_name, 
            suffix, 
            account_number, 
            email, 
            phone, 
            balance, 
            birthdate, 
            gender, 
            status 
        FROM users 
        WHERE role = 'user' 
        ORDER BY last_name ASC";

$result = $conn->query($sql);

// Handle database query errors
if (!$result) {
    sendJSON(["success" => false, "message" => "Database error: " . $conn->error]);
}

// Convert result set to array
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Return users list as JSON
sendJSON(["success" => true, "users" => $users]);
?>