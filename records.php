<?php
session_start();
include 'config.php'; // Include database connection

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
  header('Location: login.php'); // Redirect to login page if not authenticated
  exit();
}

$health_records = [];
// Load health records and include resident's name via LEFT JOIN to avoid per-row lookups
$sql = "SELECT hr.*, r.first_name AS resident_first_name, r.last_name AS resident_last_name FROM health_records hr LEFT JOIN residents r ON hr.resident_id = r.id ORDER BY hr.created_at DESC";
$result = $conn->query($sql);

// Protect against failed queries (avoid accessing properties on boolean false)
if ($result instanceof mysqli_result) {
  if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      $health_records[] = $row;
    }
  }
  $result->free();
} else {
  // Log the DB error for debugging and leave $health_records empty
  error_log('[records.php] DB query failed: ' . ($conn->error ?? 'unknown error'));
}
// Note: don't close $conn here because later code on this page may run additional queries.
// Temporary debug output: visit records.php?dbg_records=1 to show sample rows and residents schema
if (!empty($_GET['dbg_records']) && isset($_SESSION['username'])) {
  echo "<div style='padding:12px;background:#fff6; border:1px solid #eee; max-width:1200px; margin:12px auto;'><strong>DEBUG: sample health_records (first 3)</strong><pre>" . htmlspecialchars(print_r(array_slice($health_records,0,3), true)) . "</pre>";
  // show first record lookup attempt
  if (!empty($health_records)) {
    $first = $health_records[0];
    echo "<strong>DEBUG: first record keys/values</strong><pre>" . htmlspecialchars(print_r($first, true)) . "</pre>";
    // show residents table columns
    $cols = [];
    $cres = $conn->query("SHOW COLUMNS FROM residents");
    if ($cres && $cres instanceof mysqli_result) {
      while ($crow = $cres->fetch_assoc()) $cols[] = $crow;
      $cres->free();
    }
    echo "<strong>DEBUG: residents table columns</strong><pre>" . htmlspecialchars(print_r($cols, true)) . "</pre>";
    // attempt to show resident row found by our lookup function for first record
    $tryName = get_patient_name($first, $conn);
    echo "<strong>DEBUG: get_patient_name() result</strong><pre>" . htmlspecialchars($tryName) . "</pre>";
  }
  echo "</div>\n";
}
?>
<?php
// Helper to get a sensible patient name from a record regardless of column name
function get_patient_name(array $rec, $conn){
  $candidates = ['residents_name','resident_name','patient_name','name','full_name'];
  foreach ($candidates as $c) {
    if (!empty($rec[$c])) return $rec[$c];
  }
  // try concatenating first_name/last_name if present on the record
  if (!empty($rec['first_name']) || !empty($rec['last_name'])) {
    return trim(($rec['first_name'] ?? '') . ' ' . ($rec['last_name'] ?? ''));
  }

  // If the query joined resident name columns, use them (resident_first_name/resident_last_name)
  if (!empty($rec['resident_first_name']) || !empty($rec['resident_last_name'])) {
    return trim(($rec['resident_first_name'] ?? '') . ' ' . ($rec['resident_last_name'] ?? ''));
  }

  // If this record references a resident id, try to fetch the resident's name
  $residentId = $rec['resident_id'] ?? $rec['resident'] ?? $rec['res_id'] ?? null;
  if ($residentId) {
    $rid = (int)$residentId;
    if ($rid > 0 && $conn instanceof mysqli) {
      $sql = "SELECT residents_name, resident_name, patient_name, name, full_name, first_name, last_name FROM residents WHERE id = ? LIMIT 1";
      if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $rid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
          foreach ($candidates as $c) {
            if (!empty($row[$c])) {
              $stmt->close();
              if ($res) $res->free();
              return $row[$c];
            }
          }
          if (!empty($row['first_name']) || !empty($row['last_name'])) {
            $out = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            $stmt->close();
            if ($res) $res->free();
            return $out;
          }
        }
        if ($res) $res->free();
        $stmt->close();
      }
    }
  }

  // If still not found, try to lookup resident by contact number or email stored on the record
  $contact = trim($rec['contact_no'] ?? $rec['contact'] ?? $rec['phone'] ?? '');
  $email = trim($rec['email'] ?? '');
  if (($contact || $email) && $conn instanceof mysqli) {
    $clauses = [];
    $types = '';
    $params = [];
    if ($contact) { $clauses[] = 'contact_no = ?'; $types .= 's'; $params[] = $contact; }
    if ($email) { $clauses[] = 'email = ?'; $types .= 's'; $params[] = $email; }
    if ($clauses) {
      $sql2 = 'SELECT residents_name, resident_name, patient_name, name, full_name, first_name, last_name FROM residents WHERE ' . implode(' OR ', $clauses) . ' LIMIT 1';
      if ($stmt2 = $conn->prepare($sql2)) {
        // bind params dynamically
        if ($params) {
          $stmt2->bind_param($types, ...$params);
        }
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        if ($res2 && $r2 = $res2->fetch_assoc()) {
          foreach ($candidates as $c) {
            if (!empty($r2[$c])) {
              $stmt2->close();
              if ($res2) $res2->free();
              return $r2[$c];
            }
          }
          if (!empty($r2['first_name']) || !empty($r2['last_name'])) {
            $out = trim(($r2['first_name'] ?? '') . ' ' . ($r2['last_name'] ?? ''));
            $stmt2->close();
            if ($res2) $res2->free();
            return $out;
          }
        }
        if ($res2) $res2->free();
        $stmt2->close();
      }
    }
  }

  return '';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Barangay Health Monitoring System - Records</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>
  <style>
    body { font-family: 'Inter', sans-serif; }
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
          <a href="homepage.php" class="text-sm font-medium text-white/90 hover:text-white">Dashboard</a>
          <a href="residents.php" class="text-sm font-medium text-white/90 hover:text-white">Residents</a>
          <a href="consultations.php" class="text-sm font-medium text-white/90 hover:text-white">Consultations</a>
          <a href="records.php" class="text-sm font-medium text-white border-b-2 border-white/30">Records</a>
          <a href="reports.php" class="text-sm font-medium text-white/90 hover:text-white">Reports</a>
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

    <!-- spacer for fixed header -->
    <div class="h-16 md:h-20"></div>

    <!-- Records Section -->
    <section class="bg-white rounded-2xl shadow-md p-6">
      <?php if (!empty($_SESSION['flash_success']) || !empty($_SESSION['flash_error'])): ?>
        <div class="mb-4">
          <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-2 rounded"><?php echo htmlspecialchars($_SESSION['flash_success']); ?></div>
          <?php endif; ?>
          <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-2 rounded mt-2"><?php echo htmlspecialchars($_SESSION['flash_error']); ?></div>
          <?php endif; ?>
        </div>
      <?php
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);
      endif; ?>
      <?php
        // Debug info: show count of records and last inserted id (if available)
        $hr_count = 0;
        $countRes = $conn->query("SELECT COUNT(*) AS cnt FROM health_records");
        if ($countRes && $rowc = $countRes->fetch_assoc()) $hr_count = (int)$rowc['cnt'];
      ?>
      <!-- Records in DB debug counter removed from UI -->
      
      <div class="flex flex-col sm:flex-row justify-between items-center mb-4">
        <h2 class="text-lg font-semibold text-gray-700">Resident Health Records</h2>
        <div class="flex items-center gap-2 mt-3 sm:mt-0">
          <input type="text" id="searchInput" placeholder="Search records..."
            class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-600 focus:outline-none" />
          <select id="consultationFilter" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-600 focus:outline-none">
            <option value="">All Records</option>
            <option value="today">Today</option>
            <option value="week">This Week</option>
            <option value="month">This Month</option>
            <option value="3months">Last 3 Months</option>
            <option value="6months">Last 6 Months</option>
            <option value="year">This Year</option>
          </select>
          
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="bg-blue-100 text-blue-600 text-left">
              <th class="py-3 px-4 font-semibold">Patient Name</th>
              <th class="py-3 px-4 font-semibold">Contact no.</th>
              <th class="py-3 px-4 font-semibold">Email</th>
              <th class="py-3 px-4 font-semibold">Last Consultation</th>
              <th class="py-3 px-4 font-semibold">Date Added</th>
              <th class="py-3 px-4 font-semibold text-center">Actions</th>
            </tr>
          </thead>
          <tbody id="recordsTable">
            <?php if (empty($health_records)): ?>
              <tr><td colspan="6" class="p-3 text-gray-500">No health records found.</td></tr>
            <?php else: ?>
              <?php foreach ($health_records as $record): ?>
                <tr class="bg-white border-b hover:bg-gray-50">
                  <td class="px-4 py-3 font-medium text-gray-900"><?php echo htmlspecialchars(get_patient_name($record, $conn)); ?></td>
                  <td class="px-4 py-3"><?php echo htmlspecialchars($record['contact_no'] ?? ''); ?></td>
                  <td class="px-4 py-3"><?php echo htmlspecialchars($record['email'] ?? ''); ?></td>
                  <td class="px-4 py-3"><?php echo htmlspecialchars($record['record_date'] ?? $record['last_consultation'] ?? ''); ?></td>
                  <td class="px-4 py-3"><?php echo htmlspecialchars($record['created_at'] ?? ''); ?></td>
                  <td class="px-4 py-3 text-center">
                    <div class="relative inline-block text-left" x-data="{ open: false }">
                      <button @click="open = !open" class="p-2 rounded-full hover:bg-gray-100 focus:outline-none" title="More actions" aria-label="More actions">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <circle cx="5" cy="12" r="2"/>
                          <circle cx="12" cy="12" r="2"/>
                          <circle cx="19" cy="12" r="2"/>
                        </svg>
                      </button>
                      <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute right-0 mt-2 w-40 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
                        <a href="view-health-record.php?id=<?php echo $record['id']; ?>" class="block px-4 py-2 text-sm text-blue-700 hover:bg-blue-50">View</a>
                        <a href="edit-record.php?id=<?php echo $record['id']; ?>" class="block px-4 py-2 text-sm text-yellow-700 hover:bg-yellow-50">Edit</a>
                        <button class="w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50 deleteBtn" data-id="<?php echo $record['id']; ?>">Delete</button>
                      </div>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <!-- Floating Add Record Button -->
  <a href="add-records.php" class="fixed bottom-8 right-8 bg-blue-600 text-white w-14 h-14 rounded-full shadow-lg flex items-center justify-center hover:bg-blue-700 transition z-50 text-3xl font-light" title="Add New Record">
    +
  </a>

  <!-- Old notification panel removed; upcoming dropdown will be injected by JS -->
  <div id="notifOverlay" style="display:none;"></div>
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

      if (!notifPanelList) return;
      notifPanelList.innerHTML = '';
      if (!consultations.length) {
        notifPanelList.innerHTML = '<div class="text-sm text-gray-500 p-3">No notifications</div>';
        if (notifCountEl) notifCountEl.classList.add('hidden');
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
        if (item.id) node.addEventListener('click', ()=> window.open('personal-information.php?id=' + encodeURIComponent(item.id), '_blank'));
        notifPanelList.appendChild(node);
      });

      // update count (recent 24h)
      const now = Date.now();
      const dayMs = 24*60*60*1000;
      const recent = consultations.filter(c=>{
        const t = new Date(c.createdAt || c.appointmentDate || 0).getTime();
        return !isNaN(t) && (now - t) <= dayMs;
      }).length;
      if (notifCountEl) {
        if (recent > 0){
          notifCountEl.textContent = recent;
          notifCountEl.classList.remove('hidden');
        } else {
          notifCountEl.classList.add('hidden');
        }
      }
    }

    function openNotifPanel(){
      if (notifOverlay) notifOverlay.classList.remove('hidden');
      if (notifPanel) {
        notifPanel.classList.remove('translate-x-full');
        notifPanel.classList.add('translate-x-0');
      }
      if (notifBtn) notifBtn.setAttribute('aria-expanded','true');
      populateNotifPanel();
    }
    function closeNotifPanel(){
      if (notifOverlay) notifOverlay.classList.add('hidden');
      if (notifPanel) {
        notifPanel.classList.add('translate-x-full');
        notifPanel.classList.remove('translate-x-0');
      }
      if (notifBtn) notifBtn.setAttribute('aria-expanded','false');
    }

    if (notifBtn){
      notifBtn.addEventListener('click', (e)=>{
        e.stopPropagation();
        const expanded = notifBtn.getAttribute('aria-expanded') === 'true';
        if (expanded) closeNotifPanel(); else openNotifPanel();
      });
    }
    if (notifOverlay) notifOverlay.addEventListener('click', closeNotifPanel);
    if (notifClose) notifClose.addEventListener('click', closeNotifPanel);
    if (notifRefresh) notifRefresh.addEventListener('click', ()=> populateNotifPanel());

    // update panel/count on storage changes
    window.addEventListener('storage', (ev)=>{
      if (ev.key === 'consultations' || ev.key === 'consultations_updated_at') {
        populateNotifPanel();
      }
    });

    // initial populate
    document.addEventListener('DOMContentLoaded', ()=> {
      populateNotifPanel();
    });
      </script>
    <script>
  // ---- Imminent appointment reminders (within 1 hour) ----
  function getAppointmentDateTime(item){
    if (!item) return null;
    const datePart = item.appointmentDate || item.date || item.createdAt;
    const timePart = item.appointmentTime || item.time || '';
    if (!datePart) return null;
    if (typeof datePart === 'string' && (datePart.includes('T') || datePart.includes(' '))) {
      const d = new Date(datePart);
      if (!isNaN(d)) return d;
    }
    try {
      const iso = timePart ? (datePart + 'T' + timePart) : datePart;
      const d = new Date(iso);
      if (!isNaN(d)) return d;
    } catch(e){}
    return null;
  }

  function markImminentInPanel(){
    const now = Date.now();
    const imminentThreshold = 60 * 60 * 1000; // 1 hour
    const consultations = safeReadJSON('consultations').slice().sort((a,b)=>{
      const ta = new Date(a.createdAt || a.appointmentDate || 0).getTime();
      const tb = new Date(b.createdAt || b.appointmentDate || 0).getTime();
      return tb - ta;
    });

    consultations.forEach(item=>{
      const dateTime = getAppointmentDateTime(item);
      const node = notifPanelList.querySelector(`[data-id="${item.id}"]`);
      if (!node) return;
      const isImminent = dateTime && (dateTime.getTime() - now <= imminentThreshold);
      node.classList.toggle('bg-yellow-50', isImminent);
      node.classList.toggle('font-semibold', isImminent);
    });
  }

  // re-run imminent check on panel open
  document.addEventListener('DOMContentLoaded', ()=> {
    const observer = new MutationObserver(markImminentInPanel);
    observer.observe(notifPanelList, { childList: true, subtree: true });
  });

  function checkImminentAppointments(){
    const consultations = safeReadJSON('consultations') || [];
    const notified = JSON.parse(localStorage.getItem('notified_appointments') || '[]');
    const now = Date.now();
    const oneHour = 60*60*1000;
    const imminents = [];

    consultations.forEach(c=>{
      const dt = getAppointmentDateTime(c);
      if (!dt) return;
      const t = dt.getTime();
      if (t > now && (t - now) <= oneHour) {
        const key = c.id || (c.name ? (c.name + '|' + (c.appointmentDate || c.date || '')) : JSON.stringify(c));
        if (!notified.includes(key)) imminents.push({ item: c, key, t });
      }
    });

    if (!imminents.length) return;

    const newNotified = Array.from(new Set(notified.concat(imminents.map(x=>x.key))));
    localStorage.setItem('notified_appointments', JSON.stringify(newNotified));

    // Populate panel (do not auto-open) and update the badge only.
    setTimeout(()=> {
      populateNotifPanel();
      // highlight imminent items after populate
      setTimeout(markImminentInPanel, 150);
      const notifCountElLocal = document.getElementById('notifCount');
      if (notifCountElLocal) {
        notifCountElLocal.textContent = (parseInt(notifCountElLocal.textContent || '0', 10) + imminents.length).toString();
        if (imminents.length > 0) notifCountElLocal.classList.remove('hidden');
      }
    }, 200);
  }

  // run checks
  document.addEventListener('DOMContentLoaded', function(){
    checkImminentAppointments();
    setInterval(checkImminentAppointments, 60*1000);
  });

  // re-check when consultations change in storage
  window.addEventListener('storage', (ev)=>{
    if (ev.key === 'consultations' || ev.key === 'consultations_updated_at') {
      populateNotifPanel();
      checkImminentAppointments();
    }
  });
  </script>
  <script>
    // Search and Filter
    function applyFilters() {
      const searchFilter = document.getElementById("searchInput").value.toLowerCase();
      const consultationFilter = document.getElementById("consultationFilter").value;
      const now = new Date();
      
      document.querySelectorAll("#recordsTable tr").forEach(row => {
        const residentName = row.children[0].textContent.toLowerCase();
        const age = row.children[1].textContent.toLowerCase();
        const medicalConditions = row.children[2].textContent.toLowerCase();
        const dateAdded = row.children[3].textContent.toLowerCase();
        
        // Search filter
        const matchesSearch = !searchFilter || residentName.includes(searchFilter) || age.includes(searchFilter) || medicalConditions.includes(searchFilter) || dateAdded.includes(searchFilter);
        
        // Date filter
        let matchesDate = true;
        if (consultationFilter && dateAdded) {
          const recordDate = new Date(dateAdded);
          if (!isNaN(recordDate.getTime())) {
            const diffTime = now - recordDate;
            const diffDays = diffTime / (1000 * 60 * 60 * 24);
            
            switch(consultationFilter) {
              case 'today':
                matchesDate = diffDays < 1;
                break;
              case 'week':
                matchesDate = diffDays <= 7;
                break;
              case 'month':
                matchesDate = diffDays <= 30;
                break;
              case '3months':
                matchesDate = diffDays <= 90;
                break;
              case '6months':
                matchesDate = diffDays <= 180;
                break;
              case 'year':
                matchesDate = diffDays <= 365;
                break;
            }
          }
        }
        
        row.style.display = (matchesSearch && matchesDate) ? "" : "none";
      });
    }
    
    document.getElementById("searchInput").addEventListener("keyup", applyFilters);
    document.getElementById("consultationFilter").addEventListener("change", applyFilters);
    
    // Handle Delete button click
    document.getElementById("recordsTable").addEventListener("click", (e) => {
      if (e.target.classList.contains("deleteBtn")) {
        const recordId = e.target.dataset.id;
        if (confirm("Are you sure you want to delete this health record? This action cannot be undone.")) {
          const formData = new FormData();
          formData.append('id', recordId);

          fetch('delete-health-record.php', {
              method: 'POST',
              body: formData
          })
          .then(response => response.text())
          .then(data => {
              alert(data);
              location.reload(); // Reload the page to update the list
          })
          .catch(error => {
              console.error('Error:', error);
              alert('Error deleting health record.');
          });
        }
      }
    });
  </script>
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
