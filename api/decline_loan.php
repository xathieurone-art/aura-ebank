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

$role = $_SESSION['role'];
if ($role !== 'admin' && $role !== 'staff') {
    sendJSON(["success" => false, "message" => "Access denied"]);
}

$data = json_decode(file_get_contents("php://input"), true);
$loan_id = intval($data['loan_id']);

$loan = $conn->query("SELECT * FROM loans WHERE id = $loan_id")->fetch_assoc();

if (!$loan) {
    sendJSON(["success" => false, "message" => "Loan not found"]);
}

if ($loan['status'] !== 'Pending') {
    sendJSON(["success" => false, "message" => "Loan already processed"]);
}

$conn->query("UPDATE loans SET status = 'Denied' WHERE id = $loan_id");

sendJSON(["success" => true, "message" => "Loan declined"]);
?>