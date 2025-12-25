<?php
session_start();
include 'config.php'; // Include database connection

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
  header('Location: login.php'); // Redirect to login page if not authenticated
  exit();
}

$consultations = [];
$sql = "SELECT c.id, r.last_name, r.first_name, r.middle_name, r.name_extension, c.date_of_consultation, c.consultation_time, COALESCE(c.status, 'Pending') AS status FROM consultations c LEFT JOIN residents r ON c.resident_id = r.id ORDER BY c.date_of_consultation DESC, c.consultation_time ASC";
$result = $conn->query($sql);
if ($result instanceof mysqli_result) {
  if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      $consultations[] = $row;
    }
  }
  $result->free();
} else {
  error_log('[consultations.php] DB query failed: ' . ($conn->error ?? 'unknown error'));
}

// Pending consultations: count of upcoming consultations with status 'Pending'
try {
  $res = $conn->query("SELECT COUNT(*) AS cnt FROM consultations WHERE CONCAT(date_of_consultation, ' ', consultation_time) >= NOW() AND (status IS NULL OR status = '' OR status = 'Pending')");
  if ($res) { $row = $res->fetch_assoc(); $pendingConsults = (int)$row['cnt']; }
} catch (Exception $e) {}

$conn->close();
?>
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
</head>
<body class="bg-gradient-to-br from-[#f8fafc] to-white min-h-screen text-gray-700">
  <div class="max-w-full mx-auto p-6">
    
    <!-- Header: full-width blue (blue-700) with nav pushed to the right (keeps all existing items) -->
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

        <!-- Center / Right: nav pushed to the side -->
        <nav class="hidden md:flex items-center gap-6 ml-auto">
          <a href="homepage.php" class="text-sm font-medium text-white/90 hover:text-white">Dashboard</a>
          <a href="residents.php" class="text-sm font-medium text-white/90 hover:text-white">Residents</a>
          <a href="consultations.php" class="text-sm font-medium text-white border-b-2 border-white/30">Consultations</a>
          <a href="records.php" class="text-sm font-medium text-white/90 hover:text-white">Records</a>
          <a href="reports.php" class="text-sm font-medium text-white/90 hover:text-white">Reports</a>
        </nav>

        <!-- Right: notifications + profile (kept existing controls) -->
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

    <!-- Main Section: appointments table (left) + calendar (right) -->
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-6 bg-white p-6 rounded-2xl shadow-md">
      
      <!-- Left: Appointments Table -->
      <div>
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold">Consultations</h2>
          <div class="flex items-center gap-3">
            <input id="filterName" type="text" placeholder="By resident name..." 
       class="border border-gray-200 rounded-lg px-3 py-2 text-sm" />


  <button id="filterOptionsBtn" type="button" 
          class="border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white flex items-center gap-2">

    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-600" fill="none" 
         viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h18M3 12h12M3 19h6"/>
    </svg>

    <!-- SELECT replacing the Filters text -->
    <select id="filterStatus" class="bg-transparent text-sm outline-none cursor-pointer">
      <option value="">All statuses</option>
      <option value="Pending" selected>Pending</option>
      <option value="Completed">Completed</option>
      <option value="Cancelled">Cancelled</option>
      <option value="No-show">No-show</option>
    </select>

  </button>
</div>

          <!-- Floating QR Scanner Button -->
          <button id="qrScanBtn" title="Scan Resident QR to Add Consultation" class="fixed bottom-8 right-8 bg-blue-600 text-white text-2xl w-12 h-12 rounded-full shadow-lg flex items-center justify-center hover:bg-blue-700 transition z-50">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><rect x="3" y="3" width="7" height="7" rx="2" stroke="currentColor" stroke-width="2"/><rect x="14" y="3" width="7" height="7" rx="2" stroke="currentColor" stroke-width="2"/><rect x="14" y="14" width="7" height="7" rx="2" stroke="currentColor" stroke-width="2"/><rect x="3" y="14" width="7" height="7" rx="2" stroke="currentColor" stroke-width="2"/></svg>
          </button>

          <!-- QR Scanner Modal -->
          <div id="qrModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-[100] hidden">
            <div class="bg-white rounded-xl shadow-lg p-6 relative w-[350px] max-w-full">
              <button id="closeQrModal" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-xl">&times;</button>
              <h3 class="text-lg font-semibold mb-2">Scan Resident QR Code</h3>
              <div id="qrLoading" class="flex items-center justify-center mb-2" style="display:none;">
                <svg class="animate-spin h-6 w-6 text-blue-600 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
                <span class="text-blue-600 text-sm">Opening camera...</span>
              </div>
              <div id="qrTroubleshoot" class="text-xs text-red-600 mb-2" style="display:none;"></div>
              <button id="qrRetryBtn" class="bg-blue-100 text-blue-700 px-3 py-1 rounded text-xs mb-2" style="display:none;" type="button">Retry Camera</button>
              <div id="qr-reader" style="width:320px;max-width:100%;min-height:240px;background:#222;border-radius:8px;"></div>
              <div id="qrDebug" class="text-xs text-gray-400 mt-1"></div>
              <div id="qrError" class="text-red-500 text-sm mt-2 hidden"></div>
            </div>
          </div>
        </div>

        <div class="overflow-x-auto" style="max-height: calc(100vh - 260px); overflow:auto;">
          <table class="min-w-full text-sm">
            <thead>
               <tr class="text-blue-600 text-left">
                <th class="p-3 sticky top-0 bg-blue-100 z-10">Schedule Date/Time</th>
                <th class="p-3 sticky top-0 bg-blue-100 z-10">Resident Name</th>
                <th class="p-3 sticky top-0 bg-blue-100 z-10">Status</th>
                <th class="p-3 sticky top-0 bg-blue-100 z-10">                        </th>
              </tr>
            </thead>
            <tbody id="consultationsTbody" style="display: table-row-group;">
              <?php if (empty($consultations)): ?>
                <tr><td colspan="3" class="p-3 text-gray-500">No consultations found.</td></tr>
              <?php else: ?>
                <?php foreach ($consultations as $consultation): ?>
                  <tr class="border-b hover:bg-gray-50 cursor-pointer" onclick="window.location.href='view-consultation.php?id=<?php echo $consultation['id']; ?>'">
                    <td class="p-3">
                      <?php 
                        echo htmlspecialchars(date('Y-m-d', strtotime($consultation['date_of_consultation'])));
                        if (!empty($consultation['consultation_time'])) {
                          echo ' <span class="text-xs text-gray-500">' . htmlspecialchars(date('h:i A', strtotime($consultation['consultation_time']))) . '</span>';
                        }
                      ?>
                    </td>
                    <td class="p-3">
                        <?php
                          $name_parts_display = [];
                          if (!empty($consultation['last_name'])) $name_parts_display[] = htmlspecialchars($consultation['last_name']);
                          $first_middle_parts = [];
                          if (!empty($consultation['first_name'])) $first_middle_parts[] = htmlspecialchars($consultation['first_name']);
                          if (!empty($consultation['middle_name'])) $first_middle_parts[] = htmlspecialchars($consultation['middle_name']);
                          if (!empty($first_middle_parts)) {
                            $name_parts_display[] = implode(' ', $first_middle_parts);
                          }
                          $final_name = implode(', ', $name_parts_display);
                          if (!empty($consultation['name_extension'])) {
                            $final_name .= ' ' . htmlspecialchars($consultation['name_extension']);
                          }
                          if (trim($final_name) === '') {
                            $final_name = '<span class="text-gray-400">Unknown Resident</span>';
                          }
                          echo '<a href="view-consultation.php?id=' . $consultation['id'] . '" class="text-blue-600 hover:underline">' . $final_name . '</a>';
                        ?>
                        
                    </td>
                    <td class="p-3">
                      <?php
                        $status = isset($consultation['status']) ? $consultation['status'] : 'Pending';
                        $s = strtolower($status);
                        $badge = 'bg-gray-100 text-gray-800';
                        if ($s === 'completed') $badge = 'bg-green-100 text-green-800';
                        if ($s === 'cancelled') $badge = 'bg-red-100 text-red-800';
                        if ($s === 'pending') $badge = 'bg-yellow-100 text-yellow-800';
                        if ($s === 'no-show' || $s === 'noshow') $badge = 'bg-gray-100 text-gray-700';
                        echo '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold ' . $badge . '">' . htmlspecialchars($status) . '</span>';
                      ?>
                    </td>
                    <td class="p-3">
                      <div class="inline-block relative">
                        <button onclick="event.stopPropagation(); toggleMoreMenu(this);" class="moreBtn text-gray-600 hover:text-gray-800" aria-label="More">⋮</button>
                        <div class="moreMenu absolute right-0 mt-1 bg-white border rounded shadow-md hidden" style="min-width:180px; z-index:60;">
                          <button onclick="event.stopPropagation(); sendReminder(<?php echo $consultation['id']; ?>);" class="block w-full text-left px-3 py-2 hover:bg-gray-100">Send reminder</button>
                          <button onclick="event.stopPropagation(); updateStatus(<?php echo $consultation['id']; ?>, 'Completed');" class="block w-full text-left px-3 py-2 hover:bg-gray-100">Check (Completed)</button>
                          <button onclick="event.stopPropagation(); if (confirm('Mark this appointment as Cancelled?')) updateStatus(<?php echo $consultation['id']; ?>, 'Cancelled');" class="block w-full text-left px-3 py-2 hover:bg-gray-100">Cancel (Cancelled)</button>
                          <button onclick="event.stopPropagation(); if (confirm('Mark this appointment as No-show?')) updateStatus(<?php echo $consultation['id']; ?>, 'No-show');" class="block w-full text-left px-3 py-2 hover:bg-gray-100">No-show</button>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- Right: Appointment Calendar -->
      <div class="bg-white shadow-md p-6 rounded-2xl">
        
          
          <div id="calendar"></div>
      </div>
    </section>
  </div>

  <!-- overlay + notification side panel -->
  <!-- Old notification panel removed; replaced by Upcoming Appointments dropdown via JS -->
  <div id="notifOverlay" style="display:none;"></div>

  <script>
      const qrTroubleshoot = document.getElementById('qrTroubleshoot');
      const qrRetryBtn = document.getElementById('qrRetryBtn');
    // utility to format date for display
    function formatDateDisplay(d) {
      try {
        const dt = new Date(d);
        return dt.toLocaleDateString();
      } catch(e) { return d }
    }
    function formatTimeDisplay(t) {
      if (!t) return '';
      // HH:MM -> local formatted
      try {
        const [hh, mm] = t.split(':');
        const date = new Date();
        date.setHours(parseInt(hh,10), parseInt(mm,10));
        return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
      } catch(e){ return t }
    }

    // build events array from consultations array
    function buildEventsFromArray(arr) {
      const events = [];
      arr.forEach(item => {
        if (!item) return;
        const date = item.date_of_consultation || item.created_at || null;
        const time = item.consultation_time || null;
        if (date && time) {
          // Event with time (not allDay)
          const startDateTime = date + 'T' + time;
          // determine color coding
          const now = new Date();
          const eventStart = new Date(startDateTime);
          const status = (item.status || 'Pending').toLowerCase();
          let backgroundColor = null;
          let borderColor = null;

          // Blue => Upcoming (future pending appointments)
          if (eventStart > now && status === 'pending') {
            backgroundColor = '#2563eb'; // blue-600
            borderColor = '#1e40af';
          } else if (status === 'completed') {
            backgroundColor = '#16a34a'; // green-600
            borderColor = '#15803d';
          } else if (status === 'cancelled') {
            backgroundColor = '#ef4444'; // red-500
            borderColor = '#dc2626';
          } else if (status === 'no-show' || status === 'noshow') {
            backgroundColor = '#6b7280'; // gray-500
            borderColor = '#4b5563';
          } else if (status === 'pending') {
            // same-day or past pending appointments use yellow
            backgroundColor = '#f59e0b'; // amber-500
            borderColor = '#d97706';
          }

          // Do not display resident name/time on calendar — only color coding.
          // For cancelled appointments we will display a small red dot (bullet).
          let evTitle = '';
          let textColor = null;

          // determine if event is today
          const nowDate = new Date();
          const todayNoTime = new Date(nowDate.getFullYear(), nowDate.getMonth(), nowDate.getDate());
          const evDateNoTime = new Date(eventStart.getFullYear(), eventStart.getMonth(), eventStart.getDate());
          const isToday = evDateNoTime.getTime() === todayNoTime.getTime();

          if (status === 'cancelled') {
            evTitle = '•';
            textColor = '#ef4444'; // red dot
            // make background subtle so the dot is visible
            backgroundColor = 'transparent';
            borderColor = 'transparent';
          } else {
            // hide title for other statuses; for today's events override color below
            evTitle = '';
          }

          // If the event is today, override to orange regardless of pending/completed
          if (isToday) {
            backgroundColor = '#f97316'; // orange-500
            borderColor = '#ea580c';
          }

          const ev = {
            id: item.id,
            title: evTitle,
            start: startDateTime,
            allDay: false,
            extendedProps: item
          };
          if (backgroundColor) ev.backgroundColor = backgroundColor;
          if (borderColor) ev.borderColor = borderColor;
          if (textColor) ev.textColor = textColor;
          if (backgroundColor) ev.backgroundColor = backgroundColor;
          if (borderColor) ev.borderColor = borderColor;
          events.push(ev);
        } else if (date) {
          // Fallback: allDay event
          const status = (item.status || 'Pending').toLowerCase();
          let backgroundColor = null;
          if (status === 'completed') backgroundColor = '#16a34a';
          else if (status === 'cancelled') backgroundColor = '#ef4444';
          else if (status === 'no-show' || status === 'noshow') backgroundColor = '#6b7280';
          else backgroundColor = '#2563eb';

          // allDay fallback: hide title text, keep color coding; cancelled shows dot
          let evTitle = '';
          let textColor = null;
          if (status === 'cancelled') {
            evTitle = '•';
            textColor = '#ef4444';
            backgroundColor = 'transparent';
          }
          const ev = {
            id: item.id,
            title: evTitle,
            start: date,
            allDay: true,
            extendedProps: item
          };
          if (backgroundColor) ev.backgroundColor = backgroundColor;
          if (textColor) ev.textColor = textColor;
          events.push(ev);
        }
      });
      return events;
    }

    // load consultations from server JSON endpoint and render table
    async function loadConsultations(calendar) {
      const tbody = document.getElementById('consultationsTbody');
      if (!tbody) return;

      try {
        const res = await fetch('consultations-data.php');
        if (!res.ok) throw new Error('Failed to fetch consultations');
        let consultations = await res.json();

        // Missed Appointment Detector: if appointment time has passed and status is still Pending, mark as No-show
        try {
          const now = new Date();
          const missed = (consultations || []).filter(item => {
            const s = (item.status || 'Pending').toLowerCase();
            if (s !== 'pending') return false;
            if (!item.date_of_consultation) return false;
            const time = item.consultation_time || '23:59:59';
            // Build ISO-like string. Browser will interpret without timezone as local.
            const appt = new Date(item.date_of_consultation + 'T' + time);
            return appt < now;
          });

          if (missed.length) {
            // Update server for each missed appointment (silent). After updates, re-fetch list.
            await Promise.all(missed.map(async itm => {
              try {
                const fd = new FormData();
                fd.append('id', itm.id);
                fd.append('status', 'No-show');
                await fetch('update-consultation-status.php', { method: 'POST', body: fd });
              } catch (e) {
                console.error('Failed to mark no-show for', itm.id, e);
              }
            }));

            // re-fetch updated consultations
            const r2 = await fetch('consultations-data.php');
            if (r2.ok) consultations = await r2.json();
          }
        } catch (e) {
          console.error('Missed appointment detector error', e);
        }

        // collect filter values
        const fn = (document.getElementById('filterName') || {}).value || '';
        const statusF = (document.getElementById('filterStatus') || {}).value || '';
        const doctorF = (document.getElementById('filterDoctor') || {}).value || '';
        const barangayF = (document.getElementById('filterBarangay') || {}).value || '';

        const filtered = (consultations || []).filter(item => {
          // name filter
          if (fn) {
            const nameParts = [item.last_name, item.first_name, item.middle_name, item.name_extension].filter(Boolean).join(' ').toLowerCase();
            if (!nameParts.includes(fn.toLowerCase())) return false;
          }
          // status
          if (statusF) {
            const s = (item.status || 'Pending').toLowerCase();
            if (s !== statusF.toLowerCase()) return false;
          }
          // doctor
          if (doctorF) {
            if (!item.consulting_doctor) return false;
            if ((item.consulting_doctor || '').toLowerCase() !== doctorF.toLowerCase()) return false;
          }
          // barangay
          if (barangayF) {
            if (!item.barangay) return false;
            if ((item.barangay || '').toLowerCase() !== barangayF.toLowerCase()) return false;
          }
          return true;
        });

        tbody.innerHTML = '';
        if (!filtered || !filtered.length) {
          tbody.innerHTML = '<tr><td class="p-3 text-gray-500" colspan="6">No consultations found.</td></tr>';
        } else {
          filtered.forEach(item => {
            const tr = document.createElement('tr');
            tr.className = 'border-b hover:bg-gray-50 cursor-pointer';
            const name = [
              item.last_name || '',
              (item.first_name || '') + (item.middle_name ? ' ' + item.middle_name : ''),
              item.name_extension || ''
            ].filter(Boolean).join(', ').trim();
            const displayName = name !== '' ? name : '<span class="text-gray-400">Unknown Resident</span>';

            // determine badge class for status
            let s = (item.status || 'Pending').toLowerCase();
            let badge = 'bg-gray-100 text-gray-800';
            if (s === 'completed') badge = 'bg-green-100 text-green-800';
            if (s === 'cancelled') badge = 'bg-red-100 text-red-800';
            if (s === 'pending') badge = 'bg-yellow-100 text-yellow-800';
            if (s === 'no-show' || s === 'noshow') badge = 'bg-gray-100 text-gray-700';

            tr.innerHTML = `
              <td class="p-3">${formatDateDisplay(item.date_of_consultation)}${item.consultation_time ? ' <span class="text-xs text-gray-500">' + formatTimeDisplay(item.consultation_time) + '</span>' : ''}</td>
              <td class="p-3"><a href="view-consultation.php?id=${item.id}" class="text-blue-600 hover:underline">${displayName}</a></td>
              <td class="p-3"><span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold ${badge}">${item.status || 'Pending'}</span></td>
              <td class="p-3">
                <div class="inline-block relative">
                  <button onclick="event.stopPropagation(); toggleMoreMenu(this);" class="moreBtn text-gray-600 hover:text-gray-800" aria-label="More">⋮</button>
                  <div class="moreMenu absolute right-0 mt-1 bg-white border rounded shadow-md hidden" style="min-width:180px; z-index:60;">
                    <button onclick="event.stopPropagation(); sendReminder(${item.id});" class="block w-full text-left px-3 py-2 hover:bg-gray-100">Send reminder</button>
                    <button onclick="event.stopPropagation(); updateStatus(${item.id}, 'Completed');" class="block w-full text-left px-3 py-2 hover:bg-gray-100">Check (Completed)</button>
                    <button onclick="event.stopPropagation(); if (confirm('Mark this appointment as Cancelled?')) updateStatus(${item.id}, 'Cancelled');" class="block w-full text-left px-3 py-2 hover:bg-gray-100">Cancel (Cancelled)</button>
                    <button onclick="event.stopPropagation(); if (confirm('Mark this appointment as No-show?')) updateStatus(${item.id}, 'No-show');" class="block w-full text-left px-3 py-2 hover:bg-gray-100">No-show</button>
                  </div>
                </div>
              </td>
            `;

            tr.addEventListener('click', function () { openDetails(item.id); });
            tbody.appendChild(tr);
          });
        }

        // update calendar events if calendar instance provided
        if (calendar) {
          calendar.removeAllEvents();
          const events = buildEventsFromArray(filtered);
          events.forEach(ev => calendar.addEvent(ev));
        }
      } catch (err) {
        console.error('Error loading consultations:', err);
        tbody.innerHTML = '<tr><td class="p-3 text-red-500" colspan="6">Failed to load consultations.</td></tr>';
      }
    }

    // show details (open sheet prefilled in new tab)
    function openDetails(id) {
      // This needs to be updated to fetch data from the server or pass via URL
      // For now, we will simply redirect to a generic view/edit page
      window.location.href = `view-consultation.php?id=${id}`;
    }

    // delete entry
    function deleteEntry(id, calendar) {
      if (confirm('Are you sure you want to delete this consultation record?')) {
        // In a real application, this would be an AJAX call to a PHP script that deletes from the database.
        // For demo purposes, we will simulate deletion from the displayed list.
        const formData = new FormData();
        formData.append('id', id);

        fetch('delete-consultation.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            alert(data);
            // Reload consultations after deletion
      loadConsultations(calendar);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting record.');
        });
      }
    }

    // Toggle the kebab menu visibility
    function toggleMoreMenu(btn) {
      const menu = btn.parentElement.querySelector('.moreMenu');
      if (!menu) return;
      // hide other open menus
      document.querySelectorAll('.moreMenu').forEach(m => { if (m !== menu) m.classList.add('hidden'); });
      menu.classList.toggle('hidden');
    }

    // Send reminder via server endpoint
    async function sendReminder(id) {
      try {
        const res = await fetch('send-reminder.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id })
        });
        const text = await res.text();
        alert(text);
        // After sending reminder, mark consultation as Pending and mark reminder_sent server-side
        try { updateStatus(id, 'Pending', true); } catch(e) { console.error(e); }
      } catch (err) {
        console.error(err);
        alert('Failed to send reminder.');
      }
    }

    // Update consultation status via AJAX
    async function updateStatus(id, status, silent = false) {
      try {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('status', status);

        const res = await fetch('update-consultation-status.php', {
          method: 'POST',
          body: formData
        });
        const text = await res.text();
        if (!silent) alert(text);
        // re-fetch fresh data and re-render table/calendar
        try { loadConsultations(window._consultationCalendar); } catch(e) { console.error(e); }
      } catch (err) {
        console.error(err);
        alert('Failed to update status.');
      }
    }

    document.addEventListener('DOMContentLoaded', function () {
      const calendarEl = document.getElementById('calendar');
      const filterName = document.getElementById('filterName');
      const filterStatus = document.getElementById('filterStatus');
      const filterDoctor = document.getElementById('filterDoctor');
      const filterBarangay = document.getElementById('filterBarangay');
      const filterClearBtn = document.getElementById('filterClearBtn');
      const newInfoBtn = document.getElementById('newInfoBtn');

      const initialEvents = [];

      // compute a height so the calendar fits within the viewport
      function calculateCalendarHeight() {
        try {
          const top = calendarEl.getBoundingClientRect().top;
          // subtract a small margin from bottom so it doesn't touch the viewport edge
          const available = Math.max(280, window.innerHeight - top - 32);
          return available;
        } catch (e) {
          return 500;
        }
      }

      const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        selectable: true,
        height: calculateCalendarHeight(),
        events: initialEvents,
        select: async function (info) {
          const selectedDate = info.startStr; // The selected date in 'YYYY-MM-DD' format

          const today = new Date();
          today.setHours(0, 0, 0, 0);
          const clickedDate = new Date(selectedDate);
          clickedDate.setHours(0, 0, 0, 0);

          if (clickedDate <= today) {
              alert('Past dates and the current date are not allowed for new appointments.');
              return;
          }

          // Allow multiple bookings per day, just redirect to add-consultation.php with date prefilled
          window.location.href = `add-consultation.php?date=${selectedDate}`;
        },
        eventClick: function(info) {
          // open details for the clicked event
          if (info.event && info.event.id) openDetails(info.event.id);
        }
      });

      calendar.render();

      // keep calendar height responsive to viewport size
      window.addEventListener('resize', function () {
        try { calendar.setOption('height', calculateCalendarHeight()); } catch (e) { /* ignore */ }
      });

      // expose calendar for status-update refresh and trigger initial load
      window._consultationCalendar = calendar;
      // initial render of table and calendar (fetch server-side JSON)
      loadConsultations(window._consultationCalendar);

      // populate doctor and barangay filters dynamically when data loads
      async function populateFilterOptions() {
        try {
          const res = await fetch('consultations-data.php');
          if (!res.ok) return;
          const list = await res.json();
          const doctors = new Set();
          const barangays = new Set();
          list.forEach(it => {
            if (it.consulting_doctor) doctors.add(it.consulting_doctor);
            if (it.barangay) barangays.add(it.barangay);
          });
          // clear existing
          filterDoctor.innerHTML = '<option value="">All doctors</option>';
          filterBarangay.innerHTML = '<option value="">All barangays</option>';
          doctors.forEach(d => { const o = document.createElement('option'); o.value = d; o.textContent = d; filterDoctor.appendChild(o); });
          barangays.forEach(b => { const o = document.createElement('option'); o.value = b; o.textContent = b; filterBarangay.appendChild(o); });
        } catch (e) { console.error('Failed to populate filters', e); }
      }
      populateFilterOptions();

      // --- Single-button filter panel wiring ---
      (function(){
        const btn = document.getElementById('filterOptionsBtn');
        const panel = document.getElementById('filterOptionsPanel');
        const statusOpts = document.getElementById('filterStatusOptions');
        const barangayOpts = document.getElementById('filterBarangayOptions');
        const hiddenStatus = document.getElementById('filterStatus');
        const hiddenBarangay = document.getElementById('filterBarangay');

        if (!btn || !panel || !statusOpts || !barangayOpts || !hiddenStatus || !hiddenBarangay) return;

        function buildFilterPanel() {
          // Status options
          const statuses = ['', 'Pending', 'Completed', 'Cancelled', 'No-show'];
          statusOpts.innerHTML = '';
          statuses.forEach(s => {
            const display = s === '' ? 'All statuses' : s;
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'block w-full text-left px-2 py-1 rounded hover:bg-gray-100 text-sm';
            b.dataset.value = s;
            b.textContent = display;
            if ((hiddenStatus.value || '') === s) b.classList.add('bg-blue-50','font-semibold');
            b.addEventListener('click', function(){
              // update hidden select value
              hiddenStatus.value = this.dataset.value;
              // update styles
              statusOpts.querySelectorAll('button').forEach(x => x.classList.remove('bg-blue-50','font-semibold'));
              this.classList.add('bg-blue-50','font-semibold');
              // auto-apply filter on selection
              try { filterTrigger(); } catch(e) { /* ignore */ }
            });
            statusOpts.appendChild(b);
          });

          // Barangay options (mirror hidden select options)
          barangayOpts.innerHTML = '';
          Array.from(hiddenBarangay.options).forEach(opt => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'block w-full text-left px-2 py-1 rounded hover:bg-gray-100 text-sm';
            b.dataset.value = opt.value;
            b.textContent = opt.value === '' ? 'All barangays' : opt.textContent;
            if (hiddenBarangay.value === opt.value) b.classList.add('bg-blue-50','font-semibold');
            b.addEventListener('click', function(){
              hiddenBarangay.value = this.dataset.value;
              barangayOpts.querySelectorAll('button').forEach(x => x.classList.remove('bg-blue-50','font-semibold'));
              this.classList.add('bg-blue-50','font-semibold');
              // auto-apply filter on selection
              try { filterTrigger(); } catch(e) { /* ignore */ }
            });
            barangayOpts.appendChild(b);
          });
        }

        function setFilterBtnLabel() {
          const labelEl = document.getElementById('filterBtnLabel');
          if (!labelEl) return;
          const s = (hiddenStatus.value || '').trim();
          if (!s) {
            labelEl.textContent = 'Filters';
            return;
          }
          // show selected status on button
          labelEl.textContent = s === '' ? 'Filters' : s;
        }

        // update label when panel builds and when hidden selects change
        // (hiddenStatus change listener added below)
        setFilterBtnLabel();

        // toggle panel
        btn.addEventListener('click', function(e){
          e.stopPropagation();
          panel.classList.toggle('hidden');
          if (!panel.classList.contains('hidden')) buildFilterPanel();
        });

        // Note: Apply/Clear buttons removed — selections auto-apply above

        // close when clicking outside
        document.addEventListener('click', function(e){
          if (!panel.contains(e.target) && !btn.contains(e.target)) panel.classList.add('hidden');
        });

        // Keep panel in sync when hidden selects are changed programmatically
        hiddenStatus.addEventListener('change', function(){ buildFilterPanel(); setFilterBtnLabel(); });
        hiddenBarangay.addEventListener('change', function(){ buildFilterPanel(); });

      })();

      // filter inputs: trigger reload on change/input
      const filterTrigger = () => loadConsultations(window._consultationCalendar);
      if (filterName) filterName.addEventListener('input', filterTrigger);
      if (filterStatus) filterStatus.addEventListener('change', filterTrigger);
      if (filterDoctor) filterDoctor.addEventListener('change', filterTrigger);
      if (filterBarangay) filterBarangay.addEventListener('change', filterTrigger);
      if (filterClearBtn) filterClearBtn.addEventListener('click', function(){
        filterName.value=''; filterStatus.value=''; filterDoctor.value=''; filterBarangay.value=''; filterTrigger();
      });

      // delegate delete buttons and more menu clicks (Done was replaced by more menu)
      document.getElementById('consultationsTbody').addEventListener('click', (e) => {
        const v = e.target;
        if (v.classList.contains('deleteBtn')) {
          if (confirm('Delete this appointment?')) deleteEntry(v.dataset.id, calendar);
        } else if (v.classList.contains('moreBtn')) {
          // handled inline via toggleMoreMenu
        }
      });

      // listen for storage updates (though we are now using server-side data)
      // This part might need adjustment if real-time updates are desired without full page reload.
      window.addEventListener('storage', (ev) => {
        if (ev.key === 'consultations_updated_at') { // Assuming a trigger for server data update
          location.reload(); // Simple reload for demonstration
        }
      });
  });
  </script>
  <script src="assets/js/upcoming.js"></script>
  <!-- Load only local html5-qrcode library to avoid CDN/fallback issues -->
  <script src="assets/js/html5-qrcode.min.js"></script>
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

    // QR Scanner modal logic
    const qrBtn = document.getElementById('qrScanBtn');
    const qrModal = document.getElementById('qrModal');
    const closeQrModal = document.getElementById('closeQrModal');
    const qrError = document.getElementById('qrError');
    let qrScanner = null;
    let cameraId = null;
    const qrLoading = document.getElementById('qrLoading');
    const qrDebug = document.getElementById('qrDebug');

    function startQrScanner() {
      if (typeof Html5Qrcode === 'undefined') {
        qrLoading.style.display = 'none';
        qrTroubleshoot.style.display = '';
        qrTroubleshoot.textContent = 'QR scanner library failed to load. Please check your internet connection or reload the page.';
        qrRetryBtn.style.display = '';
        return;
      }
        qrTroubleshoot.style.display = 'none';
        qrRetryBtn.style.display = 'none';
      qrError.classList.add('hidden');
      qrLoading.style.display = '';
      qrDebug.textContent = '';
      // Clear previous scanner div
      const qrReaderDiv = document.getElementById('qr-reader');
      qrReaderDiv.innerHTML = '';
      qrReaderDiv.style.display = 'block';
      qrReaderDiv.style.background = '#222';
      qrReaderDiv.style.minHeight = '240px';
      qrReaderDiv.style.borderRadius = '8px';
      if (qrScanner) {
        qrScanner.stop().catch(() => {});
      }
      // Always re-create scanner to avoid stale state
      try {
        qrScanner = new Html5Qrcode("qr-reader");
      } catch (e) {
        qrDebug.textContent += ' | Scanner error: ' + e;
        qrTroubleshoot.style.display = '';
        qrTroubleshoot.textContent = 'Failed to initialize QR scanner. Please reload the page.';
        qrRetryBtn.style.display = '';
        return;
      }
      qrDebug.textContent = 'Requesting camera list...';
      // Try to prompt for permissions if not already granted
      if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: true }).then(() => {
          qrDebug.textContent += ' | Camera permission granted.';
        }).catch(err => {
          qrDebug.textContent += ' | Camera permission denied: ' + err;
          qrTroubleshoot.style.display = '';
          qrTroubleshoot.textContent = 'Camera access denied. Please allow camera permissions in your browser settings. If you are using Chrome, click the camera icon in the address bar and allow access.';
          qrRetryBtn.style.display = '';
        });
      } else {
        qrDebug.textContent += ' | getUserMedia not supported.';
        qrTroubleshoot.style.display = '';
        qrTroubleshoot.textContent = 'Camera access is not supported in this browser. Please use a modern browser like Chrome, Edge, or Firefox.';
      }
      Html5Qrcode.getCameras().then(cameras => {
        qrDebug.textContent = 'Cameras found: ' + (cameras ? cameras.length : 0);
        if (cameras && cameras.length) {
          qrDebug.textContent += ' | Devices: ' + cameras.map(c => c.label).join(', ');
          cameraId = cameras[0].id;
          qrDebug.textContent += ' | Starting camera: ' + cameraId;
          qrScanner.start(
            cameraId,
            { fps: 10, qrbox: 220 },
            qrCodeMessage => {
              let residentId = null;
              if (/^\d+$/.test(qrCodeMessage)) {
                residentId = qrCodeMessage;
              } else {
                try {
                  const url = new URL(qrCodeMessage, window.location.origin);
                  const idParam = url.searchParams.get('id');
                  if (idParam) residentId = idParam;
                } catch(e) {}
              }
              if (residentId) {
                qrScanner.stop().then(() => {
                  qrModal.classList.add('hidden');
                  window.location.href = 'add-consultation.php?id=' + encodeURIComponent(residentId);
                });
              } else {
                qrError.textContent = 'Invalid QR code. Please scan a valid resident QR.';
                qrError.classList.remove('hidden');
              }
            },
            errorMessage => {
              qrError.textContent = errorMessage;
              qrError.classList.remove('hidden');
            }
          ).then(() => {
            qrLoading.style.display = 'none';
            qrDebug.textContent += ' | Camera started.';
          }).catch(err => {
            qrLoading.style.display = 'none';
            qrError.textContent = 'Camera error: ' + err;
            qrError.classList.remove('hidden');
            qrDebug.textContent += ' | Camera error: ' + err;
            qrTroubleshoot.style.display = '';
            qrTroubleshoot.textContent = 'Camera failed to start. Make sure no other app is using the camera, and that you have allowed camera access. If you are using HTTP, switch to HTTPS.';
            qrRetryBtn.style.display = '';
          });
        } else {
          qrLoading.style.display = 'none';
          qrError.textContent = 'No camera found.';
          qrError.classList.remove('hidden');
          qrDebug.textContent += ' | No camera found.';
          qrTroubleshoot.style.display = '';
          qrTroubleshoot.textContent = 'No camera device detected. Please connect a camera and reload the page.';
          qrRetryBtn.style.display = '';
        }
          // Retry button handler
          qrRetryBtn.addEventListener('click', function() {
            startQrScanner();
          });
      }).catch(err => {
        qrLoading.style.display = 'none';
        qrError.textContent = 'Camera error: ' + err;
        qrError.classList.remove('hidden');
        qrDebug.textContent += ' | Camera error: ' + err;
      });
    }

    qrBtn.addEventListener('click', function() {
      qrModal.classList.remove('hidden');
      startQrScanner();
    });

    closeQrModal.addEventListener('click', function() {
      qrModal.classList.add('hidden');
      qrLoading.style.display = 'none';
      if (qrScanner) qrScanner.stop().catch(() => {});
    });
    qrModal.addEventListener('click', function(e) {
      if (e.target === qrModal) {
        qrModal.classList.add('hidden');
        qrLoading.style.display = 'none';
        if (qrScanner) qrScanner.stop().catch(() => {});
      }
    });

    // Calendar event click: redirect to add-consultation.php?id=RESIDENT_ID if available
    window.openDetails = function(id, residentId) {
      if (residentId) {
        window.location.href = `add-consultation.php?id=${residentId}`;
      } else {
        window.location.href = `view-consultation.php?id=${id}`;
      }
    };
  </script>
  <script>
    // Floating Add Consultation Button handler
    document.getElementById("addConsultationBtn").addEventListener("click", function() {
      window.location.href = "add-consultation.php";
    });
  </script>
</body>
</html>