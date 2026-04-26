<?php
header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'aura_ebank';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname",
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['account_number'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request data'
    ]);
    exit;
}

$accNum = $data['account_number'];
$email  = $data['email'] ?? null;
$phone  = $data['phone'] ?? null;
$newPass = $data['password'] ?? null;

try {

    if (!empty($newPass)) {

        $hashedPass = password_hash($newPass, PASSWORD_DEFAULT);

        $sql = "UPDATE users 
                SET email = ?, phone = ?, password = ? 
                WHERE account_number = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email, $phone, $hashedPass, $accNum]);

    } 
    else {

        $sql = "UPDATE users 
                SET email = ?, phone = ? 
                WHERE account_number = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email, $phone, $accNum]);
    }

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No changes made or user not found'
        ]);
    }

} catch (PDOException $e) {

    echo json_encode([
        'success' => false,
        'message' => 'Query error: ' . $e->getMessage()
    ]);
}
?>