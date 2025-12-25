<?php
session_start();
include 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
  header('Location: login.php');
  exit();
}

$residents = [];
$sql = "SELECT id, last_name, first_name, middle_name, name_extension, date_of_birth, sex, civil_status, contact_no, email, address, province, city_municipality, barangay, registration_status FROM residents ORDER BY last_name ASC, first_name ASC";
$result = $conn->query($sql);
// Debug helper: if the query fails or returns zero rows, show database error and quick counts to help diagnose
if ($result === false) {
  $dbErr = $conn->error;
  // try a simple count query to see if table exists / has rows
  $countResult = $conn->query("SELECT COUNT(*) AS c FROM residents");
  $total = ($countResult && $countResult->num_rows > 0) ? $countResult->fetch_assoc()['c'] : 'unknown';
  echo "<div style='background:#fee;border:1px solid #f99;padding:10px;margin:10px;'><strong>DB Debug:</strong> Query failed: " . htmlspecialchars($dbErr) . "<br><strong>SQL:</strong> " . htmlspecialchars($sql) . "<br><strong>Total rows (COUNT):</strong> " . htmlspecialchars($total) . "</div>";
} else {
  if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      $residents[] = $row;
    }
  } else {
    // show quick diagnostic info when zero rows returned
    $countResult = $conn->query("SELECT COUNT(*) AS c FROM residents");
    $total = ($countResult && $countResult->num_rows > 0) ? $countResult->fetch_assoc()['c'] : 'unknown';
    if ($total === 0 || $total === '0') {
      echo "<div style='background:#fffbeb;border:1px solid #f5c26b;padding:10px;margin:10px;'><strong>DB Info:</strong> The `residents` table exists but contains no rows (COUNT=0).</div>";
    } else {
      echo "<div style='background:#fffbeb;border:1px solid #f5c26b;padding:10px;margin:10px;'><strong>DB Info:</strong> Query succeeded but returned 0 rows for the SELECT projection used. Total rows in table: " . htmlspecialchars($total) . ".</div>";
    }
  }
}
$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manage Residents - Barangay Health Monitoring System</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
  <!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Barangay Health Monitoring System - Consultations</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>
  <style>
    body { font-family: 'Inter', sans-serif; }
  </style>
  <!-- FullCalendar -->
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
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

    <!-- spacer to offset fixed header height -->
    <div class="h-12 md:h-16"></div>

    <section class="bg-white p-6 rounded-2xl shadow-md mb-6 print-table">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold mb-0">Registered Residents</h2>
        <div class="flex items-center gap-3 no-print">
          <input id="residentSearch" type="search" placeholder="Search residents..." class="border rounded px-3 py-2 text-sm" />
          <select id="ageGroupFilter" class="border rounded px-2 py-2 text-sm">
            <option value="">All Ages</option>
            <option value="children">Children (0-14)</option>
            <option value="youth">Youth (15-24)</option>
            <option value="adult">Adult (25-64)</option>
            <option value="senior">Senior (65+)</option>
          </select>
          <select id="genderFilter" class="border rounded px-2 py-2 text-sm">
            <option value="">All Genders</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
          </select>
          <select id="residentSort" class="border rounded px-2 py-2 text-sm">
            <option value="az">Sort A - Z</option>
            <option value="za">Sort Z - A</option>
          </select>
          <div class="text-sm text-gray-500">Showing <span id="resCount">0</span> residents</div>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm border rounded-lg">
          <thead class="bg-blue-100 text-blue-600">
            <tr>
              <th class="p-3">Full Name</th>
              <th class="p-3">Birthdate & Age</th>
              <th class="p-3">Gender</th>
              <th class="p-3">Address</th>
              <th class="p-3">Status</th>
              <th class="p-3">Email</th>
              <th class="p-3">Contact No.</th>
              <th class="p-3 no-print">View</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($residents)): ?>
              <tr><td colspan="8" class="p-3 text-gray-500">No residents found.</td></tr>
            <?php else: ?>
              <?php foreach ($residents as $resident): ?>
                <tr class="border-b hover:bg-gray-50">
                  <td class="p-3">
                    <?php
                      $name_parts = [];
                      if (!empty($resident['last_name'])) $name_parts[] = $resident['last_name'];
                      $first_middle = [];
                      if (!empty($resident['first_name'])) $first_middle[] = $resident['first_name'];
                      if (!empty($resident['middle_name'])) $first_middle[] = $resident['middle_name'];
                      if (!empty($first_middle)) $name_parts[] = implode(' ', $first_middle);
                      $final_name = implode(', ', $name_parts);
                      if (!empty($resident['name_extension'])) $final_name .= ' ' . $resident['name_extension'];
                      echo htmlspecialchars($final_name, ENT_QUOTES);
                    ?>
                  </td>
                  <td class="p-3">
                    <?php
                      if (!empty($resident['date_of_birth']) && $resident['date_of_birth'] !== '0000-00-00') {
                        $dob = $resident['date_of_birth'];
                        $dob_display = date('M j, Y', strtotime($dob));
                        $birth = new DateTime($dob);
                        $today = new DateTime();
                        $age = $today->diff($birth)->y;
                        echo htmlspecialchars($dob_display . ' (' . $age . ' yrs)', ENT_QUOTES);
                      } else {
                        echo '<span class="text-gray-500">N/A</span>';
                      }
                    ?>
                  </td>
                  <td class="p-3"><?php echo htmlspecialchars($resident['sex']); ?></td>
                  <td class="p-3">
                    <?php
                      $address_parts = [];
                      if (!empty($resident['address'])) $address_parts[] = $resident['address'];
                      if (!empty($resident['barangay'])) $address_parts[] = $resident['barangay'];
                      if (!empty($resident['city_municipality'])) $address_parts[] = $resident['city_municipality'];
                      if (!empty($resident['province'])) $address_parts[] = $resident['province'];
                      echo htmlspecialchars(implode(', ', $address_parts), ENT_QUOTES);
                    ?>
                  </td>
                  <td class="p-3"><?php echo htmlspecialchars($resident['civil_status'] ?? ''); ?></td>
                  <td class="p-3"><?php echo htmlspecialchars($resident['email'] ?? ''); ?></td>
                  <td class="p-3"><?php echo htmlspecialchars($resident['contact_no'] ?? ''); ?></td>
                  <td class="p-3 no-print">
                    <a href="view-resident.php?id=<?php echo $resident['id']; ?>" class="text-blue-600 hover:underline">View</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
    <!-- Print All Residents Button -->
    <button id="printAllBtn" 
        class="fixed bottom-8 right-8 bg-blue-600 text-white text-lg 
               w-14 h-14 rounded-full shadow-lg flex items-center justify-center 
               hover:bg-blue-700 transition z-50 no-print"
        title="Print All Residents">

  <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none"
       viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M6 9V2h12v7m-1 4h2a2 2 0 012 2v6H5v-6a2 2 0 012-2h2m4 0v4m-2-2h4" />
  </svg>
</button>

  </div>
  <script>
    document.getElementById('printAllBtn').addEventListener('click', function(){
      window.print();
    });

    // Search and sort functionality for residents table
    document.addEventListener('DOMContentLoaded', function(){
      const tbody = document.querySelector('table tbody');
      if (!tbody) return;
      const searchInput = document.getElementById('residentSearch');
      const ageGroupFilter = document.getElementById('ageGroupFilter');
      const genderFilter = document.getElementById('genderFilter');
      const sortSelect = document.getElementById('residentSort');
      const resCount = document.getElementById('resCount');

      function updateCount(){
        const visible = Array.from(tbody.querySelectorAll('tr')).filter(r => r.style.display !== 'none').length;
        if (resCount) resCount.textContent = visible;
      }

      function filterAndSort(){
        const q = (searchInput.value || '').trim().toLowerCase();
        const ageGroup = ageGroupFilter ? ageGroupFilter.value : '';
        const gender = genderFilter ? genderFilter.value.toLowerCase() : '';
        
        // Show/hide rows based on search, age group, and gender
        Array.from(tbody.querySelectorAll('tr')).forEach(r => {
          const cells = r.querySelectorAll('td');
          if (!cells || cells.length === 0) return;
          
          const rowText = r.textContent.toLowerCase();
          
          // Search filter
          if (q && !rowText.includes(q)) {
            r.style.display = 'none';
            return;
          }
          
          // Age group filter (column 2: Birthdate & Age)
          if (ageGroup && cells.length > 1) {
            const ageText = cells[1].textContent.trim();
            const ageMatch = ageText.match(/\((\d+)\s*y/i);
            if (ageMatch) {
              const age = parseInt(ageMatch[1]);
              let matchesAge = false;
              if (ageGroup === 'children' && age <= 14) matchesAge = true;
              else if (ageGroup === 'youth' && age >= 15 && age <= 24) matchesAge = true;
              else if (ageGroup === 'adult' && age >= 25 && age <= 64) matchesAge = true;
              else if (ageGroup === 'senior' && age >= 65) matchesAge = true;
              
              if (!matchesAge) {
                r.style.display = 'none';
                return;
              }
            }
          }
          
          // Gender filter (column 3: Gender)
          if (gender && cells.length > 2) {
            const genderText = cells[2].textContent.trim().toLowerCase();
            if (!genderText.includes(gender)) {
              r.style.display = 'none';
              return;
            }
          }
          
          r.style.display = '';
        });

        // Sort visible rows by Full Name (first td)
        const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => r.style.display !== 'none');
        rows.sort((a,b) => {
          const aName = (a.querySelector('td')?.textContent || '').trim().toLowerCase();
          const bName = (b.querySelector('td')?.textContent || '').trim().toLowerCase();
          return (sortSelect.value === 'az') ? aName.localeCompare(bName) : bName.localeCompare(aName);
        });
        rows.forEach(r => tbody.appendChild(r));

        updateCount();
      }

      if (searchInput) searchInput.addEventListener('input', filterAndSort);
      if (ageGroupFilter) ageGroupFilter.addEventListener('change', filterAndSort);
      if (genderFilter) genderFilter.addEventListener('change', filterAndSort);
      if (sortSelect) sortSelect.addEventListener('change', filterAndSort);

      // initialize count and sort
      filterAndSort();
    });

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
