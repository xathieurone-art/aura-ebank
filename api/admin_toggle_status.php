<?php
require_once 'db.php';
session_start();

if (!function_exists('sendJSON')) {
    function sendJSON($data) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}


if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    sendJSON(["success" => false, "message" => "Unauthorized access"]);
}

$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['target_acc']) && isset($data['current_status'])) {
    $acc = $data['target_acc'];

    $newStatus = ($data['current_status'] === 'active') ? 'frozen' : 'active';

    $stmt = $conn->prepare("
        UPDATE users 
        SET status = ? 
        WHERE account_number = ? AND role != 'admin'
    ");
    $stmt->bind_param("ss", $newStatus, $acc);

    if ($stmt->execute()) {
        sendJSON([
            "success" => true,
            "message" => "Account $acc is now " . strtoupper($newStatus),
            "new_status" => $newStatus
        ]);
    } else {
        sendJSON(["success" => false, "message" => "Update failed: " . $conn->error]);
    }

} else {
    sendJSON(["success" => false, "message" => "Missing required data"]);
}
?>