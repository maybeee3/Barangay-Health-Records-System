<?php
// Marks notifications as seen for current session/user. Simple endpoint used by client.
if (session_status() === PHP_SESSION_NONE) session_start();
include_once 'config.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

$user_id = $_SESSION['user_id'] ?? null;
// If user_id not available, accept but do nothing server-side (client will still hide badge)
if (!$user_id) {
  echo json_encode(['ok' => true, 'message' => 'no user']);
  exit;
}

try {
  // Mark notifications as seen/read for this user
  $q = $conn->prepare("UPDATE notifications SET seen = 1, is_read = 1 WHERE user_id = ? AND (seen = 0 OR is_read = 0)");
  if ($q) {
    $q->bind_param('i', $user_id);
    $q->execute();
    $affected = $q->affected_rows;
    $q->close();
    echo json_encode(['ok' => true, 'updated' => $affected]);
    exit;
  }
} catch (Exception $e) {
  error_log('[notifications-mark-seen] ' . $e->getMessage());
}

echo json_encode(['ok' => false]);
?>
