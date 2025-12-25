<?php
session_start();
// Check if user is coming from forgot-password.php and OTP is set
if (!isset($_SESSION['reset_user_id'])) {
    header('Location: forgot-password.php');
    exit();
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_otp = trim($_POST['otp'] ?? '');
    $user_id = $_SESSION['reset_user_id'];
    require 'config.php';
    $stmt = $conn->prepare("SELECT reset_token, reset_token_expires_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $db_otp = $row['reset_token'];
        $expires_at = $row['reset_token_expires_at'];
        if (date('Y-m-d H:i:s') > $expires_at) {
            $message = 'The verification code has expired. Please request a new one.';
        } elseif (trim($input_otp) === trim((string)$db_otp)) {
            // OTP is correct, proceed to reset password
            $_SESSION['code_verified'] = true;
            header('Location: reset-password.php');
            exit();
        } else {
            $message = 'Invalid verification code. Please try again.';
        }
    } else {
        $message = 'User not found.';
    }
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: Inter, sans-serif; }</style>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#f8fafc] to-white">
    <div class="w-full max-w-md bg-white p-8 rounded-2xl shadow-lg">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-semibold mt-4 text-gray-800">Enter Verification Code</h1>
            <p class="text-sm text-gray-500 mt-1">A 6-digit code was sent to your email. Enter it below to continue.</p>
        </div>
        <?php if (!empty($message)): ?>
            <div class="bg-red-100 text-red-800 p-3 rounded-lg mb-4 text-sm">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        <form class="space-y-4" method="POST" action="">
            <div>
                <label for="otp" class="block text-sm font-medium text-gray-700 mb-1">Verification Code</label>
                <input type="text" id="otp" name="otp" maxlength="6" pattern="\d{6}" placeholder="Enter 6-digit code" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-bhms focus:outline-none" required />
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg font-semibold hover:bg-blue-700 transition">Verify Code</button>
        </form>
        <div class="text-center mt-6 text-sm text-gray-500">
            Didn't receive the code? <a href="forgot-password.php" class="text-bhms hover:underline">Resend</a>
        </div>
    </div>
</body>
</html>
