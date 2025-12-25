<?php
session_start();
// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = $_POST['email'] ?? '';
  $password = $_POST['password'] ?? '';
  if ($email === 'brgysanisidrohealth@gmail.com' && $password === 'Healthmonitoring') {
    // Successful login: set session and redirect to homepage
    $_SESSION['username'] = 'Administrator';
    $_SESSION['email'] = $email;
    $_SESSION['role'] = 'Administrator';
    // clear any previous messages
    unset($_SESSION['login_message']);
    header('Location: homepage.php');
    exit();
  } else {
    $login_message = 'Invalid email or password.';
  }
}
// Example: Show a login message if set (e.g., after failed login)
if (isset($_SESSION['login_message'])) {
  $login_message = $_SESSION['login_message'];
  unset($_SESSION['login_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Brgy. San Isidro Health Monitoring</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    :root{--primary:#4aa3ff;--accent:#8ee7d6}
    html,body{height:100%;margin:0;font-family:'Poppins',Arial,sans-serif}
    body{background:url('Background.jpeg') center/cover no-repeat fixed;display:flex;align-items:center;justify-content:center}
    .overlay{position:fixed;inset:0;pointer-events:none;}
    .panel{width:880px;max-width:95%;display:flex;border-radius:10px;overflow:hidden;box-shadow:0 10px 30px rgba(2,6,23,0.15);position:relative;z-index:1}
    .left{
  flex: 0 0 360px;
  padding: 42px 36px;
  background: #2563eb;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.left h1{
  margin: 6px 0 18px;
  font-weight: 700;
  letter-spacing: 2px;
  color: #0043d4ff;
}
.form{width:100%;max-width:260px}
    .field{position:relative;margin-bottom:12px}
    .input{width:100%;padding:12px 16px;border-radius:999px;border:none;background:#fff;box-shadow:inset 0 -2px 6px rgba(0,0,0,0.03);font-size:14px}
    .input::placeholder{color:#9aaec0}
    .eye{position:absolute;right:14px;top:50%;transform:translateY(-50%);width:20px;height:20px;opacity:0.6}
    .forgot{display:block;text-align:left;font-size:13px;color:#0043d4ff;margin:6px 0}
    .btn{display:block;margin:10px 0;padding:10px;border-radius:999px;background:linear-gradient(180deg,var(--primary),#79b9f9);color:#fff;border:none;font-weight:600;cursor:pointer;width:100%}
    .google-btn{display:flex;align-items:center;gap:10px;justify-content:center;padding:10px;border-radius:999px;background:#fff;border:none;box-shadow:0 6px 16px rgba(11,75,120,0.06);width:100%;font-weight:600}
    .google-icon{width:18px;height:18px}
    .right{flex:0 0 420px;background:rgba(255,255,255,0.45);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;text-align:center;backdrop-filter:blur(4px)}
    .right img{width:120px;height:120px;object-fit:contain}
    .right h2{margin-top:12px;color:#23598a}
    .caption{color:#23598a;font-weight:700;margin-top:8px}
    @media (max-width:760px){.panel{flex-direction:column}.left,.right{width:100%}}
  </style>
  <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>
  <div class="overlay" aria-hidden="true"></div>

  <div class="panel" role="main">
    <div class="left">
      <h1>LOGIN</h1>
      <?php if (isset($login_message)): ?>
        <div style="background:#fff5f5;color:#b00;padding:8px;border-radius:8px;margin-bottom:10px;width:260px;text-align:center;"><?php echo htmlspecialchars($login_message); ?></div>
      <?php endif; ?>

      <form method="post" action="" class="form" novalidate>
        <div class="field">
          <input class="input" type="email" name="email" placeholder="Email" required autocomplete="username">
        </div>
        <div class="field">
          <input id="pwd" class="input" type="password" name="password" placeholder="Password" required autocomplete="current-password">
          <!-- decorative eye icon -->
          
            <path d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7z" stroke="'#2563eb'" stroke-opacity="0.7" stroke-width="1.2"/>
            <circle cx="12" cy="12" r="3" stroke="'#2563eb'" stroke-opacity="0.7" stroke-width="1.2"/>
          </svg>
        </div>
        <a class="forgot" href="forgot-password.php">Forgot password?</a>
        <button type="submit" href="homepage.php" class="btn">Login</button>

        <div class="field" style="display:flex;justify-content:center;margin-top:8px">
          <div id="g_id_onload"
               data-client_id="497472237712-go5tktl8mkumop68fg3vjo4o4kctavk9.apps.googleusercontent.com"
               data-callback="handleCredentialResponse"
               data-auto_prompt="false">
          </div>
        </div>
      </form>

      <div class="field" style="display:flex;justify-content:center;margin-top:8px;width:260px">
        <div class="g_id_signin"
             data-type="standard"
             data-shape="rectangular"
             data-theme="outline"
             data-text="continue_with"
             data-size="large"
             data-logo_alignment="left">
        </div>
      </div>
    </div>

    <div class="right">
      <img src="Brgy. San Isidro-LOGO.png" alt="logo">
      <div class="caption">BARANGAY HEALTH<br>MONITORING</div>
    </div>
  </div>

  

  

<script>
  function handleCredentialResponse(response) {
    // This is your Google credential (JWT)
    console.log("Google Credential:", response.credential);
    // Send credential to server for verification
    fetch('verify-google.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ credential: response.credential })
    }).then(async res => {
      const data = await res.json().catch(() => ({}));
      if (res.ok && data.success) {
        // successful login
        window.location.href = 'homepage.php';
      } else {
        // show error message to user
        const msg = data && data.message ? data.message : 'Google sign-in failed.';
        alert(msg);
      }
    }).catch(err => {
      console.error('Verification error', err);
      alert('Unable to verify Google sign-in. Please try again later.');
    });
  }

  // Make the custom Google button trigger the Google Sign-In prompt
  window.onload = function() {
    const customBtn = document.getElementById('customGoogleLogin');
    if (customBtn) {
      customBtn.addEventListener('click', function() {
        if (window.google && window.google.accounts && window.google.accounts.id) {
          window.google.accounts.id.prompt();
        } else {
          alert('Google Sign-In is not ready yet. Please try again in a moment.');
        }
      });
    }
  };
</script>

</body>
</html>
