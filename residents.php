<?php
session_start();
include 'config.php'; // Include database connection

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
  header('Location: login.php'); // Redirect to login page if not authenticated
  exit();
}




$residents = [];
$barangay_filter = isset($_GET['barangay']) ? $_GET['barangay'] : '';

// Get unique barangays for filter dropdown
$barangays = [];
$barangay_sql = "SELECT DISTINCT barangay FROM residents WHERE barangay IS NOT NULL AND barangay <> '' ORDER BY barangay ASC";
$barangay_result = $conn->query($barangay_sql);
if ($barangay_result && $barangay_result->num_rows > 0) {
  while ($row = $barangay_result->fetch_assoc()) {
    $barangays[] = $row['barangay'];
  }
}

$sql = "SELECT id, last_name, first_name, middle_name, name_extension, date_of_birth, sex, contact_no, civil_status, province, city_municipality, barangay FROM residents";
if ($barangay_filter !== '') {
  $sql .= " WHERE barangay = '" . $conn->real_escape_string($barangay_filter) . "'";
}
$sql .= " ORDER BY last_name ASC, first_name ASC";
$result = $conn->query($sql);

if ($result === false) {
  // Query failed, show error message
  die('<div style=\"color:red;\">Database query error: ' . htmlspecialchars($conn->error) . '</div>');
}

if ($result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    $residents[] = $row;
  }
}
$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Barangay Health Monitoring System - Residents</title>
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
  </style>
</head>
<body class="bg-gradient-to-br from-[#f8fafc] to-white min-h-screen text-gray-700">
  <div class="max-l-full mx-auto p-6">
    <!-- Header: full-width blue (blue-700) fixed, nav pushed to the right -->
    <header class="bg-blue-700 text-white fixed inset-x-0 top-0 z-40">
      <div class="max-l-full mx-auto flex items-center gap-4 py-4 px-6">
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
          <a href="residents.php" class="text-sm font-medium text-white border-b-2 border-white/30">Residents</a>
          <a href="consultations.php" class="text-sm font-medium text-white/90 hover:text-white">Consultations</a>
          <a href="records.php" class="text-sm font-medium text-white/90 hover:text-white">Records</a>
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
                <a href="reports.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 active:bg-gray-200">
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

    <!-- Residents Content -->
    <main class="space-y-6">
      <section class="bg-white p-6 rounded-2xl shadow-md relative">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-4 gap-4">
          <div class="flex flex-col md:flex-row items-center justify-center w-full gap-4">
            <h2 class="text-lg font-semibold text-gray-700">List of Residents</h2>
            <div class="relative w-full max-w-md md:mx-auto">
              <input type="text" id="searchInput" placeholder="Search residents..."
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-bhms focus:outline-none w-full" />
              <div id="suggestionsBox" class="absolute top-full left-0 right-0 z-10 w-full bg-white border border-gray-300 rounded-lg shadow-lg mt-1 hidden"></div>
            </div>
          </div>
          <form method="get" class="flex items-center gap-2">
            <label for="barangayFilter" class="text-sm font-medium text-gray-700">Filter by Address:</label>
            <select name="barangay" id="barangayFilter" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-2 py-1 text-sm focus:ring-2 focus:ring-bhms focus:outline-none">
              <option value="">All</option>
              <?php foreach ($barangays as $b): ?>
                <option value="<?php echo htmlspecialchars($b); ?>" <?php if ($barangay_filter === $b) echo 'selected'; ?>><?php echo htmlspecialchars($b); ?></option>
              <?php endforeach; ?>
            </select>
            <!-- Filter button removed: selection now auto-submits -->
          </form>
        </div>

        <!-- Floating Add Resident Button -->
        <button id="addResidentBtn" title="Add Resident" class="fixed bottom-8 right-8 bg-bhms text-white text-2xl w-12 h-12 rounded-full shadow-lg flex items-center justify-center hover:bg-blue-700 transition z-50">
          +
        </button>

        <!-- Table -->
        <div class="overflow-x-auto" style="max-height: calc(100vh - 180px); overflow-y: auto;">
          <table class="min-w-full text-sm">
            <thead>
               <tr class="bg-blue-100 text-blue-600 text-left">
                <th class="p-3">Resident Name</th>
                <th class="p-3">Age</th>
                <th class="p-3">Sex</th>
                <th class="p-3">Contact Number</th>
                <th class="p-3">Civil Status</th>
                <th class="p-3">Address</th>
                <th class="p-3">                  </th>
              </tr>
            </thead>
            <tbody id="residentTableBody">
              <?php if (empty($residents)): ?>
                <tr><td colspan="7" class="p-3 text-gray-500">No residents found.</td></tr>
              <?php else: ?>
                <?php foreach ($residents as $resident): ?>
                  <tr class="border-b hover:bg-gray-50">
                    <td class="p-3">
                        <?php
                            $name_parts_display = [];
                            if (!empty($resident['last_name'])) $name_parts_display[] = $resident['last_name'];
                            $first_middle_parts = [];
                            if (!empty($resident['first_name'])) $first_middle_parts[] = $resident['first_name'];
                            if (!empty($resident['middle_name'])) $first_middle_parts[] = $resident['middle_name'];
                            if (!empty($first_middle_parts)) {
                                $name_parts_display[] = implode(' ', $first_middle_parts);
                            }
                            $final_name = implode(', ', $name_parts_display);
                            if (!empty($resident['name_extension'])) {
                                $final_name .= ' ' . $resident['name_extension'];
                            }
                            // escape once for output attributes
                            $final_name_esc = htmlspecialchars($final_name, ENT_QUOTES);
                            echo $final_name_esc;
                        ?>
                    </td>
                    <!-- Age -->
                    <td class="p-3">
                      <?php
                          if (!empty($resident['date_of_birth'])) {
                              $dob = new DateTime($resident['date_of_birth']);
                              $now = new DateTime();
                              $age = $now->diff($dob)->y;
                              echo htmlspecialchars($age);
                          } else {
                              echo '-';
                          }
                      ?>
                    </td>
                    <!-- Sex -->
                    <td class="p-3"><?php echo htmlspecialchars($resident['sex'] ?? '-'); ?></td>
                    <!-- Contact Number -->
                    <td class="p-3"><?php echo htmlspecialchars($resident['contact_no'] ?? '-'); ?></td>
                    <!-- Civil Status -->
                    <td class="p-3"><?php echo htmlspecialchars($resident['civil_status'] ?? '-'); ?></td>
                    <!-- Address -->
                    <td class="p-3">
                      <?php
                          $address_parts = [];
                          if (!empty($resident['purok'])) $address_parts[] = $resident['purok'];
                          if (!empty($resident['barangay'])) $address_parts[] = $resident['barangay'];
                          if (!empty($resident['city_municipality'])) $address_parts[] = $resident['city_municipality'];
                          if (!empty($resident['province'])) $address_parts[] = $resident['province'];
                          $address_str = implode(', ', $address_parts);
                          $address_str_esc = htmlspecialchars($address_str, ENT_QUOTES);
                          echo $address_str_esc;
                      ?>
                    </td>
                    <td class="p-3">
                      <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="p-2 rounded-full hover:bg-gray-100 focus:outline-none" title="More actions" aria-label="More actions">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <circle cx="5" cy="12" r="2"/>
                            <circle cx="12" cy="12" r="2"/>
                            <circle cx="19" cy="12" r="2"/>
                          </svg>
                        </button>
                        <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute right-0 mt-2 w-40 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
                          <a href="view-resident.php?id=<?php echo $resident['id']; ?>" class="block px-4 py-2 text-sm text-blue-700 hover:bg-blue-50">View</a>
                          <a href="edit-resident.php?id=<?php echo $resident['id']; ?>" class="block px-4 py-2 text-sm text-yellow-700 hover:bg-yellow-50">Edit</a>
                          <button class="w-full text-left px-4 py-2 text-sm text-green-700 hover:bg-green-50 printBtn" data-id="<?php echo $resident['id']; ?>" data-name="<?php echo $final_name_esc; ?>" data-address="<?php echo $address_str_esc; ?>">Print ID</button>
                          <button class="w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50 deleteBtn" data-id="<?php echo $resident['id']; ?>">Delete</button>
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

      
    </main>
  </div>

   <script>
    // Redirect to Add Resident Page
    document.getElementById("addResidentBtn").addEventListener("click", () => {
      window.location.href = "add-residents.php";
    });

    // Autocomplete Search Filter
    const searchInput = document.getElementById("searchInput");
    const suggestionsBox = document.getElementById("suggestionsBox");
    let debounceTimer;

    searchInput.addEventListener("keyup", function (e) {
      clearTimeout(debounceTimer);
      const query = this.value.trim();

      if (query.length === 0) {
        suggestionsBox.classList.add("hidden");
        // Reset the table filter when the search box is empty
        document.querySelectorAll("#residentTableBody tr").forEach(row => {
          row.style.display = "";
        });
        return;
      }

      // Immediately filter the table client-side for snappy feedback
      filterTable(query);

      // If user pressed Enter, hide suggestions and don't call fetch
      if (e.key === 'Enter') {
        suggestionsBox.classList.add("hidden");
        return;
      }

      // Debounced suggestions fetch (keeps dropdown behavior)
      debounceTimer = setTimeout(() => {
        fetch(`fetch_resident_suggestions.php?query=${encodeURIComponent(query)}`)
          .then(response => response.json())
          .then(data => {
            suggestionsBox.innerHTML = '';
            if (data.length > 0) {
              data.forEach(item => {
                const suggestionItem = document.createElement("div");
                suggestionItem.classList.add("p-2", "cursor-pointer", "hover:bg-blue-100");
                suggestionItem.textContent = item.name;
                suggestionItem.dataset.id = item.id;
                suggestionItem.addEventListener("click", () => {
                  searchInput.value = item.name;
                  suggestionsBox.classList.add("hidden");
                  filterTable(item.name); // Filter table based on selected suggestion
                });
                suggestionsBox.appendChild(suggestionItem);
              });
              suggestionsBox.classList.remove("hidden");
            } else {
              suggestionsBox.classList.add("hidden");
            }
          })
          .catch(error => {
            console.error('Error fetching suggestions:', error);
            suggestionsBox.classList.add("hidden");
          });
      }, 300); // Debounce for 300ms
    });

    // Hide suggestions when clicking outside
    document.addEventListener("click", (e) => {
      if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
        suggestionsBox.classList.add("hidden");
      }
    });

    // Function to filter the table based on a search term
    function filterTable(searchTerm) {
      const filter = searchTerm.toLowerCase();
      const rows = document.querySelectorAll("#residentTableBody tr");
      let foundMatch = false;
      rows.forEach(row => {
        // Get text content from the combined "Resident Name" column
        const residentName = row.children[0].textContent.toLowerCase();
        
        if (residentName.includes(filter)) {
          row.style.display = "";
          foundMatch = true;
        } else {
          row.style.display = "none";
        }
      });
      // If no match found for the filter, but there are other residents, show all
      if (!foundMatch && filter === "") {
        rows.forEach(row => row.style.display = "");
      }
    }

    // Initial table filter (if search input has value on page load)
    if (searchInput.value.trim().length > 0) {
      filterTable(searchInput.value.trim());
    }

    // Handle Delete button click
    document.getElementById("residentTableBody").addEventListener("click", (e) => {
      if (e.target.classList.contains("deleteBtn")) {
        const residentId = e.target.dataset.id;
        if (confirm("Are you sure you want to delete this resident and ALL associated records (health records, consultations)? This action cannot be undone.")) {
          const formData = new FormData();
          formData.append('id', residentId);

          fetch('delete-resident.php', {
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
              alert('Error deleting resident record.');
          });
        }
      }
    });

  </script>

  <!-- overlay + notification side panel -->
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

      </script>
      <script src="assets/js/upcoming.js"></script>
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
  <!-- QR code lib and Print ID handler -->
  <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
  <script>
    // Print a formatted Resident ID card with QR code
    document.addEventListener('click', function(e){
      const btn = e.target.closest && e.target.closest('.printBtn');
      if (!btn) return;
      const id = btn.dataset.id;
      const name = btn.dataset.name || '';
      const address = btn.dataset.address || '';

      const qrUrl = new URL('view-resident.php?id=' + encodeURIComponent(id), window.location.href).toString();

      QRCode.toDataURL(qrUrl, { width: 250, margin: 1 }).then(function(dataUrl){
        const w = window.open('', '_blank');
        if (!w) { alert('Popup blocked. Please allow popups for this site to print the ID.'); return; }
        const title = 'Resident ID - ' + name;
        const html = `<!doctype html>
          <html>
            <head>
              <meta charset="utf-8" />
              <title>${title}</title>
              <style>
                body{ font-family: Inter, Arial, Helvetica, sans-serif; margin:0; padding:20px; background:#f4f6fb }
                .card{ width:360px; height:220px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; box-shadow:0 4px 12px rgba(16,24,40,0.06); padding:16px; display:flex; gap:12px; align-items:center }
                .left{ flex:1 }
                .logo{ width:64px; height:64px; border-radius:8px; background:#eef2ff; display:flex; align-items:center; justify-content:center; font-weight:700; color:#1e40af }
                .name{ font-size:18px; font-weight:700; color:#0f172a; margin-top:8px }
                .meta{ font-size:12px; color:#475569; margin-top:6px }
                .qr{ width:110px; height:110px; background:#fff; display:flex; align-items:center; justify-content:center; }
                .top-strip{ width:100%; height:6px; background:#2563eb; border-radius:4px }
                @media print { body{background:#fff} .card{ box-shadow:none; border: none } }
              </style>
            </head>
            <body>
              <div style="display:flex; justify-content:center; align-items:center; height:100%;">
                <div>
                  <div class="top-strip"></div>
                  <div class="card">
                    <div class="left">
                      <div style="display:flex; gap:12px; align-items:center;">
                        <div class="logo">BRGY</div>
                        <div>
                          <div style="font-size:11px; color:#64748b;">Barangay Resident</div>
                          <div class="name">${name}</div>
                        </div>
                      </div>
                      <div class="meta">${address}</div>
                    </div>
                    <div class="qr"><img src="${dataUrl}" alt="QR Code" style="width:110px;height:110px;display:block" /></div>
                  </div>
                  <div style="text-align:center; font-size:11px; color:#94a3b8; margin-top:8px">Present this ID when requested by health staff.</div>
                </div>
              </div>
              <script>
                window.onload = function(){
                  setTimeout(function(){ window.print(); }, 250);
                };
              <\/script>
            </body>
          </html>`;

        w.document.open();
        w.document.write(html);
        w.document.close();
      }).catch(function(err){
        console.error('QR generation error', err);
        alert('Failed to generate QR code for printing the ID.');
      });
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