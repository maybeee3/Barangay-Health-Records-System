<?php
include 'config.php';
header('Content-Type: application/json');

// Return residents who have at least one consultation
try {
  $sql = "SELECT r.id, CONCAT(r.last_name, ', ', r.first_name, ' ', COALESCE(r.middle_name,''), ' ', COALESCE(r.name_extension,'')) AS name
          FROM residents r
          JOIN consultations c ON c.resident_id = r.id
          GROUP BY r.id
          ORDER BY r.last_name ASC, r.first_name ASC";
  $res = $conn->query($sql);
  $out = [];
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $name = preg_replace('/\s+/', ' ', trim($row['name']));
      $out[] = ['id' => $row['id'], 'name' => $name];
    }
  }
  echo json_encode($out);
} catch (Exception $e) {
  echo json_encode([]);
}
$conn->close();
exit();