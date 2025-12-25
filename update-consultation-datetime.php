<?php
session_start();
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: consultations.php');
    exit();
}

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$date = isset($_POST['date_of_consultation']) ? $_POST['date_of_consultation'] : '';
$time = isset($_POST['consultation_time']) ? $_POST['consultation_time'] : '';

// Basic validation
if ($id <= 0 || !$date || !$time) {
    header('Location: view-consultation.php?id=' . $id . '&updated=0&error=invalid');
    exit();
}

// Validate date format YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    header('Location: view-consultation.php?id=' . $id . '&updated=0&error=date');
    exit();
}

// Validate time format HH:MM
if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
    header('Location: view-consultation.php?id=' . $id . '&updated=0&error=time');
    exit();
}

// Check conflict
$sql = "SELECT COUNT(*) FROM consultations WHERE date_of_consultation = ? AND consultation_time = ? AND id <> ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('ssi', $date, $time, $id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    if ($count > 0) {
        header('Location: view-consultation.php?id=' . $id . '&updated=0&error=conflict');
        exit();
    }
} else {
    header('Location: view-consultation.php?id=' . $id . '&updated=0&error=db');
    exit();
}

// Perform update
$sqlu = "UPDATE consultations SET date_of_consultation = ?, consultation_time = ? WHERE id = ?";
$stmtu = $conn->prepare($sqlu);
if ($stmtu) {
    $stmtu->bind_param('ssi', $date, $time, $id);
    if ($stmtu->execute()) {
        $stmtu->close();
        header('Location: view-consultation.php?id=' . $id . '&updated=1');
        exit();
    } else {
        $stmtu->close();
        header('Location: view-consultation.php?id=' . $id . '&updated=0&error=update');
        exit();
    }
} else {
    header('Location: view-consultation.php?id=' . $id . '&updated=0&error=prepare');
    exit();
}

?>
