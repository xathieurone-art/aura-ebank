<?php

require_once 'db.php';
session_start();

function sendJSON($data) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    sendJSON(["success" => false, "message" => "Unauthorized access."]);
}

$user_role = $_SESSION['role'];
$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? '';

if ($action === 'get_stats') {
    
    $stats = $conn->query("
        SELECT SUM(balance) as total_money, COUNT(id) as total_users 
        FROM users WHERE role = 'user'
    ")->fetch_assoc();

    $stats['total_money'] = (float)($stats['total_money'] ?? 0);
    $stats['total_users'] = (int)($stats['total_users'] ?? 0);

    sendJSON(["success" => true, "stats" => $stats]);
}

if ($action === 'get_global_logs') {
    
    $sql = "SELECT 
                t.id, t.created_at, t.reference_number,
                u1.account_number AS sender_acc,
                u2.account_number AS receiver_acc,
                t.amount, t.description, t.note
            FROM transactions t
            LEFT JOIN users u1 ON t.sender_id = u1.id
            LEFT JOIN users u2 ON t.receiver_id = u2.id
            ORDER BY t.created_at DESC";

    $result = $conn->query($sql);
    $logs = [];

    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }

    sendJSON(["success" => true, "logs" => $logs]);
}

if ($action === 'undo_transaction') {
    
    if ($user_role !== 'admin') {
        sendJSON(["success" => false, "message" => "Access denied. Only administrators can reverse transactions."]);
    }
    
    $t_id = intval($data['transaction_id']);

    $stmt = $conn->prepare("
        SELECT t.*, u.account_number 
        FROM transactions t 
        JOIN users u ON t.sender_id = u.id 
        WHERE t.id = ?
    ");
    $stmt->bind_param("i", $t_id);
    $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc();

    if ($t && strpos($t['description'], 'REVERSED') === false) {
        $amt = abs(floatval($t['amount']));
        $s_id = intval($t['sender_id']);
        $r_id = intval($t['receiver_id']);

        $raw_acc = (string)$t['account_number'];
        $numeric_only = preg_replace('/[^0-9]/', '', $raw_acc);
        $acc_digits = substr($numeric_only, 0, 2);

        if (strlen($acc_digits) < 1) $acc_digits = '00';

        $conn->begin_transaction();

        try {
            $conn->query("UPDATE users SET balance = balance + $amt WHERE id = $s_id");
            $conn->query("UPDATE users SET balance = balance - $amt WHERE id = $r_id");

            $new_desc_original = $t['description'] . " (REVERSED)";
            $upd = $conn->prepare("UPDATE transactions SET description = ? WHERE id = ?");
            $upd->bind_param("si", $new_desc_original, $t_id);
            $upd->execute();

            $date_str = date("Ymd");
            $rev_ref = "REV-" . $date_str . "-" . $acc_digits . "-" . rand(1000, 9999);
            $rev_desc = "Reversal of Transaction #$t_id";

            $stmt_ins = $conn->prepare("
                INSERT INTO transactions 
                (sender_id, receiver_id, amount, reference_number, description, note) 
                VALUES (?, ?, ?, ?, ?, 'Admin Reversal')
            ");
            $stmt_ins->bind_param("iidss", $r_id, $s_id, $amt, $rev_ref, $rev_desc);
            $stmt_ins->execute();

            $conn->commit();

            sendJSON(["success" => true, "message" => "Transaction reversed. Ref: $rev_ref"]);
        } catch (Exception $e) {
            $conn->rollback();
            sendJSON(["success" => false, "message" => "Error: " . $e->getMessage()]);
        }
    } else {
        sendJSON(["success" => false, "message" => "Invalid transaction or already reversed."]);
    }
}

if ($action === 'delete_user') {
   
    if ($user_role !== 'admin') {
        sendJSON(["success" => false, "message" => "Access denied. Only administrators can delete users."]);
    }
    
    $acc = $data['target_acc'] ?? '';

    $stmt = $conn->prepare("DELETE FROM users WHERE account_number = ? AND role != 'admin'");
    $stmt->bind_param("s", $acc);

    if ($stmt->execute()) {
        sendJSON(["success" => true, "message" => "Account $acc deleted."]);
    } else {
        sendJSON(["success" => false, "message" => "Delete failed."]);
    }
}

if ($action === 'reset_password') {
    
    $acc = $data['target_acc'] ?? '';
    $newPassRaw = $data['new_password'] ?? 'default123';
    $newPassHash = password_hash($newPassRaw, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE account_number = ?");
    $stmt->bind_param("ss", $newPassHash, $acc);

    if ($stmt->execute()) {
        sendJSON(["success" => true, "message" => "Password updated successfully."]);
    } else {
        sendJSON(["success" => false, "message" => "Update failed."]);
    }
}

if ($action === 'edit_user') {
   
    $acc = $data['target_acc'] ?? '';

    $fname = $data['first_name'] ?? '';
    $mname = $data['middle_name'] ?? '';
    $lname = $data['last_name'] ?? '';
    $suffix = $data['suffix'] ?? '';
    $gender = $data['gender'] ?? '';
    $email = $data['email'] ?? '';
    $phone = $data['phone'] ?? '';

    $stmt = $conn->prepare("
        UPDATE users SET 
        first_name = ?, middle_name = ?, last_name = ?, suffix = ?, 
        gender = ?, email = ?, phone = ? 
        WHERE account_number = ? AND role != 'admin'
    ");

    $stmt->bind_param("ssssssss", $fname, $mname, $lname, $suffix, $gender, $email, $phone, $acc);

    if ($stmt->execute()) {
        sendJSON(["success" => true, "message" => "Client profile updated successfully."]);
    } else {
        sendJSON(["success" => false, "message" => "Failed to update profile: " . $conn->error]);
    }
}

if ($action === 'get_users') {
    
    $result = $conn->query("
        SELECT first_name, middle_name, last_name, suffix,
               account_number, email, phone, balance, birthdate, gender, status
        FROM users
        WHERE role = 'user'
        ORDER BY last_name ASC
    ");

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    sendJSON(["success" => true, "users" => $users]);
}
?>