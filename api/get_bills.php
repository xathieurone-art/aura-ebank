<?php
require_once 'db.php';
session_start();

// Helper function to send JSON responses
function sendJSON($data) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendJSON(["success" => false, "message" => "Unauthorized"]);
}

// Get logged-in user's ID
$user_id = (int)$_SESSION['user_id'];

// Fetch all bills for this user, newest first
$result = $conn->query("SELECT * FROM bills WHERE user_id = $user_id ORDER BY created_at DESC");

// Convert result set to array
$bills = [];
while ($row = $result->fetch_assoc()) {
    $bills[] = $row;
}

// Return bills as JSON array
sendJSON($bills);
?>