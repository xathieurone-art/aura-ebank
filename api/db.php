<?php
// Database configuration
$host = "localhost";
$user = "root";
$pass = "";
$db   = "aura_ebank";

// Disable default mysqli error reporting 
mysqli_report(MYSQLI_REPORT_OFF);

try {
    // Establish database connection
    $conn = new mysqli($host, $user, $pass, $db);

    // Check for connection errors
    if ($conn->connect_error) {
        die(json_encode([
            "success" => false,
            "message" => "DB Connection Failed: " . $conn->connect_error
        ]));
    }

    // Set UTF-8 encoding for proper character handling
    $conn->set_charset("utf8mb4");

} catch (Exception $e) {
    // Catch any exceptions during connection
    die(json_encode([
        "success" => false,
        "message" => "Database Exception: " . $e->getMessage()
    ]));
}

// Helper function to send JSON responses (if not already defined)
if (!function_exists('sendJSON')) {
    function sendJSON($data) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
?>