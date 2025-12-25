<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!-- Page loading started -->";

session_start();

if (!file_exists('config.php')) {
    die('Error: config.php not found');
}

include 'config.php';

if (!isset($conn)) {
    die('Error: Database connection not established');
}

echo "<!-- Database connected -->";

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Get record ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<script>alert('Invalid record ID.'); window.location.href='records.php';</script>";
    exit();
}
$record_id = intval($_GET['id']);

// Fetch health record and join with resident info
$stmt = $conn->prepare("SELECT hr.*, hr.reason AS reason_for_consultation, r.date_of_birth AS resident_birthday, r.sex AS resident_sex, r.civil_status AS resident_civil_status, r.address AS resident_address, r.email AS resident_email, r.contact_no AS resident_contact_no, r.barangay, r.city_municipality, r.province, CONCAT(r.last_name, ', ', r.first_name, IFNULL(CONCAT(' ', r.middle_name), ''), IFNULL(CONCAT(' ', r.name_extension), '')) AS resident_name FROM health_records hr LEFT JOIN residents r ON hr.resident_id = r.id WHERE hr.id = ? LIMIT 1");
$stmt->bind_param('i', $record_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
  echo "<script>alert('Record not found.'); window.location.href='records.php';</script>";
  exit();
}
$record = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST received for record ID: " . $record_id);
    $record_date = trim($_POST['record_date'] ?? '');
    $record_time = trim($_POST['record_time'] ?? '');
    $contact_no = trim($_POST['contact_no'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $v_date = trim($_POST['v_date'] ?? '');
    $v_time = trim($_POST['v_time'] ?? '');
    $v_temp = trim($_POST['v_temp'] ?? '');
    $v_wt = trim($_POST['v_wt'] ?? '');
    $v_ht = trim($_POST['v_ht'] ?? '');
    $v_bp = trim($_POST['v_bp'] ?? '');
    $v_rr = trim($_POST['v_rr'] ?? '');
    $v_pr = trim($_POST['v_pr'] ?? '');
    $reason_for_consultation = trim($_POST['reason_for_consultation'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');
    $assessment = trim($_POST['assessment'] ?? '');
    $consulting_doctor = trim($_POST['consulting_doctor'] ?? '');
    
    $sql = "UPDATE health_records SET 
            record_date = ?, record_time = ?, contact_no = ?, email = ?,
            v_temp = ?, v_wt = ?, v_ht = ?, v_bp = ?, v_rr = ?, v_pr = ?,
            reason = ?, treatment = ?, assessment = ?, consulting_doctor = ?
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("SQL Prepare Error: " . $conn->error);
        die("SQL Error: " . htmlspecialchars($conn->error));
    }
    
    error_log("Binding parameters...");
    $bind_result = $stmt->bind_param('ssssssssssssssi', 
        $record_date, $record_time, $contact_no, $email,
        $v_temp, $v_wt, $v_ht, $v_bp, $v_rr, $v_pr,
        $reason_for_consultation, $treatment, $assessment, $consulting_doctor,
        $record_id
    );
    
    if ($bind_result === false) {
        error_log("Bind Error: " . $stmt->error);
        die("Bind Error: " . htmlspecialchars($stmt->error));
    }
    
    error_log("Executing UPDATE...");
    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;
        error_log("Update executed. Affected rows: " . $affected_rows);
        $stmt->close();
        $conn->close();
        
        $_SESSION['success_message'] = 'Record updated successfully! (' . $affected_rows . ' row updated)';
        
        // Check if headers already sent
        if (headers_sent($file, $line)) {
            error_log("Headers already sent in $file on line $line");
            echo "<script>alert('Record updated!'); window.location.href='view-health-record.php?id=" . $record_id . "';</script>";
            exit();
        }
        
        header('Location: view-health-record.php?id=' . $record_id);
        exit();
    } else {
        $error_message = 'Error updating record: ' . htmlspecialchars($stmt->error);
        $stmt->close();
    }
}

// Don't close connection here - we need it for displaying the form
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Edit Patient Record | Barangay Health Monitoring System</title>
  <style>
    @page { size: A4; margin: 18mm; }
    body { font-family: Arial, Helvetica, sans-serif; color: #000; font-size:12px; background: #f8fafc; }
    .container { max-width: 900px; margin: 20px auto; background: #fff; padding: 32px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    .paper-table { width:100%; border-collapse:collapse; }
    .paper-table td { vertical-align:top; padding:2px 6px; }
    .paper-input { border:0; border-bottom:1px solid #000; background:transparent; font-family:inherit; font-size:12px; padding:2px 4px; width: 100%; }
    textarea.paper-textarea { border:0; border-bottom:1px solid #000; background:transparent; font-family:inherit; font-size:12px; padding:6px; resize:none; width: 100%; }
    .section-title{font-weight:700;margin-bottom:8px;font-size:18px}
    .boxed label { font-size:15px; font-weight:700; }
    .boxed .paper-input, .boxed textarea.paper-textarea { font-size:14px; }
    .title { text-align:center; }
    .small { font-size:11px; }
    .logo { width:72px; height:72px; object-fit:contain; }
    .boxed { border:1px solid #000; padding:18px; min-height:360px; border-radius: 6px; }
    .right-info div { margin-bottom:6px; text-align:right; }
    
   @media print {
      @page { size: A4; margin: 10mm; }
      body { margin:0; background: #fff; }
      .container { box-shadow:none; max-width:210mm; width:100%; }
      .no-print { display:none !important; }
      input.paper-input, textarea.paper-textarea { background:transparent !important; border:0 !important; border-bottom:1px solid #000 !important; }
      .boxed { page-break-inside: avoid; }
    }
  </style>
</head>
<body>
  <div class="container">
    <form id="recordForm" method="POST">
     
    <table class="paper-table">
      <tr>
        <td style="width:14%;"><img src="Brgy. San Isidro-LOGO.png" alt="logo" class="logo" /></td>
        <td style="width:62%;" class="title">
          <div style="font-weight:700; font-size:14px;">PAGSANJAN RURAL HEALTH UNIT</div>
          <div style="font-weight:600; font-size:13px; margin-top:2px;">PATIENT RECORD</div>
          <div class="small" style="margin-top:4px;">Brgy. San Isidro / Pagsanjan, Laguna</div>
        </td>
        <td style="width:24%;" class="right-info">
          <div><strong class="small">DATE:</strong> <input name="record_date" type="date" class="paper-input" style="width:140px;" value="<?php echo htmlspecialchars($record['record_date'] ?? ''); ?>"/></div>
          <div><strong class="small">TIME:</strong> <input name="record_time" type="time" class="paper-input" style="width:140px;" value="<?php echo htmlspecialchars($record['record_time'] ?? ''); ?>"/></div>
          <div><strong class="small">CONTACT NO.:</strong> <input name="contact_no" type="text" class="paper-input" style="width:140px;" value="<?php echo htmlspecialchars($record['contact_no'] ?? ''); ?>"/></div>
          <div><strong class="small">EMAIL:</strong> <input name="email" type="email" class="paper-input" style="width:140px;" value="<?php echo htmlspecialchars($record['email'] ?? ''); ?>"/></div>
        </td>
      </tr>
    </table>

    <div style="height:8px;"></div>

    <table class="paper-table" style="font-size:12px;">
      <tr>
        <td style="width:14%;"><strong class="small">NAME OF PATIENT:</strong></td>
        <td style="width:83%;">
          <input type="text" class="paper-input" value="<?php echo htmlspecialchars($record['resident_name'] ?? ''); ?>" readonly style="background: #f0f0f0;"/>
        </td>
      </tr>
      <tr style="height:8px;"></tr>
      <tr>
        <td style="width:15%;"><strong class="small">BIRTHDAY:</strong></td>
        <td style="width:18%;">
          <input type="text" class="paper-input" style="width:100%; min-width:90px;" readonly value="<?php echo htmlspecialchars($record['resident_birthday'] ?? ''); ?>" style="background: #f0f0f0;"/>
        </td>
        <td style="width:7%;"><strong class="small">AGE:</strong></td>
        <td style="width:7%;">
          <input type="text" class="paper-input" style="width:100%; min-width:40px;" readonly value="<?php 
            if (!empty($record['resident_birthday'])) {
              $dob = new DateTime($record['resident_birthday']);
              $now = new DateTime();
              echo $now->diff($dob)->y;
            }
          ?>" style="background: #f0f0f0;"/>
        </td>
        <td style="width:7%;"><strong class="small">SEX:</strong></td>
        <td style="width:7%;">
          <input type="text" class="paper-input" style="width:100%; min-width:40px;" readonly value="<?php echo htmlspecialchars($record['resident_sex'] ?? ''); ?>" style="background: #f0f0f0;"/>
        </td>
        <td style="width:12%;"><strong class="small">CIVIL STATUS:</strong></td>
        <td style="width:17%;">
          <input type="text" class="paper-input" style="width:100%; min-width:70px;" readonly value="<?php echo htmlspecialchars($record['resident_civil_status'] ?? ''); ?>" style="background: #f0f0f0;"/>
        </td>
      </tr>
      <tr style="height:8px;"></tr>
      <tr>
        <td><strong class="small">ADDRESS</strong></td>
        <td colspan="7"><input type="text" class="paper-input" style="width:100%; height:20px;" readonly value="<?php echo htmlspecialchars($record['resident_address'] ?? ''); ?>" style="background: #f0f0f0;"/></td>
      </tr>
    </table>

    <div style="height:10px;"></div>

    <div class="boxed">
      <div class="section-title">RECORD DETAILS</div>

      <div class="vitals-vertical" style="margin-bottom:12px;">
        <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
          <div style="width:120px;"><strong class="small">DATE:</strong></div>
          <div><input name="v_date" type="date" class="paper-input" style="width:220px;" value="<?php echo htmlspecialchars($record['v_date'] ?? ''); ?>"/></div>
        </div>
        <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
          <div style="width:120px;"><strong class="small">TIME:</strong></div>
          <div><input name="v_time" type="time" class="paper-input" style="width:120px;" value="<?php echo htmlspecialchars($record['v_time'] ?? ''); ?>"/></div>
        </div>
        <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
          <div style="width:120px;"><strong class="small">TEMPERATURE:</strong></div>
          <div style="display:flex; align-items:center; gap:8px;"><input name="v_temp" type="text" class="paper-input" style="width:160px;" value="<?php echo htmlspecialchars($record['v_temp'] ?? ''); ?>"/><span class="small">°C</span></div>
        </div>
        <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
          <div style="width:120px;"><strong class="small">WEIGHT:</strong></div>
          <div style="display:flex; align-items:center; gap:8px;"><input name="v_wt" type="text" class="paper-input" style="width:160px;" value="<?php echo htmlspecialchars($record['v_wt'] ?? ''); ?>"/><span class="small">kg</span></div>
        </div>
        <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
          <div style="width:120px;"><strong class="small">HEIGHT:</strong></div>
          <div style="display:flex; align-items:center; gap:8px;"><input name="v_ht" type="text" class="paper-input" style="width:160px;" value="<?php echo htmlspecialchars($record['v_ht'] ?? ''); ?>"/><span class="small">cm</span></div>
        </div>
        <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
          <div style="width:120px;"><strong class="small">BLOOD PRESSURE:</strong></div>
          <div style="display:flex; align-items:center; gap:8px;"><input name="v_bp" type="text" class="paper-input" style="width:160px;" value="<?php echo htmlspecialchars($record['v_bp'] ?? ''); ?>"/><span class="small">mmHg</span></div>
        </div>
        <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
          <div style="width:120px;"><strong class="small">RESPIRATORY RATE:</strong></div>
          <div style="display:flex; align-items:center; gap:8px;"><input name="v_rr" type="text" class="paper-input" style="width:160px;" value="<?php echo htmlspecialchars($record['v_rr'] ?? ''); ?>"/><span class="small">breaths/min</span></div>
        </div>
        <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
          <div style="width:120px;"><strong class="small">PULSE RATE:</strong></div>
          <div style="display:flex; align-items:center; gap:8px;"><input name="v_pr" type="text" class="paper-input" style="width:160px;" value="<?php echo htmlspecialchars($record['v_pr'] ?? ''); ?>"/><span class="small">bpm</span></div>
        </div>
      </div>

      <div class="mt-4">
        <label for="reason_for_consultation" style="font-weight:700; display:block; margin-bottom:6px;">REASON OF CONSULTATION / COMPLAINT</label>
        <textarea id="reason_for_consultation" name="reason_for_consultation" class="paper-textarea" style="width:100%; height:120px;"><?php echo htmlspecialchars($record['reason_for_consultation'] ?? ''); ?></textarea>
      </div>

      <div style="margin-bottom:12px;">
        <label for="treatment" style="font-weight:700; display:block; margin-bottom:6px;">DIAGNOSIS / TREATMENT</label>
        <textarea id="treatment" name="treatment" class="paper-textarea" style="width:100%; height:120px;"><?php echo htmlspecialchars($record['treatment'] ?? ''); ?></textarea>
      </div>

      <div style="margin-bottom:6px;">
        <label for="assessment" style="font-weight:700; display:block; margin-bottom:6px;">ASSESSMENT</label>
        <textarea id="assessment" name="assessment" class="paper-textarea" style="width:100%; height:120px;"><?php echo htmlspecialchars($record['assessment'] ?? ''); ?></textarea>
      </div>

      <div class="mt-4">
        <div class="section-title">DOCTOR/STAFF</div>
        <input type="text" name="consulting_doctor" class="paper-input" value="<?php echo htmlspecialchars($record['consulting_doctor'] ?? 'Lhee Za Milanez'); ?>">
      </div>
    </div>

    <div class="no-print" style="display:flex; justify-content:flex-end; gap:8px; margin-top:12px;">
      <button type="button" onclick="window.location.href='records.php';" style="padding:8px 16px; background:#e5e7eb; color:#374151; border-radius:6px; border:1px solid #d1d5db; cursor:pointer;">
        Cancel
      </button>
      <button type="button" onclick="window.print();" style="padding:8px 16px; background:#bfdbfe; color:#1e3a8a; border-radius:6px; border:1px solid #93c5fd; cursor:pointer;">
        Print
      </button>
      <button type="submit" style="padding:8px 16px; border-radius:6px; border:1px solid #1d4ed8; background:#2563eb; cursor:pointer; color:white;">
        Update Record
      </button>
    </div>

    </form>
  </div>
</body>
</html>
