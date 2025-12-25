<?php
session_start(); // Start the session to access session variables

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
  header('Location: login.php'); // Redirect to login page if not authenticated
  exit();
}
// Load DB connection and prepare report data
include_once 'config.php';

// Load system settings for printable header (if available)
$settings_file = __DIR__ . '/settings.json';
$settings = [];
if (file_exists($settings_file)) {
  $sj = @file_get_contents($settings_file);
  $data = $sj ? json_decode($sj, true) : null;
  if (is_array($data)) $settings = $data;
}

$system_name = $settings['system_name'] ?? 'Barangay Health Monitoring System';
$barangay_name = $settings['barangay_name'] ?? '';
$address = $settings['address'] ?? '';
$logo_path = (!empty($settings['logo_path']) && file_exists(__DIR__ . '/' . $settings['logo_path'])) ? $settings['logo_path'] : 'Brgy. San Isidro-LOGO.png';

// --- Initialize date filter from GET parameters ---
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$dateFilter = '';
$dateDescription = 'All time';

if ($from && $to) {
  $safeFrom = $conn->real_escape_string($from);
  $safeTo = $conn->real_escape_string($to);
  $dateFilter = "WHERE c.date_of_consultation BETWEEN '{$safeFrom}' AND '{$safeTo}'";
  $dateDescription = date('F j, Y', strtotime($from)) . ' – ' . date('F j, Y', strtotime($to));
} elseif ($from) {
  $safeFrom = $conn->real_escape_string($from);
  $dateFilter = "WHERE c.date_of_consultation >= '{$safeFrom}'";
  $dateDescription = 'From ' . date('F j, Y', strtotime($from));
} elseif ($to) {
  $safeTo = $conn->real_escape_string($to);
  $dateFilter = "WHERE c.date_of_consultation <= '{$safeTo}'";
  $dateDescription = 'Until ' . date('F j, Y', strtotime($to));
}

// --- Total residents (for percentages) ---
$totalResidents = 0;
try {
  $r = $conn->query("SELECT COUNT(*) AS cnt FROM residents");
  if ($r) { $row = $r->fetch_assoc(); $totalResidents = (int)$row['cnt']; }
} catch (Exception $e) { }

// --- Age groups by sex (stacked bar) ---
$ageGroups = ['0-4','5-14','15-24','25-44','45-64','65+'];
$ageGender = [];
foreach ($ageGroups as $g) $ageGender[$g] = ['Male'=>0,'Female'=>0,'Other'=>0];
try {
  $sql = "SELECT
            CASE
              WHEN TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE()) BETWEEN 0 AND 4 THEN '0-4'
              WHEN TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE()) BETWEEN 5 AND 14 THEN '5-14'
              WHEN TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE()) BETWEEN 15 AND 24 THEN '15-24'
              WHEN TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE()) BETWEEN 25 AND 44 THEN '25-44'
              WHEN TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE()) BETWEEN 45 AND 64 THEN '45-64'
              ELSE '65+'
            END AS age_group,
            COALESCE(NULLIF(LOWER(sex),''),'other') AS sex,
            COUNT(*) AS cnt
          FROM residents
          GROUP BY age_group, sex";
  $res = $conn->query($sql);
  if ($res) {
    while ($r = $res->fetch_assoc()) {
      $ag = $r['age_group'] ?: '65+';
      $sex = ucfirst($r['sex'] ?: 'Other');
      if (!in_array($sex, ['Male','Female'])) $sex = 'Other';
      if (!isset($ageGender[$ag])) $ageGender[$ag] = ['Male'=>0,'Female'=>0,'Other'=>0];
      $ageGender[$ag][$sex] = (int)$r['cnt'];
    }
  }
} catch (Exception $e) {}

// --- Consultations per month (last 12 months) ---
$consultMonths = [];
$consultCounts = [];
try {
  $labels = [];
  for ($i = 11; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-{$i} month"));
    $labels[] = $m;
    $consultCounts[$m] = 0;
  }
  $q = $conn->query("SELECT DATE_FORMAT(date_of_consultation, '%Y-%m') AS ym, COUNT(*) AS cnt FROM consultations WHERE date_of_consultation >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY ym ORDER BY ym");
  if ($q) {
    while ($rw = $q->fetch_assoc()) {
      $ym = $rw['ym'];
      if (isset($consultCounts[$ym])) $consultCounts[$ym] = (int)$rw['cnt'];
    }
  }
  $consultMonths = array_values($consultCounts);
} catch (Exception $e) {}

// --- Immunization approximation ---
$immunCount = 0;
try {
  $iq = $conn->query("SELECT COUNT(*) AS cnt FROM consultations WHERE LOWER(COALESCE(reason_for_consultation,'')) LIKE '%vaccin%' OR LOWER(COALESCE(reason_for_consultation,'')) LIKE '%immuniz%'");
  if ($iq) { $ir = $iq->fetch_assoc(); $immunCount = (int)$ir['cnt']; }
} catch (Exception $e) {}
$immunPercent = $totalResidents > 0 ? round($immunCount / $totalResidents * 100, 1) : 0;

// --- Prenatal checkups per month (last 12 months) ---
$prenatalCounts = [];
try {
  $pcounts = [];
  for ($i = 11; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-{$i} month"));
    $pcounts[$m] = 0;
  }
  $pq = $conn->query("SELECT DATE_FORMAT(date_of_consultation, '%Y-%m') AS ym, COUNT(*) AS cnt FROM consultations WHERE (LOWER(COALESCE(reason_for_consultation,'')) LIKE '%prenat%' OR LOWER(COALESCE(reason_for_consultation,'')) LIKE '%anc%' OR LOWER(COALESCE(reason_for_consultation,'')) LIKE '%pregn%') AND date_of_consultation >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY ym ORDER BY ym");
  if ($pq) {
    while ($pr = $pq->fetch_assoc()) {
      $ym = $pr['ym'];
      if (isset($pcounts[$ym])) $pcounts[$ym] = (int)$pr['cnt'];
    }
  }
  $prenatalCounts = array_values($pcounts);
} catch (Exception $e) {}

// --- Health trends (keyword-based counts) ---
$illnessKeywords = ['dengue','flu','fever','hypertension','diarrhea','cold'];
$illnessCounts = [];
try {
  $totalConsults = 0;
  $tq = $conn->query("SELECT COUNT(*) AS cnt FROM consultations");
  if ($tq) { $tr = $tq->fetch_assoc(); $totalConsults = (int)$tr['cnt']; }
  foreach ($illnessKeywords as $kw) {
    $kq = $conn->query("SELECT COUNT(*) AS cnt FROM consultations WHERE LOWER(COALESCE(reason_for_consultation,'')) LIKE '%" . $conn->real_escape_string($kw) . "%'");
    $kcount = 0;
    if ($kq) { $kr = $kq->fetch_assoc(); $kcount = (int)$kr['cnt']; }
    $illnessCounts[$kw] = $kcount;
  }
  // other = remaining
  $sum = array_sum($illnessCounts);
  $illnessCounts['other'] = max(0, $totalConsults - $sum);
} catch (Exception $e) {}

// --- Residents by Barangay (for graph) ---
$barangayCounts = [];
$barangayLabels = [];
$barangayData = [];
$filterType = $_GET['filter'] ?? 'all';
$where = '';
if ($filterType === 'day') {
  $where = "WHERE DATE(created_at) = CURDATE()";
} elseif ($filterType === 'week') {
  $where = "WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($filterType === 'month') {
  $where = "WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
} elseif ($filterType === 'year') {
  $where = "WHERE YEAR(created_at) = YEAR(CURDATE())";
}
try {
  $q = $conn->query("SELECT barangay, COUNT(*) AS cnt FROM residents $where GROUP BY barangay ORDER BY cnt DESC");
  if ($q) while ($row = $q->fetch_assoc()) {
    $barangayCounts[$row['barangay'] ?: 'Unknown'] = (int)$row['cnt'];
  }
  $barangayLabels = array_keys($barangayCounts);
  $barangayData = array_values($barangayCounts);
} catch (Exception $e) {}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Barangay Health Monitoring System - Reports</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            bhms: { DEFAULT: '#2563eb', light: '#e6f0ff' }
          },
          fontFamily: { inter: ['Inter', 'sans-serif'] }
        }
      }
    }
  </script>
  <style>
    body { font-family: Inter, system-ui, sans-serif; }
    @media print {
      header, #filterSection { display: none !important; }
      body { background: white; }
      section { box-shadow: none !important; }
      /* show our printable header and signatories */
      .print-header, .print-signatories { display: block !important; }
    }
    /* hidden on screen, shown only on print */
    .print-header, .print-signatories, .page-footer { display: none; }
    /* Summary visible on-screen and on-print; detailed-report can be hidden on print */
    .print-summary { display: block; }
    .detailed-report { display: block; }
    @page { size: A4; margin: 20mm; }
    @media print {
      /* page number content */
      .pagenum:after { content: counter(page) " of " counter(pages); }
      .page-footer { display:block; position: fixed; bottom: 0; left: 0; right: 0; }
      /* print layout: show print header and summary, hide large/detailed sections */
      .print-header, .print-signatories, .print-summary { display: block !important; }
      .detailed-report { display: none !important; }
      /* Force the summary layout to match the official print summary format */
      .print-summary { width: 100%; padding: 6mm !important; }
      .print-summary .print-top-grid { display: grid !important; grid-template-columns: repeat(4, 1fr) !important; gap: 8px !important; }
      .print-summary .print-top-grid > div { background: #fff !important; border: 1px solid #ddd; padding: 8px !important; box-shadow: none !important; }
      .print-summary .print-top-grid .text-xl { font-size: 20px !important; }
      .print-summary .print-three-grid { display: grid !important; grid-template-columns: repeat(3, 1fr) !important; gap: 10px !important; margin-top: 8px !important; }
      .print-summary .print-three-grid > div { background: #fff !important; border: none !important; padding: 6px !important; }
      .print-summary .print-three-grid ul { margin:0; padding-left:16px; }
      /* Make the printed output black & white friendly */
      .print-summary .bg-gray-50, .print-summary .bg-white { background: #fff !important; color: #000 !important; }
      /* improve readability for print */
      .bg-white, .bg-gray-50 { background: white !important; }
      .shadow-md, .shadow-2xl { box-shadow: none !important; }
      body { color: #000; }
    }
  </style>
</head>
<body class="bg-gradient-to-br from-[#f8fafc] to-white min-h-screen text-gray-700">
  <div class="max-w-full mx-auto p-6">

    <!-- Header: full-width blue (blue-700) with nav pushed to the right (preserves all items) -->
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
          <a href="homepage.php" class="text-sm font-medium text-white/90 hover:text-white transition">Dashboard</a>
          <a href="residents.php" class="text-sm font-medium text-white/90 hover:text-white transition">Residents</a>
          <a href="consultations.php" class="text-sm font-medium text-white/90 hover:text-white transition">Consultations</a>
          <a href="records.php" class="text-sm font-medium text-white/90 hover:text-white transition">Records</a>
          <a href="reports.php" class="text-sm font-medium text-white border-b-2 border-white/30">Reports</a>
        </nav>

        <div class="flex items-center gap-4 ml-4">
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

    <!-- Printable official header (appears only on print) -->
    <div class="print-header bg-white p-4 rounded-md mb-4 border" style="text-align:center;">
      <div style="display:flex;align-items:center;gap:12px;justify-content:center;">
        <div style="width:72px;height:72px;flex:0 0 72px;">
          <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="logo" style="width:72px;height:72px;object-fit:cover;border-radius:6px;" />
        </div>
        <div style="text-align:left;">
          <div style="font-weight:700;font-size:16px;"><?php echo htmlspecialchars($barangay_name ?: $system_name); ?></div>
          <div style="font-size:14px;color:#333;"><?php echo htmlspecialchars($system_name); ?></div>
          <?php if (!empty($address)): ?><div style="font-size:12px;color:#555;margin-top:4px;"><?php echo htmlspecialchars($address); ?></div><?php endif; ?>
        </div>
      </div>
      <div style="margin-top:12px;font-weight:600;">Official Report — <?php echo date('F j, Y'); ?></div>
    </div>

    <!-- Filter and Print Section -->
    <div id="filterSection" class="flex flex-col sm:flex-row justify-end items-center mb-6 gap-3">
      <div class="flex items-center gap-2 bg-white px-4 py-2 rounded-lg shadow">
        <label for="startDate" class="text-sm text-gray-500">From:</label>
        <input type="date" id="startDate" class="border-none text-sm focus:ring-0" value="<?php echo htmlspecialchars($from ?? ''); ?>" />
        <label for="endDate" class="text-sm text-gray-500 ml-2">To:</label>
        <input type="date" id="endDate" class="border-none text-sm focus:ring-0" value="<?php echo htmlspecialchars($to ?? ''); ?>" />
        <button type="button" id="applyFilterBtn" onclick="applyDateFilter()" class="ml-3 bg-bhms text-white px-3 py-1 rounded-md text-sm hover:bg-blue-700 transition">Apply</button>
        <button type="button" id="clearFilterBtn" onclick="clearDateFilter()" class="ml-2 bg-gray-200 text-gray-700 px-3 py-1 rounded-md text-sm hover:bg-gray-300 transition">Clear</button>
      </div>

      <button onclick="window.print()" class="bg-bhms text-white p-3 rounded-lg shadow hover:bg-blue-700 transition flex items-center justify-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M6 9V2h12v7m-1 4h2a2 2 0 012 2v6H5v-6a2 2 0 012-2h2m4 0v4m-2-2h4" />
        </svg>
      </button>
    </div>

    <?php
    // Additional report queries for extended reports section
    // Consultation totals
    $consult_daily = 0; $consult_week = 0; $consult_month = 0; $consult_year = 0;
    try {
      $r = $conn->query("SELECT COUNT(*) AS cnt FROM consultations WHERE date_of_consultation = CURDATE()"); if ($r) { $consult_daily = (int)$r->fetch_assoc()['cnt']; }
      $r = $conn->query("SELECT COUNT(*) AS cnt FROM consultations WHERE date_of_consultation BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()"); if ($r) { $consult_week = (int)$r->fetch_assoc()['cnt']; }
      $r = $conn->query("SELECT COUNT(*) AS cnt FROM consultations WHERE MONTH(date_of_consultation) = MONTH(CURDATE()) AND YEAR(date_of_consultation) = YEAR(CURDATE())"); if ($r) { $consult_month = (int)$r->fetch_assoc()['cnt']; }
      $r = $conn->query("SELECT COUNT(*) AS cnt FROM consultations WHERE YEAR(date_of_consultation) = YEAR(CURDATE())"); if ($r) { $consult_year = (int)$r->fetch_assoc()['cnt']; }
    } catch (Exception $e) {}

    // Consultation breakdown by age group and sex (Children:0-14, Youth:15-24, Adult:25-64, Senior:65+)
    $ageBreakdown = ['Children'=>0,'Youth'=>0,'Adult'=>0,'Senior'=>0];
    $sexBreakdown = ['male'=>0,'female'=>0,'other'=>0];
    try {
      $sql = "SELECT r.id, r.sex, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) AS age FROM residents r JOIN consultations c ON c.resident_id = r.id " . ($dateFilter ?: '');
      $res = $conn->query($sql);
      if ($res) {
        $seen = [];
        while ($rw = $res->fetch_assoc()) {
          $age = (int)$rw['age'];
          // Age group
          if ($age <= 14) $ageBreakdown['Children']++;
          else if ($age <= 24) $ageBreakdown['Youth']++;
          else if ($age <= 64) $ageBreakdown['Adult']++;
          else $ageBreakdown['Senior']++;
          // sex
          $s = strtolower(trim($rw['sex'] ?? ''));
          if ($s === 'male') $sexBreakdown['male']++;
          else if ($s === 'female') $sexBreakdown['female']++;
          else $sexBreakdown['other']++;
        }
      }
    } catch (Exception $e) {}

    // List of patients who consulted (recent 500)
    $patientsList = [];
    try {
      $q = $conn->query("SELECT c.*, r.first_name, r.middle_name, r.last_name, r.barangay, r.address, r.date_of_birth FROM consultations c LEFT JOIN residents r ON c.resident_id = r.id " . ($dateFilter ?: '') . " ORDER BY c.date_of_consultation DESC, c.consultation_time DESC LIMIT 500");
      if ($q) while ($p = $q->fetch_assoc()) $patientsList[] = $p;
    } catch (Exception $e) {}

    // Consultant activity
    $consultantActivity = [];
    try {
      $q = $conn->query("SELECT COALESCE(consulting_doctor,'Unassigned') AS doc, COUNT(*) AS total, SUM(CASE WHEN date_of_consultation < CURDATE() THEN 1 ELSE 0 END) AS completed, SUM(CASE WHEN date_of_consultation >= CURDATE() THEN 1 ELSE 0 END) AS upcoming, SUM(reminder_sent) AS emails_sent FROM consultations GROUP BY doc ORDER BY total DESC");
      if ($q) while ($r = $q->fetch_assoc()) $consultantActivity[] = $r;
    } catch (Exception $e) {}

    // Appointment report (using consultations as appointments)
    $total_scheduled = 0; $upcoming_appt = 0; $completed_appt = 0; $no_shows = 0; $cancelled = 0;
    try {
      $r = $conn->query("SELECT COUNT(*) AS cnt FROM consultations WHERE date_of_consultation >= CURDATE()"); if ($r) $total_scheduled = (int)$r->fetch_assoc()['cnt'];
      $r = $conn->query("SELECT COUNT(*) AS cnt FROM consultations WHERE date_of_consultation > CURDATE()"); if ($r) $upcoming_appt = (int)$r->fetch_assoc()['cnt'];
      $r = $conn->query("SELECT COUNT(*) AS cnt FROM consultations WHERE date_of_consultation < CURDATE()"); if ($r) $completed_appt = (int)$r->fetch_assoc()['cnt'];
      // No-shows and Cancelled: count by status (if status column used)
      $rq = $conn->query("SELECT COUNT(*) AS cnt FROM consultations WHERE LOWER(COALESCE(status,'')) IN ('no-show','noshow','no show')"); if ($rq) $no_shows = (int)$rq->fetch_assoc()['cnt'];
      $rq2 = $conn->query("SELECT COUNT(*) AS cnt FROM consultations WHERE LOWER(COALESCE(status,'')) IN ('cancelled','canceled')"); if ($rq2) $cancelled = (int)$rq2->fetch_assoc()['cnt'];
    } catch (Exception $e) {}

    // Most frequent residents
    $frequentResidents = [];
    try {
      $q = $conn->query("SELECT r.id, CONCAT(r.first_name,' ',r.last_name) AS name, r.barangay, COUNT(*) AS cnt FROM consultations c JOIN residents r ON c.resident_id = r.id " . ($dateFilter ?: '') . " GROUP BY r.id ORDER BY cnt DESC LIMIT 20");
      if ($q) while ($fr = $q->fetch_assoc()) $frequentResidents[] = $fr;
    } catch (Exception $e) {}

    // Purok / Zone health report by barangay
    $zoneReport = [];
    try {
      $q = $conn->query("SELECT r.barangay, COUNT(*) AS cnt FROM consultations c JOIN residents r ON c.resident_id = r.id " . ($dateFilter ?: '') . " GROUP BY r.barangay ORDER BY cnt DESC");
      if ($q) while ($z = $q->fetch_assoc()) $zoneReport[] = $z;
    } catch (Exception $e) {}

    // --- Report period and summary calculations (respect optional date filters passed as GET) ---
    $report_title = trim($_GET['report_title'] ?? 'Monthly Consultation Report');
    $from = trim($_GET['from'] ?? '');
    $to = trim($_GET['to'] ?? '');
    $dateFilter = '';
    $dateDescription = 'All time';
    if ($from && $to) {
      $safeFrom = $conn->real_escape_string($from);
      $safeTo = $conn->real_escape_string($to);
      $dateFilter = "WHERE date_of_consultation BETWEEN '{$safeFrom}' AND '{$safeTo}'";
      $dateDescription = date('F j, Y', strtotime($from)) . ' – ' . date('F j, Y', strtotime($to));
    } elseif ($from) {
      $safeFrom = $conn->real_escape_string($from);
      $dateFilter = "WHERE date_of_consultation >= '{$safeFrom}'";
      $dateDescription = 'From ' . date('F j, Y', strtotime($from));
    } elseif ($to) {
      $safeTo = $conn->real_escape_string($to);
      $dateFilter = "WHERE date_of_consultation <= '{$safeTo}'";
      $dateDescription = 'Until ' . date('F j, Y', strtotime($to));
    }

    // Summary counts (apply date filter when available)
    $total_consultations = 0;
    $completed_consultations = 0;
    $pending_consultations = 0;
    $missed_consultations = 0;
    $cancelled_consultations = 0;
    try {
      $r = $conn->query("SELECT COUNT(*) AS cnt FROM consultations " . ($dateFilter ?: ''));
      if ($r) $total_consultations = (int)$r->fetch_assoc()['cnt'];

      $rq = $conn->query("SELECT COUNT(*) AS cnt FROM consultations " . ($dateFilter ? ($dateFilter . " AND ") : "WHERE ") . "LOWER(COALESCE(status,'')) IN ('completed','done')");
      if ($rq) $completed_consultations = (int)$rq->fetch_assoc()['cnt'];

      $rq2 = $conn->query("SELECT COUNT(*) AS cnt FROM consultations " . ($dateFilter ? ($dateFilter . " AND ") : "WHERE ") . "(status IS NULL OR status = '' OR LOWER(status) = 'pending')");
      if ($rq2) $pending_consultations = (int)$rq2->fetch_assoc()['cnt'];

      $rq3 = $conn->query("SELECT COUNT(*) AS cnt FROM consultations " . ($dateFilter ? ($dateFilter . " AND ") : "WHERE ") . "LOWER(COALESCE(status,'')) IN ('no-show','noshow','no show')");
      if ($rq3) $missed_consultations = (int)$rq3->fetch_assoc()['cnt'];

      $rq4 = $conn->query("SELECT COUNT(*) AS cnt FROM consultations " . ($dateFilter ? ($dateFilter . " AND ") : "WHERE ") . "LOWER(COALESCE(status,'')) IN ('cancelled','canceled')");
      if ($rq4) $cancelled_consultations = (int)$rq4->fetch_assoc()['cnt'];
    } catch (Exception $e) {}

    // Email report summary (using consultations.email and reminder_sent as proxy)
    $emailReports = [];
    try {
      $q = $conn->query("SELECT id, resident_id, email, consulting_doctor, date_of_consultation, consultation_time, reminder_sent, created_at FROM consultations WHERE email IS NOT NULL AND email != '' ORDER BY date_of_consultation DESC LIMIT 200");
      if ($q) while ($er = $q->fetch_assoc()) $emailReports[] = $er;
    } catch (Exception $e) {}
    ?>

    <!-- Reports Dashboard: Requested report contents -->
    <div class="max-w-full mx-auto p-6">
      <!-- Printable Report Summary (appears on printed report and on-screen summary) -->
      <section class="bg-white p-4 rounded-2xl shadow-md mb-6 print-summary">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h3 class="text-lg font-semibold"><?php echo htmlspecialchars($report_title); ?></h3>
            <div class="text-sm text-gray-500">Report Period: <?php echo htmlspecialchars($dateDescription); ?></div>
            <div class="text-sm text-gray-500">Generated: <?php echo date('F j, Y H:i'); ?> by <?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></div>
          </div>
          <div class="text-right text-sm text-gray-600">
            <div><strong><?php echo htmlspecialchars($barangay_name ?: $system_name); ?></strong></div>
            <div><?php echo htmlspecialchars($address); ?></div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4 print-top-grid">
          <div class="bg-gray-50 p-3 rounded">
            <div class="text-xs text-gray-600">Total Residents</div>
            <div class="text-xl font-semibold"><?php echo number_format($totalResidents); ?></div>
          </div>
          <div class="bg-gray-50 p-3 rounded">
            <div class="text-xs text-gray-600">Total Consultations</div>
            <div class="text-xl font-semibold"><?php echo number_format($total_consultations); ?></div>
          </div>
          <div class="bg-gray-50 p-3 rounded">
            <div class="text-xs text-gray-600">Completed Consultations</div>
            <div class="text-xl font-semibold"><?php echo number_format($completed_consultations); ?></div>
          </div>
          <div class="bg-gray-50 p-3 rounded">
            <div class="text-xs text-gray-600">Pending / Missed</div>
            <div class="text-xl font-semibold"><?php echo number_format($pending_consultations + $missed_consultations); ?></div>
          </div>
        </div>
        
        <!-- Compact summaries: Consultation Breakdown / Monthly Consultations / Purok Summary -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4 print-three-grid">
          <div class="bg-white p-3 rounded">
            <div class="text-sm font-semibold mb-2">Consultation Breakdown</div>
            <div class="text-xs text-gray-600">By Age Group (summary)</div>
            <div class="mt-2 text-sm">
              <table class="min-w-full text-sm">
                <tbody>
                  <?php foreach (array_keys($ageGender) as $ageKey) {
                    $g = $ageGender[$ageKey];
                    $totalByAge = ($g['Male'] ?? 0) + ($g['Female'] ?? 0) + ($g['Other'] ?? 0);
                    echo '<tr><td style="width:60%;padding:4px;">' . htmlspecialchars($ageKey) . '</td><td style="width:40%;padding:4px;text-align:right;">' . number_format($totalByAge) . '</td></tr>';
                  } ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="bg-white p-3 rounded">
            <div class="text-sm font-semibold mb-2">Monthly Consultation Report</div>
            <div class="text-xs text-gray-600">Last 6 months</div>
            <div class="mt-2 text-sm">
              <table class="min-w-full text-sm">
                <tbody>
                  <?php
                    $mqq = $conn->query("SELECT DATE_FORMAT(date_of_consultation, '%Y-%m') AS ym, COUNT(*) AS cnt FROM consultations " . ($dateFilter ?: "WHERE date_of_consultation >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)") . " GROUP BY ym ORDER BY ym DESC");
                    if ($mqq) {
                      while ($mr = $mqq->fetch_assoc()) {
                        echo '<tr><td style="width:70%;padding:4px;">' . htmlspecialchars($mr['ym']) . '</td><td style="width:30%;padding:4px;text-align:right;">' . number_format((int)$mr['cnt']) . '</td></tr>';
                      }
                    } else {
                      echo '<tr><td colspan="2" class="text-sm text-gray-500">No data</td></tr>';
                    }
                  ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="bg-white p-3 rounded">
            <div class="text-sm font-semibold mb-2">Purok / Zone Health Report</div>
            <div class="text-xs text-gray-600">Top areas by consultations</div>
            <div class="mt-2 text-sm">
              <table class="min-w-full text-sm">
                <tbody>
                  <?php
                    $top = 0;
                    foreach ($zoneReport as $z) {
                      if ($top++ >= 6) break;
                      echo '<tr><td style="width:70%;padding:4px;">' . htmlspecialchars($z['barangay'] ?: 'Unknown') . '</td><td style="width:30%;padding:4px;text-align:right;">' . number_format((int)$z['cnt']) . '</td></tr>';
                    }
                    if ($top === 0) echo '<tr><td colspan="2" class="text-sm text-gray-500">No data</td></tr>';
                  ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- 1. Total Number of Consultations -->
      <div class="detailed-report">
     

      <!-- 2. Consultation Breakdown -->
      <section class="bg-white p-4 rounded-2xl shadow-md mb-6">
        <h3 class="font-semibold mb-2">Consultation Breakdown</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <div class="text-sm text-gray-500">By Age Group</div>
            <ul class="mt-2 space-y-1">
              <?php foreach ($ageBreakdown as $k=>$v): ?>
                <li class="flex justify-between"><span><?php echo htmlspecialchars($k); ?></span><span class="font-semibold"><?php echo number_format($v); ?></span></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div>
            <div class="text-sm text-gray-500">By Sex</div>
            <ul class="mt-2 space-y-1">
              <?php foreach ($sexBreakdown as $k=>$v): ?>
                <li class="flex justify-between"><span><?php echo ucfirst($k); ?></span><span class="font-semibold"><?php echo number_format($v); ?></span></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      </section>

      <!-- 3. List of All Patients Who Consulted -->
      <section class="bg-white p-4 rounded-2xl shadow-md mb-6">
        <h3 class="font-semibold mb-2">List of Patients Who Consulted</h3>
        <div class="overflow-auto">
          <table class="min-w-full text-sm">
            <thead class="text-left text-xs text-gray-500">
              <tr>
                <th class="px-2 py-2">Resident Name</th>
                <th class="px-2 py-2">Age</th>
                <th class="px-2 py-2">Address / Purok</th>
                <th class="px-2 py-2">Date of Consultation</th>
                <th class="px-2 py-2">Consultant/Doctor</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($patientsList as $p):
                $name = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
                $dob = $p['date_of_birth'] ?? null;
                $age = $dob ? (int)date_diff(date_create($dob), date_create('now'))->y : '';
                $addr = trim(($p['barangay'] ?? '') . ' ' . ($p['address'] ?? ''));
              ?>
              <tr class="border-t">
                <td class="px-2 py-2"><?php echo htmlspecialchars($name); ?></td>
                <td class="px-2 py-2"><?php echo htmlspecialchars($age); ?></td>
                <td class="px-2 py-2"><?php echo htmlspecialchars($addr); ?></td>
                <td class="px-2 py-2"><?php echo htmlspecialchars($p['date_of_consultation'] . ' ' . ($p['consultation_time'] ?? '')); ?></td>
                <td class="px-2 py-2"><?php echo htmlspecialchars($p['consulting_doctor'] ?? ''); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- 4. Appointment Report (Consultant Activity removed per request) -->
      <!-- Appointment Summary removed per user request -->

      <!-- 6. Health Records Summary & 8. Purok / Zone Health Report -->
      <section class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="bg-white p-4 rounded-2xl shadow-md">
          <h4 class="font-semibold mb-2">Most Frequent Residents</h4>
          <ol class="list-decimal pl-5">
            <?php foreach ($frequentResidents as $fr): ?>
              <li class="py-1"><?php echo htmlspecialchars($fr['name']); ?> — <?php echo number_format($fr['cnt']); ?> visits — <?php echo htmlspecialchars($fr['barangay'] ?? ''); ?></li>
            <?php endforeach; ?>
          </ol>
        </div>

        <div class="bg-white p-4 rounded-2xl shadow-md">
          <h4 class="font-semibold mb-2">Purok / Zone Health Report</h4>
          <ul class="space-y-1">
            <?php foreach ($zoneReport as $z): ?>
              <li class="flex justify-between"><span><?php echo htmlspecialchars($z['barangay'] ?: 'Unknown'); ?></span><span class="font-semibold"><?php echo number_format($z['cnt']); ?></span></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </section>

      </div> <!-- end .detailed-report -->
      <!-- Email Report Summary removed as requested -->
    <!-- Print signatories (printed at bottom of report) -->
    <div class="print-signatories mt-8" style="margin-top:24px;">
      <div style="display:flex;gap:40px;justify-content:space-between;">
        <div style="flex:1;text-align:center;">
          <div style="height:64px;border-bottom:1px solid #000;margin-bottom:6px;"></div>
          <div style="font-size:13px;font-weight:600;">Prepared by</div>
          <div style="font-size:12px;color:#555;"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></div>
        </div>
        <div style="flex:1;text-align:center;">
          <div style="height:64px;border-bottom:1px solid #000;margin-bottom:6px;"></div>
          <div style="font-size:13px;font-weight:600;">Approved by</div>
          <div style="font-size:12px;color:#555;">___________________________</div>
        </div>
        <div style="flex:1;text-align:center;">
          <div style="height:64px;border-bottom:1px solid #000;margin-bottom:6px;"></div>
          <div style="font-size:13px;font-weight:600;">Date</div>
          <div style="font-size:12px;color:#555;"><?php echo date('F j, Y'); ?></div>
        </div>
      </div>
    </div>

  <script>
    // Chart data injected from server
    const AGE_GROUPS = <?php echo $ageGroupsJson ?? '[]'; ?>;
    const AGE_GENDER = <?php echo $ageGenderJson ?? '{}'; ?>;
    const CONSULT_LABELS = <?php echo $consultLabelsJson ?? '[]'; ?>;
    const CONSULT_DATA = <?php echo $consultDataJson ?? '[]'; ?>;
    const IMMUN = <?php echo $immunJson ?? '{}'; ?>;
    const PRENATAL_DATA = <?php echo $prenatalJson ?? '[]'; ?>;
    const ILLNESS_COUNTS = <?php echo $illnessJson ?? '{}'; ?>;

    // helper to create charts safely
    function safeGetCtx(id){ const el = document.getElementById(id); return el ? el.getContext('2d') : null; }

    // Illness chart (doughnut)
    (function(){
      const ctx = safeGetCtx('illnessChart');
      if (!ctx) return;
      const labels = Object.keys(ILLNESS_COUNTS || {});
      const data = labels.map(l => ILLNESS_COUNTS[l]);
      new Chart(ctx, { type: 'doughnut', data: { labels, datasets: [{ data, backgroundColor: ['#ef4444','#f97316','#f59e0b','#10b981','#60a5fa','#7c3aed','#cbd5e1'] }]}, options: { responsive:true, plugins:{legend:{position:'right'}} } });
    })();

    // Age & gender stacked bar
    (function(){
      const ctx = safeGetCtx('ageGenderChart');
      if (!ctx) return;
      const labels = Array.isArray(AGE_GROUPS) && AGE_GROUPS.length ? AGE_GROUPS : Object.keys(AGE_GENDER);
      const male = labels.map(l => (AGE_GENDER[l] && AGE_GENDER[l].Male) ? AGE_GENDER[l].Male : 0);
      const female = labels.map(l => (AGE_GENDER[l] && AGE_GENDER[l].Female) ? AGE_GENDER[l].Female : 0);
      const other = labels.map(l => (AGE_GENDER[l] && AGE_GENDER[l].Other) ? AGE_GENDER[l].Other : 0);
      new Chart(ctx, { type: 'bar', data: { labels, datasets: [ { label: 'Male', data: male, backgroundColor:'#60a5fa' }, { label: 'Female', data: female, backgroundColor:'#fb7185' }, { label: 'Other', data: other, backgroundColor:'#94a3b8' } ] }, options: { responsive:true, scales:{ x:{ stacked:true }, y:{ stacked:true, beginAtZero:true } }, plugins:{legend:{position:'top'}} } });
    })();

    // Immunization coverage (doughnut)
    (function(){
      const ctx = safeGetCtx('immunizationChart');
      if (!ctx) return;
      const vaccinated = IMMUN.count || 0;
      const notVaccinated = Math.max(0, <?php echo (int)$totalResidents; ?> - vaccinated);
      new Chart(ctx, { type: 'doughnut', data: { labels:['Vaccinated','Not vaccinated'], datasets:[{ data:[vaccinated, notVaccinated], backgroundColor:['#10b981','#e5e7eb'] }] }, options:{ responsive:true, plugins:{legend:{position:'bottom'}, tooltip:{callbacks:{label:function(ctx){ return ctx.label + ': ' + ctx.parsed + ' (' + (Math.round((ctx.parsed/(vaccinated+notVaccinated||1))*1000)/10) + '%)'; }}}} } });
    })();

    // Prenatal checkups (line)
    (function(){
      const ctx = safeGetCtx('prenatalChart');
      if (!ctx) return;
      const labels = CONSULT_LABELS;
      const data = PRENATAL_DATA;
      new Chart(ctx, { type: 'line', data:{ labels, datasets:[{ label:'Prenatal checkups', data, borderColor:'#f97316', backgroundColor:'rgba(249,115,22,0.08)', fill:true, tension:0.3 }] }, options:{ responsive:true, scales:{ y:{ beginAtZero:true } } } });
    })();

    // Health trends (consultations per month)
    (function(){
      const ctx = safeGetCtx('healthTrendsChart');
      if (!ctx) return;
      new Chart(ctx, { type:'line', data:{ labels: CONSULT_LABELS, datasets:[{ label:'Consultations', data: CONSULT_DATA, borderColor:'#2563eb', backgroundColor:'rgba(37,99,235,0.08)', fill:true, tension:0.3 }] }, options:{ responsive:true, scales:{ y:{ beginAtZero:true } } } });
    })();
  </script>

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
  <!-- Page footer for print: disclaimer and page number -->
  <div class="page-footer" style="display:none;">
    <div style="font-size:12px;color:#333;text-align:center;padding:6px 12px;border-top:1px solid #ddd;">
      <div style="font-size:11px;color:#666;">This report is system-generated and valid for official barangay health monitoring purposes.</div>
      <div style="margin-top:6px;font-size:12px;">Page <span class="pagenum"></span></div>
    </div>
  </div>
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
    const oneHour = 60*60*1000;
    Array.from(document.querySelectorAll('#notifPanelList > div')).forEach(node=>{
      try{
        const ts = node.getAttribute('data-ts');
        const t = ts ? parseInt(ts,10) : NaN;
        if (!isNaN(t) && t > now && (t - now) <= oneHour) {
          if (!node.querySelector('.reminder-badge')) {
            const badge = document.createElement('div');
            badge.className = 'reminder-badge text-xs text-red-600 font-semibold mb-1';
            badge.textContent = 'Reminder: <1h';
            node.querySelector('.flex-1')?.insertAdjacentElement('afterbegin', badge);
            node.classList.add('border','border-red-200','bg-red-50');
          }
        }
      }catch(e){}
    });
  }

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
  // checkImminentAppointments();
  </script>
  <script>
    // Apply date filter: reload page with from/to GET params, preserving other params like report_title
    function applyDateFilter() {
      const start = document.getElementById('startDate').value;
      const end = document.getElementById('endDate').value;
      if (!start || !end) {
        alert('Please select both start and end dates.');
        return;
      }
      const params = new URLSearchParams(window.location.search);
      params.set('from', start);
      params.set('to', end);
      // keep report_title if present
      window.location.search = params.toString();
    }

    function clearDateFilter() {
      const params = new URLSearchParams(window.location.search);
      params.delete('from');
      params.delete('to');
      window.location.search = params.toString();
    }

    // Chart scripts (same as before)
    (function(){
      const el = document.getElementById('illnessChart');
      if (el) new Chart(el, {
        type: 'bar',
        data: {
          labels: ['Dengue', 'Flu', 'Hypertension', 'Asthma', 'Fever'],
          datasets: [{ label: 'Cases', data: [35, 50, 40, 25, 30], backgroundColor: '#2563eb', borderRadius: 6 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } } }
      });
    })();

    (function(){
      const el = document.getElementById('ageGenderChart');
      if (el) new Chart(el, {
        type: 'doughnut',
        data: { labels: ['Children', 'Adults', 'Seniors'], datasets: [{ data: [40,45,15], backgroundColor: ['#60a5fa','#2563eb','#1e3a8a'] }] },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
      });
    })();

    (function(){
      const el = document.getElementById('immunizationChart');
      if (el) new Chart(el, {
        type: 'bar',
        data: { labels: ['Measles','Polio','Hepatitis B','Tetanus'], datasets:[{ label: '% Coverage', data:[95,88,90,92], backgroundColor:'#93c5fd', borderColor:'#2563eb', borderWidth:2 }] },
        options: { responsive:true, plugins:{legend:{display:false}}, scales:{ y:{ beginAtZero:true, max:100 } } }
      });
    })();

    (function(){
      const el = document.getElementById('prenatalChart');
      if (el) new Chart(el, {
        type: 'line', data: { labels:['Jan','Feb','Mar','Apr','May','Jun'], datasets:[{ label:'Prenatal Checkups', data:[20,30,25,40,45,35], borderColor:'#2563eb', backgroundColor:'rgba(37,99,235,0.1)', fill:true, tension:0.3 }] },
        options:{ responsive:true, plugins:{legend:{display:false}} }
      });
    })();

    (function(){
      const el = document.getElementById('healthTrendsChart');
      if (el) new Chart(el, {
        type:'line', data: { labels:['Mon','Tue','Wed','Thu','Fri','Sat','Sun'], datasets:[{ label:'Fever Cases', data:[10,12,15,20,25,28,30], borderColor:'#f59e0b', backgroundColor:'rgba(245,158,11,0.15)', fill:true, tension:0.4 }] },
        options:{ responsive:true, plugins:{legend:{display:false}} }
      });
    })();
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
