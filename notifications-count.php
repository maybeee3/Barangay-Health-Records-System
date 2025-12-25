<?php
session_start();
include 'config.php';

header('Content-Type: application/json; charset=utf-8');

// Determine user id: prefer user_id, fallback to username lookup if available
$user_id = null;
if (isset($_SESSION['user_id'])) {
  $user_id = (int)$_SESSION['user_id'];
} elseif (isset($_SESSION['username'])) {
  // try to find id by username (best-effort)
  try {
    $u = $conn->real_escape_string($_SESSION['username']);
    $res = $conn->query("SELECT id FROM users WHERE username = '" . $u . "' LIMIT 1");
    if ($res && $res->num_rows) {
      $r = $res->fetch_assoc();
      $user_id = (int)$r['id'];
    }
  } catch (Exception $e) { /* ignore */ }
}

$result = ['unread' => 0, 'upcoming' => 0, 'total' => 0];

try {
  // Unread notifications for this user (if we have a user_id). If not, count all unread as fallback.
  if ($user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0");
    if ($stmt) {
      $stmt->bind_param('i', $user_id);
      $stmt->execute();
      $r = $stmt->get_result();
      if ($r) {
        $row = $r->fetch_assoc();
        $result['unread'] = (int)$row['cnt'];
      }
      $stmt->close();
    }
  } else {
    $r = $conn->query("SELECT COUNT(*) AS cnt FROM notifications WHERE is_read = 0");
    if ($r) { $row = $r->fetch_assoc(); $result['unread'] = (int)$row['cnt']; }
  }

  // Upcoming consultations in the next 2 hours that are still Pending (and optionally not reminded)
  $q = $conn->prepare("SELECT COUNT(*) AS cnt FROM consultations WHERE CAST(CONCAT(date_of_consultation, ' ', consultation_time) AS DATETIME) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 HOUR) AND (status IS NULL OR status = '' OR status = 'Pending')");
  if ($q) {
    $q->execute();
    $r2 = $q->get_result();
    if ($r2) {
      $row2 = $r2->fetch_assoc();
      $result['upcoming'] = (int)$row2['cnt'];
    }
    $q->close();
  }

  $result['total'] = $result['unread'] + $result['upcoming'];
} catch (Exception $e) {
  error_log('[notifications-count.php] ' . $e->getMessage());
}

echo json_encode($result);
exit();
?>
