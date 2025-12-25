<?php
session_start();
include 'config.php';
if (!isset($_SESSION['username'])) { header('Location: login.php'); exit(); }

// Filters - default to last 12 months instead of current month only
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-12 months'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$barangay = $_GET['barangay'] ?? '';
$age_group = $_GET['age_group'] ?? '';
$gender = $_GET['gender'] ?? '';
$period = $_GET['period'] ?? 'monthly'; // monthly or quarterly

// Fetch distinct barangays (use barangay column or fallback to purok)
$barangays = [];
$bqr = $conn->query("SELECT DISTINCT TRIM(COALESCE(NULLIF(barangay,''), NULLIF(purok,''))) AS barangay FROM residents WHERE COALESCE(NULLIF(barangay,''), NULLIF(purok,'')) IS NOT NULL ORDER BY barangay");
if ($bqr && $bqr instanceof mysqli_result) {
	while ($br = $bqr->fetch_assoc()) { if (!empty($br['barangay'])) $barangays[] = $br['barangay']; }
}

// Ensure specific barangay options always appear (de-duplicated), keep them at top
$defaults = ["Crisostomo Ext", "National High Way", "Maligaya St", "General Taino St"];
$combined = array_values(array_unique(array_merge($defaults, $barangays)));
$barangays = $combined;

// Build filters for consultations
$filters = [];
$params = [];
$types = '';
if ($start_date) { $filters[] = "c.date_of_consultation >= ?"; $params[] = $start_date; $types .= 's'; }
if ($end_date) { $filters[] = "c.date_of_consultation <= ?"; $params[] = $end_date; $types .= 's'; }
if ($barangay) {
	// normalize parameter: lower + remove non-alphanumeric for robust matching
	$bparam = strtolower(trim($barangay));
	$bnorm = preg_replace('/[^a-z0-9]/', '', $bparam);
	// Normalize DB side by removing spaces, dots, hyphens before comparing
	$filters[] = "(REPLACE(REPLACE(REPLACE(LOWER(r.purok),' ',''),'.',''),'-','') LIKE CONCAT('%', ?, '%') OR REPLACE(REPLACE(REPLACE(LOWER(r.barangay),' ',''),'.',''),'-','') LIKE CONCAT('%', ?, '%'))";
	$params[] = $bnorm; $params[] = $bnorm; $types .= 'ss';
}
if ($gender) { $filters[] = "r.sex = ?"; $params[] = $gender; $types .= 's'; }
if ($age_group) {
	switch ($age_group) {
		case '0-17': $filters[] = "TIMESTAMPDIFF(YEAR, r.date_of_birth, c.date_of_consultation) BETWEEN 0 AND 17"; break;
		case '18-35': $filters[] = "TIMESTAMPDIFF(YEAR, r.date_of_birth, c.date_of_consultation) BETWEEN 18 AND 35"; break;
		case '36-60': $filters[] = "TIMESTAMPDIFF(YEAR, r.date_of_birth, c.date_of_consultation) BETWEEN 36 AND 60"; break;
		case '60+': $filters[] = "TIMESTAMPDIFF(YEAR, r.date_of_birth, c.date_of_consultation) >= 60"; break;
	}
}
$where = '';
if ($filters) $where = 'WHERE ' . implode(' AND ', $filters);

// Totals: daily / weekly / monthly / quarterly
$totals_daily = [];
$totals_weekly = [];
$totals_monthly = [];
$totals_quarterly = [];

$sql = "SELECT DATE_FORMAT(c.date_of_consultation, '%Y-%m-%d') AS d, COUNT(*) AS cnt FROM consultations c LEFT JOIN residents r ON c.resident_id = r.id $where GROUP BY d ORDER BY d";
$stmt = $conn->prepare($sql);
if ($stmt) { if ($types) $stmt->bind_param($types, ...$params); $stmt->execute(); $res = $stmt->get_result(); while ($row = $res->fetch_assoc()) $totals_daily[$row['d']] = (int)$row['cnt']; $stmt->close(); }

$sql = "SELECT DATE_FORMAT(c.date_of_consultation, '%x-W%v') AS w, COUNT(*) AS cnt FROM consultations c LEFT JOIN residents r ON c.resident_id = r.id $where GROUP BY w ORDER BY w";
$stmt = $conn->prepare($sql);
if ($stmt) { if ($types) $stmt->bind_param($types, ...$params); $stmt->execute(); $res = $stmt->get_result(); while ($row = $res->fetch_assoc()) $totals_weekly[$row['w']] = (int)$row['cnt']; $stmt->close(); }

$sql = "SELECT DATE_FORMAT(c.date_of_consultation, '%Y-%m') AS m, COUNT(*) AS cnt FROM consultations c LEFT JOIN residents r ON c.resident_id = r.id $where GROUP BY m ORDER BY m";
$stmt = $conn->prepare($sql);
if ($stmt) { if ($types) $stmt->bind_param($types, ...$params); $stmt->execute(); $res = $stmt->get_result(); while ($row = $res->fetch_assoc()) $totals_monthly[$row['m']] = (int)$row['cnt']; $stmt->close(); }

$sql = "SELECT CONCAT(YEAR(c.date_of_consultation), '-Q', QUARTER(c.date_of_consultation)) AS q, COUNT(*) AS cnt FROM consultations c LEFT JOIN residents r ON c.resident_id = r.id $where GROUP BY q ORDER BY q";
$stmt = $conn->prepare($sql);
if ($stmt) { if ($types) $stmt->bind_param($types, ...$params); $stmt->execute(); $res = $stmt->get_result(); while ($row = $res->fetch_assoc()) $totals_quarterly[$row['q']] = (int)$row['cnt']; $stmt->close(); }

// Consultations list
$consults = [];
$sql = "SELECT c.*, r.first_name, r.last_name, r.middle_name, r.date_of_birth, r.sex, r.purok, r.barangay FROM consultations c LEFT JOIN residents r ON c.resident_id = r.id $where ORDER BY c.date_of_consultation DESC, c.consultation_time DESC LIMIT 1000";
$stmt = $conn->prepare($sql);
if ($stmt) { if ($types) $stmt->bind_param($types, ...$params); $stmt->execute(); $res = $stmt->get_result(); while ($row = $res->fetch_assoc()) $consults[] = $row; $stmt->close(); }

// Status counts
$status_counts = ['Pending'=>0,'Completed'=>0,'Cancelled'=>0,'No-show'=>0];
$sql = "SELECT COALESCE(NULLIF(TRIM(status),''),'Pending') AS status_norm, COUNT(*) AS cnt FROM consultations c LEFT JOIN residents r ON c.resident_id = r.id $where GROUP BY status_norm";
$res = $conn->query($sql);
if ($res && $res instanceof mysqli_result) { while ($r = $res->fetch_assoc()) { $s = $r['status_norm']; if (strtolower($s) === 'noshow') $s = 'No-show'; if (!isset($status_counts[$s])) $status_counts[$s] = 0; $status_counts[$s] += (int)$r['cnt']; } }

// Total distinct residents
$total_distinct_residents = 0;
$sql = "SELECT COUNT(DISTINCT c.resident_id) AS cnt FROM consultations c LEFT JOIN residents r ON c.resident_id = r.id $where";
$res = $conn->query($sql);
if ($res && $row = $res->fetch_assoc()) $total_distinct_residents = (int)$row['cnt'];

// Disease distribution (top 10)
$disease_dist = [];
$sql = "SELECT COALESCE(NULLIF(TRIM(reason_for_consultation),''),'Unknown') AS reason, COUNT(*) AS cnt FROM consultations c LEFT JOIN residents r ON c.resident_id = r.id $where GROUP BY reason ORDER BY cnt DESC LIMIT 10";
$res = $conn->query($sql);
if ($res && $res instanceof mysqli_result) { while ($r = $res->fetch_assoc()) $disease_dist[] = $r; }

// Health records / risk groups (apply filters)
$highbp = []; $highfever = []; $abnormal_pulse = []; $under_over = [];
$hr_filters = [];
if ($start_date) { $sd = $conn->real_escape_string($start_date . ' 00:00:00'); $hr_filters[] = "hr.created_at >= '$sd'"; }
if ($end_date) { $ed = $conn->real_escape_string($end_date . ' 23:59:59'); $hr_filters[] = "hr.created_at <= '$ed'"; }
if ($barangay) { $p = strtolower(trim($barangay)); $p_n = preg_replace('/[^a-z0-9]/','',$p); $p_esc = $conn->real_escape_string($p_n); $hr_filters[] = "(REPLACE(REPLACE(REPLACE(LOWER(r.purok),' ',''),'.',''),'-','') LIKE '%$p_esc%' OR REPLACE(REPLACE(REPLACE(LOWER(r.barangay),' ',''),'.',''),'-','') LIKE '%$p_esc%')"; }
if ($gender) { $g = $conn->real_escape_string($gender); $hr_filters[] = "r.sex = '$g'"; }
if ($age_group) {
	switch ($age_group) {
		case '0-17': $hr_filters[] = "TIMESTAMPDIFF(YEAR, r.date_of_birth, hr.created_at) BETWEEN 0 AND 17"; break;
		case '18-35': $hr_filters[] = "TIMESTAMPDIFF(YEAR, r.date_of_birth, hr.created_at) BETWEEN 18 AND 35"; break;
		case '36-60': $hr_filters[] = "TIMESTAMPDIFF(YEAR, r.date_of_birth, hr.created_at) BETWEEN 36 AND 60"; break;
		case '60+': $hr_filters[] = "TIMESTAMPDIFF(YEAR, r.date_of_birth, hr.created_at) >= 60"; break;
	}
}
$hr_where = '';
if ($hr_filters) $hr_where = ' AND ' . implode(' AND ', $hr_filters);

$sql = "SELECT hr.*, r.first_name, r.last_name, r.date_of_birth, r.sex, r.purok, r.barangay FROM health_records hr LEFT JOIN residents r ON hr.resident_id = r.id WHERE ((hr.v_bp IS NOT NULL AND hr.v_bp != '') OR (hr.v_temp IS NOT NULL AND hr.v_temp != '') OR (hr.v_pr IS NOT NULL AND hr.v_pr != '') OR (hr.v_wt IS NOT NULL AND hr.v_ht IS NOT NULL)) " . $hr_where . " ORDER BY hr.created_at DESC LIMIT 5000";
$res = $conn->query($sql);
if ($res && $res instanceof mysqli_result) {
	while ($r = $res->fetch_assoc()) {
		$bp = $r['v_bp'] ?? '';
		if ($bp && preg_match('/(\d{2,3})\D+(\d{2,3})/', $bp, $m)) { $sys = (int)$m[1]; $dia = (int)$m[2]; if ($sys >= 140 || $dia >= 90) $highbp[] = $r; }
		$temp = (float)($r['v_temp'] ?? 0); if ($temp > 37.5) $highfever[] = $r;
		$pr = (int)($r['v_pr'] ?? 0); if ($pr && ($pr < 50 || $pr > 110)) $abnormal_pulse[] = $r;
		$wt = (float)($r['v_wt'] ?? 0); $ht = (float)($r['v_ht'] ?? 0);
		if ($wt && $ht) { if ($ht > 3) $htM = $ht / 100.0; else $htM = $ht; if ($htM > 0) { $bmi = $wt / ($htM * $htM); if ($bmi < 18.5 || $bmi >= 25) $under_over[] = array_merge($r, ['bmi' => $bmi]); } }
	}
}

$highbp_count = count($highbp); $highfever_count = count($highfever); $abnormal_pulse_count = count($abnormal_pulse); $under_over_count = count($under_over);

// Chart data
$daily_labels = array_keys($totals_daily); $daily_values = array_map('intval', array_values($totals_daily));
$monthly_labels = array_keys($totals_monthly); $monthly_values = array_map('intval', array_values($totals_monthly));

// Total consultations
$total_consultations = array_sum($totals_daily);

?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Consultation Reports - Barangay Health Monitoring</title>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
	<script src="https://cdn.tailwindcss.com"></script>
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<style>
		body{font-family:Inter, sans-serif}
		.card{background:#fff;border-radius:10px;padding:14px;box-shadow:0 6px 18px rgba(2,6,23,.06)}
		@media print { .no-print{display:none;} body{background:#fff;} .card{box-shadow:none;} }
	</style>
</head>
<body class="bg-gray-50">
		<!-- Shared header (logo, nav, notifications, admin menu) -->
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

		<div class="h-16"></div>

		<div class="max-w-6xl mx-auto p-6">
		<h1 class="text-2xl font-semibold mb-2">CONSULTATION REPORTS</h1>
		<p class="mb-4 text-sm text-gray-600">Summary and printable Barangay Health Summary for LGU / DOH submissions.</p>

		<section class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
			<div class="card">
				<div class="text-sm text-gray-500">Total Consultations (Selected Range)</div>
				<div class="text-3xl font-bold mt-2"><?php echo $total_consultations; ?></div>
				<div class="text-xs text-gray-400 mt-1">Aggregated (daily / weekly / monthly)</div>
			</div>
		</section>

		<section class="mb-6 card no-print">
			<div class="flex items-center justify-between mb-3">
				<div><strong>Filters</strong><div class="text-xs text-gray-500">Date range, Barangay, Age group, Gender, Period</div></div>
				<div class="flex items-center gap-2">
					<form id="filtersForm" method="get" class="flex items-center gap-2">
						<input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="border px-2 py-1 rounded" />
						<input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="border px-2 py-1 rounded" />
												<select name="barangay" class="border px-2 py-1 rounded">
													<option value="">All Barangays</option>
													<option value="Crisostomo Ext" <?php if($barangay=='Crisostomo Ext') echo 'selected'; ?>>Crisostomo Ext</option>
													<option value="National High Way" <?php if($barangay=='National High Way') echo 'selected'; ?>>National High Way</option>
													<option value="Maligaya St" <?php if($barangay=='Maligaya St') echo 'selected'; ?>>Maligaya St</option>
													<option value="General Taino St" <?php if($barangay=='General Taino St') echo 'selected'; ?>>General Taino St</option>
												</select>
						<select name="age_group" class="border px-2 py-1 rounded"><option value="">All ages</option><option value="0-17" <?php if($age_group=='0-17') echo 'selected'; ?>>0-17</option><option value="18-35" <?php if($age_group=='18-35') echo 'selected'; ?>>18-35</option><option value="36-60" <?php if($age_group=='36-60') echo 'selected'; ?>>36-60</option><option value="60+" <?php if($age_group=='60+') echo 'selected'; ?>>60+</option></select>
						<select name="gender" class="border px-2 py-1 rounded"><option value="">All</option><option value="Male" <?php if($gender=='Male') echo 'selected'; ?>>Male</option><option value="Female" <?php if($gender=='Female') echo 'selected'; ?>>Female</option></select>
						<select name="period" class="border px-2 py-1 rounded"><option value="monthly" <?php if($period=='monthly') echo 'selected'; ?>>Monthly</option><option value="quarterly" <?php if($period=='quarterly') echo 'selected'; ?>>Quarterly</option></select>
						<button type="submit" class="px-3 py-1 bg-blue-600 text-white rounded">Apply</button>
					</form>
				</div>
			</div>

			<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
				<div><canvas id="dailyChart" height="140"></canvas></div>
				<div><canvas id="monthlyChart" height="140"></canvas></div>
			</div>

			<div class="mt-4">
				<div class="text-xs text-gray-500 mb-2">Export will include data based on the filters above (Date: <?php echo htmlspecialchars($start_date); ?> to <?php echo htmlspecialchars($end_date); ?>)</div>
				<div class="flex gap-2">
					<button id="exportCsv" data-type="consultations" class="px-3 py-1 bg-gray-800 text-white rounded hover:bg-gray-900">Export CSV</button>
					<button id="exportVitals" data-type="vitals" class="px-3 py-1 bg-green-700 text-white rounded hover:bg-green-800">Export Vitals CSV</button>
					<button id="exportLGU" class="px-3 py-1 bg-indigo-600 text-white rounded hover:bg-indigo-700">Export LGU/DOH CSV</button>
					<button id="printReport" class="px-3 py-1 bg-gray-600 text-white rounded hover:bg-gray-700">Print / Save PDF</button>
				</div>
			</div>
		</section>

		<!-- Consultation tables and printable summary removed per user request -->

	</div>

<script>
	const dailyLabels = <?php echo json_encode($daily_labels); ?>;
	const dailyValues = <?php echo json_encode($daily_values); ?>;
	const monthlyLabels = <?php echo json_encode($monthly_labels); ?>;
	const monthlyValues = <?php echo json_encode($monthly_values); ?>;

	new Chart(document.getElementById('dailyChart').getContext('2d'), { type:'bar', data:{labels:dailyLabels,datasets:[{label:'Consultations',backgroundColor:'#2563eb',data:dailyValues}]}, options:{responsive:true,plugins:{legend:{display:false}}} });
	new Chart(document.getElementById('monthlyChart').getContext('2d'), { type:'line', data:{labels:monthlyLabels,datasets:[{label:'Consultations',borderColor:'#10b981',data:monthlyValues,fill:false}]}, options:{responsive:true,plugins:{legend:{display:false}}} });

	document.getElementById('exportCsv').addEventListener('click', function(){ const qs = new URLSearchParams(new FormData(document.getElementById('filtersForm'))).toString(); window.open('export_reports.php?type=consultations&' + qs, '_blank'); });
	document.getElementById('exportVitals').addEventListener('click', function(){ const qs = new URLSearchParams(new FormData(document.getElementById('filtersForm'))).toString(); window.open('export_reports.php?type=vitals&' + qs, '_blank'); });
	document.getElementById('exportLGU').addEventListener('click', function(){ const qs = new URLSearchParams(new FormData(document.getElementById('filtersForm'))).toString(); window.open('export_reports.php?type=lgu&' + qs, '_blank'); });
	document.getElementById('printReport').addEventListener('click', function(){ window.print(); });
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

