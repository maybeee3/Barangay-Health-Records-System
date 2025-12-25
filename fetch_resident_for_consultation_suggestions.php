<?php
include 'config.php';
header('Content-Type: application/json');

if (isset($_GET['query'])) {
    $q = '%' . $conn->real_escape_string($_GET['query']) . '%';
    $sql = "SELECT id, CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name, ''), ' ', COALESCE(name_extension, '')) AS name FROM residents WHERE last_name LIKE ? OR first_name LIKE ? OR middle_name LIKE ? OR name_extension LIKE ? ORDER BY last_name ASC, first_name ASC LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssss', $q, $q, $q, $q);
    $stmt->execute();
    $result = $stmt->get_result();
    $residents = [];
    while ($row = $result->fetch_assoc()) {
        $residents[] = $row;
    }
    echo json_encode($residents);
    $stmt->close();
    $conn->close();
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT id, last_name, first_name, middle_name, name_extension, date_of_birth as birthday, sex, civil_status, contact_no as contact_number, email, address, province, city_municipality, barangay FROM residents WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $resident = $result->fetch_assoc();
    if ($resident) {
        // Compose full address
        $address_parts = array_filter([
            $resident['address'],
            $resident['barangay'],
            $resident['city_municipality'],
            $resident['province']
        ]);
        $resident['address'] = implode(', ', $address_parts);
        // Calculate age
        if (!empty($resident['birthday'])) {
            $dob = new DateTime($resident['birthday']);
            $today = new DateTime();
            $age = $today->diff($dob)->y;
            $resident['age'] = $age;
        }
        // Compose full name for autofill
        $full_name = trim($resident['first_name'] . ' ' . $resident['middle_name'] . ' ' . $resident['last_name'] . ' ' . $resident['name_extension']);
        $resident['name'] = $full_name;
    }
    echo json_encode($resident);
    $stmt->close();
    $conn->close();
    exit();
}
echo json_encode([]); exit();
