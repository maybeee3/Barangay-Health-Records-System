<?php
session_start();
include 'config.php';

header('Content-Type: application/json; charset=utf-8');

// Simple auth guard (best-effort)
$user_id = null;
if (isset($_SESSION['user_id'])) $user_id = (int)$_SESSION['user_id'];

$notifications = [];
try {
  // Load persisted notifications for this user (if table exists)
  if ($user_id) {
    $stmt = $conn->prepare("SELECT n.id, n.title, n.message, n.is_read, n.created_at, n.consultation_id, n.resident_id FROM notifications n WHERE n.user_id = ? ORDER BY n.created_at DESC LIMIT 50");
    if ($stmt) {
      $stmt->bind_param('i', $user_id);
      $stmt->execute();
      $res = $stmt->get_result();
      while ($row = $res->fetch_assoc()) {
        $notifications[] = $row;
      }
      $stmt->close();
    }
  }
} catch (Exception $e) {}

// Also include upcoming consultations within next 2 hours as transient reminder items
try {
  $q = $conn->query("SELECT c.id, c.resident_id, CONCAT_WS(' ', r.first_name, r.last_name) AS resident_name, c.date_of_consultation, c.consultation_time, c.consulting_doctor FROM consultations c LEFT JOIN residents r ON c.resident_id = r.id WHERE CAST(CONCAT(c.date_of_consultation, ' ', c.consultation_time) AS DATETIME) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 HOUR) AND (c.status IS NULL OR c.status = '' OR c.status = 'Pending') ORDER BY c.date_of_consultation, c.consultation_time");
  if ($q) {
    while ($r = $q->fetch_assoc()) {
      $dt = $r['date_of_consultation'] . ' ' . ($r['consultation_time'] ?? '');
      $notifications[] = [
        'id' => 'cons_' . $r['id'],
        'title' => 'Upcoming Consultation',
        'message' => 'Consultation for ' . trim($r['resident_name'] ?? '') . ' at ' . $dt,
        'is_read' => 0,
        'created_at' => $dt,
        'consultation_id' => $r['id'],
        'resident_id' => $r['resident_id'],
        'consulting_doctor' => $r['consulting_doctor'] ?? ''
      ];
    }
  }
} catch (Exception $e) {}

echo json_encode($notifications);
exit();
?>
