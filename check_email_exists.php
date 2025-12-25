<?php
// check_email_exists.php - Check if email exists in residents table
header('Content-Type: application/json');
require_once 'config.php';

$email = isset($_GET['email']) ? trim($_GET['email']) : '';

if (empty($email)) {
    echo json_encode(['exists' => false, 'message' => 'Email is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as full_name FROM residents WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $resident = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($resident) {
        echo json_encode([
            'exists' => true,
            'message' => 'Email found',
            'resident' => $resident
        ]);
    } else {
        echo json_encode([
            'exists' => false,
            'message' => 'Email not found in resident records'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'exists' => false,
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);
}
