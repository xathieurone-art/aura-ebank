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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendJSON(["success" => false, "message" => "Unauthorized access."]);
}

$data = json_decode(file_get_contents("php://input"), true);

$sender_id = (int)$_SESSION['user_id'];
$target_acc = $data['target_acc'] ?? '';
$amount = floatval($data['amount'] ?? 0);
$user_note = $data['description'] ?? '';

// Validate amount
if ($amount <= 0) {
    sendJSON(["success" => false, "message" => "Please enter a valid amount."]);
}

// Find receiver by account number
$stmt = $conn->prepare("
    SELECT id, CONCAT(first_name, ' ', last_name) AS receiver_fullname 
    FROM users 
    WHERE account_number = ? 
    LIMIT 1
");
$stmt->bind_param("s", $target_acc);
$stmt->execute();
$receiver = $stmt->get_result()->fetch_assoc();

if (!$receiver) {
    sendJSON(["success" => false, "message" => "Recipient account not found."]);
}

$receiver_id = (int)$receiver['id'];
$receiver_name = $receiver['receiver_fullname'];

// Prevent self-transfer
if ($receiver_id === $sender_id) {
    sendJSON(["success" => false, "message" => "Cannot send money to yourself."]);
}

// Start transaction for data consistency
$conn->begin_transaction();

try {

    // Lock sender row to prevent race conditions
    $stmt_sender = $conn->prepare("
        SELECT balance, account_number 
        FROM users 
        WHERE id = ? 
        FOR UPDATE
    ");
    $stmt_sender->bind_param("i", $sender_id);
    $stmt_sender->execute();
    $user = $stmt_sender->get_result()->fetch_assoc();

    // Check sufficient balance
    if ($user['balance'] < $amount) {
        throw new Exception("Insufficient balance.");
    }

    // Deduct from sender
    $stmt_sender_update = $conn->prepare("
        UPDATE users 
        SET balance = balance - ? 
        WHERE id = ?
    ");
    $stmt_sender_update->bind_param("di", $amount, $sender_id);
    $stmt_sender_update->execute();

    // Add to receiver
    $stmt_receiver_update = $conn->prepare("
        UPDATE users 
        SET balance = balance + ? 
        WHERE id = ?
    ");
    $stmt_receiver_update->bind_param("di", $amount, $receiver_id);
    $stmt_receiver_update->execute();

    // Generate unique reference number
    $date_str = date("Ymd");
    $acc_prefix = substr(preg_replace('/[^0-9]/', '', $user['account_number']), 0, 2);
    $random_num = rand(1000, 9999);

    $ref_no = "REF-$date_str-$acc_prefix-$random_num";

    $final_description = "Transfer to " . $receiver_name;
    $note = !empty($user_note) ? $user_note : "No note provided";

    // Log the transaction
    $log_stmt = $conn->prepare("
        INSERT INTO transactions 
        (sender_id, receiver_id, amount, reference_number, description, note) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $log_stmt->bind_param(
        "iidsss",
        $sender_id,
        $receiver_id,
        $amount,
        $ref_no,
        $final_description,
        $note
    );

    if (!$log_stmt->execute()) {
        throw new Exception("Transaction logging failed.");
    }

    // Commit all changes
    $conn->commit();

    sendJSON([
        "success" => true,
        "message" => "₱" . number_format($amount, 2) . " sent to $receiver_name!",
        "ref_no" => $ref_no,
        "new_balance" => ($user['balance'] - $amount)
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