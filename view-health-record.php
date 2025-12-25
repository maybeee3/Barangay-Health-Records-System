<?php
session_start();
include 'config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<script>alert('Invalid record ID.'); window.location.href='records.php';</script>";
    exit();
}
$record_id = intval($_GET['id']);


// Fetch health record and join with resident info
$stmt = $conn->prepare("SELECT hr.*, r.date_of_birth AS resident_birthday, r.sex AS resident_sex, r.civil_status AS resident_civil_status, r.address AS resident_address, r.email AS resident_email, r.contact_no AS resident_contact_no, r.barangay, r.city_municipality, r.province, CONCAT(r.last_name, ', ', r.first_name, IFNULL(CONCAT(' ', r.middle_name), ''), IFNULL(CONCAT(' ', r.name_extension), '')) AS resident_name FROM health_records hr LEFT JOIN residents r ON hr.resident_id = r.id WHERE hr.id = ? LIMIT 1");
$stmt->bind_param('i', $record_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
  echo "<script>alert('Record not found.'); window.location.href='records.php';</script>";
  exit();
}
$record = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>View Health Record | Barangay Health Monitoring System</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', Arial, sans-serif; background: #f8fafc; }
    .paper-container { max-width: 900px; margin: 32px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); padding: 32px 32px 24px 32px; }
    .paper-table { width: 100%; border-collapse: collapse; }
    .paper-table td { padding: 4px 6px; vertical-align: middle; }
    .paper-input, .paper-textarea { border: none; border-bottom: 1px solid #aaa; background: transparent; font-size: 13px; width: 100%; padding: 2px 4px; color: #222; }
    .paper-input[readonly], .paper-textarea[readonly] { background: #f8fafc; color: #222; }
    .section-title { font-size: 17px; font-weight: 700; margin-bottom: 8px; letter-spacing: 1px; }
    .small { font-size: 13px; font-weight: 700; }
    .boxed { border: 1px solid #222; border-radius: 6px; padding: 18px 18px 12px 18px; background: #fff; margin-bottom: 18px; }
    @media print {
      body { background: #fff; }
      .paper-container { box-shadow: none; border-radius: 0; padding: 0; margin: 0; }
      .no-print { display: none !important; }
      .boxed { border: 1px solid #000; }
    }
  </style>
</head>
<body>
  <div class="paper-container">
    <?php if (isset($_SESSION['success_message'])): ?>
      <div style="background:#d4edda; border:1px solid #c3e6cb; color:#155724; padding:12px; border-radius:6px; margin-bottom:16px;">
        <?php 
          echo htmlspecialchars($_SESSION['success_message']); 
          unset($_SESSION['success_message']);
        ?>
      </div>
    <?php endif; ?>
    
    <div style="display:flex; align-items:center; gap:18px; margin-bottom:8px;">
      <img src="Brgy. San Isidro-LOGO.png" alt="logo" style="width:70px; height:70px; object-fit:contain;" />
      <div style="flex:1; text-align:center;">
        <div style="font-weight:700; font-size:20px;">PAGSANJAN RURAL HEALTH UNIT</div>
        <div style="font-weight:600; font-size:15px;">PATIENT RECORD</div>
        <div style="font-size:12px;">Brgy. San Isidro / Pagsanjan, Laguna</div>
      </div>
      <div style="text-align:right; min-width:180px; font-size:13px;">
        <div><span class="small">DATE:</span> <?php echo htmlspecialchars($record['record_date'] ?? ''); ?></div>
        <div><span class="small">TIME:</span> <?php echo htmlspecialchars($record['record_time'] ?? ''); ?></div>
        <div><span class="small">CONTACT NO.:</span> <?php echo htmlspecialchars($record['contact_no'] ?? ''); ?></div>
        <div><span class="small">EMAIL:</span> <?php echo htmlspecialchars($record['email'] ?? $record['email_address'] ?? ''); ?></div>
      </div>
    </div>
    <table class="paper-table" style="font-size:12px;">
      <tr>
        <td style="width:14%;"><strong class="small">NAME OF PATIENT:</strong></td>
        <td style="width:83%;">
          <span class="paper-input" readonly><?php echo htmlspecialchars($record['residents_name'] ?? $record['resident_name'] ?? ''); ?></span>
        </td>
      </tr>
      <tr style="height:8px;"></tr>
      <tr>
        <td style="width:15%;"><strong class="small">BIRTHDAY:</strong></td>
        <td style="width:18%;">
          <span class="paper-input" readonly><?php echo htmlspecialchars($record['resident_birthday'] ?? ''); ?></span>
        </td>
        <td style="width:7%;"><strong class="small">AGE:</strong></td>
        <td style="width:7%;">
          <span class="paper-input" readonly>
            <?php
              if (!empty($record['resident_birthday'])) {
                $dob = new DateTime($record['resident_birthday']);
                $now = new DateTime();
                $age = $now->diff($dob)->y;
                echo $age;
              }
            ?>
          </span>
        </td>
        <td style="width:7%;"><strong class="small">SEX:</strong></td>
        <td style="width:7%;">
          <span class="paper-input" readonly><?php echo htmlspecialchars($record['resident_sex'] ?? ''); ?></span>
        </td>
        <td style="width:12%;"><strong class="small">CIVIL STATUS:</strong></td>
        <td style="width:17%;">
          <span class="paper-input" readonly><?php echo htmlspecialchars($record['resident_civil_status'] ?? ''); ?></span>
        </td>
      </tr>
      <tr style="height:8px;"></tr>
      <tr>
        <td><strong class="small">ADDRESS</strong></td>
        <td colspan="7">
          <span class="paper-input" readonly>
            <?php
              $address_parts = [];
              if (!empty($record['resident_address'])) $address_parts[] = $record['resident_address'];
              if (!empty($record['barangay'])) $address_parts[] = $record['barangay'];
              if (!empty($record['city_municipality'])) $address_parts[] = $record['city_municipality'];
              if (!empty($record['province'])) $address_parts[] = $record['province'];
              echo htmlspecialchars(implode(', ', $address_parts));
            ?>
          </span>
        </td>
      </tr>
    </table>
    <div style="height:10px;"></div>
    <div class="boxed">
      <div class="section-title">RECORD DETAILS</div>
      <div style="margin-bottom:12px;">
        <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
          <div style="width:120px;"><strong class="small">DATE:</strong></div>
          <div><span class="paper-input" readonly><?php echo htmlspecialchars($record['record_date'] ?? ''); ?></span></div>
        </div>
        <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
          <div style="width:120px;"><strong class="small">TIME:</strong></div>
          <div><span class="paper-input" readonly><?php echo htmlspecialchars($record['record_time'] ?? ''); ?></span></div>
        </div>
        <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
          <div style="width:120px;"><strong class="small">TEMPERATURE:</strong></div>
          <div style="display:flex; align-items:center; gap:8px;"><span class="paper-input" readonly><?php echo htmlspecialchars($record['v_temp'] ?? ''); ?></span><span class="small">°C</span></div>
        </div>
        <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
          <div style="width:120px;"><strong class="small">WEIGHT:</strong></div>
          <div style="display:flex; align-items:center; gap:8px;"><span class="paper-input" readonly><?php echo htmlspecialchars($record['v_wt'] ?? ''); ?></span><span class="small">kg</span></div>
        </div>
        <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
          <div style="width:120px;"><strong class="small">HEIGHT:</strong></div>
          <div style="display:flex; align-items:center; gap:8px;"><span class="paper-input" readonly><?php echo htmlspecialchars($record['v_ht'] ?? ''); ?></span><span class="small">cm</span></div>
        </div>
        <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
          <div style="width:120px;"><strong class="small">BLOOD PRESSURE:</strong></div>
          <div style="display:flex; align-items:center; gap:8px;"><span class="paper-input" readonly><?php echo htmlspecialchars($record['v_bp'] ?? ''); ?></span><span class="small">mmHg</span></div>
        </div>
        <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
          <div style="width:120px;"><strong class="small">RESPIRATORY RATE:</strong></div>
          <div style="display:flex; align-items:center; gap:8px;"><span class="paper-input" readonly><?php echo htmlspecialchars($record['v_rr'] ?? ''); ?></span><span class="small">breaths/min</span></div>
        </div>
        <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
          <div style="width:120px;"><strong class="small">PULSE RATE:</strong></div>
          <div style="display:flex; align-items:center; gap:8px;"><span class="paper-input" readonly><?php echo htmlspecialchars($record['v_pr'] ?? ''); ?></span><span class="small">bpm</span></div>
        </div>
      </div>
      <div style="margin-bottom:12px;">
        <label style="font-weight:700; display:block; margin-bottom:6px;">REASON OF CONSULTATION / COMPLAINT</label>
        <div class="paper-textarea" style="width:100%; height:120px; background-image: linear-gradient(to bottom, rgba(0,0,0,0.06) 1px, transparent 1px); background-size: 100% 18px;" readonly><?php echo htmlspecialchars($record['reason_for_consultation'] ?? $record['reason'] ?? ''); ?></div>
      </div>
      <div style="margin-bottom:12px;">
        <label style="font-weight:700; display:block; margin-bottom:6px;">DIAGNOSIS / TREATMENT</label>
        <div class="paper-textarea" style="width:100%; height:120px; background-image: linear-gradient(to bottom, rgba(0,0,0,0.06) 1px, transparent 1px); background-size: 100% 18px;" readonly><?php echo htmlspecialchars($record['treatment'] ?? ''); ?></div>
      </div>
      <div style="margin-bottom:6px;">
        <label style="font-weight:700; display:block; margin-bottom:6px;">ASSESSMENT</label>
        <div class="paper-textarea" style="width:100%; height:120px; background-image: linear-gradient(to bottom, rgba(0,0,0,0.06) 1px, transparent 1px); background-size: 100% 18px;" readonly><?php echo htmlspecialchars($record['assessment'] ?? ''); ?></div>
      </div>
      <div class="mt-4">
        <div class="section-title">DOCTOR/STAFF</div>
        <span class="paper-input" readonly><?php echo htmlspecialchars($record['consulting_doctor'] ?? ''); ?></span>
      </div>
    </div>
    <div style="height:12px;"></div>
    <div class="no-print" style="display:flex; justify-content:flex-end; gap:8px; margin-top:12px;">
      <a href="records.php" style="text-decoration:none; color:#374151; background:#e5e7eb; border:1px solid #d1d5db; padding:8px 16px; border-radius:6px; font-weight:500;">← Back to Records</a>
      <button type="button" onclick="window.print();" style="padding:8px 16px; background:#bfdbfe; color:#1e3a8a; border:1px solid #93c5fd; border-radius:6px; cursor:pointer; font-weight:500;">Print</button>
    </div>
  </div>
</body>
</html>
