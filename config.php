<?php
$servername = "localhost";
$username = "root"; // Default XAMPP username
$password = "";     // Default XAMPP password
$dbname = "health_monitoring"; // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Define API key for email validation (e.g., Abstract API)
define('ABSTRACT_API_KEY', '63f3f52503c440c8a12b0e9b51df485e'); // Replace with your actual Abstract API Key

// SMTP settings for outgoing mail (used by send-reminder.php). Fill these with your SMTP provider details.
// Example for Gmail SMTP (requires App Password / OAuth2): host=smtp.gmail.com, secure='tls', port=587
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'brgysanisidrohealth@gmail.com');
define('SMTP_PASSWORD', 'qeaizirmbznswhgx');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');

?>

<?php
// Simple activity logging helper. Inserts into `activity_logs` table (creates it if missing).
function log_activity($conn, $user_id, $username, $action_type, $message, $target_resident_id = null) {
    if (!($conn instanceof mysqli)) return false;
    // Ensure table exists
    $conn->query("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        username VARCHAR(100) DEFAULT NULL,
        action_type VARCHAR(100) DEFAULT NULL,
        message TEXT,
        target_resident_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Prepare and insert (use escaping to avoid bind edge-cases with NULLs)
    $u = is_null($user_id) ? 'NULL' : intval($user_id);
    $t = is_null($target_resident_id) ? 'NULL' : intval($target_resident_id);
    $userEsc = $conn->real_escape_string($username ?? '');
    $atypeEsc = $conn->real_escape_string($action_type ?? '');
    $msgEsc = $conn->real_escape_string($message ?? '');
    $sql = "INSERT INTO activity_logs (user_id, username, action_type, message, target_resident_id) VALUES (" . ($u==='NULL'?'NULL':intval($u)) . ", '" . $userEsc . "', '" . $atypeEsc . "', '" . $msgEsc . "', " . ($t==='NULL'?'NULL':intval($t)) . ")";
    return (bool)$conn->query($sql);
}

?>
