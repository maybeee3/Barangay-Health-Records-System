<?php
// export_reports.php
// Simple CSV exporter for reports. Supports: type=consultations|vitals|lgu

session_start();
include 'config.php';
if (!isset($_SESSION['username'])) { header('HTTP/1.1 403 Forbidden'); echo 'Forbidden'; exit(); }

$type = $_GET['type'] ?? 'consultations';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$barangay = $_GET['barangay'] ?? '';
$age_group = $_GET['age_group'] ?? '';
$gender = $_GET['gender'] ?? '';
$period = $_GET['period'] ?? 'monthly';

$filters = [];
$params = [];
$types = '';
if ($start_date) { $filters[] = "c.date_of_consultation >= ?"; $params[] = $start_date; $types .= 's'; }
if ($end_date) { $filters[] = "c.date_of_consultation <= ?"; $params[] = $end_date; $types .= 's'; }
if ($barangay) { 
  $bparam = strtolower(trim($barangay)); 
  $bnorm = preg_replace('/[^a-z0-9]/','',$bparam); 
  $filters[] = "REPLACE(REPLACE(REPLACE(LOWER(r.barangay),' ',''),'.',''),'-','') LIKE CONCAT('%', ?, '%')"; 
  $params[] = $bnorm; 
  $types .= 's'; 
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

if ($type === 'vitals') {
  // Export vitals from health_records - build query with filters
  $vit_filters = [];
  if ($start_date) { $vit_filters[] = "DATE(hr.created_at) >= '" . $conn->real_escape_string($start_date) . "'"; }
  if ($end_date) { $vit_filters[] = "DATE(hr.created_at) <= '" . $conn->real_escape_string($end_date) . "'"; }
  if ($barangay) { 
    $b = strtolower(trim($barangay)); 
    $b_n = preg_replace('/[^a-z0-9]/','',$b); 
    $b_esc = $conn->real_escape_string($b_n); 
    $vit_filters[] = "REPLACE(REPLACE(REPLACE(LOWER(r.barangay),' ',''),'.',''),'-','') LIKE '%" . $b_esc . "%'"; 
  }
  if ($gender) { $vit_filters[] = "r.sex = '" . $conn->real_escape_string($gender) . "'"; }
  if ($age_group) {
    if ($age_group === '0-17') $vit_filters[] = "TIMESTAMPDIFF(YEAR, r.date_of_birth, hr.created_at) BETWEEN 0 AND 17";
    if ($age_group === '18-35') $vit_filters[] = "TIMESTAMPDIFF(YEAR, r.date_of_birth, hr.created_at) BETWEEN 18 AND 35";
    if ($age_group === '36-60') $vit_filters[] = "TIMESTAMPDIFF(YEAR, r.date_of_birth, hr.created_at) BETWEEN 36 AND 60";
    if ($age_group === '60+') $vit_filters[] = "TIMESTAMPDIFF(YEAR, r.date_of_birth, hr.created_at) >= 60";
  }
  
  $vit_where = '';
  if (!empty($vit_filters)) {
    $vit_where = 'WHERE ' . implode(' AND ', $vit_filters);
  }
  
  $sql = "SELECT hr.created_at, r.last_name, r.first_name, r.barangay, hr.v_bp, hr.v_temp, hr.v_pr, hr.v_wt, hr.v_ht 
          FROM health_records hr 
          LEFT JOIN residents r ON hr.resident_id = r.id 
          $vit_where
          ORDER BY hr.created_at DESC 
          LIMIT 5000";
  $res = $conn->query($sql);
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="vitals_export_' . date('Y-m-d') . '.csv"');
  header('Pragma: no-cache');
  header('Expires: 0');
  $out = fopen('php://output', 'w');
  fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
  fputcsv($out, ['Date','Last Name','First Name','Barangay','BP','Temp','Pulse','Weight','Height']);
  if ($res && $res instanceof mysqli_result) {
    $count = 0;
    while ($r = $res->fetch_assoc()) {
      fputcsv($out, [ 
        $r['created_at'] ?? '', 
        $r['last_name'] ?? '', 
        $r['first_name'] ?? '', 
        $r['barangay'] ?? '', 
        $r['v_bp'] ?? '', 
        $r['v_temp'] ?? '', 
        $r['v_pr'] ?? '', 
        $r['v_wt'] ?? '', 
        $r['v_ht'] ?? '' 
      ]);
      $count++;
    }
    if ($count === 0) {
      fputcsv($out, ['No vitals data found for the selected filters']);
    }
  }
  fclose($out);
  exit();
}

if ($type === 'lgu') {
  // LGU / DOH summary CSV
  $filters = [];
  $params = [];
  $types = '';
  if ($start_date) { $filters[] = "c.date_of_consultation >= ?"; $params[] = $start_date; $types .= 's'; }
  if ($end_date) { $filters[] = "c.date_of_consultation <= ?"; $params[] = $end_date; $types .= 's'; }
  if ($barangay) { 
    $bparam = strtolower(trim($barangay)); 
    $bnorm = preg_replace('/[^a-z0-9]/','',$bparam); 
    $filters[] = "REPLACE(REPLACE(REPLACE(LOWER(r.barangay),' ',''),'.',''),'-','') LIKE CONCAT('%', ?, '%')"; 
    $params[] = $bnorm; 
    $types .= 's'; 
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

  // Total consultations
  $total_consultations = 0;
  $sql = "SELECT COUNT(*) AS cnt FROM consultations c LEFT JOIN residents r ON c.resident_id = r.id $where";
  if (!empty($types) && !empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($stmt) { $stmt->bind_param($types, ...$params); $stmt->execute(); $res = $stmt->get_result(); if ($res && $row = $res->fetch_assoc()) $total_consultations = (int)$row['cnt']; $stmt->close(); }
  } else {
    $res = $conn->query($sql);
    if ($res && $row = $res->fetch_assoc()) $total_consultations = (int)$row['cnt'];
  }

  // Distinct residents
  $total_distinct = 0;
  $sql = "SELECT COUNT(DISTINCT c.resident_id) AS cnt FROM consultations c LEFT JOIN residents r ON c.resident_id = r.id $where";
  $res = $conn->query($sql);
  if ($res && $row = $res->fetch_assoc()) $total_distinct = (int)$row['cnt'];

  // Disease distribution
  $disease = [];
  $sql = "SELECT COALESCE(NULLIF(TRIM(reason_for_consultation),''),'Unknown') AS reason, COUNT(*) AS cnt FROM consultations c LEFT JOIN residents r ON c.resident_id = r.id $where GROUP BY reason ORDER BY cnt DESC";
  $res = $conn->query($sql);
  if ($res && $res instanceof mysqli_result) { while ($r = $res->fetch_assoc()) $disease[] = $r; }

  // Risk groups from health_records
  $hr_where = 'WHERE ((hr.v_bp IS NOT NULL AND hr.v_bp != "") OR (hr.v_temp IS NOT NULL AND hr.v_temp != "") OR (hr.v_pr IS NOT NULL AND hr.v_pr != "") OR (hr.v_wt IS NOT NULL AND hr.v_ht IS NOT NULL))';
  if ($barangay) { 
    $b = strtolower(trim($barangay)); 
    $b_n = preg_replace('/[^a-z0-9]/','',$b); 
    $b_esc = $conn->real_escape_string($b_n); 
    $hr_where .= " AND REPLACE(REPLACE(REPLACE(LOWER(r.barangay),' ',''),'.',''),'-','') LIKE '%" . $b_esc . "%'"; 
  }
  if ($gender) { $hr_where .= " AND r.sex = '" . $conn->real_escape_string($gender) . "'"; }
  if ($start_date) { $hr_where .= " AND hr.created_at >= '" . $conn->real_escape_string($start_date . ' 00:00:00') . "'"; }
  if ($end_date) { $hr_where .= " AND hr.created_at <= '" . $conn->real_escape_string($end_date . ' 23:59:59') . "'"; }
  if ($age_group) {
    if ($age_group === '0-17') $hr_where .= " AND TIMESTAMPDIFF(YEAR, r.date_of_birth, hr.created_at) BETWEEN 0 AND 17";
    if ($age_group === '18-35') $hr_where .= " AND TIMESTAMPDIFF(YEAR, r.date_of_birth, hr.created_at) BETWEEN 18 AND 35";
    if ($age_group === '36-60') $hr_where .= " AND TIMESTAMPDIFF(YEAR, r.date_of_birth, hr.created_at) BETWEEN 36 AND 60";
    if ($age_group === '60+') $hr_where .= " AND TIMESTAMPDIFF(YEAR, r.date_of_birth, hr.created_at) >= 60";
  }

  $sql = "SELECT hr.*, r.first_name, r.last_name FROM health_records hr LEFT JOIN residents r ON hr.resident_id = r.id $hr_where";
  $res = $conn->query($sql);
  $highbp = 0; $highfever = 0; $abnormal_pulse = 0; $under_over = 0;
  if ($res && $res instanceof mysqli_result) {
    while ($r = $res->fetch_assoc()) {
      $bp = $r['v_bp'] ?? '';
      if ($bp && preg_match('/(\d{2,3})\D+(\d{2,3})/', $bp, $m)) { $sys = (int)$m[1]; $dia = (int)$m[2]; if ($sys >= 140 || $dia >= 90) $highbp++; }
      $temp = (float)($r['v_temp'] ?? 0); if ($temp > 37.5) $highfever++;
      $pr = (int)($r['v_pr'] ?? 0); if ($pr && ($pr < 50 || $pr > 110)) $abnormal_pulse++;
      $wt = (float)($r['v_wt'] ?? 0); $ht = (float)($r['v_ht'] ?? 0); if ($wt && $ht) { if ($ht > 3) $htM = $ht / 100.0; else $htM = $ht; if ($htM > 0) { $bmi = $wt / ($htM * $htM); if ($bmi < 18.5 || $bmi >= 25) $under_over++; } }
    }
  }

  // Aggregation table
  $agg = [];
  if ($period === 'quarterly') {
    $sql = "SELECT CONCAT(YEAR(c.date_of_consultation), '-Q', QUARTER(c.date_of_consultation)) AS period, COUNT(*) AS cnt FROM consultations c LEFT JOIN residents r ON c.resident_id = r.id $where GROUP BY period ORDER BY period";
  } else {
    $sql = "SELECT DATE_FORMAT(c.date_of_consultation, '%Y-%m') AS period, COUNT(*) AS cnt FROM consultations c LEFT JOIN residents r ON c.resident_id = r.id $where GROUP BY period ORDER BY period";
  }
  if (!empty($types) && !empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($stmt) { $stmt->bind_param($types, ...$params); $stmt->execute(); $res = $stmt->get_result(); while ($row = $res->fetch_assoc()) $agg[$row['period']] = (int)$row['cnt']; $stmt->close(); }
  } else {
    $res = $conn->query($sql);
    if ($res) { while ($row = $res->fetch_assoc()) $agg[$row['period']] = (int)$row['cnt']; }
  }

  // Stream CSV
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="lgu_doh_summary_' . date('Y-m-d') . '.csv"');
  header('Pragma: no-cache');
  header('Expires: 0');
  $out = fopen('php://output', 'w');
  fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
  fputcsv($out, ['LGU / DOH Consultation Summary']);
  fputcsv($out, ['Generated: ' . date('F j, Y H:i:s')]);
  fputcsv($out, ['Date Range: ' . ($start_date ?: 'All') . ' to ' . ($end_date ?: 'All')]);
  fputcsv($out, []);
  fputcsv($out, ['SUMMARY STATISTICS']);
  fputcsv($out, ['Total distinct residents', $total_distinct]);
  fputcsv($out, ['Total consultations', $total_consultations]);
  fputcsv($out, []);
  fputcsv($out, ['DISEASE DISTRIBUTION','Count']);
  if (count($disease) > 0) {
    foreach ($disease as $d) { fputcsv($out, [$d['reason'], (int)$d['cnt']]); }
  } else {
    fputcsv($out, ['No disease data available']);
  }
  fputcsv($out, []);
  fputcsv($out, ['RISK GROUP SUMMARY','Count']);
  fputcsv($out, ['High BP (≥140/90)', $highbp]); 
  fputcsv($out, ['High Fever (>37.5°C)', $highfever]); 
  fputcsv($out, ['Abnormal Pulse (<50 or >110)', $abnormal_pulse]); 
  fputcsv($out, ['Under/Overweight (BMI <18.5 or ≥25)', $under_over]);
  fputcsv($out, []);
  fputcsv($out, ['CONSULTATION TRENDS']);
  fputcsv($out, ['Aggregated by ' . ($period === 'quarterly' ? 'Quarter' : 'Month')]);
  fputcsv($out, ['Period','Consultations']);
  if (count($agg) > 0) {
    foreach ($agg as $k=>$v) { fputcsv($out, [$k, $v]); }
  } else {
    fputcsv($out, ['No consultation data for selected period']);
  }
  fclose($out);
  exit();
}

// default: consultations
$sql = "SELECT c.date_of_consultation, c.consultation_time, r.last_name, r.first_name, r.barangay, c.reason_for_consultation, c.consulting_doctor
        FROM consultations c
        LEFT JOIN residents r ON c.resident_id = r.id
        $where
        ORDER BY c.date_of_consultation DESC, c.consultation_time DESC
        LIMIT 5000";

// Use prepared statement only if we have parameters
if (!empty($types) && !empty($params)) {
  $stmt = $conn->prepare($sql);
  if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
  } else {
    $res = false;
  }
} else {
  // No filters, use simple query
  $res = $conn->query($sql);
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="consultations_export_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');
$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
fputcsv($out, ['Date','Time','Last Name','First Name','Barangay','Complaint','Consultant']);
if ($res && $res instanceof mysqli_result) {
  $count = 0;
  while ($r = $res->fetch_assoc()) {
    fputcsv($out, [ 
      $r['date_of_consultation'] ?? '', 
      $r['consultation_time'] ?? '', 
      $r['last_name'] ?? '', 
      $r['first_name'] ?? '', 
      $r['barangay'] ?? '', 
      $r['reason_for_consultation'] ?? '', 
      $r['consulting_doctor'] ?? '' 
    ]);
    $count++;
  }
  if ($count === 0) {
    fputcsv($out, ['No data found for the selected filters']);
  }
}
fclose($out);
exit();
