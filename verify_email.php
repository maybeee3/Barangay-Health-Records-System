<?php
// verify_email.php
// Returns JSON { exists: true|false }
header('Content-Type: application/json');
require 'config.php';

$email = isset($_GET['email']) ? trim($_GET['email']) : '';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['exists' => false]);
    exit;
}

$conn2 = new mysqli($servername, $username, $password, $dbname);
if ($conn2->connect_error) {
    echo json_encode(['exists' => false]);
    exit;
}

$stmt = $conn2->prepare('SELECT id FROM residents WHERE email = ? LIMIT 1');
if (!$stmt) {
    echo json_encode(['exists' => false]);
    $conn2->close();
    exit;
}
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
$exists = ($stmt->num_rows > 0);
$stmt->close();
$conn2->close();

echo json_encode(['exists' => $exists]);
exit;

?>
