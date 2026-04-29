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

// Start transaction to ensure data consistency
$conn->begin_transaction();

try {
    // Fetch loan details with user's account number
    $loan = $conn->query("
        SELECT l.*, u.account_number 
        FROM loans l 
        JOIN users u ON l.user_id = u.id 
        WHERE l.id = $loan_id
    ")->fetch_assoc();
    
    if (!$loan) {
        throw new Exception("Loan not found");
    }
    
    // Prevent double processing
    if ($loan['status'] !== 'Pending') {
        throw new Exception("Loan already processed");
    }
    
    // Update loan status to Approved
    $conn->query("UPDATE loans SET status = 'Approved' WHERE id = $loan_id");
    
    // Credit the loan amount to user's balance
    $conn->query("UPDATE users SET balance = balance + " . $loan['amount'] . " WHERE id = " . $loan['user_id']);
    
    // Generate loan reference: LOA-YYYYMMDD-XX-XXXX
    $date_part = date("Ymd");
    
    // Extract only digits from account number
    $digits_only = preg_replace('/[^0-9]/', '', $loan['account_number']);
    
    // Take first 2 digits, default to '00' if less than 2 digits exist
    $acc_prefix = substr($digits_only, 0, 2);
    if (strlen($acc_prefix) < 2) {
        $acc_prefix = str_pad($acc_prefix, 2, '0', STR_PAD_RIGHT);
    }
    
    // Generate 4 random digits (1000-9999)
    $random_num = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    
    // Build final reference number
    $ref_no = "LOA-" . $date_part . "-" . $acc_prefix . "-" . $random_num;
    $desc = "Loan Approved - " . $loan['loan_type'];
    
    $conn->query("
        INSERT INTO transactions (receiver_id, amount, reference_number, description, note, created_at) 
        VALUES (" . $loan['user_id'] . ", " . $loan['amount'] . ", '$ref_no', '$desc', 'Loan disbursement', NOW())
    ");
    
    // Commit all changes
    $conn->commit();
    sendJSON(["success" => true, "message" => "Loan approved and amount credited to user balance"]);
    
} catch (Exception $e) {
    // Roll back on any error
    $conn->rollback();
    sendJSON(["success" => false, "message" => $e->getMessage()]);
}
?>