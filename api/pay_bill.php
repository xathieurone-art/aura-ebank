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

// Parse request data
$data = json_decode(file_get_contents("php://input"), true);

$user_id = (int)$_SESSION['user_id'];
$biller = $data['biller'] ?? '';
$amount = floatval($data['amount'] ?? 0);

// Validate input
if ($amount <= 0) {
    sendJSON(["success" => false, "message" => "Invalid amount"]);
}

if (empty($biller)) {
    sendJSON(["success" => false, "message" => "Biller required"]);
}

// Start transaction for data consistency
$conn->begin_transaction();

try {
    // Lock user row and get account number
    $stmt = $conn->prepare("SELECT balance, account_number FROM users WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        throw new Exception("User not found");
    }

    // Check sufficient balance
    if ($user['balance'] < $amount) {
        throw new Exception("Insufficient balance");
    }

    // Deduct amount from user balance
    $newBalance = $user['balance'] - $amount;

    $updateStmt = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?");
    $updateStmt->bind_param("di", $newBalance, $user_id);
    $updateStmt->execute();

    // Record the bill payment
    $stmt_bill = $conn->prepare("INSERT INTO bills (user_id, biller, amount, created_at) VALUES (?, ?, ?, NOW())");
    $stmt_bill->bind_param("isd", $user_id, $biller, $amount);
    $stmt_bill->execute();

    // Generate bill reference: BIL-YYYYMMDD-XX-XXXX
    $date_part = date("Ymd");
    
    // Extract only digits from account number
    $digits_only = preg_replace('/[^0-9]/', '', $user['account_number']);
    
    // Take first 2 digits, default to '00' if less than 2 digits exist
    $acc_prefix = substr($digits_only, 0, 2);
    if (strlen($acc_prefix) < 2) {
        $acc_prefix = str_pad($acc_prefix, 2, '0', STR_PAD_RIGHT);
    }
    
    // Generate 4 random digits (1000-9999)
    $random_num = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    
    // Build final reference number
    $ref_no = "BIL-" . $date_part . "-" . $acc_prefix . "-" . $random_num;
    $desc = "Bill Payment: " . $biller;

    $stmt_log = $conn->prepare("INSERT INTO transactions (sender_id, receiver_id, amount, reference_number, description, note, created_at) VALUES (?, NULL, ?, ?, ?, 'Bill Payment', NOW())");
    $stmt_log->bind_param("idss", $user_id, $amount, $ref_no, $desc);
    $stmt_log->execute();

    // Commit all changes
    $conn->commit();

    sendJSON([
        "success" => true,
        "message" => "Bill paid successfully!",
        "new_balance" => $newBalance,
        "reference_number" => $ref_no
    ]);

} catch (Exception $e) {
    // Rollback on any error
    $conn->rollback();
    sendJSON(["success" => false, "message" => $e->getMessage()]);
}
?>