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
    sendJSON(["success" => false, "message" => "Unauthorized"]);
}

$user_id = (int)$_SESSION['user_id'];

// Enable strict error reporting for this query
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Fetch transactions where user is sender OR receiver, with formatted names
$sql = "SELECT 
            t.amount, 
            t.description, 
            t.note, 
            t.reference_number,
            t.created_at as date,
            t.sender_id,
            t.receiver_id,
            COALESCE(CONCAT(u1.first_name, ' ', IFNULL(SUBSTRING(u1.middle_name, 1, 1), ''), '. ', u1.last_name), 'External System') AS sender_name,
            COALESCE(CONCAT(u2.first_name, ' ', IFNULL(SUBSTRING(u2.middle_name, 1, 1), ''), '. ', u2.last_name), 'External System') AS receiver_name
        FROM transactions t
        LEFT JOIN users u1 ON t.sender_id = u1.id
        LEFT JOIN users u2 ON t.receiver_id = u2.id
        WHERE t.sender_id = ? OR t.receiver_id = ?
        ORDER BY t.created_at DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    sendJSON(["success" => false, "message" => "Prepare failed: " . $conn->error]);
}

$stmt->bind_param("ii", $user_id, $user_id);

if (!$stmt->execute()) {
    sendJSON(["success" => false, "message" => "Execute failed: " . $stmt->error]);
}

$result = $stmt->get_result();
if (!$result) {
    sendJSON(["success" => false, "message" => "Result failed: " . $conn->error]);
}

$logs = [];

// Process each transaction to determine direction (sent/received)
while ($row = $result->fetch_assoc()) {

    $isSender = ((int)$row['sender_id'] === $user_id);
    $s_name = $row['sender_name'] ?? "External System";
    $r_name = $row['receiver_name'] ?? "External System";

    // Format description based on transaction direction
    if ($isSender) {
        $display_desc = "Sent to " . $r_name;
        $display_amount = -abs($row['amount']); // Negative for sent money
    } else {
        $display_desc = "Received from " . $s_name;
        $display_amount = abs($row['amount']); // Positive for received money
    }

    // Append note if exists
    if (!empty($row['note']) && $row['note'] !== 'No note provided') {
        $display_desc .= " (" . $row['note'] . ")";
    }

    $logs[] = [
        "date" => $row['date'],
        "reference_number" => $row['reference_number'] ?? '---',
        "description" => $display_desc,
        "amount" => $display_amount
    ];
}

sendJSON(["success" => true, "logs" => $logs]);
?>