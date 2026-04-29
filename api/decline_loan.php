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

// Verify admin or staff role
$role = $_SESSION['role'];
if ($role !== 'admin' && $role !== 'staff') {
    sendJSON(["success" => false, "message" => "Access denied"]);
}

// Get loan ID from request
$data = json_decode(file_get_contents("php://input"), true);
$loan_id = intval($data['loan_id']);

// Fetch loan details
$loan = $conn->query("SELECT * FROM loans WHERE id = $loan_id")->fetch_assoc();

// Validate loan exists
if (!$loan) {
    sendJSON(["success" => false, "message" => "Loan not found"]);
}

// Prevent re-processing already handled loans
if ($loan['status'] !== 'Pending') {
    sendJSON(["success" => false, "message" => "Loan already processed"]);
}

// Update loan status to Denied
$conn->query("UPDATE loans SET status = 'Denied' WHERE id = $loan_id");

sendJSON(["success" => true, "message" => "Loan declined"]);
?>