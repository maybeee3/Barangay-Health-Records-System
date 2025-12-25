<?php
session_start();
include 'config.php';

// simple auth guard
if (!isset($_SESSION['username'])) {
  header('Location: login.php');
  exit();
}

// Expect POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: add-records.php');
  exit();
}

// Collect form values (basic sanitization)
$resident_id = isset($_POST['patient_id']) ? intval($_POST['patient_id']) : 0;
$record_date = $_POST['record_date'] ?? $_POST['v_date'] ?? date('Y-m-d');
$record_time = $_POST['record_time'] ?? $_POST['v_time'] ?? date('H:i');
$contact_no = $_POST['contact_no'] ?? '';
$email = $_POST['email'] ?? '';
$reason = $_POST['reason_for_consultation'] ?? '';
$treatment = $_POST['treatment'] ?? '';
$assessment = $_POST['assessment'] ?? '';
$consulting_doctor = $_POST['consulting_doctor'] ?? '';
$created_by = $_POST['created_by'] ?? ($_SESSION['username'] ?? '');

if ($resident_id <= 0) {
  // Missing resident selection
  $_SESSION['flash_error'] = 'Please select a patient before saving the record.';
  header('Location: add-records.php');
  exit();
}

// 1) Ensure `health_records` table exists and insert full record so it appears on records.php
$inserted = false;
$create_sql = "CREATE TABLE IF NOT EXISTS health_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  resident_id INT NOT NULL,
  record_date DATE DEFAULT NULL,
  record_time TIME DEFAULT NULL,
  contact_no VARCHAR(50) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  reason TEXT DEFAULT NULL,
  treatment TEXT DEFAULT NULL,
  assessment TEXT DEFAULT NULL,
  consulting_doctor VARCHAR(255) DEFAULT NULL,
  v_temp VARCHAR(20) DEFAULT NULL,
  v_wt VARCHAR(20) DEFAULT NULL,
  v_ht VARCHAR(20) DEFAULT NULL,
  v_bp VARCHAR(50) DEFAULT NULL,
  v_rr VARCHAR(20) DEFAULT NULL,
  v_pr VARCHAR(20) DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (resident_id) REFERENCES residents(id)
);";
$conn->query($create_sql);

// Insert the full record
$insert_sql = "INSERT INTO health_records (resident_id, record_date, record_time, contact_no, email, reason, treatment, assessment, consulting_doctor, v_temp, v_wt, v_ht, v_bp, v_rr, v_pr, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($insert_sql);
if ($stmt) {
  $v_temp = $_POST['v_temp'] ?? null;
  $v_wt = $_POST['v_wt'] ?? null;
  $v_ht = $_POST['v_ht'] ?? null;
  $v_bp = $_POST['v_bp'] ?? null;
  $v_rr = $_POST['v_rr'] ?? null;
  $v_pr = $_POST['v_pr'] ?? null;
  $stmt->bind_param('isssssssssssssss', $resident_id, $record_date, $record_time, $contact_no, $email, $reason, $treatment, $assessment, $consulting_doctor, $v_temp, $v_wt, $v_ht, $v_bp, $v_rr, $v_pr, $created_by);
  if ($stmt->execute()) {
    $inserted = true;
    $new_id = $stmt->insert_id;
    $_SESSION['last_inserted_record_id'] = $new_id;
    // Log activity: record saved
    try {
      $usernameLog = $_SESSION['username'] ?? $created_by ?? 'System';
      if (function_exists('log_activity')) {
        $msg = "Saved health record #{$new_id} for resident ID {$resident_id}";
        log_activity($conn, null, $usernameLog, 'record_saved', $msg, $resident_id);
      }
    } catch (Exception $e) {}
  } else {
    $_SESSION['flash_error'] = 'Failed to save record: ' . htmlspecialchars($stmt->error);
  }
  $stmt->close();
} else {
  $_SESSION['flash_error'] = 'Failed to prepare record insert: ' . htmlspecialchars($conn->error);
}

// 2) Send the record copy to the patient's email if provided (use PHPMailer)
if (!empty($email)) {
  // Load PHPMailer (use correct path under phpmailer/src)
  $base = __DIR__ . '/phpmailer/src/';
  require_once $base . 'Exception.php';
  require_once $base . 'PHPMailer.php';
  require_once $base . 'SMTP.php';

  $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
  try {
    // SMTP settings from config
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port = SMTP_PORT;

    $mail->setFrom(SMTP_USERNAME, 'Barangay Health Unit');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Your Patient Record from Barangay Health Unit';

    $body = '<p>Dear patient,</p>';
    $body .= '<p>This is a copy of your patient record dated <strong>' . htmlspecialchars($record_date) . ' ' . htmlspecialchars($record_time) . '</strong>.</p>';
    $body .= '<h4>Consultation details</h4>';
    $body .= '<p><strong>Consulting Doctor:</strong> ' . htmlspecialchars($consulting_doctor) . '</p>';
    $body .= '<p><strong>Reason / Complaint:</strong><br/>' . nl2br(htmlspecialchars($reason)) . '</p>';
    $body .= '<p><strong>Treatment / Diagnosis:</strong><br/>' . nl2br(htmlspecialchars($treatment)) . '</p>';
    $body .= '<p><strong>Assessment:</strong><br/>' . nl2br(htmlspecialchars($assessment)) . '</p>';
    $body .= '<h4>Vitals</h4>';
    $body .= '<ul>';
    $body .= '<li>Temperature: ' . htmlspecialchars($_POST['v_temp'] ?? '') . '</li>';
    $body .= '<li>Weight: ' . htmlspecialchars($_POST['v_wt'] ?? '') . '</li>';
    $body .= '<li>Height: ' . htmlspecialchars($_POST['v_ht'] ?? '') . '</li>';
    $body .= '<li>Blood Pressure: ' . htmlspecialchars($_POST['v_bp'] ?? '') . '</li>';
    $body .= '<li>Respiratory Rate: ' . htmlspecialchars($_POST['v_rr'] ?? '') . '</li>';
    $body .= '<li>Pulse Rate: ' . htmlspecialchars($_POST['v_pr'] ?? '') . '</li>';
    $body .= '</ul>';
    $body .= '<p>If you have questions, contact the Barangay Health Unit.</p>';

    $mail->Body = $body;
    $mail->AltBody = strip_tags(str_replace(['<br/>','<br>','</p>','</h4>'], "\n", $body));

    $mail->send();
    $_SESSION['flash_success'] = 'Record saved and emailed to patient.';
    // Log activity: record emailed
    try {
      $usernameLog = $_SESSION['username'] ?? $created_by ?? 'System';
      if (function_exists('log_activity')) {
        $msg = "Emailed health record #{$new_id} to {$email} for resident ID {$resident_id}";
        log_activity($conn, null, $usernameLog, 'record_emailed', $msg, $resident_id);
      }
    } catch (Exception $e) {}
  } catch (\PHPMailer\PHPMailer\Exception $e) {
    // Email failed, but we still proceed
    $_SESSION['flash_error'] = 'Record saved but email failed to send: ' . $e->getMessage();
    try {
      $usernameLog = $_SESSION['username'] ?? $created_by ?? 'System';
      if (function_exists('log_activity')) {
        $msg = "Failed to email health record #{$new_id} to {$email} for resident ID {$resident_id}: " . $e->getMessage();
        log_activity($conn, null, $usernameLog, 'record_email_failed', $msg, $resident_id);
      }
    } catch (Exception $e2) {}
  }
} else {
  if ($inserted) $_SESSION['flash_success'] = 'Record saved.';
}

$conn->close();

// Redirect back to records page
header('Location: records.php');
exit();

?>