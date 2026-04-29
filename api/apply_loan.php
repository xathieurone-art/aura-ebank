<?php
require_once 'db.php';
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Please login first"]);
    exit;
}

// Parse incoming JSON data
$raw_input = file_get_contents("php://input");
$data = json_decode($raw_input, true);

// Validate JSON data received
if (!$data) {
    echo json_encode(["success" => false, "message" => "No data received. Raw: " . $raw_input]);
    exit;
}

// Extract and sanitize input values
$user_id = (int)$_SESSION['user_id'];
$loan_type = isset($data['loan_type']) ? $data['loan_type'] : '';
$amount = isset($data['amount']) ? floatval($data['amount']) : 0;

// Validate loan type
if (empty($loan_type)) {
    echo json_encode(["success" => false, "message" => "Please select a loan type"]);
    exit;
}

// Validate loan amount is positive
if ($amount <= 0) {
    echo json_encode(["success" => false, "message" => "Please enter a valid loan amount"]);
    exit;
}

// Insert loan application with Pending status
$sql = "INSERT INTO loans (user_id, loan_type, amount, status, created_at) VALUES (?, ?, ?, 'Pending', NOW())";
$stmt = $conn->prepare($sql);

// Check if prepare failed
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Database prepare error: " . $conn->error]);
    exit;
}

$stmt->bind_param("isd", $user_id, $loan_type, $amount);

// Execute and return result
if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Loan application submitted successfully"]);
} else {
    echo json_encode(["success" => false, "message" => "Database error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>