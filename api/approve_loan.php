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

$conn->begin_transaction();

try {
    $loan = $conn->query("SELECT * FROM loans WHERE id = $loan_id")->fetch_assoc();
    
    if (!$loan) {
        throw new Exception("Loan not found");
    }
    
    if ($loan['status'] !== 'Pending') {
        throw new Exception("Loan already processed");
    }
    
    $conn->query("UPDATE loans SET status = 'Approved' WHERE id = $loan_id");
    $conn->query("UPDATE users SET balance = balance + " . $loan['amount'] . " WHERE id = " . $loan['user_id']);
    
    $ref_no = "LOAN-" . date("Ymd") . "-" . rand(1000, 9999);
    $desc = "Loan Approved - " . $loan['loan_type'];
    
    $conn->query("
        INSERT INTO transactions (receiver_id, amount, reference_number, description, note, created_at) 
        VALUES (" . $loan['user_id'] . ", " . $loan['amount'] . ", '$ref_no', '$desc', 'Loan disbursement', NOW())
    ");
    
    $conn->commit();
    sendJSON(["success" => true, "message" => "Loan approved and amount credited to user balance"]);
    
} catch (Exception $e) {
    $conn->rollback();
    sendJSON(["success" => false, "message" => $e->getMessage()]);
}
?>