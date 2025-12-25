<?php
include 'config.php';
header('Content-Type: application/json');

// Try to read from a users/health workers table first
try {
  $out = [];
  // Only show consultants who have at least one record in consultations
  $q = $conn->query("SELECT DISTINCT COALESCE(consulting_doctor,'Unassigned') AS name FROM consultations WHERE consulting_doctor IS NOT NULL AND consulting_doctor != '' ORDER BY name ASC");
  if ($q) {
    $i = 1;
    while ($r = $q->fetch_assoc()) { $out[] = ['id'=>'c_'.$i++, 'name'=>trim($r['name'])]; }
  }
  echo json_encode($out);
} catch (Exception $e) {
  echo json_encode([]);
}
$conn->close();
exit();
