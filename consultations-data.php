<?php
session_start();
include 'config.php';

// simple auth guard
if (!isset($_SESSION['username'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit();
}

$rows = [];
$sql = "SELECT c.id, c.resident_id, r.last_name, r.first_name, r.middle_name, r.name_extension, c.date_of_consultation, c.consultation_time, COALESCE(c.status, 'Pending') AS status, c.reminder_sent FROM consultations c LEFT JOIN residents r ON c.resident_id = r.id ORDER BY c.date_of_consultation DESC, c.consultation_time ASC";
$res = $conn->query($sql);
if ($res instanceof mysqli_result) {
  while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
  }
  $res->free();
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($rows);
exit();
?>
