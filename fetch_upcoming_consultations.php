<?php
header('Content-Type: application/json; charset=utf-8');
include_once 'config.php';

$results = [];
try {
    // Return all consultations scheduled within the next 2 hours for display in notifications.
    // We deliberately do not filter by reminder_sent here so the UI shows all scheduled consultants.
    $sql = "SELECT c.id, c.resident_id, c.email AS resident_email, c.consulting_doctor, c.date_of_consultation, c.consultation_time, CONCAT_WS(' ', r.first_name, r.last_name) AS resident_name FROM consultations c LEFT JOIN residents r ON c.resident_id = r.id WHERE CAST(CONCAT(c.date_of_consultation, ' ', c.consultation_time) AS DATETIME) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 HOUR) ORDER BY c.date_of_consultation, c.consultation_time";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $results[] = $row;
        }
    }
} catch (Exception $e) {
    // ignore, return empty
}

echo json_encode($results, JSON_UNESCAPED_UNICODE);
?>