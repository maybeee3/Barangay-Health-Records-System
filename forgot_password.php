<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Adjust this path if your PHPMailer files are in a different location
require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier']);

    if (empty($identifier)) {
        $message = 'Please enter your active Gmail account or phone number.';
        $message_type = 'error';
    } else {
        if (isset($conn) && $conn->connect_error == null) {
           
      $stmt = $conn->prepare("SELECT id, email FROM users WHERE email = ?");
      if (!$stmt) {
        die('Prepare failed: ' . $conn->error);
      }
      $stmt->bind_param("s", $identifier);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        $user_email = $user['email'];

                // Generate a 6-digit numeric verification code
                $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes')); // Code valid for 10 minutes

                // Store code and expiry in the database
                $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?");
                $update_stmt->bind_param("ssi", $verification_code, $expires_at, $user_id);

                if ($update_stmt->execute()) {
                    // Attempt to send email using PHPMailer if an email is available for the user
                    if (!empty($user_email)) {
                        $mail = new PHPMailer(true);
                        try {
                            //Server settings
                            // $mail->SMTPDebug = SMTP::DEBUG_SERVER;      // Enable verbose debug output (for testing)
                            $mail->isSMTP();                             // Send using SMTP
                            $mail->Host       = 'smtp.gmail.com';        // Set the SMTP server to send through
                            $mail->SMTPAuth   = true;                    // Enable SMTP authentication
                            $mail->Username   = 'ajmaclld@gmail.com';    // <--- REPLACE with your full Gmail address, e.g., 'your.email@gmail.com'
                            // >>> PASTE YOUR 16-CHARACTER GMAIL APP PASSWORD HERE <<<
                            $mail->Password   = 'iuqmtqurovjgjnyr'; // <--- REPLACE with your generated 16-character Gmail App Password
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption
                            $mail->Port       = 587;                     // TCP port to connect to

                            //Recipients
                            $mail->setFrom('no-reply@yourdomain.com', 'BHMS Password Reset');
                            $mail->addAddress($user_email);     // Add a recipient

                            // Content
                            $mail->isHTML(true);                                  // Set email format to HTML
                            $mail->Subject = 'Password Reset Verification Code';
                            $mail->Body    = 'Hello,<br><br>Your password reset verification code is: <strong>' . htmlspecialchars($verification_code) . '</strong><br><br>This code is valid for 10 minutes. If you did not request a password reset, please ignore this email.<br><br>Best regards,<br>BHMS Team';
                            $mail->AltBody = 'Hello,\n\nYour password reset verification code is: ' . htmlspecialchars($verification_code) . '\n\nThis code is valid for 10 minutes. If you did not request a password reset, please ignore this email.\n\nBest regards,\nBHMS Team';

                            $mail->send();
                            $message = 'A verification code has been sent to your email. Please check your inbox (and spam folder).';
                            $message_type = 'success';

                        } catch (Exception $e) {
                            $message = "Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
                            $message_type = 'error';
                            error_log("PHPMailer Error: {$mail->ErrorInfo}");
                        }
                    } else {
                      $message = 'No active email associated with this account to send a code.';
                      $message_type = 'error';
                    }

                    // Redirect to the verification code entry page regardless of email/SMS sending success (if code stored successfully)
                    if ($message_type === 'success') {
                      $_SESSION['reset_user_id'] = $user_id;
                        $_SESSION['verification_sent_to'] = $identifier; // Or $user_email for more specific display
                      header('Location: verify-google-otp.php');
                      exit();
                    }

                } else {
                    $message = 'Error saving verification code to database: ' . $conn->error;
                    $message_type = 'error';
                }
                $update_stmt->close();
            } else {
                $message = 'No account found with that email or phone number.';
                $message_type = 'error';
            }
            $stmt->close();
            $conn->close();
        } else {
            $message = 'Database connection error. Please try again later.';
            $message_type = 'error';
            error_log("Database connection failed or \$conn is not set in forgot_password.php POST request.");
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Forgot Password</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: { bhms: { DEFAULT: '#2563eb', light: '#e6f0ff' } },
          fontFamily: { inter: ['Inter', 'sans-serif'] }
        }
      }
    }
  </script>
  <style>
    body { font-family: Inter, sans-serif; }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#f8fafc] to-white">
  <div class="w-full max-w-md bg-white p-8 rounded-2xl shadow-lg">
    <div class="text-center mb-6">
      <h1 class="text-2xl font-semibold mt-4 text-gray-800">Forgot Your Password?</h1>
      <p class="text-sm text-gray-500 mt-1">To reset your password, please enter your active Gmail account associated with your account. A verification code will be sent to the Gmail you provided. Make sure your email is active so you can receive the code. Enter the code to proceed with creating a new password.</p>
    </div>

    <?php if (!empty($message)): ?>
      <div class="<?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> p-3 rounded-lg mb-4 text-sm">
        <?php echo $message; ?>
      </div>
    <?php endif; ?>

    <form class="space-y-4" method="POST" action="forgot-password.php">
      <div>
        <label for="identifier" class="block text-sm font-medium text-gray-700 mb-1">Email or Phone Number</label>
        <input type="text" id="identifier" name="identifier" placeholder="Enter your email or phone number"
          class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-bhms focus:outline-none" required />
      </div>

      <button type="submit"
        class="w-full bg-bhms text-white py-2 rounded-lg font-semibold hover:bg-blue-700 transition">Send Verification Code</button>
    </form>

    <div class="text-center mt-6 text-sm text-gray-500">
      Remember your password? <a href="login.php" class="text-bhms hover:underline">Login here</a>
    </div>
  </div>
</body>
</html>
