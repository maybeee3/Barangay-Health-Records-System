<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
// DB counts for dashboard
include_once 'config.php';

$totalResidents = 0;
$totalConsults = 0;
$activeCases = 0;
$recoveredCount = 0;
$pendingConsults = 0;

// Total residents
try {
  $res = $conn->query("SELECT COUNT(*) AS cnt FROM residents");
  if ($res) { $row = $res->fetch_assoc(); $totalResidents = (int)$row['cnt']; }
} catch (Exception $e) {}

// Total consultations
try {
  $res = $conn->query("SELECT COUNT(*) AS cnt FROM consultations");
  if ($res) { $row = $res->fetch_assoc(); $totalConsults = (int)$row['cnt']; }
} catch (Exception $e) {}

// Active cases: distinct residents with consultations in the last 14 days
try {
  $res = $conn->query("SELECT COUNT(DISTINCT resident_id) AS cnt FROM consultations WHERE date_of_consultation >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)");
  if ($res) { $row = $res->fetch_assoc(); $activeCases = (int)$row['cnt']; }
} catch (Exception $e) {}

// Recovered / Cleared: consultations completed today
try {
  $res = $conn->query("SELECT COUNT(*) AS cnt FROM consultations WHERE date_of_consultation = CURDATE() AND status = 'Completed'");
  if ($res) { $row = $res->fetch_assoc(); $recoveredCount = (int)$row['cnt']; }
} catch (Exception $e) {}

// Pending consultations: count of consultations scheduled for today with status 'Pending'
try {
  $res = $conn->query("SELECT COUNT(*) AS cnt FROM consultations WHERE date_of_consultation = CURDATE() AND (status IS NULL OR status = '' OR status = 'Pending')");
  if ($res) { $row = $res->fetch_assoc(); $pendingConsults = (int)$row['cnt']; }
} catch (Exception $e) {}

// Upcoming consultations within next 2 hours to show in notifications (transient)
$upcoming_consults = [];
try {
  // Fetch upcoming consultations within next 2 hours for display on the dashboard notifications/recent list.
  // We intentionally do not filter by reminder_sent here so the UI shows all scheduled consultants.
  $q = $conn->query("SELECT c.id, c.resident_id, c.email AS resident_email, c.consulting_doctor, c.date_of_consultation, c.consultation_time, CONCAT_WS(' ', r.first_name, r.last_name) AS resident_name FROM consultations c LEFT JOIN residents r ON c.resident_id = r.id WHERE CAST(CONCAT(c.date_of_consultation, ' ', c.consultation_time) AS DATETIME) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 HOUR) ORDER BY c.date_of_consultation, c.consultation_time");
  if ($q) {
    while ($row = $q->fetch_assoc()) {
      $upcoming_consults[] = $row;
    }
  }
} catch (Exception $e) {}
// encode for JS
$upcoming_json = json_encode($upcoming_consults, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
// Today's consultations (for Today's Schedule panel)
$todays_consults = [];
try {
  $q2 = $conn->query("SELECT c.id, c.resident_id, c.consultation_time, c.date_of_consultation, COALESCE(c.status, '') AS raw_status, CONCAT_WS(' ', r.first_name, r.last_name) AS resident_name FROM consultations c LEFT JOIN residents r ON c.resident_id = r.id WHERE c.date_of_consultation = CURDATE() AND (c.status IS NULL OR c.status = '' OR LOWER(c.status) = 'pending') ORDER BY c.consultation_time");
  if ($q2) {
    while ($r = $q2->fetch_assoc()) $todays_consults[] = $r;
  }
} catch (Exception $e) {}

// Ensure activity/audit table exists and load recent activity
$activity_logs = [];
try {
  $conn->query("CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    username VARCHAR(100) DEFAULT NULL,
    action_type VARCHAR(100) DEFAULT NULL,
    message TEXT,
    target_resident_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {}
try {
  $qa = $conn->query("SELECT al.*, CONCAT_WS(' ', r.first_name, r.last_name) AS resident_name FROM activity_logs al LEFT JOIN residents r ON al.target_resident_id = r.id ORDER BY al.created_at DESC LIMIT 20");
  if ($qa) {
    while ($row = $qa->fetch_assoc()) $activity_logs[] = $row;
  }
} catch (Exception $e) {}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Barangay Health Monitoring System - Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>
  <script>
    // Tailwind config for custom colors & font
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            bhms: {
              DEFAULT: '#2563eb',
              light: '#e6f0ff'
            }
          },
          fontFamily: {
            inter: ['Inter', 'sans-serif']
          }
        }
      }
    }
  </script>
  <script>
    // Ensure items visible in the Recent Notifications list are also present
    // in the side Notifications panel. Runs after other DOMContentLoaded handlers.
    document.addEventListener('DOMContentLoaded', function(){
      try {
        const recent = document.getElementById('recentNotifications');
        const panel = document.getElementById('notifPanelList');
        if (!recent || !panel) return;

        Array.from(recent.children).forEach(li => {
          try {
            const id = li.getAttribute && (li.getAttribute('data-consult-id') || li.dataset.consultId || li.getAttribute('data-id'));
            if (id && panel.querySelector('[data-consult-id="' + id + '"]')) return;
            // Do a simple text-based dedupe too
            const text = (li.textContent||'').trim().slice(0,200);
            const exists = Array.from(panel.children).some(n => (n.textContent||'').trim().slice(0,200) === text);
            if (exists) return;
            const node = document.createElement('div');
            node.className = 'p-3 rounded-lg bg-gray-50 flex gap-3 items-start hover:bg-gray-100 cursor-pointer';
            node.innerHTML = li.innerHTML;
            if (id) node.setAttribute('data-consult-id', id);
            panel.insertBefore(node, panel.firstChild);
          } catch(e){}
        });
      } catch(e){}
    });
  </script>
  <!-- Chart.js for charts -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; }
    /* Make these dashboard panels independently scrollable and fit the viewport */
    .panel-scroll { max-height: calc(100vh - 240px); overflow: auto; }
    @media (max-width: 1024px) { .panel-scroll { max-height: calc(100vh - 300px); } }
  </style>
</head>
<body class="bg-gradient-to-br from-[#f8fafc] to-white min-h-screen text-gray-700">
  <div class="max-w-full mx-auto p-6">
    <!-- Header: full-width blue (blue-700) with nav pushed to the right -->
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
            <a href="homepage.php" class="text-sm font-medium text-white/90 hover:text-white border-b-2 border-white/30">Dashboard</a>
            <a href="residents.php" class="text-sm font-medium text-white/90 hover:text-white transition">Residents</a>
            <a href="consultations.php" class="text-sm font-medium text-white/90 hover:text-white transition">Consultations</a>
            <a href="records.php" class="text-sm font-medium text-white/90 hover:text-white transition">Records</a>
            <a href="reports.php" class="text-sm font-medium text-white/90 hover:text-white transition">Reports</a>
        </nav>

        <div class="flex items-center gap-4 ml-4">
          <!-- Notification button (opens side panel) -->
          <button id="notifBtn" aria-expanded="false" aria-controls="notifPanel" class="relative p-2 rounded-lg bg-white/10 hover:bg-white/20 transition" title="Notifications">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white/90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118.5 14.5V11a6.5 6.5 0 10-13 0v3.5c0 .538-.214 1.055-.595 1.445L3 17h5m4 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span id="notifCount" class="absolute -top-1 -right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-semibold text-white bg-red-500 rounded-full hidden">0</span>
          </button>

          <!-- Admin control: compact hamburger icon -->
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
                <a href="manage-residents.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 active:bg-gray-200">
                  Manage Residents
                </a>
                <a href="manage-consultations.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 active:bg-gray-200">
                  Consultation Records
                </a>
                <a href="manage-reports.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 active:bg-gray-200">
                  Generate Reports
                </a>
                <a href="system_settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 active:bg-gray-200">
                  System Settings
                </a>
                <a href="backup_restore.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 active:bg-gray-200">
                  Backup & Restore
                </a>
              </div>
              <div class="py-1">
                <a href="landing-page.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 active:bg-red-100">
                  Logout
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- spacer for fixed header (reduced height) -->
    <div class="h-12 md:h-16"></div>

   
    <!-- Grid layout -->
    <main class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <!-- Top summary cards (span 8) -->
      <section class="lg:col-span-8 space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
          <!-- Card 1: Total Residents -->
          <div class="bg-white p-3 rounded-2xl shadow-md hover:scale-[1.01] transition-transform">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-xs text-gray-400">Total </div>
                <div class="text-xs text-gray-400">Residents</div>
                <div class="text-2xl font-semibold mt-1" id="totalResidents"><?php echo number_format($totalResidents); ?></div>
                <div class="text-xs text-gray-400 mt-1">Registered Residents</div>
              </div>
              <div class="p-3 rounded-lg bg-bhms-light">
                <!-- user icon -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-bhms" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A9 9 0 1119.88 6.196M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              </div>
            </div>
            <div class="mt-2">
              <canvas id="spark1" height="5"></canvas>
            </div>
          </div>

          <!-- Card 2: Pending Consultation -->
          <div class="bg-white p-3 rounded-2xl shadow-md hover:scale-[1.01] transition-transform">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-xs text-gray-400">Pending Consultation</div>
                <div class="text-2xl font-semibold mt-1" id="pendingConsults"><?php echo number_format($pendingConsults); ?></div>
                <div class="text-xs text-gray-400 mt-1">Scheduled Consultations today</div>
              </div>
              <div class="p-3 rounded-lg bg-yellow-100">
                <!-- calendar/clock icon -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
              </div>
            </div>
            <div class="mt-2">
              <canvas id="spark2" height="5"></canvas>
            </div>
          </div>

          <!-- Card: Recovered -->
          <div class="bg-white p-3 rounded-2xl shadow-md hover:scale-[1.01] transition-transform">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-xs text-gray-400">Recovered / Cleared</div>
                <div class="text-2xl font-semibold mt-1" id="recoveredCount"><?php echo number_format($recoveredCount); ?></div>
                <div class="text-xs text-gray-400 mt-1">Completed consultations today</div>
              </div>
              <div class="p-3 rounded-lg bg-green-100">
                <!-- check icon -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
              </div>
            </div>
            <div class="mt-2">
              <canvas id="spark3" height="5"></canvas>
            </div>
          </div>

          <!-- Card: Total Consultations -->
          <div class="bg-white p-3 rounded-2xl shadow-md hover:scale-[1.01] transition-transform">
            <div class="flex items-start justify-between">
              <div>
                <div class="text-xs text-gray-400">Total Consultations</div>
                <div class="text-2xl font-semibold mt-1" id="totalConsults"><?php echo number_format($totalConsults); ?></div>
                <div class="text-xs text-gray-400 mt-1">Residents with Medical Records</div>
              </div>
              <div class="p-3 rounded-lg bg-bhms-light">
                <!-- stethoscope icon -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-bhms" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 00-4-4H3" />
                </svg>
              </div>
            </div>
            <div class="mt-2">
              <canvas id="spark4" height="5"></canvas>
            </div>
          </div>
        </div>

        <!-- Recent Activity Log (moved here from right column) -->
        <div class="bg-white p-5 rounded-2xl shadow-md mt-4">
          <div class="flex items-center justify-between mb-4">
            <div>
              <div class="text-lg font-semibold">Recent Activity Log</div>
            </div>
          </div>

          <div id="activityLog" class="space-y-2 panel-scroll">
            <?php if (empty($activity_logs)): ?>
              <div class="text-sm text-gray-400">No recent activity.</div>
            <?php else: ?>
              <?php foreach ($activity_logs as $a): ?>
                <?php
                  $time = $a['created_at'] ?? '';
                  $user = htmlspecialchars($a['username'] ?? ($_SESSION['username'] ?? 'System'));
                  $action = $a['action_type'] ?? '';
                  $rawMsg = $a['message'] ?? '';

                  // format timestamp label (bracketed) similar to examples
                  $timeLabel = '';
                  if (!empty($time)) {
                    try {
                      $dt = new DateTime($time);
                      $now = new DateTime();
                      $today = $now->format('Y-m-d');
                      $yesterday = $now->modify('-1 day')->format('Y-m-d');
                      $now = new DateTime();
                      $dts = $dt->format('Y-m-d');
                      if ($dts === $today) {
                        $timeLabel = $dt->format('h:i A');
                      } elseif ($dts === $yesterday) {
                        $timeLabel = 'Yesterday, ' . $dt->format('g:i A');
                      } else {
                        $timeLabel = $dt->format('M j, Y \a\t g:i A');
                      }
                    } catch (Exception $e) { $timeLabel = htmlspecialchars((string)$time); }
                  }

                  $renderHtml = '';

                  // Helper to safely decode JSON payloads
                  $maybe = is_string($rawMsg) ? json_decode($rawMsg, true) : null;

                  if ($action === 'reminder_sent' && is_array($maybe) && isset($maybe['title'])) {
                    $title = htmlspecialchars($maybe['title']);
                    $to = htmlspecialchars($maybe['to'] ?? '');
                    $appt_raw = $maybe['appointment'] ?? '';
                    $appt_display = '';
                    if (!empty($appt_raw)) {
                      $adt = date_create($appt_raw);
                      if ($adt) $appt_display = date_format($adt, 'M j, Y \a\t g:i A');
                      else $appt_display = htmlspecialchars($appt_raw);
                    }
                    $renderHtml = "<div class=\"font-semibold\">{$title}</div>";
                    $renderHtml .= "<div class=\"text-xs text-gray-500 mt-1\">&bull; To: {$to}<br>&bull; Appointment: {$appt_display}</div>";

                  } elseif ($action === 'consultation_added') {
                    // Best-effort structured view: By => username, Patient => resident_name, Diagnosis => try parse from message
                    $title = 'New Consultation Logged';
                    $patient = htmlspecialchars($a['resident_name'] ?? '');
                    if (empty($patient)) {
                      // fallback: try extract from raw message
                      if (preg_match('/Added consultation for\s+([^\(]+)/i', $rawMsg, $m)) $patient = htmlspecialchars(trim($m[1]));
                    }
                    $by = $user;
                    // try to extract diagnosis/reason from message
                    $diag = '';
                    if (preg_match('/reason[:\-]\s*(.+)$/i', $rawMsg, $m)) $diag = htmlspecialchars(trim($m[1]));
                    $renderHtml = "<div class=\"font-semibold\">{$title}</div>";
                    $renderHtml .= "<div class=\"text-xs text-gray-500 mt-1\">&bull; By: {$by}<br>&bull; Patient: {$patient}";
                    if (!empty($diag)) $renderHtml .= "<br>&bull; Diagnosis: {$diag}";
                    $renderHtml .= "</div>";

                  } elseif (($action === 'record_saved' || $action === 'record_emailed' || $action === 'record_email_failed')) {
                    $title = ($action === 'record_emailed') ? 'Patient Record Emailed' : (($action === 'record_email_failed') ? 'Record Email Failed' : 'Patient Record Saved');
                    $patient = htmlspecialchars($a['resident_name'] ?? '');
                    $renderHtml = "<div class=\"font-semibold\">{$title}</div>";
                    $renderHtml .= "<div class=\"text-xs text-gray-500 mt-1\">";
                    if (!empty($patient)) $renderHtml .= "&bull; Patient: {$patient}";
                    if ($action === 'record_emailed' && is_string($rawMsg) && strpos($rawMsg, 'to') !== false) {
                      $renderHtml .= "<br>&bull; " . htmlspecialchars($rawMsg);
                    }
                    $renderHtml .= "</div>";

                  } elseif ($action === 'resident_added') {
                    $title = 'Resident Profile Added';
                    $patient = htmlspecialchars($a['resident_name'] ?? '');
                    $renderHtml = "<div class=\"font-semibold\">{$title}</div>";
                    $renderHtml .= "<div class=\"text-xs text-gray-500 mt-1\">&bull; Resident: {$patient}</div>";

                  } else {
                    // Detect plain-text reminder lines like: "Sent reminder to carlamae.villafranca1@gmail.com"
                    $renderHtml = '';
                    $remEmail = null;
                    if (is_string($rawMsg) && preg_match('/(?:sent reminder to|reminder sent to)\s*:??\s*(\S+@\S+)/i', $rawMsg, $mm)) {
                      $remEmail = $mm[1];
                    }
                    if ($remEmail) {
                      $title = 'Check-Up Reminder Sent';
                      $toLine = '';
                      $toName = htmlspecialchars($a['resident_name'] ?? '');
                      if (!empty($toName)) {
                        $toLine = 'Resident 	6 ' . $toName; // placeholder dash replaced below
                      }
                      // If resident_name not available, show email
                      if (empty($toName)) $toLine = htmlspecialchars($remEmail);
                      // Build display: To: Resident – Name (or email)
                      // Use an en-dash-like separator ' – '
                      if (!empty($toName)) $toLine = 'Resident – ' . $toName;
                      $renderHtml = '<div class="font-semibold">' . $title . '</div>';
                      $renderHtml .= '<div class="text-xs text-gray-500 mt-1">&bull; To: ' . htmlspecialchars($toLine) . '</div>';
                      // Try to extract appointment datetime from message if present (simple YYYY-MM-DD or time)
                      if (preg_match('/(\d{4}-\d{2}-\d{2})/', $rawMsg, $md)) {
                        $adt = date_create($md[1]);
                        if ($adt) $appt_display = date_format($adt, 'M j, Y'); else $appt_display = $md[1];
                        $renderHtml .= '<div class="text-xs text-gray-500">&bull; Appointment: ' . htmlspecialchars($appt_display) . '</div>';
                      } elseif (preg_match('/(\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{1,2},?\s*\d{0,4})/i', $rawMsg, $md2)) {
                        $renderHtml .= '<div class="text-xs text-gray-500">&bull; Appointment: ' . htmlspecialchars($md2[1]) . '</div>';
                      }
                    } else {
                      // Generic: strip technical ids and show cleaned message
                      $clean = preg_replace(array("/\(resident_id=\d+\)/i", "/consultation ID\s*\d+/i", "/\(ID\s*\d+\)/i", "/resident_id=\d+/i"), '', (string)$rawMsg);
                      $clean = trim(preg_replace('/\s{2,}/', ' ', $clean));
                      if ($clean === '') $clean = $rawMsg;
                      $renderHtml = '<div class="text-sm text-gray-700">' . htmlspecialchars((string)$clean) . '</div>';
                    }
                  }
                ?>
                <div class="p-3 rounded-lg border border-gray-100">
                  <?php if (!empty($timeLabel)): ?>
                    <div class="text-xs text-gray-500 font-mono mb-2">[<?php echo htmlspecialchars($timeLabel); ?>]</div>
                  <?php endif; ?>
                  <?php echo $renderHtml; ?>
                  <div class="text-xs text-gray-400 mt-2"><?php echo $user; ?></div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Recent Notifications moved to side panel -->
      </section>

      <!-- Right column: Today's Schedule -->
      <aside class="lg:col-span-4">
        <!-- Today's Schedule panel -->
        <div class="bg-white p-5 rounded-2xl shadow-md">
          <div class="flex items-center justify-between mb-4">
            <div>
              <div class="text-lg font-semibold">Today's Schedule</div>
            </div>
            <div class="text-xs text-gray-500"><?php echo date('F j, Y'); ?></div>
          </div>

          <div id="todaysSchedule" class="space-y-2 panel-scroll">
            <?php if (empty($todays_consults)): ?>
              <div class="text-sm text-gray-400">No appoinment today.</div>
            <?php else: ?>
              <?php foreach ($todays_consults as $t): ?>
                <?php
                  // determine status: Ongoing if scheduled time is <= now, otherwise Pending
                  $time = $t['consultation_time'] ?? '00:00:00';
                  $dtStr = ($t['date_of_consultation'] ?? date('Y-m-d')) . ' ' . $time;
                  $dt = DateTime::createFromFormat('Y-m-d H:i:s', $dtStr);
                  if (!$dt) { $dt = DateTime::createFromFormat('Y-m-d H:i', $dtStr); }
                  $now = new DateTime();
                  $statusLabel = 'Pending';
                  if ($dt && $dt <= $now) $statusLabel = 'Ongoing';
                  $displayTime = $time ? date('h:i A', strtotime($time)) : '';
                  $residentName = trim($t['resident_name'] ?? '') ?: ('Resident #' . ($t['resident_id'] ?? ''));
                ?>
                <div class="p-3 rounded-lg border border-gray-100 flex items-center justify-between">
                  <div>
                    <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($residentName); ?></div>
                    <div class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($displayTime); ?></div>
                  </div>
                  <div class="text-right">
                    <span class="px-2 py-1 rounded-full text-xs <?php echo ($statusLabel === 'Ongoing') ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>"><?php echo $statusLabel; ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </aside>
      
      
    </main>
  </div>

  <!-- overlay + notification side panel -->
  <div id="notifOverlay" class="fixed inset-0 bg-black/40 hidden z-40"></div>

  <aside id="notifPanel" class="fixed top-16 right-4 w-80 max-w-sm h-[70vh] bg-white/95 rounded-xl shadow-2xl transform translate-x-full transition-transform z-50 overflow-auto">
    <div class="flex items-center justify-between p-4 border-b">
      <div class="font-semibold">Notifications</div>
      <div class="flex items-center gap-2">
        <button id="notifRefresh" class="text-sm text-gray-500 hover:text-gray-700">Refresh</button>
        <button id="notifClose" class="text-gray-500 hover:text-gray-800" title="Close">&times;</button>
      </div>
    </div>
    <div id="notifPanelList" class="p-3 space-y-2">
      <!-- populated by JS -->
      <div class="text-sm text-gray-500">Loading...</div>
    </div>
  </aside>

  <script>
    // safe JSON read
    function safeReadJSON(key){ try { return JSON.parse(localStorage.getItem(key) || '[]'); } catch(e){ return []; } }

    // escape HTML for simple text insertion
    function escapeHtml(str){ if (str === null || str === undefined) return ''; return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }

    // Render dashboard numbers & sparklines from real data
    function renderDashboard(){
      // The totalResidents and totalConsults are now fetched from PHP
      // document.getElementById('totalResidents').textContent = totalResidents;
      // document.getElementById('totalConsults').textContent = totalConsults;
      document.getElementById('activeCases').textContent = 0; // These still need to be database-driven
      document.getElementById('recoveredCount').textContent = 0; // These still need to be database-driven

      // For sparklines, we'd need to fetch historical data from the database.
      // For now, I'll keep them as dummy data or remove them if not needed.
      const dummyLast7 = [0, 0, 0, 0, 0, 0, 0]; // Replace with actual database data later
      createSpark('spark1', dummyLast7, '#2563eb');
      createSpark('spark2', dummyLast7, '#ef4444');
      createSpark('spark3', dummyLast7, '#10b981');
      createSpark('spark4', dummyLast7, '#2563eb');
    }

    // Create sparkline charts (moved here to be available)
    function createSpark(ctxId, data, color){
      const ctxEl = document.getElementById(ctxId);
      if (!ctxEl) return
      const ctx = ctxEl.getContext('2d');
      if (ctx.__chart) ctx.__chart.destroy();
      ctx.__chart = new Chart(ctx, {
        type: 'line',
        data: { labels: data.map((_,i)=>i+1), datasets: [{ data, borderColor: color, borderWidth: 2, pointRadius: 0, fill: false, tension: 0.3 }]},
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false } } }
      });
    }

    // ---- Recent notifications and Upcoming Appointments (still using localStorage, will need database integration)
    // (Keep these for now, or update if you want full database integration for these too)
    function timeAgoLabel(ts){
      const d = new Date(ts).getTime();
      if (isNaN(d)) return '';
      const diff = Date.now() - d;
      const sec = Math.floor(diff/1000);
      if (sec < 60) return `${sec}s ago`;
      const min = Math.floor(sec/60);
      if (min < 60) return `${min}m ago`;
      const hr = Math.floor(min/60);
      if (hr < 24) return `${hr}h ago`;
      const days = Math.floor(hr/24);
      return `${days}d ago`;
    }

    function renderRecentNotifications(limit = 5){
      const consultations = safeReadJSON('consultations');
      const container = document.getElementById('recentNotifications');
      if (!container) return;
      const sorted = consultations.slice().sort((a,b)=>{
        const ta = new Date(a.createdAt || a.appointmentDate || 0).getTime();
        const tb = new Date(b.createdAt || b.appointmentDate || 0).getTime();
        return tb - ta;
      }).slice(0, limit);
      container.innerHTML = '';
      if (!sorted.length){
        container.innerHTML = '<div class="text-sm text-gray-400">No recent notifications</div>';
        return;
      }
      sorted.forEach(item => {
        const whenLabel = item.createdAt ? timeAgoLabel(item.createdAt) : (item.appointmentDate ? item.appointmentDate : '');
        const name = item.name || 'Unnamed';
        const appt = item.appointmentDate ? (item.appointmentDate + (item.appointmentTime ? ' • ' + item.appointmentTime : '')) : '';
        const action = item.appointmentType || item.type || (appt ? 'Appointment' : 'New entry');
        const li = document.createElement('li');
        li.className = 'p-3 rounded-lg bg-gray-50 flex justify-between items-start';
        li.innerHTML = `<div><strong>${name}</strong> — <span class="text-sm text-gray-500">${action}</span><div class="text-xs text-gray-400 mt-1">${appt}</div></div><div class="text-xs text-gray-400">${whenLabel}</div>`;
        if (item.id){
          li.style.cursor = 'pointer';
          li.addEventListener('click', ()=> window.open('personal-information.html?id=' + encodeURIComponent(item.id), '_blank'));
        }
        container.appendChild(li);
      });
    }

    function formatTimeDisplay(t){
      if (!t) return '';
      try {
        const [hh, mm] = t.split(':');
        const d = new Date(); d.setHours(parseInt(hh,10), parseInt(mm||'0',10));
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      } catch(e){ return t; }
    }

    function renderUpcomingAppointments(limit = 5){
      const consultations = safeReadJSON('consultations');
      const container = document.getElementById('upcomingList');
      if (!container) return;
      const items = consultations
        .map(c => {
          const dateStr = c.appointmentDate || c.createdAt || null;
          if (!dateStr) return null;
          const timeStr = c.appointmentTime || c.time || '';
          const iso = dateStr + (timeStr ? ('T' + timeStr) : 'T00:00');
          const dt = new Date(iso);
          if (isNaN(dt)) return null;
          return { ...c, dt };
        })
        .filter(Boolean)
        .filter(i => i.dt.getTime() >= new Date(new Date().toDateString()).getTime())
        .sort((a,b) => a.dt - b.dt)
        .slice(0, limit);

      container.innerHTML = '';
      if (!items.length) {
        container.innerHTML = '<div class="text-sm text-gray-400">No upcoming appointments</div>';
        return;
      }

      items.forEach(item => {
        const month = String(item.dt.getMonth() + 1).padStart(2,'0');
        const day = String(item.dt.getDate()).padStart(2,'0');
        const dateBox = `${month}/${day}`;
        const name = item.name || (item.patientName || 'Unnamed');
        const type = item.appointmentType || item.type || 'Consultation';
        const assigned = item.assignedTo || item.assigned || item.provider || '';
        const timeLabel = item.appointmentTime ? formatTimeDisplay(item.appointmentTime) : (item.time ? formatTimeDisplay(item.time) : '');
        const meta = `${assigned ? 'Assigned: ' + assigned + ' • ' : ''}${timeLabel ? timeLabel : item.dt.toLocaleDateString()}`;

        const card = document.createElement('div');
        card.className = 'p-3 rounded-xl border border-gray-100 hover:shadow-md transition flex items-start gap-3 cursor-pointer';
        card.innerHTML = `
          <div class="w-12 h-12 bg-bhms-light rounded-lg flex items-center justify-center text-sm font-semibold">${dateBox}</div>
          <div class="flex-1">
            <div class="font-semibold">${type} - ${name}</div>
            <div class="text-sm text-gray-400">${meta}</div>
          </div>
        `;
        if (item.id){
          card.addEventListener('click', () => window.open('personal-information.html?id=' + encodeURIComponent(item.id), '_blank'));
        }
        container.appendChild(card);
      });
    }

    // initial render and live updates
    document.addEventListener('DOMContentLoaded', function(){
      renderDashboard();
      renderRecentNotifications();
      renderUpcomingAppointments();

      // Indicate server provided upcoming so shared script skips fetching duplicate data
      window._serverUpcomingProvided = true;
      // Inject server-side upcoming consultations (within 2 hours) into notifications and recent list
      try {
        window._serverUpcomingList = <?php echo $upcoming_json ?? '[]'; ?> || [];
        const serverUpcoming = window._serverUpcomingList;
        if (serverUpcoming && serverUpcoming.length) {
          try {
            const dismissed = JSON.parse(localStorage.getItem('dismissed_consults') || '[]');
            const seen = JSON.parse(localStorage.getItem('seen_consults') || '[]');
            const itemsToShow = serverUpcoming.filter(it => !dismissed.includes(String(it.id)));
            const itemsToNotify = itemsToShow.filter(it => !seen.includes(String(it.id)));

            // Do NOT auto-open the panel or show toast popups for upcoming items.
            // We only update the badge/count so users see there are pending notifications.

            const panelEl = document.getElementById('notifPanelList');
            const recentContainer = document.getElementById('recentNotifications');

            itemsToShow.forEach(item => {
              try {
                const when = (item.date_of_consultation || '') + (item.consultation_time ? (' • ' + item.consultation_time) : '');
                const name = item.resident_name || ('Resident #' + (item.resident_id || ''));
                const consultant = item.consulting_doctor || 'Not specified';

                const icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-bhms" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/></svg>';
                const node = document.createElement('div');
                node.className = 'p-3 rounded-lg bg-yellow-50 flex gap-3 items-start hover:bg-yellow-100 cursor-pointer border border-yellow-100';
                node.innerHTML = `<div class="flex-shrink-0">${icon}</div>
                                  <div class="flex-1">
                                    <div class="font-semibold">${escapeHtml(name)} <span class="text-xs text-gray-400">• Upcoming</span></div>
                                    <div class="text-xs text-gray-500 mt-1">${escapeHtml(when)} • Consultant: ${escapeHtml(consultant)}</div>
                                  </div>
                                  <div class="text-xs text-gray-400">Soon</div>`;

                if (item.resident_id) {
                  node.addEventListener('click', ()=>{
                    try {
                      const list = JSON.parse(localStorage.getItem('seen_consults') || '[]');
                      if (!list.includes(String(item.id))) { list.push(String(item.id)); localStorage.setItem('seen_consults', JSON.stringify(list)); }
                    } catch(e){}
                    window.open('view-resident.php?id=' + encodeURIComponent(item.resident_id), '_blank');
                  });
                }

                if (panelEl) { node.setAttribute('data-consult-id', String(item.id)); panelEl.insertBefore(node, panelEl.firstChild); }

                if (recentContainer) {
                  const li = document.createElement('li');
                  li.className = 'p-3 rounded-lg bg-yellow-50 flex justify-between items-start';
                  li.innerHTML = `<div><strong>${escapeHtml(name)}</strong> — <span class="text-sm text-gray-500">Upcoming consultation</span><div class="text-xs text-gray-400 mt-1">${escapeHtml(when)} • ${escapeHtml(consultant)}</div></div><div class="text-xs text-gray-400">Soon</div>`;
                  li.addEventListener('click', ()=>{
                    try {
                      const list = JSON.parse(localStorage.getItem('seen_consults') || '[]');
                      if (!list.includes(String(item.id))) { list.push(String(item.id)); localStorage.setItem('seen_consults', JSON.stringify(list)); }
                    } catch(e){}
                    if (item.resident_id) window.open('view-resident.php?id=' + encodeURIComponent(item.resident_id), '_blank');
                  });
                  li.setAttribute('data-consult-id', String(item.id));
                  recentContainer.insertBefore(li, recentContainer.firstChild);
                }
              } catch(e) { console.error('Error adding serverUpcoming item', e); }
            });

            // update badge with unseen count only
            const notifCountElLocal = document.getElementById('notifCount');
            if (notifCountElLocal) {
              notifCountElLocal.textContent = (parseInt(notifCountElLocal.textContent || '0', 10) + itemsToNotify.length).toString();
              if (itemsToNotify.length > 0) notifCountElLocal.classList.remove('hidden');
            }
          } catch(e){ console.error('Error processing server upcoming consultations', e); }
        }
      } catch(e){ console.error('Error processing server upcoming consultations', e); }

      // See all button
      const seeAllBtn = document.getElementById('seeAllAppt');
      if (seeAllBtn) seeAllBtn.addEventListener('click', ()=> window.location.href = 'consultations.php');

      // re-render on storage changes (when personal-information saves)
      window.addEventListener('storage', (ev)=>{
        if (ev.key === 'consultations' || ev.key === 'residents' || ev.key === 'consultations_updated_at') {
          // For now, we reload the page to get updated counts from PHP
          location.reload();
        }
      });
  
      // Periodically fetch server-side notification counts (unread + upcoming reminders)
      async function fetchNotifCounts(){
        try{
          const res = await fetch('notifications-count.php');
          if (!res.ok) return;
          const j = await res.json();
          const el = document.getElementById('notifCount');
          if (!el) return;
          const unread = parseInt(j.unread || 0, 10) || 0;
          const upcoming = parseInt(j.upcoming || 0, 10) || 0;
          const total = (parseInt(j.total || (unread + upcoming), 10) || (unread + upcoming));
          // show total in badge, and include detailed tooltip text
          if (total > 0) {
            el.textContent = String(total);
            el.classList.remove('hidden');
            el.title = `${unread} unread, ${upcoming} upcoming`;
          } else {
            el.classList.add('hidden');
            el.title = '';
          }
        }catch(e){ console.error('Failed to fetch notif counts', e); }
      }
      // initial fetch and interval (every 60s)
      fetchNotifCounts();
      setInterval(fetchNotifCounts, 60 * 1000);
    });

  </script>

  <script>
  // helper (if not already present)
  function safeReadJSON(key){ try { return JSON.parse(localStorage.getItem(key) || '[]'); } catch(e){ return []; } }

  const notifBtn = document.getElementById('notifBtn');
  const notifPanel = document.getElementById('notifPanel');
  const notifOverlay = document.getElementById('notifOverlay');
  const notifClose = document.getElementById('notifClose');
  const notifRefresh = document.getElementById('notifRefresh');
  const notifCountEl = document.getElementById('notifCount');
  const notifPanelList = document.getElementById('notifPanelList');

  function timeAgoLabel(ts){
    const d = new Date(ts).getTime();
    if (isNaN(d)) return '';
    const diff = Date.now() - d;
    const sec = Math.floor(diff/1000);
    if (sec < 60) return `${sec}s ago`;
    const min = Math.floor(sec/60);
    if (min < 60) return `${min}m ago`;
    const hr = Math.floor(min/60);
    if (hr < 24) return `${hr}h ago`;
    const days = Math.floor(hr/24);
    return `${days}d ago`;
  }

  function populateNotifPanel(limit = 20){
    const consultations = safeReadJSON('consultations').slice().sort((a,b)=>{
      const ta = new Date(a.createdAt || a.appointmentDate || 0).getTime();
      const tb = new Date(b.createdAt || b.appointmentDate || 0).getTime();
      return tb - ta;
    }).slice(0, limit);

    notifPanelList.innerHTML = '';
    if (!consultations.length) {
      notifPanelList.innerHTML = '<div class="text-sm text-gray-500 p-3">No notifications</div>';
      notifCountEl.classList.add('hidden');
      return;
    }

    consultations.forEach(item=>{
      const icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-bhms" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/></svg>';
      const when = item.createdAt ? timeAgoLabel(item.createdAt) : (item.appointmentDate || '');
      const name = item.name || item.patientName || 'Unnamed';
      const action = item.appointmentType || item.type || (item.appointmentDate ? 'Appointment' : 'Record');
      const appt = item.appointmentDate ? ('<div class="text-xs text-gray-400 mt-1">' + item.appointmentDate + (item.appointmentTime ? ' • ' + item.appointmentTime : '') + '</div>') : '';
      const node = document.createElement('div');
      node.className = 'p-3 rounded-lg bg-gray-50 flex gap-3 items-start hover:bg-gray-100 cursor-pointer';
      node.innerHTML = `<div class="flex-shrink-0">${icon}</div>
                        <div class="flex-1">
                          <div class="font-semibold">${name} <span class="text-xs text-gray-400">• ${action}</span></div>
                          ${appt}
                        </div>
                        <div class="text-xs text-gray-400">${when}</div>`;
      if (item.id) node.addEventListener('click', ()=> window.open('personal-information.html?id=' + encodeURIComponent(item.id), '_blank'));
      notifPanelList.appendChild(node);
    });

    // update count (recent 24h)
    const now = Date.now();
    const dayMs = 24*60*60*1000;
    const recent = consultations.filter(c=>{
      const t = new Date(c.createdAt || c.appointmentDate || 0).getTime();
      return !isNaN(t) && (now - t) <= dayMs;
    }).length;
    if (recent > 0){
      notifCountEl.textContent = recent;
      notifCountEl.classList.remove('hidden');
    } else {
      notifCountEl.classList.add('hidden');
    }
  }

  // Append recent notifications from localStorage into the panel without clearing existing items
  function appendLocalRecentToPanel(limit = 20){
    try {
      const consultations = safeReadJSON('consultations').slice().sort((a,b)=>{
        const ta = new Date(a.createdAt || a.appointmentDate || 0).getTime();
        const tb = new Date(b.createdAt || b.appointmentDate || 0).getTime();
        return tb - ta;
      }).slice(0, limit);

      if (!consultations.length) return;
      const panel = document.getElementById('notifPanelList');
      if (!panel) return;

      consultations.forEach(item => {
        try {
          // avoid duplicates by consult id or by short text match
          const id = item.id || item.consultationId || item.patientId || '';
          if (id && panel.querySelector('[data-consult-id="' + id + '"]')) return;
          const shortText = (item.name || item.patientName || '') + ' ' + (item.appointmentDate || item.createdAt || '');
          const exists = Array.from(panel.children).some(n => (n.textContent||'').trim().slice(0,120) === (shortText||'').trim().slice(0,120));
          if (exists) return;

          const icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-bhms" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/></svg>';
          const when = item.createdAt ? timeAgoLabel(item.createdAt) : (item.appointmentDate || '');
          const name = item.name || item.patientName || 'Unnamed';
          const action = item.appointmentType || item.type || (item.appointmentDate ? 'Appointment' : 'Record');
          const appt = item.appointmentDate ? ('<div class="text-xs text-gray-400 mt-1">' + escapeHtml(item.appointmentDate) + (item.appointmentTime ? ' • ' + escapeHtml(item.appointmentTime) : '') + '</div>') : '';

          const node = document.createElement('div');
          node.className = 'p-3 rounded-lg bg-gray-50 flex gap-3 items-start hover:bg-gray-100 cursor-pointer';
          if (id) node.setAttribute('data-consult-id', String(id));
          node.innerHTML = `<div class="flex-shrink-0">${icon}</div><div class="flex-1"><div class="font-semibold">${escapeHtml(name)} <span class="text-xs text-gray-400">• ${escapeHtml(action)}</span></div>${appt}</div><div class="text-xs text-gray-400">${escapeHtml(when)}</div>`;
          if (item.id) node.addEventListener('click', ()=> window.open('personal-information.html?id=' + encodeURIComponent(item.id), '_blank'));
          panel.appendChild(node);
        } catch(e){ console.error('appendLocalRecentToPanel item', e); }
      });
    } catch(e){ console.error('appendLocalRecentToPanel', e); }
  }

  // Fetch notifications list from server and populate the panel (overrides localStorage view)
  async function fetchAndPopulateNotifPanel(limit = 50){
    try{
      const res = await fetch('notifications-list.php');
      if (!res.ok) { console.warn('notifications-list fetch failed'); return; }
      const list = await res.json();
      notifPanelList.innerHTML = '';
      // show server-provided upcoming consultations first (2-hour reminders)
      try { insertUpcomingIntoPanel(window._serverUpcomingList || []); } catch(e) { console.error('insertUpcomingIntoPanel failed', e); }
      // append server notifications
      if (!list || !list.length) {
        notifPanelList.innerHTML = '<div class="text-sm text-gray-500 p-3">No notifications</div>';
      } else {
        list.slice(0, limit).forEach(item => {
          try{
            const icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-bhms" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/></svg>';
            const when = item.created_at || item.createdAt || item.createdAt || '';
            const title = item.title || item.message || 'Notification';
            const msg = item.message || '';
            const node = document.createElement('div');
            node.className = 'p-3 rounded-lg bg-gray-50 flex gap-3 items-start hover:bg-gray-100 cursor-pointer';
            node.innerHTML = `<div class="flex-shrink-0">${icon}</div><div class="flex-1"><div class="font-semibold">${escapeHtml(title)}</div><div class="text-xs text-gray-500 mt-1">${escapeHtml(msg)}</div></div><div class="text-xs text-gray-400">${escapeHtml(when)}</div>`;
            if (item.resident_id) node.addEventListener('click', ()=> window.open('view-resident.php?id=' + encodeURIComponent(item.resident_id), '_blank'));
            notifPanelList.appendChild(node);
          }catch(e){ console.error('render notif item', e); }
        });
      }
      // append any recent localStorage notifications
      try { appendLocalRecentToPanel(10); } catch(e) { console.error('appendLocalRecentToPanel failed', e); }

      // Call server to mark notifications seen and hide badge locally
      try {
        // mark server notifications as seen/read, then refresh counts
        await fetch('notifications-mark-seen.php', { method: 'POST' }).catch(()=>{});
        try { await fetchNotifCounts(); } catch(e){}
      } catch(e){}
      try { notifCountEl.classList.add('hidden'); notifCountEl.textContent = '0'; } catch(e){}

    }catch(e){ console.error('Failed to fetch notifications list', e); }
  }

  // Send reminder for a consultation id via the server endpoint
  async function sendReminderForConsult(id, node){
    if (!id) return;
    try{
      node.classList.add('opacity-60','pointer-events-none');
      const res = await fetch('send-reminder.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
      });
      const text = await res.text();
      if (res.ok) {
        showPanelToast('Reminder sent');
        // decrement badge if present
        try {
          const cur = parseInt((notifCountEl.textContent||'0'),10) || 0;
          if (cur > 0) notifCountEl.textContent = String(Math.max(0, cur-1));
          if ((notifCountEl.textContent||'0') === '0') notifCountEl.classList.add('hidden');
        } catch(e){}
      } else {
        showPanelToast('Failed to send reminder: ' + text);
      }
    }catch(e){ showPanelToast('Failed to send reminder'); console.error(e); }
    finally{ node.classList.remove('opacity-60'); node.classList.remove('pointer-events-none'); }
  }

  // Small toast shown inside the panel header
  function showPanelToast(msg, timeout = 3500){
    try{
      const hdr = document.querySelector('#notifPanel .flex.items-center');
      if (!hdr) return;
      const t = document.createElement('div');
      t.className = 'panel-toast p-2 text-sm text-white bg-bhms rounded-md ml-2';
      t.textContent = msg;
      hdr.appendChild(t);
      setTimeout(()=> t.remove(), timeout);
    } catch(e){ console.error('toast error', e); }
  }

  // Merge server-provided upcoming consultations into the panel, placing them above server notifications
  function insertUpcomingIntoPanel(upcoming){
    if (!upcoming || !upcoming.length) return;
    // ensure we have a panel list node
    const panel = document.getElementById('notifPanelList');
    if (!panel) return;
    upcoming.forEach(item => {
      try{
        // Avoid duplicates by consult id
        if (item.id && panel.querySelector('[data-consult-id="' + item.id + '"]')) return;
        const when = (item.date_of_consultation || '') + (item.consultation_time ? (' • ' + item.consultation_time) : '');
        const name = item.resident_name || ('Resident #' + (item.resident_id || ''));
        const consultant = item.consulting_doctor || 'Not specified';
        const icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-bhms" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/></svg>';
        const node = document.createElement('div');
        node.className = 'p-3 rounded-lg bg-yellow-50 flex gap-3 items-start hover:bg-yellow-100 cursor-pointer border border-yellow-100';
        node.setAttribute('data-consult-id', String(item.id || ''));
        node.innerHTML = `<div class="flex-shrink-0">${icon}</div><div class="flex-1"><div class="font-semibold">${escapeHtml(name)} <span class="text-xs text-gray-400">• Upcoming</span></div><div class="text-xs text-gray-500 mt-1">${escapeHtml(when)} • Consultant: ${escapeHtml(consultant)}</div></div><div class="text-xs text-gray-400">Soon</div>`;
        // click => send reminder instead of opening resident page
        node.addEventListener('click', function(e){
          e.preventDefault();
          e.stopPropagation();
          if (!item.id) { showPanelToast('No consultation id'); return; }
          sendReminderForConsult(item.id, node);
        });
        // insert at top
        panel.insertBefore(node, panel.firstChild);
      }catch(e){ console.error('insert upcoming', e); }
    });
  }

  function openNotifPanel(){
    notifOverlay.classList.remove('hidden');
    notifPanel.classList.remove('translate-x-full');
    notifPanel.classList.add('translate-x-0');
    notifBtn.setAttribute('aria-expanded','true');
    // panel population is handled by fetchAndPopulateNotifPanel which merges server + upcoming + local items
  }
  function closeNotifPanel(){
    notifOverlay.classList.add('hidden');
    notifPanel.classList.add('translate-x-full');
    notifPanel.classList.remove('translate-x-0');
    notifBtn.setAttribute('aria-expanded','false');
  }

  // ---- Imminent appointment reminders (within 1 hour) ----
  function getAppointmentDateTime(item){
    if (!item) return null;
    // prefer explicit appointmentDate + appointmentTime
    const datePart = item.appointmentDate || item.date || item.createdAt;
    const timePart = item.appointmentTime || item.time || '';
    if (!datePart) return null;
    // If datePart already includes time (ISO), use it
    if (datePart.includes('T') || datePart.includes(' ')) {
      const d = new Date(datePart);
      if (!isNaN(d)) return d;
    }
    // Combine date + time if possible (assumes ISO date or YYYY-MM-DD)
    try {
      const iso = timePart ? (datePart + 'T' + timePart) : datePart;
      const d = new Date(iso);
      if (!isNaN(d)) return d;
    } catch(e){}
    return null;
  }

  function markImminentInPanel(items){
    // ensure panel list highlights imminent items (called after populate)
    const now = Date.now();
    const oneHour = 60*60*1000;
    // for each node in panel, add reminder badge if within 1 hour
    Array.from(document.querySelectorAll('#notifPanelList > div')).forEach(node=>{
      try{
        const whenText = node.querySelector('.text-xs.text-gray-400, .text-xs')?.textContent || '';
        // the node creation uses item.appointmentDate as displayed - we instead rely on data-ts attribute if present
        const ts = node.getAttribute('data-ts');
        const t = ts ? parseInt(ts,10) : NaN;
        if (!isNaN(t) && t > now && (t - now) <= oneHour) {
          if (!node.querySelector('.reminder-badge')) {
            const badge = document.createElement('div');
            badge.className = 'reminder-badge text-xs text-red-600 font-semibold';
            badge.textContent = 'Reminder: < 1h';
            node.querySelector('.flex-1')?.insertAdjacentElement('afterbegin', badge);
          }
        }
      }catch(e){}
    });
  }

  function checkImminentAppointments(){
    const consultations = safeReadJSON('consultations');
    const notified = JSON.parse(localStorage.getItem('notified_appointments') || '[]');
    const now = Date.now();
    const oneHour = 60*60*1000;
    const imminents = [];

    consultations.forEach(c=>{
      const dt = getAppointmentDateTime(c);
      if (!dt) return;
      const t = dt.getTime();
      if (t > now && (t - now) <= oneHour) {
        // use id if available, else use a fallback key (name+date)
        const key = c.id || (c.name ? (c.name + '|' + (c.appointmentDate || c.date || '')) : JSON.stringify(c));
        if (!notified.includes(key)) imminents.push({ item: c, key, t });
      }
    });

    if (imminents.length) {
      // mark as notified so reminder doesn't repeat
      const newNotified = Array.from(new Set(notified.concat(imminents.map(x=>x.key))));
      localStorage.setItem('notified_appointments', JSON.stringify(newNotified));

      // ensure panel is visible and populated; highlight imminent items
      openNotifPanel();
      // small delay to allow populate to render before marking
      setTimeout(()=> {
        // ensure panel has merged server + upcoming + local items
        try { fetchAndPopulateNotifPanel(); } catch(e){ console.error(e); }
        // after populate, annotate imminent nodes
        // populateNotifPanel creates nodes without data-ts; modify populateNotifPanel to include data-ts or re-populate here
        // We'll re-scan panel and add badges based on consultation timestamps
        const now2 = Date.now();
        const oneHour2 = 60*60*1000;
        // add a visual shake / toast
        try {
          // show simple toast inside panel header
          const toast = document.createElement('div');
          toast.className = 'p-2 text-sm text-white bg-red-600 rounded-md';
          toast.textContent = `${imminents.length} upcoming appointment(s) within 1 hour`;
          document.querySelector('#notifPanel .flex.items-center')?.appendChild(toast);
          setTimeout(()=> toast.remove(), 5000);
        }
       catch(e){}
        // highlight items by searching panel entries for matching name/date
        imminents.forEach(im => {
          const name = im.item.name || im.item.patientName || '';
          const dateStr = (im.item.appointmentDate || im.item.date || '').toString();
          Array.from(document.querySelectorAll('#notifPanelList > div')).forEach(node=>{
            if (node.textContent.includes(name) && node.textContent.includes(dateStr)) {
              node.classList.add('border-2','border-red-100');
              if (!node.querySelector('.reminder-pill')) {
                const pill = document.createElement('div');
                pill.className = 'reminder-pill text-xs text-red-600 font-semibold';
                pill.textContent = 'Reminder: <1h';
                node.querySelector('.flex-1')?.insertAdjacentElement('afterbegin', pill);
              }
            }
          });
        });
      }, 250);
    }
  }

  // run check on load and every minute
  document.addEventListener('DOMContentLoaded', function(){
    checkImminentAppointments();
    setInterval(checkImminentAppointments, 60 * 1000);
  });

  // ensure reminders don't repeat when user clears storage externally
  window.addEventListener('storage', (ev)=>{
    if (ev.key === 'consultations' || ev.key === 'consultations_updated_at') {
      checkImminentAppointments();
    }
  });
  </script>
  <script>
    // Keep panel open & interactive when notif button is clicked.
    // Adds stopPropagation on panel and a safe toggle for the notif button.
    document.addEventListener('DOMContentLoaded', function(){
      const notifBtn = document.getElementById('notifBtn');
      const notifPanel = document.getElementById('notifPanel');
      const notifOverlay = document.getElementById('notifOverlay');

      if (!notifBtn || !notifPanel || !notifOverlay) return;

      // ensure clicking the bell toggles the panel and does not close it immediately
          notifBtn.addEventListener('click', async function(e){
            e.preventDefault();
            e.stopPropagation();
            // Fetch fresh counts and list before opening
            try { await fetchNotifCounts(); } catch(e) { console.error(e); }
            try { await fetchAndPopulateNotifPanel(); } catch(e) { console.error(e); }
            const expanded = this.getAttribute('aria-expanded') === 'true';
            if (expanded) closeNotifPanel(); else openNotifPanel();
          });

      // prevent clicks inside the panel from bubbling to document/overlay
      notifPanel.addEventListener('click', function(e){
        e.stopPropagation();
      });

      // overlay should still close the panel
      notifOverlay.addEventListener('click', function(e){
        // only close when overlay itself is clicked (not when panel is clicked)
        if (e.target === notifOverlay) closeNotifPanel();
      });

      // close with ESC
      document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') closeNotifPanel();
      });
    });
  </script>
  <script src="assets/js/notifications.js"></script>
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
