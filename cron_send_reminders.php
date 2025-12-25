<?php
// cron_send_reminders.php
// Find consultations scheduled ~8 hours from now and send email reminders.
// Usage: run from CLI or schedule in Windows Task Scheduler every 15-60 minutes.

require __DIR__ . '/config.php';

// connect using config variables
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
  error_log('cron_send_reminders: DB connect error: ' . $conn->connect_error);
  exit(1);
}

// We treat "around 8 hours from now" as ±30 minutes window so cron can run hourly
$window_before = 30; // minutes
$window_after = 30;  // minutes

$now = new DateTime('now');
$targetStart = clone $now;
$targetStart->modify('+8 hours');
$targetStart->modify("-{$window_before} minutes");
$targetEnd = clone $now;
$targetEnd->modify('+8 hours');
$targetEnd->modify("+{$window_after} minutes");

$startStr = $targetStart->format('Y-m-d H:i:s');
$endStr = $targetEnd->format('Y-m-d H:i:s');

// Query consultations where scheduled datetime is between start and end, and reminder_sent=0
$sql = "SELECT id, resident_id, email, date_of_consultation, consultation_time FROM consultations WHERE (reminder_sent = 0 OR reminder_sent IS NULL) AND email IS NOT NULL AND email != '' AND (STR_TO_DATE(CONCAT(date_of_consultation, ' ', consultation_time), '%Y-%m-%d %H:%i') BETWEEN ? AND ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
  error_log('cron_send_reminders: prepare failed: ' . $conn->error);
  exit(1);
}
$stmt->bind_param('ss', $startStr, $endStr);
$stmt->execute();
$res = $stmt->get_result();

if (!$res) {
  error_log('cron_send_reminders: query failed: ' . $stmt->error);
  $stmt->close();
  $conn->close();
  exit(1);
}

$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (count($rows) === 0) {
  // nothing to do
  $conn->close();
  exit(0);
}

// Prepare PHPMailer if available, otherwise fallback to mail()
$usePHPMailer = file_exists(__DIR__ . '/phpmailer/src/PHPMailer.php');
if ($usePHPMailer) {
  require __DIR__ . '/phpmailer/src/Exception.php';
  require __DIR__ . '/phpmailer/src/PHPMailer.php';
  require __DIR__ . '/phpmailer/src/SMTP.php';
}

foreach ($rows as $r) {
  $id = (int)$r['id'];
  $to = trim($r['email']);
  // Validate email; if invalid or empty, try resident's email
  if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    $residentEmail = '';
    if (!empty($r['resident_id'])) {
      $q = $conn->prepare('SELECT email FROM residents WHERE id = ?');
      if ($q) {
        $q->bind_param('i', $r['resident_id']);
        $q->execute();
        $resQ = $q->get_result();
        if ($resQ && $resQ->num_rows > 0) {
          $rowQ = $resQ->fetch_assoc();
          $residentEmail = trim($rowQ['email'] ?? '');
        }
        $q->close();
      }
    }
    if ($residentEmail && filter_var($residentEmail, FILTER_VALIDATE_EMAIL)) {
      $to = $residentEmail;
    } else {
      error_log('cron_send_reminders: no valid email for consultation id ' . $id);
      continue; // skip this record
    }
  }
  $scheduled = $r['date_of_consultation'] . ' ' . $r['consultation_time'];

  $subject = 'Reminder: Consultation on ' . $r['date_of_consultation'] . ' at ' . $r['consultation_time'];
  $body = "Dear patient,\n\nThis is a reminder that you have a scheduled consultation on {$scheduled}. Please arrive on time or contact the Barangay Health Unit to reschedule.\n\nRegards,\nBarangay Health Unit";

  $sent = false;
  if ($usePHPMailer) {
    try {
      $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
$mail->isSMTP();
$mail->Host = SMTP_HOST;
$mail->SMTPAuth = true;
$mail->Username = SMTP_USERNAME;
$mail->Password = SMTP_PASSWORD;
$mail->SMTPSecure = SMTP_SECURE; // tls
$mail->Port = SMTP_PORT; // 587

$mail->setFrom(SMTP_USERNAME, 'Barangay Health Unit');
$mail->addAddress($to);
$mail->Subject = $subject;
$mail->Body = $body;

$mail->send();

      $sent = true;
    } catch (\PHPMailer\PHPMailer\Exception $ex) {
      error_log('cron_send_reminders: PHPMailer error for id ' . $id . ' to ' . $to . ' : ' . $ex->getMessage());
      $sent = false;
    }
  } else {
    $headers = 'From: Barangay Health Unit <' . (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'no-reply@example.com') . '>' . "\r\n" . 'Reply-To: ' . (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'no-reply@example.com') . "\r\n";
    if (mail($to, $subject, $body, $headers)) {
      $sent = true;
    } else {
      error_log('cron_send_reminders: PHP mail() failed for id ' . $id . ' to ' . $to);
      $sent = false;
    }
  }

  if ($sent) {
    // mark reminder_sent = 1
    $u = $conn->prepare('UPDATE consultations SET reminder_sent = 1 WHERE id = ?');
    if ($u) {
      $u->bind_param('i', $id);
      $u->execute();
      if ($u->affected_rows === 0) {
        error_log('cron_send_reminders: warning - updated 0 rows for id ' . $id);
      }
      $u->close();
    } else {
      error_log('cron_send_reminders: prepare update failed: ' . $conn->error);
    }
  }
}

$conn->close();
exit(0);

?>
