<?php
session_start();
include 'config.php';

// Simple auth
if (!isset($_SESSION['username'])) {
  http_response_code(401);
  echo 'Unauthorized';
  exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo 'Method not allowed';
  exit();
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

if ($id <= 0 || $status === '') {
  http_response_code(400);
  echo 'Missing id or status';
  exit();
}

$allowed = ['Pending','Completed','Cancelled','No-show','NoShow','noshow','pending','completed','cancelled'];
if (!in_array($status, $allowed, true)) {
  http_response_code(400);
  echo 'Invalid status';
  exit();
}

// normalize to canonical forms
if (strtolower($status) === 'noshow') $status = 'No-show';
if (strtolower($status) === 'completed') $status = 'Completed';
if (strtolower($status) === 'cancelled') $status = 'Cancelled';
if (strtolower($status) === 'pending') $status = 'Pending';

$stmt = $conn->prepare("UPDATE consultations SET status = ? WHERE id = ?");
if (!$stmt) {
  http_response_code(500);
  echo 'DB prepare failed: ' . ($conn->error ?? 'unknown');
  exit();
}
$stmt->bind_param('si', $status, $id);
if ($stmt->execute()) {
  echo 'Status updated to ' . $status;
} else {
  http_response_code(500);
  echo 'Failed to update status: ' . htmlspecialchars($stmt->error);
}
$stmt->close();
$conn->close();
?>
