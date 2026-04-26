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

$data = json_decode(file_get_contents("php://input"), true);

$user_id = (int)$_SESSION['user_id'];
$biller = $data['biller'] ?? '';
$amount = floatval($data['amount'] ?? 0);

if ($amount <= 0) {
    sendJSON(["success" => false, "message" => "Invalid amount"]);
}

if (empty($biller)) {
    sendJSON(["success" => false, "message" => "Biller required"]);
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        throw new Exception("User not found");
    }

    if ($user['balance'] < $amount) {
        throw new Exception("Insufficient balance");
    }

    $newBalance = $user['balance'] - $amount;

    $updateStmt = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?");
    $updateStmt->bind_param("di", $newBalance, $user_id);
    $updateStmt->execute();

    $stmt_bill = $conn->prepare("INSERT INTO bills (user_id, biller, amount, created_at) VALUES (?, ?, ?, NOW())");
    $stmt_bill->bind_param("isd", $user_id, $biller, $amount);
    $stmt_bill->execute();

    $ref_no = "BILL-" . date("Ymd") . "-" . rand(1000, 9999);
    $desc = "Bill Payment: " . $biller;

    $stmt_log = $conn->prepare("INSERT INTO transactions (sender_id, receiver_id, amount, reference_number, description, note, created_at) VALUES (?, NULL, ?, ?, ?, 'Bill Payment', NOW())");
    $stmt_log->bind_param("idss", $user_id, $amount, $ref_no, $desc);
    $stmt_log->execute();

    $conn->commit();

    sendJSON([
        "success" => true,
        "message" => "Bill paid successfully!",
        "new_balance" => $newBalance
    ]);

} catch (Exception $e) {
    $conn->rollback();
    sendJSON(["success" => false, "message" => $e->getMessage()]);
}
?>