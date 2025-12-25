<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
  http_response_code(403);
  echo 'Unauthorized.';
  exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && is_numeric($_POST['id'])) {
  $resident_id = intval($_POST['id']);
  // Archive flow: copy rows to *_archive tables then delete from main tables
  // Ensure archive tables exist
  $conn->query("CREATE TABLE IF NOT EXISTS residents_archive LIKE residents");
  $conn->query("CREATE TABLE IF NOT EXISTS consultations_archive LIKE consultations");
  $conn->query("CREATE TABLE IF NOT EXISTS health_records_archive LIKE health_records");

  $logs_dir = __DIR__ . '/backup_logs';
  if (!is_dir($logs_dir)) mkdir($logs_dir, 0755, true);

  // Archive consultations
  $conn->query("INSERT INTO consultations_archive SELECT * FROM consultations WHERE resident_id = " . $resident_id);
  $conn->query("DELETE FROM consultations WHERE resident_id = " . $resident_id);

  // Archive health_records
  $conn->query("INSERT INTO health_records_archive SELECT * FROM health_records WHERE resident_id = " . $resident_id);
  $conn->query("DELETE FROM health_records WHERE resident_id = " . $resident_id);

  // Archive resident
  $conn->query("INSERT INTO residents_archive SELECT * FROM residents WHERE id = " . $resident_id);
  $conn->query("DELETE FROM residents WHERE id = " . $resident_id);

  $logfile = $logs_dir . DIRECTORY_SEPARATOR . 'archive_' . date('Ymd_His') . '.log';
  $msg = date('c') . " - User {$_SESSION['username']} archived resident id={$resident_id}\n";
  file_put_contents($logfile, $msg, FILE_APPEND);

  echo 'Resident and associated records archived successfully.';
  $conn->close();
  exit();
} else {
  http_response_code(400);
  echo 'Invalid request.';
  exit();
}
