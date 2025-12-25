<?php
session_start();
include 'config.php';
if (!isset($_SESSION['username'])) { header('Location: login.php'); exit(); }

$logs_dir = __DIR__ . '/backup_logs';
if (!is_dir($logs_dir)) mkdir($logs_dir, 0755, true);

$message = '';

// Handle actions: restore or permanently delete from archive
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'restore_residents') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) $ids = array_filter(array_map('trim', explode(',', (string)$ids)));
    if (!empty($ids)) {
      $ids = array_map('intval', $ids);
      $idlist = implode(',', $ids);
      // restore
      $conn->query("INSERT INTO residents SELECT * FROM residents_archive WHERE id IN ({$idlist})");
      $conn->query("DELETE FROM residents_archive WHERE id IN ({$idlist})");
      $message = 'Residents restored.';
      file_put_contents($logs_dir . '/restore_' . date('Ymd_His') . '.log', date('c') . " - User {$_SESSION['username']} restored residents: {$idlist}\n", FILE_APPEND);
    } else { $message = 'No IDs provided.'; }
  }

  if ($action === 'delete_residents_permanent') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) $ids = array_filter(array_map('trim', explode(',', (string)$ids)));
    if (!empty($ids)) {
      $ids = array_map('intval', $ids);
      $idlist = implode(',', $ids);
      $conn->query("DELETE FROM residents_archive WHERE id IN ({$idlist})");
      $message = 'Residents permanently deleted from archive.';
      file_put_contents($logs_dir . '/delete_' . date('Ymd_His') . '.log', date('c') . " - User {$_SESSION['username']} permanently deleted residents: {$idlist}\n", FILE_APPEND);
    } else { $message = 'No IDs provided.'; }
  }

  if ($action === 'restore_health') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) $ids = array_filter(array_map('trim', explode(',', (string)$ids)));
    if (!empty($ids)) {
      $ids = array_map('intval', $ids);
      $idlist = implode(',', $ids);
      $conn->query("INSERT INTO health_records SELECT * FROM health_records_archive WHERE id IN ({$idlist})");
      $conn->query("DELETE FROM health_records_archive WHERE id IN ({$idlist})");
      $message = 'Health records restored.';
      file_put_contents($logs_dir . '/restore_' . date('Ymd_His') . '.log', date('c') . " - User {$_SESSION['username']} restored health_records: {$idlist}\n", FILE_APPEND);
    } else { $message = 'No IDs provided.'; }
  }

  if ($action === 'delete_health_permanent') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) $ids = array_filter(array_map('trim', explode(',', (string)$ids)));
    if (!empty($ids)) {
      $ids = array_map('intval', $ids);
      $idlist = implode(',', $ids);
      $conn->query("DELETE FROM health_records_archive WHERE id IN ({$idlist})");
      $message = 'Health records permanently deleted from archive.';
      file_put_contents($logs_dir . '/delete_' . date('Ymd_His') . '.log', date('c') . " - User {$_SESSION['username']} permanently deleted health_records: {$idlist}\n", FILE_APPEND);
    } else { $message = 'No IDs provided.'; }
  }
}

// Ensure archive tables exist for listing (no heavy impact if created)
$conn->query("CREATE TABLE IF NOT EXISTS residents_archive LIKE residents");
$conn->query("CREATE TABLE IF NOT EXISTS health_records_archive LIKE health_records");

$archived_residents = [];
$res = $conn->query("SELECT id, last_name, first_name, middle_name, barangay, date_of_birth FROM residents_archive ORDER BY id DESC LIMIT 500");
if ($res) while ($r = $res->fetch_assoc()) $archived_residents[] = $r;

$archived_health = [];
$res = $conn->query("SELECT hr.id, hr.resident_id, hr.record_date, hr.v_bp, hr.v_pr, r.last_name, r.first_name FROM health_records_archive hr LEFT JOIN residents_archive r ON hr.resident_id = r.id ORDER BY hr.id DESC LIMIT 500");
if ($res) while ($r = $res->fetch_assoc()) $archived_health[] = $r;

// Helper to resolve a resident's display name by id (check live table first, then archive)
function resident_display_name($rid, $conn) {
  $rid = (int)$rid;
  if ($rid <= 0) return '';
  // try live residents table
  $sql = "SELECT last_name, first_name FROM residents WHERE id = ? LIMIT 1";
  if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $rid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
      $stmt->close();
      $res->free();
      return trim((($row['last_name'] ?? '') ? ($row['last_name'] . ', ') : '') . ($row['first_name'] ?? '')) ?: ('Resident ' . $rid);
    }
    if ($res) $res->free();
    $stmt->close();
  }
  // try archive table
  $sql2 = "SELECT last_name, first_name FROM residents_archive WHERE id = ? LIMIT 1";
  if ($stmt2 = $conn->prepare($sql2)) {
    $stmt2->bind_param('i', $rid);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    if ($res2 && $row2 = $res2->fetch_assoc()) {
      $stmt2->close();
      $res2->free();
      return trim((($row2['last_name'] ?? '') ? ($row2['last_name'] . ', ') : '') . ($row2['first_name'] ?? '')) ?: ('Resident ' . $rid);
    }
    if ($res2) $res2->free();
    $stmt2->close();
  }
  return 'Resident ' . $rid;
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Archived Data - Backup & Restore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>
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
    .card{background:#fff;border-radius:8px;padding:12px;box-shadow:0 6px 18px rgba(2,6,23,.06)}
  </style>
</head>
<body class="bg-gradient-to-br from-[#f8fafc] to-white min-h-screen text-gray-700">
  <div class="max-w-full mx-auto p-6">
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

        <nav class="hidden md:flex items-center gap-6 ml-auto">
          <a href="homepage.php" class="text-sm font-medium text-white/90 hover:text-white">Dashboard</a>
          <a href="residents.php" class="text-sm font-medium text-white/90 hover:text-white">Residents</a>
          <a href="consultations.php" class="text-sm font-medium text-white/90 hover:text-white">Consultations</a>
          <a href="records.php" class="text-sm font-medium text-white/90 hover:text-white">Records</a>
          <a href="reports.php" class="text-sm font-medium text-white/90 hover:text-white">Reports</a>
        </nav>

        <div class="flex items-center gap-4 ml-4">
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
            <!-- Dropdown Menu (toggled by #adminMenuBtn) -->
            <div id="adminMenuDropdown" class="absolute right-0 top-full mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 divide-y divide-gray-100 focus:outline-none origin-top-right z-50 hidden">
              <div class="py-1">
                <a href="manage-residents.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 active:bg-gray-200">Manage Residents</a>
                <a href="manage-consultations.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 active:bg-gray-200">Consultation Records</a>
                <a href="reports.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 active:bg-gray-200">Generate Reports</a>
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
    <div class="h-16 md:h-20"></div>
    <main class="space-y-6">
      <section class="bg-white p-6 rounded-2xl shadow-md relative">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-4 gap-4">
          <div class="flex flex-col md:flex-row items-center justify-center w-full gap-4">
            <h2 class="text-lg font-semibold text-gray-700">Archived Residents & Health Records</h2>
          </div>
        </div>
    <?php if($message): ?><div class="p-3 mb-4 rounded bg-yellow-100 text-yellow-800"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

    <div class="grid md:grid-cols-2 gap-4 mb-6">
      <div class="card">
        <h3 class="font-semibold mb-2">Archived Residents</h3>
        <form method="post">
          <input type="hidden" name="action" value="restore_residents" />
          <div class="overflow-auto max-h-64 mb-2">
            <?php if(empty($archived_residents)): ?>
              <div class="text-sm text-gray-600">No archived residents found.</div>
            <?php else: ?>
              <?php foreach($archived_residents as $ar): ?>
                <label class="block"><input type="checkbox" name="ids[]" value="<?php echo (int)$ar['id']; ?>"> <?php echo htmlspecialchars($ar['last_name'] . ', ' . $ar['first_name'] . ' (' . ($ar['barangay'] ?? '') . ')'); ?></label>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <div class="flex gap-2">
            <button class="px-3 py-2 bg-green-600 text-white rounded">Restore Selected</button>
            <button formaction="" formmethod="post" name="action" value="delete_residents_permanent" class="px-3 py-2 bg-red-600 text-white rounded">Delete Permanently</button>
          </div>
        </form>
      </div>

      <div class="card">
        <h3 class="font-semibold mb-2">Archived Health Records</h3>
        <form method="post">
          <input type="hidden" name="action" value="restore_health" />
          <div class="overflow-auto max-h-64 mb-2">
            <?php if(empty($archived_health)): ?>
              <div class="text-sm text-gray-600">No archived health records found.</div>
            <?php else: ?>
              <?php foreach($archived_health as $ah): ?>
                <?php
                  $displayName = '';
                  if (!empty($ah['last_name']) || !empty($ah['first_name'])) {
                    $displayName = trim(($ah['last_name'] ?? '') . ( (!empty($ah['last_name']) && !empty($ah['first_name'])) ? ', ' : '') . ($ah['first_name'] ?? '') );
                  } else {
                    $displayName = resident_display_name($ah['resident_id'] ?? 0, $conn);
                  }
                ?>
                <label class="block"><input type="checkbox" name="ids[]" value="<?php echo (int)$ah['id']; ?>"> <?php echo htmlspecialchars($displayName); ?> — <?php echo htmlspecialchars($ah['record_date'] ?? ''); ?></label>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <div class="flex gap-2">
            <button class="px-3 py-2 bg-green-600 text-white rounded">Restore Selected</button>
            <button formaction="" formmethod="post" name="action" value="delete_health_permanent" class="px-3 py-2 bg-red-600 text-white rounded">Delete Permanently</button>
          </div>
        </form>
      </div>
    </div>

    <div class="text-sm text-gray-600">Tip: Use the archive/restore functions carefully. Restoring will copy rows back to the main tables and remove them from the archive.</div>
      </section>
    </main>
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
        // toggle returns true when class is now present -> hidden
        adminBtn.setAttribute('aria-expanded', (!isHidden).toString());
      });

      // Close when clicking outside
      document.addEventListener('click', function(e){
        if (!adminDropdown.classList.contains('hidden')) {
          adminDropdown.classList.add('hidden');
          adminBtn.setAttribute('aria-expanded', 'false');
        }
      });

      // Prevent clicks inside dropdown from closing
      adminDropdown.addEventListener('click', function(e){ e.stopPropagation(); });

      // Close on Escape
      document.addEventListener('keydown', function(e){ if (e.key === 'Escape') { adminDropdown.classList.add('hidden'); adminBtn.setAttribute('aria-expanded', 'false'); } });
    })();
  </script>
</body>
</html>
