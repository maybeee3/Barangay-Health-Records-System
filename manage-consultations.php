<?php
session_start();
include 'config.php';

// Enable error display for debugging while diagnosing blank page issues
@ini_set('display_errors', 1);
@ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
  header('Location: login.php');
  exit();
}

$records = [];
$sql = "SELECT hr.*, r.first_name, r.last_name, r.middle_name, r.name_extension, r.barangay, r.city_municipality, r.province FROM health_records hr LEFT JOIN residents r ON hr.resident_id = r.id ORDER BY hr.created_at DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $records[] = $row;
  }
}
$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manage Consultations - Barangay Health Monitoring System</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Print rules: landscape and hide elements marked .no-print */
    @page { size: A4 landscape; }
    @media print {
      .no-print { display: none !important; }
      /* ensure table uses full width in landscape */
      table { width: 100%; }
      /* avoid breaking rows across pages */
      tr { page-break-inside: avoid; }
      thead { display: table-header-group; }
    }
  </style>
</head>
<body class="bg-gradient-to-br from-[#f8fafc] to-white min-h-screen text-gray-700">
  <div max-w-full mx-auto p-6">
    
    <!-- Header copied from homepage.php -->
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
    <div class="h-12 md:h-16"></div>

    <section class="bg-white p-6 rounded-2xl shadow-md mb-6 print-table">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold mb-0">Health Record</h2>
        <div class="flex items-center gap-3 no-print">
          <input id="residentSearch" type="search" placeholder="Search residents..." class="border rounded px-3 py-2 text-sm" />
          <select id="residentSort" class="border rounded px-2 py-2 text-sm">
            <option value="az">Sort A - Z</option>
            <option value="za">Sort Z - A</option>
          </select>
          <div class="text-sm text-gray-500">Showing <span id="resCount">0</span> records</div>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm border rounded-lg">
          <thead class="bg-blue-100 text-blue-600">
            <tr>
              <th class="p-3">Resident Name</th>
              <th class="p-3">Address</th>
              <th class="p-3">Date & Time</th>
              <th class="p-3">Complaint</th>
              <th class="p-3">Diagnosis / Treatment</th>
              <th class="p-3">Consultant</th>
              <th class="p-3">View</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($records)): ?>
              <tr><td colspan="7" class="p-3 text-gray-500">No health records found.</td></tr>
            <?php else: ?>
              <?php foreach ($records as $c): ?>
                <tr class="border-b hover:bg-gray-50">
                  <td class="p-3">
                    <?php
                      $name_parts = [];
                      if (!empty($c['last_name'])) $name_parts[] = $c['last_name'];
                      $first_middle = [];
                      if (!empty($c['first_name'])) $first_middle[] = $c['first_name'];
                      if (!empty($c['middle_name'])) $first_middle[] = $c['middle_name'];
                      if (!empty($first_middle)) $name_parts[] = implode(' ', $first_middle);
                      $final_name = implode(', ', $name_parts);
                      if (!empty($c['name_extension'])) $final_name .= ' ' . $c['name_extension'];
                      echo htmlspecialchars($final_name, ENT_QUOTES);
                    ?>
                  </td>
                  <td class="p-3">
                    <?php
                      $address_parts = [];
                      if (!empty($c['barangay'])) $address_parts[] = $c['barangay'];
                      if (!empty($c['city_municipality'])) $address_parts[] = $c['city_municipality'];
                      if (!empty($c['province'])) $address_parts[] = $c['province'];
                      echo htmlspecialchars(implode(', ', $address_parts), ENT_QUOTES);
                    ?>
                  </td>
                  <td class="p-3"><?php echo htmlspecialchars(($c['record_date'] ?? $c['created_at'] ?? '') . ' ' . ($c['record_time'] ?? '')); ?></td>
                  <td class="p-3"><?php
                    // Complaint column: prefer explicit reason fields first
                    $complaintKeys = ['reason','reason_for_consultation','notes','symptoms','presenting_complaint','chief_complaint','details','comments'];
                    $complaint = '';
                    foreach ($complaintKeys as $k) {
                      if (!empty($c[$k])) { $complaint = $c[$k]; break; }
                    }
                    echo htmlspecialchars($complaint ?: '');
                  ?></td>

                  <td class="p-3"><?php
                    // Diagnosis / Treatment column: combine likely diagnosis/treatment fields
                    $diagKeys = ['diagnosis','assessment','treatment','treatment_prescription','procedures','impression','plan'];
                    $parts = [];
                    foreach ($diagKeys as $k) {
                      if (!empty($c[$k])) $parts[] = $c[$k];
                    }
                    echo htmlspecialchars(implode(' • ', $parts) ?: ($c['description'] ?? ''));
                  ?></td>

                  <td class="p-3"><?php echo htmlspecialchars($c['consulting_doctor'] ?? $c['recorded_by'] ?? $c['created_by'] ?? ''); ?></td>
                  <td class="p-3">
                    <a href="view-health-record.php?id=<?php echo $c['id']; ?>" class="text-blue-600 hover:underline">View</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
    <!-- Print All Consultations Button -->
    <button id="printAllBtn" class="fixed bottom-8 right-8 bg-blue-600 text-white text-lg w-14 h-14 rounded-full shadow-lg flex items-center justify-center hover:bg-blue-700 transition z-50 no-print" title="Print All Consultations">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7m-1 4h2a2 2 0 012 2v6H5v-6a2 2 0 012-2h2m4 0v4m-2-2h4" /></svg>
    </button>
  </div>
  <script>
    (function(){
      // Table filter / sort for residentSearch + residentSort
      const searchEl = document.getElementById('residentSearch');
      const sortEl = document.getElementById('residentSort');
      const resCountEl = document.getElementById('resCount');
      const tbody = document.querySelector('table tbody');
      if (!tbody) return;

      // Build initial rows snapshot
      let rows = Array.from(tbody.querySelectorAll('tr'));

      function normalize(s){ return (s || '').toString().toLowerCase().trim(); }

      function matchesQuery(row, q){
        if (!q) return true;
        // search over visible cells (name, address, complaint, diagnosis)
        const text = normalize(row.textContent);
        return text.indexOf(q) !== -1;
      }

      function applyFilterAndSort(){
        const q = normalize(searchEl ? searchEl.value : '');
        const order = sortEl ? sortEl.value : 'az';

        // Filter
        const visible = rows.filter(r => matchesQuery(r, q));

        // Hide all then show visible (preserve DOM order for sorting step)
        rows.forEach(r => r.style.display = 'none');
        visible.forEach(r => r.style.display = '');

        // Sort visible by Resident Name (cell 0)
        visible.sort((a,b) => {
          const aName = normalize(a.cells[0] ? a.cells[0].textContent : '');
          const bName = normalize(b.cells[0] ? b.cells[0].textContent : '');
          if (aName < bName) return order === 'az' ? -1 : 1;
          if (aName > bName) return order === 'az' ? 1 : -1;
          return 0;
        });

        // Re-append visible rows in sorted order
        // Disconnect mutation observer while re-appending to avoid triggering
        // a mutation -> re-append -> mutation infinite loop that freezes the UI.
        if (observer) observer.disconnect();
        visible.forEach(r => tbody.appendChild(r));
        if (observer) observer.observe(tbody, { childList: true, subtree: false });

        // Update count
        if (resCountEl) resCountEl.textContent = String(visible.length);
      }

      if (searchEl) searchEl.addEventListener('input', applyFilterAndSort);
      if (sortEl) sortEl.addEventListener('change', applyFilterAndSort);

      // Recompute rows if DOM changes (e.g., pagination or dynamic inserts)
      const observer = new MutationObserver(()=>{ rows = Array.from(tbody.querySelectorAll('tr')); applyFilterAndSort(); });
      observer.observe(tbody, { childList: true, subtree: false });

      // Initialize on DOM ready
      document.addEventListener('DOMContentLoaded', applyFilterAndSort);
    })();
  </script>
  <script>
    document.getElementById('printAllBtn').addEventListener('click', function(){
      window.print();
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
