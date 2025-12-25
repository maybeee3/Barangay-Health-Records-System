<?php
include 'config.php';
header('Content-Type: application/json');
if (!isset($_GET['patient_id'])) { echo json_encode([]); exit(); }
$id = intval($_GET['patient_id']);
$sql = "SELECT * FROM consultations WHERE resident_id = ? ORDER BY date_of_consultation DESC, consultation_time DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$consult = $result->fetch_assoc();
echo json_encode($consult ?: []);
$stmt->close();
$conn->close();
exit();