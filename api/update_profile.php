<?php
header('Content-Type: application/json');

// Database configuration
$host = 'localhost';
$dbname = 'aura_ebank';
$username = 'root';
$password = '';

// Establish PDO database connection
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

// Parse incoming JSON data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
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

    // Update includes password if provided
    if (!empty($newPass)) {

        $hashedPass = password_hash($newPass, PASSWORD_DEFAULT);

        $sql = "UPDATE users 
                SET email = ?, phone = ?, password = ? 
                WHERE account_number = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email, $phone, $hashedPass, $accNum]);

    } 
    // Update without password (email/phone only)
    else {

        $sql = "UPDATE users 
                SET email = ?, phone = ? 
                WHERE account_number = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email, $phone, $accNum]);
    }

    // Check if any rows were affected
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