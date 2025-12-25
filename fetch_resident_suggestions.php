<?php
include 'config.php';
header('Content-Type: application/json');
if (isset($_GET['q'])) {
    $q = '%' . $conn->real_escape_string($_GET['q']) . '%';
    $sql = "SELECT id, last_name, first_name, middle_name, name_extension, date_of_birth, sex, civil_status, contact_no, email, address, province, city_municipality, barangay FROM residents WHERE CONCAT(last_name, ' ', first_name, ' ', middle_name, ' ', name_extension) LIKE ? OR last_name LIKE ? OR first_name LIKE ? ORDER BY last_name ASC, first_name ASC LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $q, $q, $q);
    $stmt->execute();
    $result = $stmt->get_result();
    $residents = [];
    while ($row = $result->fetch_assoc()) {
        $row['full_name'] = trim($row['last_name'] . ', ' . $row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['name_extension']);
        $residents[] = $row;
    }
    echo json_encode($residents);
    $stmt->close();
    $conn->close();
    exit();
}
echo json_encode([]); exit();
