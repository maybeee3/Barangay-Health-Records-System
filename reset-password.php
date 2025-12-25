<?php
session_start();
include 'config.php';

$message = '';
$message_type = '';
$can_reset_password = false;

// Check if user is authorized to reset password via session
if (isset($_SESSION['code_verified']) && $_SESSION['code_verified'] === true && isset($_SESSION['reset_user_id'])) {
    $can_reset_password = true;
    $user_id = $_SESSION['reset_user_id'];
} else {
    $message = 'Please go through the password verification process first.';
    $message_type = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_reset_password) {
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($password) || empty($confirm_password)) {
        $message = 'Please enter and confirm your new password.';
        $message_type = 'error';
    } else if ($password !== $confirm_password) {
        $message = 'Passwords do not match.';
        $message_type = 'error';
    } else if (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters long.';
        $message_type = 'error';
    } else {
        // Hash the new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Update password and clear token fields
        if (isset($conn) && $conn->connect_error == null) {
            $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_password, $user_id);

            if ($update_stmt->execute()) {
                $message = 'Your password has been reset successfully. You can now log in to your account to continue accessing the system. Please <a href="login.php" class="font-medium text-green-800 underline">click here</a> to be redirected to the login page.';
                $message_type = 'success';
                
                // Clear session variables after successful reset
                unset($_SESSION['code_verified']);
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['verification_sent_to']);

                $can_reset_password = false; // Invalidate the form after successful reset
            } else {
                $message = 'Error updating password: ' . $conn->error;
                $message_type = 'error';
            }
            $update_stmt->close();
        } else {
            $message = 'Database connection error. Please try again later.';
            $message_type = 'error';
            error_log("Database connection failed or \$conn is not set in reset_password.php POST request.");
        }
    }
}

if (isset($conn)) {
    $conn->close();
}

// If connection was closed after POST, re-open for displaying the page (if needed)
if (!isset($conn) || $conn->connect_error != null) {
    include 'config.php'; // Re-include config to establish \$conn if it was closed
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Reset Password</title>
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
      <h1 class="text-2xl font-semibold mt-4 text-gray-800">Reset Your Password</h1>
      <p class="text-sm text-gray-500 mt-1">Please enter your new password below.</p>
    </div>

    <?php if (!empty($message)): ?>
      <div class="<?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> p-3 rounded-lg mb-4 text-sm">
        <?php echo $message; ?>
      </div>
    <?php endif; ?>

    <?php if ($can_reset_password): ?>
      <form class="space-y-4" method="POST" action="">
        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
          <div class="relative">
            <input type="password" id="password" name="password" placeholder="Enter your new password"
              class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-bhms focus:outline-none pr-10" required />
            <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none">
              <svg id="eyeOpenPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              <svg id="eyeClosedPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7 .223-.746.541-1.458.949-2.107M10 10l.942.942a3 3 0 11.83-3.132M14.5 14.5l-1.06-1.06a3 3 0 00-4.243-4.243m3.39-3.39l3.05-3.05M5.257 5.257l1.414 1.414L12 12l.707.707m-5.657 4.95L17.757 6.243A10.05 10.05 0 0119 12c-.187.648-.417 1.267-.687 1.868" />
              </svg>
            </button>
          </div>
        </div>
        <div>
          <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
          <div class="relative">
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your new password"
              class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-bhms focus:outline-none pr-10" required />
            <button type="button" id="toggleConfirmPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none">
              <svg id="eyeOpenConfirm" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              <svg id="eyeClosedConfirm" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7 .223-.746.541-1.458.949-2.107M10 10l.942.942a3 3 0 11.83-3.132M14.5 14.5l-1.06-1.06a3 3 0 00-4.243-4.243m3.39-3.39l3.05-3.05M5.257 5.257l1.414 1.414L12 12l.707.707m-5.657 4.95L17.757 6.243A10.05 10.05 0 0119 12c-.187.648-.417 1.267-.687 1.868" />
              </svg>
            </button>
          </div>
        </div>

        <button type="submit"
          class="w-full bg-bhms text-white py-2 rounded-lg font-semibold hover:bg-blue-700 transition">Reset Password</button>
      </form>

      <script>
        // Toggle password visibility for New Password field
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeOpenPassword = document.getElementById('eyeOpenPassword');
        const eyeClosedPassword = document.getElementById('eyeClosedPassword');

        if (togglePassword && passwordInput && eyeOpenPassword && eyeClosedPassword) {
          togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            eyeOpenPassword.classList.toggle('hidden');
            eyeClosedPassword.classList.toggle('hidden');
          });
        }

        // Toggle password visibility for Confirm New Password field
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const eyeOpenConfirm = document.getElementById('eyeOpenConfirm');
        const eyeClosedConfirm = document.getElementById('eyeClosedConfirm');

        if (toggleConfirmPassword && confirmPasswordInput && eyeOpenConfirm && eyeClosedConfirm) {
          toggleConfirmPassword.addEventListener('click', function () {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            eyeOpenConfirm.classList.toggle('hidden');
            eyeClosedConfirm.classList.toggle('hidden');
          });
        }
      </script>
    <?php else: ?>
      <div class="text-center text-gray-600">
        <p>Please go through the <a href="forgot_password.php" class="text-bhms hover:underline">Forgot Password</a> process to reset your password.</p>
      </div>
    <?php endif; ?>

    <div class="text-center mt-6 text-sm text-gray-500">
      Remember your password? <a href="login.php" class="text-bhms hover:underline">Login here</a>
    </div>
  </div>
</body>
</html>
