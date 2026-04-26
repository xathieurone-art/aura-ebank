<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "aura_ebank";

mysqli_report(MYSQLI_REPORT_OFF);

try {
    $conn = new mysqli($host, $user, $pass, $db);

    if ($conn->connect_error) {
        die(json_encode([
            "success" => false,
            "message" => "DB Connection Failed: " . $conn->connect_error
        ]));
    }

    $conn->set_charset("utf8mb4");

} catch (Exception $e) {
    die(json_encode([
        "success" => false,
        "message" => "Database Exception: " . $e->getMessage()
    ]));
}
if (!function_exists('sendJSON')) {
    function sendJSON($data) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
?>