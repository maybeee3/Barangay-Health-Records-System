<?php
require 'config.php';

// Accept JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['id'])) {
    http_response_code(400);
    echo 'Missing consultation id';
    exit;
}
$id = (int)$input['id'];

// DB connection
$conn2 = new mysqli($servername, $username, $password, $dbname);
if ($conn2->connect_error) {
    http_response_code(500);
    echo 'DB connection error';
    exit;
}

// Fetch consultation + resident info
$stmt = $conn2->prepare("
        SELECT c.id, c.resident_id, c.email, c.date_of_consultation, c.consultation_time,
            r.first_name, r.last_name
    FROM consultations c
    LEFT JOIN residents r ON c.resident_id = r.id
    WHERE c.id = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    http_response_code(404);
    echo 'Consultation not found';
    exit;
}

$rec = $res->fetch_assoc();
$stmt->close();


$to = trim($rec['email'] ?? '');
// If consultation record does not contain email, try resident's email
if (empty($to)) {
    // try to fetch resident email
    $conn3 = new mysqli($servername, $username, $password, $dbname);
    if (!$conn3->connect_error && isset($rec['id'])) {
        // need resident_id from consultations
        $stmtR = $conn3->prepare("SELECT r.email FROM consultations c LEFT JOIN residents r ON c.resident_id = r.id WHERE c.id = ?");
        if ($stmtR) {
            $stmtR->bind_param('i', $id);
            $stmtR->execute();
            $resR = $stmtR->get_result();
            if ($resR && $resR->num_rows > 0) {
                $rrow = $resR->fetch_assoc();
                $to = trim($rrow['email'] ?? '');
            }
            $stmtR->close();
        }
    }
    if (isset($conn3) && $conn3->ping()) $conn3->close();
}

// Validate email format
if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo 'No valid recipient email found for this consultation';
    exit;
}

$subject = "Reminder: Consultation on {$rec['date_of_consultation']} {$rec['consultation_time']}";
$body = "Dear {$rec['first_name']},\n\n"
      . "This is a reminder for your scheduled consultation on "
      . "{$rec['date_of_consultation']} at {$rec['consultation_time']}.\n\n"
      . "Please contact the Barangay Health Unit if you need to reschedule.\n\n"
      . "Regards,\nBarangay Health Unit";

// If PHPMailer exists
if (file_exists(__DIR__ . '/phpmailer/src/PHPMailer.php')) {
    require __DIR__ . '/phpmailer/src/Exception.php';
    require __DIR__ . '/phpmailer/src/PHPMailer.php';
    require __DIR__ . '/phpmailer/src/SMTP.php';
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->Host = SMTP_HOST;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;

        // Debug (optional)
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer DEBUG [$level] $str");
        };

        $mail->setFrom(SMTP_USERNAME, 'Barangay Health Unit');
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
                // mark reminder_sent and set status = 'Pending' in consultations table
                $u = $conn2->prepare("UPDATE consultations SET reminder_sent = 1, status = 'Pending' WHERE id = ?");
                if ($u) { $u->bind_param('i', $id); $u->execute(); $u->close(); }
                // log activity
                try {
                    $usernameLog = session_status() === PHP_SESSION_NONE ? null : ($_SESSION['username'] ?? null);
                    if (empty($usernameLog)) {
                        // try starting session to read username if available
                        if (session_status() === PHP_SESSION_NONE) @session_start();
                        $usernameLog = $_SESSION['username'] ?? 'System';
                    }
                    if (function_exists('log_activity')) {
                        $residentId = isset($rec['resident_id']) ? intval($rec['resident_id']) : null;
                        $recipient = $to;
                        // Store structured JSON so the UI can render a friendly message
                        $payload = json_encode([
                            'title' => 'Check-Up Reminder Sent',
                            'to' => trim((($rec['first_name'] ?? '') . ' ' . ($rec['last_name'] ?? '')) ?: $recipient),
                            'appointment' => trim((string)($rec['date_of_consultation'] ?? '')) . ' ' . trim((string)($rec['consultation_time'] ?? ''))
                        ]);
                        log_activity($conn2, null, $usernameLog, 'reminder_sent', $payload, $residentId);
                    }
                } catch (Exception $e) {}
                echo "Reminder sent to {$to}";
                exit;

    } catch (\PHPMailer\PHPMailer\Exception $e) {
        // Fallback: SSL 465
        try {
            $mail->SMTPSecure = 'ssl';
            $mail->Port = 465;
                        $mail->send();
                        // mark reminder_sent and set status = 'Pending' as well
                        $u2 = $conn2->prepare("UPDATE consultations SET reminder_sent = 1, status = 'Pending' WHERE id = ?");
                        if ($u2) { $u2->bind_param('i', $id); $u2->execute(); $u2->close(); }
                        try {
                            if (function_exists('log_activity')) {
                                if (session_status() === PHP_SESSION_NONE) @session_start();
                                $usernameLog = $_SESSION['username'] ?? 'System';
                                $residentId = isset($rec['resident_id']) ? intval($rec['resident_id']) : null;
                                $payload = json_encode([
                                    'title' => 'Check-Up Reminder Sent',
                                    'to' => trim((($rec['first_name'] ?? '') . ' ' . ($rec['last_name'] ?? '')) ?: $to),
                                    'appointment' => trim((string)($rec['date_of_consultation'] ?? '')) . ' ' . trim((string)($rec['consultation_time'] ?? ''))
                                ]);
                                log_activity($conn2, null, $usernameLog, 'reminder_sent', $payload, $residentId);
                            }
                        } catch (Exception $e) {}
                        echo "Reminder sent to {$to} via SSL fallback";
                        exit;
        } catch (\PHPMailer\PHPMailer\Exception $e2) {
            http_response_code(500);
            echo "Mail error (primary): {$e->getMessage()}\nFallback error: {$e2->getMessage()}";
            exit;
        }
    }
}

// Fallback to PHP mail()
$headers = "From: Barangay Health Unit <" . SMTP_USERNAME . ">\r\n";
$headers .= "Reply-To: " . SMTP_USERNAME . "\r\n";

    if (mail($to, $subject, $body, $headers)) {
        // mark reminder_sent and set status = 'Pending'
        $conn2b = new mysqli($servername, $username, $password, $dbname);
        if (!$conn2b->connect_error) {
            $u = $conn2b->prepare("UPDATE consultations SET reminder_sent = 1, status = 'Pending' WHERE id = ?");
            if ($u) { $u->bind_param('i', $id); $u->execute(); $u->close(); }
            $conn2b->close();
        }
        echo "Reminder sent to {$to}";
    } else {
        http_response_code(500);
        echo "Failed to send mail";
    }
?>
