<?php
session_start();
include 'config.php';
if (!isset($_SESSION['username'])) { header('Location: login.php'); exit(); }

$settings_file = __DIR__ . '/settings.json';
$defaults = [
  'system_name' => 'Barangay Health Monitoring System',
  'barangay_name' => '',
  'address' => '',
  'contact_number' => '',
  'official_email' => '',
  'logo_path' => 'assets/img/brand_logo.png'
];

$settings = $defaults;
if (file_exists($settings_file)) {
  $json = file_get_contents($settings_file);
  $data = json_decode($json, true);
  if (is_array($data)) $settings = array_merge($defaults, $data);
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // sanitize inputs
  $system_name = trim($_POST['system_name'] ?? '');
  $barangay_name = trim($_POST['barangay_name'] ?? '');
  $address = trim($_POST['address'] ?? '');
  $contact_number = trim($_POST['contact_number'] ?? '');
  $official_email = trim($_POST['official_email'] ?? '');

  // handle logo upload
  $logo_path = $settings['logo_path'];
  if (!empty($_FILES['logo']['name'])) {
    $allowed = ['image/png','image/jpeg','image/jpg','image/gif'];
    if (in_array($_FILES['logo']['type'], $allowed)) {
      $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
      $target_dir = __DIR__ . '/assets/img/';
      if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
      $target_file = $target_dir . 'brand_logo.' . $ext;
      if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
        // store web path
        $logo_path = 'assets/img/brand_logo.' . $ext;
      } else {
        $message = 'Failed to move uploaded logo file.';
      }
    } else {
      $message = 'Invalid logo file type. Allowed: PNG, JPG, GIF.';
    }
  }

  // save settings
  $save = [
    'system_name' => $system_name ?: $defaults['system_name'],
    'barangay_name' => $barangay_name,
    'address' => $address,
    'contact_number' => $contact_number,
    'official_email' => $official_email,
    'logo_path' => $logo_path
  ];
  if (file_put_contents($settings_file, json_encode($save, JSON_PRETTY_PRINT))) {
    $message = $message ? $message : 'Settings saved successfully.';
    $settings = array_merge($settings, $save);
  } else {
    $message = 'Failed to save settings file. Check file permissions.';
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>System Settings</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-sans">
  <header class="bg-blue-700 text-white fixed inset-x-0 top-0 z-40">
    <div class="max-w-full mx-auto flex items-center gap-4 py-4 px-6">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-white/10 shadow-md flex items-center justify-center">
          <img src="Brgy. San Isidro-LOGO.png" alt="Brgy. San Isidro Logo" class="w-full h-full object-cover rounded-full">
        </div>
        <div>
          <div class="text-sm text-white/90">Barangay Health</div>
          <div class="text-lg font-semibold text-white">Monitoring System</div>
        </div>
      </div>

      <nav class="hidden md:flex items-center gap-6 ml-auto no-print">
        <a href="homepage.php" class="text-sm font-medium text-white/90 hover:text-white transition">Dashboard</a>
        <a href="residents.php" class="text-sm font-medium text-white/90 hover:text-white transition">Residents</a>
        <a href="consultations.php" class="text-sm font-medium text-white/90 hover:text-white transition">Consultations</a>
        <a href="records.php" class="text-sm font-medium text-white/90 hover:text-white transition">Records</a>
        <a href="reports.php" class="text-sm font-medium text-white/90 hover:text-white transition">Reports</a>
      </nav>

      <div class="flex items-center gap-4 ml-4 no-print">
        <button id="notifBtn" aria-expanded="false" aria-controls="notifPanel" class="relative p-2 rounded-lg bg-white/10 hover:bg-white/20 transition" title="Notifications">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white/90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118.5 14.5V11a6.5 6.5 0 10-13 0v3.5c0 .538-.214 1.055-.595 1.445L3 17h5m4 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
          <span id="notifCount" class="absolute -top-1 -right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-semibold text-white bg-red-500 rounded-full hidden">0</span>
        </button>

        <div class="ml-auto flex items-center relative">
          <button id="adminMenuBtn" aria-label="Open admin menu" aria-controls="adminMenuDropdown" aria-expanded="false" class="p-2 rounded-md bg-white/10 hover:bg-white/20 text-white">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="3" y1="6" x2="21" y2="6" stroke-linecap="round" stroke-linejoin="round" />
              <line x1="3" y1="12" x2="21" y2="12" stroke-linecap="round" stroke-linejoin="round" />
              <line x1="3" y1="18" x2="21" y2="18" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>
          <div id="adminMenuDropdown" class="absolute right-0 top-full mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 divide-y divide-gray-100 focus:outline-none origin-top-right z-50 hidden">
            <div class="py-1">
              <a href="manage-residents.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 active:bg-gray-200">Manage Residents</a>
              <a href="manage-consultations.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 active:bg-gray-200">Consultation Records</a>
              <a href="manage-reports.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 active:bg-gray-200">Generate Reports</a>
              <a href="system_settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 active:bg-gray-200">System Settings</a>
              <a href="backup_restore.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 active:bg-gray-200">Backup & Restore</a>
            </div>
            <div class="py-1">
              <a href="landing-page.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 active:bg-red-100">Logout</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <div class="h-16"></div>

  <div class="max-w-3xl mx-auto p-6">
    <h1 class="text-2xl font-semibold mb-4">General Settings</h1>
    <?php if ($message): ?>
      <div class="p-3 mb-4 rounded bg-green-100 text-green-800"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="space-y-4 card">
      <div>
        <label class="block text-sm font-medium text-gray-700">System Name</label>
        <input name="system_name" class="mt-1 block w-full border px-3 py-2 rounded" value="<?php echo htmlspecialchars($settings['system_name']); ?>" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Barangay Name</label>
        <input name="barangay_name" class="mt-1 block w-full border px-3 py-2 rounded" value="<?php echo htmlspecialchars($settings['barangay_name']); ?>" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Logo</label>
        <?php if (!empty($settings['logo_path']) && file_exists(__DIR__ . '/' . $settings['logo_path'])): ?>
          <div class="mt-2 mb-2"><img src="<?php echo htmlspecialchars($settings['logo_path']); ?>" alt="logo" style="max-height:80px;" /></div>
        <?php endif; ?>
        <input type="file" name="logo" accept="image/*" class="mt-1" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Address</label>
        <input name="address" class="mt-1 block w-full border px-3 py-2 rounded" value="<?php echo htmlspecialchars($settings['address']); ?>" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Contact number</label>
        <input name="contact_number" class="mt-1 block w-full border px-3 py-2 rounded" value="<?php echo htmlspecialchars($settings['contact_number']); ?>" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Official email address</label>
        <input name="official_email" type="email" class="mt-1 block w-full border px-3 py-2 rounded" value="<?php echo htmlspecialchars($settings['official_email']); ?>" />
      </div>

      <div class="flex items-center gap-2">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Save Settings</button>
        <a href="homepage.php" class="text-sm text-gray-600">Back to Dashboard</a>
      </div>
    </form>
  </div>
    <script>
      // Admin menu toggle (for #adminMenuBtn / #adminMenuDropdown)
      (function(){
        const adminBtn = document.getElementById('adminMenuBtn');
        const adminDropdown = document.getElementById('adminMenuDropdown');
        if (!adminBtn || !adminDropdown) return;

        adminBtn.addEventListener('click', function(e){
          e.stopPropagation();
          const isHidden = adminDropdown.classList.toggle('hidden');
          adminBtn.setAttribute('aria-expanded', (!isHidden).toString());
        });

        document.addEventListener('click', function(){
          if (!adminDropdown.classList.contains('hidden')) {
            adminDropdown.classList.add('hidden');
            adminBtn.setAttribute('aria-expanded', 'false');
          }
        });

        adminDropdown.addEventListener('click', function(e){ e.stopPropagation(); });

        document.addEventListener('keydown', function(e){ if (e.key === 'Escape') { adminDropdown.classList.add('hidden'); adminBtn.setAttribute('aria-expanded', 'false'); } });
      })();
    </script>
  </body>
  </html>
