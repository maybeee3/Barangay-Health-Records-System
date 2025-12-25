<?php
// Return clean JSON and avoid leaking PHP warnings/notices into response
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

header('Content-Type: application/json');

$response = ['available' => false];

session_start();
include 'config.php';

// Ensure session authentication
if (!isset($_SESSION['username'])) {
    $response['error'] = 'Unauthorized access.';
    echo json_encode($response);
    exit();
}

// Accept both date and time for slot checking
$selected_date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_STRING);
$selected_time = filter_input(INPUT_GET, 'time', FILTER_SANITIZE_STRING);
if (!$selected_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $response['error'] = 'Invalid date provided.';
    echo json_encode($response);
    exit();
}
if (!$selected_time || !preg_match('/^\d{2}:\d{2}$/', $selected_time)) {
    $response['error'] = 'Invalid time provided.';
    echo json_encode($response);
    exit();
}

// Check database connection
if (!isset($conn) || (isset($conn) && $conn->connect_errno)) {
    $response['error'] = 'Database connection error.';
    echo json_encode($response);
    exit();
}

try {
    // Check if the exact date+time slot is already booked
    $stmt = $conn->prepare("SELECT COUNT(*) FROM consultations WHERE DATE(date_of_consultation) = ? AND consultation_time = ?");
    if (!$stmt) {
        $dbErr = $conn->error;
        $escaped_date = $conn->real_escape_string($selected_date);
        $escaped_time = $conn->real_escape_string($selected_time);
        $fallbackSql = "SELECT COUNT(*) AS cnt FROM consultations WHERE DATE(date_of_consultation) = '" . $escaped_date . "' AND consultation_time = '" . $escaped_time . "'";
        $fallbackResult = $conn->query($fallbackSql);
        if ($fallbackResult !== false) {
            $row = $fallbackResult->fetch_assoc();
            $count = isset($row['cnt']) ? (int)$row['cnt'] : 0;
            $response['available'] = ($count == 0);
        } else {
            $response['error'] = 'Database query prepare failed: ' . $dbErr . ' | fallback error: ' . $conn->error;
            echo json_encode($response);
            exit();
        }
    } else {
        $stmt->bind_param('ss', $selected_date, $selected_time);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        $response['available'] = ($count == 0);
    }
} catch (Exception $e) {
    $response['error'] = 'Exception while checking date/time availability.';
}

echo json_encode($response);
