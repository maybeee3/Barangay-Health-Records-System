<?php
session_start();
include 'config.php';

echo "<h2>Database Debug Information</h2>";

// Check consultations
echo "<h3>Consultations Table:</h3>";
$r = $conn->query("SELECT COUNT(*) as cnt FROM consultations");
if ($r) {
    $row = $r->fetch_assoc();
    echo "Total consultations: " . $row['cnt'] . "<br>";

    if ($row['cnt'] > 0) {
        echo "<h4>Sample consultations (first 5):</h4>";
        $r2 = $conn->query("SELECT c.id, c.date_of_consultation, c.consultation_time, r.first_name, r.last_name, r.barangay, c.reason_for_consultation, c.consulting_doctor 
                            FROM consultations c 
                            LEFT JOIN residents r ON c.resident_id = r.id 
                            LIMIT 5");
        if ($r2) {
            echo "<table border='1'><tr><th>ID</th><th>Date</th><th>Time</th><th>Name</th><th>Barangay</th><th>Reason</th><th>Doctor</th></tr>";
            while ($row2 = $r2->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row2['id'] . "</td>";
                echo "<td>" . ($row2['date_of_consultation'] ?? 'NULL') . "</td>";
                echo "<td>" . ($row2['consultation_time'] ?? 'NULL') . "</td>";
                echo "<td>" . ($row2['first_name'] ?? '') . " " . ($row2['last_name'] ?? '') . "</td>";
                echo "<td>" . ($row2['barangay'] ?? 'NULL') . "</td>";
                echo "<td>" . ($row2['reason_for_consultation'] ?? 'NULL') . "</td>";
                echo "<td>" . ($row2['consulting_doctor'] ?? 'NULL') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "Error querying consultations: " . $conn->error . "<br>";
        }
    }
} else {
    echo "Error: " . $conn->error . "<br>";
}

// Check health records
echo "<h3>Health Records Table:</h3>";
$r3 = $conn->query("SELECT COUNT(*) as cnt FROM health_records");
if ($r3) {
    $row3 = $r3->fetch_assoc();
    echo "Total health records: " . $row3['cnt'] . "<br>";

    if ($row3['cnt'] > 0) {
        echo "<h4>Sample health records (first 5):</h4>";
        $r4 = $conn->query("SELECT hr.id, hr.created_at, r.first_name, r.last_name, r.barangay, hr.v_bp, hr.v_temp 
                            FROM health_records hr 
                            LEFT JOIN residents r ON hr.resident_id = r.id 
                            LIMIT 5");
        if ($r4) {
            echo "<table border='1'><tr><th>ID</th><th>Date</th><th>Name</th><th>Barangay</th><th>BP</th><th>Temp</th></tr>";
            while ($row4 = $r4->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row4['id'] . "</td>";
                echo "<td>" . ($row4['created_at'] ?? 'NULL') . "</td>";
                echo "<td>" . ($row4['first_name'] ?? '') . " " . ($row4['last_name'] ?? '') . "</td>";
                echo "<td>" . ($row4['barangay'] ?? 'NULL') . "</td>";
                echo "<td>" . ($row4['v_bp'] ?? 'NULL') . "</td>";
                echo "<td>" . ($row4['v_temp'] ?? 'NULL') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "Error querying health records: " . $conn->error . "<br>";
        }
    }
} else {
    echo "Error: " . $conn->error . "<br>";
}

// Test the export query with current filters
echo "<h3>Testing Export Query:</h3>";
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-12 months'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
echo "Date range: $start_date to $end_date<br>";

$filters = [];
$params = [];
$types = '';
if ($start_date) { $filters[] = "c.date_of_consultation >= ?"; $params[] = $start_date; $types .= 's'; }
if ($end_date) { $filters[] = "c.date_of_consultation <= ?"; $params[] = $end_date; $types .= 's'; }
$where = '';
if ($filters) $where = 'WHERE ' . implode(' AND ', $filters);

$sql = "SELECT c.date_of_consultation, c.consultation_time, r.last_name, r.first_name, r.barangay
        FROM consultations c
        LEFT JOIN residents r ON c.resident_id = r.id
        $where
        ORDER BY c.date_of_consultation DESC
        LIMIT 10";

echo "SQL: " . $sql . "<br>";
echo "Params: " . implode(', ', $params) . "<br><br>";

if (!empty($types) && !empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = false;
        echo "Error preparing statement: " . $conn->error . "<br>";
    }
} else {
    $res = $conn->query($sql);
}

if ($res) {
    $count = 0;
    echo "<table border='1'><tr><th>Date</th><th>Time</th><th>Last Name</th><th>First Name</th><th>Barangay</th></tr>";
    while ($r = $res->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . ($r['date_of_consultation'] ?? '') . "</td>";
        echo "<td>" . ($r['consultation_time'] ?? '') . "</td>";
        echo "<td>" . ($r['last_name'] ?? '') . "</td>";
        echo "<td>" . ($r['first_name'] ?? '') . "</td>";
        echo "<td>" . ($r['barangay'] ?? '') . "</td>";
        echo "</tr>";
        $count++;
    }
    echo "</table>";
    echo "<br>Total rows returned: $count<br>";
    if (isset($stmt)) $stmt->close();
} else {
    echo "Error executing query: " . $conn->error . "<br>";
}
