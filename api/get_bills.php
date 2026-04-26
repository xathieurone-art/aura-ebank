<?php
require_once 'db.php';
session_start();

function sendJSON($data) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    sendJSON(["success" => false, "message" => "Unauthorized"]);
}

$user_id = (int)$_SESSION['user_id'];

$result = $conn->query("SELECT * FROM bills WHERE user_id = $user_id ORDER BY created_at DESC");

$bills = [];
while ($row = $result->fetch_assoc()) {
    $bills[] = $row;
}

sendJSON($bills);
?>