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

$data = json_decode(file_get_contents("php://input"), true);

$acc = $data['account_number'] ?? '';
$amount = (float)($data['amount'] ?? 0);
$action = $data['action'] ?? '';

// Validate required fields
if (!$acc || $amount <= 0) {
    sendJSON(["success" => false, "message" => "Invalid Request: acc='$acc' amount=$amount"]);
}

// Start transaction for data consistency
$conn->begin_transaction();

try {

    // Lock user row to prevent race conditions
    $stmt = $conn->prepare("
        SELECT id, balance 
        FROM users 
        WHERE account_number = ? 
        FOR UPDATE
    ");
    $stmt->bind_param("s", $acc);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        throw new Exception("Account not found");
    }

    $user_id = (int)$user['id'];

    // Check sufficient funds for withdrawal
    if ($action === 'withdraw' && $user['balance'] < $amount) {
        throw new Exception("Insufficient funds");
    }

    // Calculate new balance based on action
    $newBalance = ($action === 'deposit')
        ? $user['balance'] + $amount
        : $user['balance'] - $amount;

    // Update user balance
    $stmt_update = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?");
    $stmt_update->bind_param("di", $newBalance, $user_id);

    if (!$stmt_update->execute()) {
        throw new Exception("Update failed: " . $stmt_update->error);
    }

    // Generate unique reference number
    $sys_id = NULL;
    $prefix = ($action === 'deposit') ? 'DEP' : 'WIT';
    $date_part = date('Ymd');

    $acc_prefix = substr(preg_replace('/[^0-9]/', '', $acc), 0, 2);
    $rand = str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);

    $ref = $prefix . '-' . $date_part . '-' . $acc_prefix . '-' . $rand;

    $desc = ucfirst($action) . ' Transaction';

    // Set sender/receiver (NULL for system side)
    $sender_id = $action === 'deposit' ? $sys_id : $user_id;
    $receiver_id = $action === 'deposit' ? $user_id : $sys_id;

    // Log the transaction
    $stmt_log = $conn->prepare("
        INSERT INTO transactions 
        (sender_id, receiver_id, amount, reference_number, description, note, created_at) 
        VALUES (?, ?, ?, ?, ?, 'System Transaction', NOW())
    ");

    $stmt_log->bind_param("iidss", $sender_id, $receiver_id, $amount, $ref, $desc);

    if (!$stmt_log->execute()) {
        throw new Exception("Log failed: " . $stmt_log->error);
    }

    // Commit all changes
    $conn->commit();

    sendJSON([
        "success" => true,
        "new_balance" => $newBalance,
        "ref" => $ref
    ]);

} catch (Exception $e) {

    // Rollback on any error
    $conn->rollback();

    sendJSON([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>