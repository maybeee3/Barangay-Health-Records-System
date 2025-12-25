<?php
session_start();
include 'config.php';

header('Content-Type: text/plain; charset=utf-8');

if (!isset($_SESSION['username'])) {
  http_response_code(403);
  echo 'Unauthorized.';
  exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && is_numeric($_POST['id'])) {
  $rec_id = intval($_POST['id']);

  // Ensure archive table exists
  $conn->query("CREATE TABLE IF NOT EXISTS health_records_archive LIKE health_records");

  // Copy into archive then delete from main table
  $conn->query("INSERT INTO health_records_archive SELECT * FROM health_records WHERE id = " . $rec_id);
  $affected = $conn->affected_rows;
  $conn->query("DELETE FROM health_records WHERE id = " . $rec_id);

  // Log
  $logs_dir = __DIR__ . '/backup_logs';
  if (!is_dir($logs_dir)) mkdir($logs_dir, 0755, true);
  $msg = date('c') . " - User {$_SESSION['username']} archived health_record id={$rec_id}\n";
  file_put_contents($logs_dir . '/archive_' . date('Ymd_His') . '.log', $msg, FILE_APPEND);

  if ($affected > 0) {
    echo 'Health record archived successfully.';
  } else {
    http_response_code(404);
    echo 'Health record not found or nothing to archive.';
  }
  exit();
} else {
  http_response_code(400);
  echo 'Invalid request.';
  exit();
}
