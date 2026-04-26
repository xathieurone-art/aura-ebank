<?php
require_once 'db.php';
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Please login first"]);
    exit;
}

$raw_input = file_get_contents("php://input");
$data = json_decode($raw_input, true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "No data received. Raw: " . $raw_input]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$loan_type = isset($data['loan_type']) ? $data['loan_type'] : '';
$amount = isset($data['amount']) ? floatval($data['amount']) : 0;

if (empty($loan_type)) {
    echo json_encode(["success" => false, "message" => "Please select a loan type"]);
    exit;
}

if ($amount <= 0) {
    echo json_encode(["success" => false, "message" => "Please enter a valid loan amount"]);
    exit;
}

$sql = "INSERT INTO loans (user_id, loan_type, amount, status, created_at) VALUES (?, ?, ?, 'Pending', NOW())";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Database prepare error: " . $conn->error]);
    exit;
}

$stmt->bind_param("isd", $user_id, $loan_type, $amount);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Loan application submitted successfully"]);
} else {
    echo json_encode(["success" => false, "message" => "Database error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>