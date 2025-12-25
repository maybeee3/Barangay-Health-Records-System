<?php
session_start();
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get notifications for this user
$notifications = [];
$sql = "SELECT n.*, r.full_name AS resident_name, c.date_of_consultation, c.consultation_time 
        FROM notifications n 
        LEFT JOIN residents r ON n.resident_id = r.id 
        LEFT JOIN consultations c ON n.consultation_id = c.id 
        WHERE n.user_id = ? 
        ORDER BY n.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while($row = $result->fetch_assoc()) {
    // Format date for display
    $created_date = new DateTime($row['created_at']);
    $row['formatted_date'] = $created_date->format('F j, Y - g:i A');
    
    $notifications[] = $row;
}

$stmt->close();

// Also fetch consultations scheduled within the next 2 hours (reminder window)
$upcomingSql = "SELECT c.id, c.resident_id, c.email AS resident_email, c.consulting_doctor, c.date_of_consultation, c.consultation_time, r.first_name, r.last_name 
                FROM consultations c 
                LEFT JOIN residents r ON c.resident_id = r.id 
                /* Include all consultations scheduled within the next 2 hours for display */
                WHERE CAST(CONCAT(c.date_of_consultation, ' ', c.consultation_time) AS DATETIME) 
                    BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 HOUR) 
                ORDER BY c.date_of_consultation, c.consultation_time";

$uStmt = $conn->prepare($upcomingSql);
if ($uStmt) {
    $uStmt->execute();
    $uResult = $uStmt->get_result();
    while ($uRow = $uResult->fetch_assoc()) {
        // Create a transient notification-like item for display
        $dt = new DateTime($uRow['date_of_consultation'] . ' ' . $uRow['consultation_time']);
        $formatted = $dt->format('F j, Y - g:i A');

        $notificationsItem = [
            'id' => 'cons_' . $uRow['id'],
            'title' => 'Upcoming Consultation',
            'message' => 'Consultation for ' . trim(($uRow['first_name'] ?? '') . ' ' . ($uRow['last_name'] ?? '')) . ' scheduled at ' . $formatted,
            'is_read' => 0,
            'created_at' => $uRow['date_of_consultation'] . ' ' . $uRow['consultation_time'],
            'consulting_doctor' => $uRow['consulting_doctor'] ?? '',
            'resident_email' => $uRow['resident_email'] ?? '',
            'formatted_date' => $formatted
        ];

        // Prepend so upcoming items show first
        array_unshift($notifications, $notificationsItem);
    }
    $uStmt->close();
}

// Attempt to send reminder emails for the upcoming consultations we just fetched.
// We will mark reminder_sent = 1 only if email sending succeeds to avoid duplicates.
// Load PHPMailer
require_once 'phpmailer/PHPMailer-master/src/Exception.php';
require_once 'phpmailer/PHPMailer-master/src/PHPMailer.php';
require_once 'phpmailer/PHPMailer-master/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

$sentIds = [];
foreach ($notifications as $note) {
    if (!is_string($note['id']) || strpos($note['id'], 'cons_') !== 0) continue;
    // extract consultation id
    $cid = (int)substr($note['id'], 5);
    if ($cid <= 0) continue;

    // fetch consultation row to get emails and details (fresh)
    $q = $conn->prepare("SELECT email, consulting_doctor, date_of_consultation, consultation_time FROM consultations WHERE id = ? AND reminder_sent = 0 LIMIT 1");
    if (!$q) continue;
    $q->bind_param('i', $cid);
    $q->execute();
    $res = $q->get_result();
    $c = $res->fetch_assoc();
    $q->close();
    if (!$c) continue;

    $toResident = trim($c['email']);
    $consultant = trim($c['consulting_doctor'] ?? '');
    // If consulting_doctor contains an @, treat it as an email address
    $toConsultant = (strpos($consultant, '@') !== false) ? $consultant : '';

    // Build email content
    $when = (new DateTime($c['date_of_consultation'] . ' ' . $c['consultation_time']))->format('F j, Y - g:i A');
    $subject = 'Consultation Reminder — ' . $when;
    $body = "<p>This is a reminder for the upcoming consultation scheduled on <strong>{$when}</strong>.</p>";
    $body .= "<p>Please be ready for the consultation.</p>";

    // If no recipient emails, skip sending but leave the notification visible
    if (empty($toResident) && empty($toConsultant)) continue;

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->setFrom(SMTP_USERNAME, 'Barangay Health Monitoring');
        if (!empty($toResident)) $mail->addAddress($toResident);
        if (!empty($toConsultant)) $mail->addAddress($toConsultant);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();

        // mark as sent
        $u = $conn->prepare("UPDATE consultations SET reminder_sent = 1 WHERE id = ?");
        if ($u) {
            $u->bind_param('i', $cid);
            $u->execute();
            $u->close();
        }

        $sentIds[] = $cid;
    } catch (PHPMailerException $e) {
        // Log error to PHP error log; leave reminder_sent = 0 so it can retry later
        error_log('Reminder email send failed for consultation ' . $cid . ': ' . $e->getMessage());
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | Health Monitoring System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <header class="bg-white border-b border-gray-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="index.php" class="text-xl font-bold text-blue-600">HMS</a>
                    </div>
                    <nav class="ml-6 flex space-x-8">
                        <a href="index.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Dashboard</a>
                        <a href="consultations.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Consultations</a>
                        <a href="notifications.php" class="border-blue-500 text-blue-600 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Notifications</a>
                    </nav>
                </div>
                <div>
                    <a href="landing-page.php" class="text-gray-500 hover:text-gray-700">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Notifications</h1>
            <?php if (count($notifications) > 0): ?>
            <button id="mark-all-read-btn" class="text-sm text-blue-600 hover:text-blue-800">Mark all as read</button>
            <?php endif; ?>
        </div>

        <div class="bg-white shadow overflow-hidden sm:rounded-md">
            <?php if (count($notifications) > 0): ?>
                <ul class="divide-y divide-gray-200">
                    <?php foreach ($notifications as $notification): 
                        $note_id_str = (string)$notification['id'];
                        $is_consult = strpos($note_id_str, 'cons_') === 0;
                    ?>
                        <li class="notification-item <?php echo $notification['is_read'] ? '' : 'bg-blue-50'; ?>" data-id="<?php echo htmlspecialchars($notification['id']); ?>">
                            <div class="px-4 py-4 sm:px-6 cursor-pointer hover:bg-gray-50">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-medium text-blue-600 truncate">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                    </p>
                                    <div class="ml-2 flex-shrink-0 flex">
                                        <p class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $notification['is_read'] ? 'bg-gray-100 text-gray-800' : 'bg-blue-100 text-blue-800'; ?>">
                                            <?php echo $notification['is_read'] ? 'Read' : 'New'; ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-2 sm:flex sm:justify-between">
                                    <div class="sm:flex flex-col">
                                        <p class="flex items-center text-sm text-gray-700">
                                            <?php echo htmlspecialchars($notification['message']); ?>
                                        </p>
                                        <?php if ($is_consult): ?>
                                            <p class="text-sm text-gray-600 mt-1">Consultant: <?php echo htmlspecialchars($notification['consulting_doctor'] ?? 'Not specified'); ?></p>
                                            <?php if (!empty($notification['resident_email'])): ?>
                                                <p class="text-sm text-gray-600 mt-1">Resident email: <?php echo htmlspecialchars($notification['resident_email']); ?></p>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-2 flex items-center text-sm text-gray-500 sm:mt-0">
                                        <span>
                                            <?php echo htmlspecialchars($notification['formatted_date']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="py-8 text-center text-gray-500">
                    <p>No notifications to display</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mark individual notification as read (skip transient upcoming-consultation items)
            document.querySelectorAll('.notification-item').forEach(item => {
                const nid = item.dataset.id || '';
                if (nid.startsWith('cons_')) {
                    // upcoming consultation reminder - do not attach mark-as-read behavior
                    item.classList.add('bg-yellow-50');
                    return;
                }
                item.addEventListener('click', function() {
                    const notificationId = this.dataset.id;
                    markAsRead(notificationId, this);
                });
            });

            // Mark all notifications as read
            const markAllBtn = document.getElementById('mark-all-read-btn');
            if (markAllBtn) {
                markAllBtn.addEventListener('click', markAllAsRead);
            }

            function markAsRead(notificationId, element) {
                const formData = new FormData();
                formData.append('notification_id', notificationId);

                fetch('mark_notification_read.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        element.classList.remove('bg-blue-50');
                        const statusBadge = element.querySelector('.rounded-full');
                        if (statusBadge) {
                            statusBadge.classList.remove('bg-blue-100', 'text-blue-800');
                            statusBadge.classList.add('bg-gray-100', 'text-gray-800');
                            statusBadge.textContent = 'Read';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error marking notification as read:', error);
                });
            }

            function markAllAsRead() {
                const formData = new FormData();
                formData.append('mark_all', 'true');

                fetch('mark_notification_read.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.querySelectorAll('.notification-item').forEach(item => {
                            item.classList.remove('bg-blue-50');
                            const statusBadge = item.querySelector('.rounded-full');
                            if (statusBadge) {
                                statusBadge.classList.remove('bg-blue-100', 'text-blue-800');
                                statusBadge.classList.add('bg-gray-100', 'text-gray-800');
                                statusBadge.textContent = 'Read';
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error marking all notifications as read:', error);
                });
            }
        });
    </script>
</body>
</html>