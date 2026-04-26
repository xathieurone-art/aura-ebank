<?php
require_once 'db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!function_exists('sendJSON')) {
    function sendJSON($data) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fname   = $data['first_name'] ?? '';
    $mname   = $data['middle_name'] ?? '';
    $lname   = $data['last_name'] ?? '';
    $suffix  = $data['suffix'] ?? '';
    $gender  = $data['gender'] ?? '';
    $email   = $data['email'] ?? '';
    $phone   = $data['phone'] ?? '';
    $address = $data['address'] ?? '';
    $bday    = $data['birthdate'] ?? '';
    $raw_password = $data['password'] ?? '';

    $regex = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/";
    if (!preg_match($regex, $raw_password)) {
        sendJSON(["success" => false, "message" => "Password too weak."]);
    }

    $passwordHash = password_hash($raw_password, PASSWORD_DEFAULT);

    try {
        $count_result = $conn->query("SELECT COUNT(*) as total FROM users");
        $row = $count_result->fetch_assoc();

        $next_id = str_pad($row['total'] + 1, 2, '0', STR_PAD_LEFT);
        $date_part = date('Ymd');
        $rand_part = rand(10, 99);

        $acc_num = "AURA-" . $next_id . $date_part . $rand_part;

    } catch (Exception $e) {
        $acc_num = "AURA-" . date('His') . rand(10, 99);
    }

    try {
        $stmt = $conn->prepare("
            INSERT INTO users 
            (first_name, middle_name, last_name, suffix, gender, email, password, phone, address, birthdate, account_number, role) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'user')
        ");

        $stmt->bind_param(
            "sssssssssss",
            $fname, $mname, $lname, $suffix, $gender,
            $email, $passwordHash, $phone, $address,
            $bday, $acc_num
        );

        if ($stmt->execute()) {
            sendJSON([
                "success" => true,
                "message" => "Account created! Account No: $acc_num",
                "account_number" => $acc_num
            ]);
        }

        $stmt->close();

    } catch (mysqli_sql_exception $e) {

        if ($e->getCode() === 1062) {
            sendJSON(["success" => false, "message" => "Email is already registered."]);
        } else {
            sendJSON(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
        }
    }
}
?>