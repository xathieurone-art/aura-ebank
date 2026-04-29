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

$role = $_SESSION['role'];
$user_id = (int)$_SESSION['user_id'];

// Admin/Staff: view all loans with user details
if ($role === 'admin' || $role === 'staff') {
    $sql = "
        SELECT l.*, u.first_name, u.last_name, u.account_number 
        FROM loans l
        JOIN users u ON l.user_id = u.id
        ORDER BY 
            CASE WHEN l.status = 'Pending' THEN 1 ELSE 2 END,  -- Pending loans first
            l.created_at DESC
    ";
    $result = $conn->query($sql);
    
    if (!$result) {
        sendJSON(["success" => false, "message" => "Query error: " . $conn->error]);
    }
    
    $loans = [];
    while ($row = $result->fetch_assoc()) {
        $loans[] = $row;
    }
    sendJSON($loans);
} 
// Regular user: view only their own loans
else {
    $sql = "
        SELECT l.*, u.first_name, u.last_name, u.account_number 
        FROM loans l
        JOIN users u ON l.user_id = u.id
        WHERE l.user_id = ?
        ORDER BY l.created_at DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $loans = [];
    while ($row = $result->fetch_assoc()) {
        $loans[] = $row;
    }
    sendJSON($loans);
}
?>