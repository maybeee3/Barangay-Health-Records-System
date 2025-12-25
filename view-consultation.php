<?php
session_start();
include 'config.php'; // Include database connection

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
  header('Location: login.php'); // Redirect to login page if not authenticated
  exit();
}

$consultation = null;
$resident_info = null;


$consultation_error = null;
$debug_info = [];
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
  $consultation_id = $_GET['id'];
  $debug_info[] = 'Requested consultation ID: ' . htmlspecialchars($consultation_id);
  // Select only columns that exist in the `residents` table and map names where necessary
  $stmt = $conn->prepare("SELECT c.*, r.last_name, r.first_name, r.middle_name, r.name_extension, r.date_of_birth, r.sex, r.civil_status, r.contact_no AS contact_number, r.email AS resident_email, r.province, r.city_municipality, r.barangay, r.guardian_name AS emergency_contact_name, r.guardian_relationship AS emergency_contact_relationship, r.guardian_contact_no AS emergency_contact_number, r.address FROM consultations c LEFT JOIN residents r ON c.resident_id = r.id WHERE c.id = ?");
  if ($stmt) {
    $stmt->bind_param("i", $consultation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
      $consultation = $result->fetch_assoc();
      $resident_info = $consultation;
      // Backfill/normalize some fields that older schema or code expects
      if (empty($consultation['contact_number']) && !empty($consultation['contact_no'])) {
        $consultation['contact_number'] = $consultation['contact_no'];
      }
      // If `date_of_birth` exists, compute `age` (use consultation date if available, otherwise today)
      if (empty($consultation['age'])) {
        $dob = $consultation['date_of_birth'] ?? null;
        if (!empty($dob) && $dob !== '0000-00-00') {
          try {
            $birth = new DateTime($dob);
            $refDate = $consultation['date_of_consultation'] ?? date('Y-m-d');
            $ref = new DateTime($refDate);
            $ageInterval = $birth->diff($ref);
            $consultation['age'] = $ageInterval->y;
          } catch (Exception $e) {
            $consultation['age'] = '';
          }
        } else {
          $consultation['age'] = '';
        }
      }
      // Map guardian fields to emergency contact fields if present
      if (!empty($consultation['guardian_name']) && empty($consultation['emergency_contact_name'])) {
        $consultation['emergency_contact_name'] = $consultation['guardian_name'];
      }
      if (!empty($consultation['guardian_relationship']) && empty($consultation['emergency_contact_relationship'])) {
        $consultation['emergency_contact_relationship'] = $consultation['guardian_relationship'];
      }
      if (!empty($consultation['guardian_contact_no']) && empty($consultation['emergency_contact_number'])) {
        $consultation['emergency_contact_number'] = $consultation['guardian_contact_no'];
      }
    } else {
      $consultation_error = 'Consultation record not found.';
      $debug_info[] = 'No rows returned for this ID.';
    }
    $stmt->close();
  } else {
    $consultation_error = 'Consultation record not found or resident missing.';
    $debug_info[] = 'SQL prepare failed: ' . htmlspecialchars($conn->error);
  }
} else {
  $consultation_error = 'Invalid consultation record ID.';
  $debug_info[] = 'ID missing or not numeric.';
}
$conn->close();
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>View Consultation | Barangay Health Monitoring System</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @page { size: A4; margin: 18mm; }
    body { font-family: Arial, Helvetica, sans-serif; color: #000; font-size:12px; }
    .container { max-width: 800px; margin: 0 auto; }
    .paper-table { width:100%; border-collapse:collapse; }
    .paper-table td { vertical-align:top; padding:2px 6px; }
    .paper-underline { border-bottom:1px solid #b1ffcbff; display:inline-block; }
    .paper-input { border:0; border-bottom:1px solid #000; background:transparent; font-family:inherit; font-size:12px; padding:2px 4px; }
    textarea.paper-text { border:0; background:transparent; font-family:inherit; font-size:12px; padding:6px; }
    textarea.paper-textarea { border:0; border-bottom:1px solid #000; background:transparent; font-family:inherit; font-size:12px; padding:6px; resize:none; }
    .section-title{font-weight:700;margin-bottom:6px;font-size:12px}
    .checkbox-inline{display:inline-flex;align-items:center;gap:6px;margin-right:12px}
    .title { text-align:center; }
    .small { font-size:11px; }
    .logo { width:72px; height:72px; object-fit:contain; }
    .boxed { border:1px solid #000; padding:8px; min-height:360px; }
    .right-info div { margin-bottom:6px; text-align:right; }
    .section-label { font-weight:700; }
    .vitals-row td { border-bottom:1px dotted #999; padding:6px 4px; }
    .complaint-lines { height:280px; }
    
    @media print {
      body { margin:0; }
      .container { box-shadow:none; max-width:210mm; width:100%; }
      .no-print { display:none !important; }
      /* Ensure inputs/textarea render as plain lines on paper */
      input.paper-input, textarea.paper-textarea, textarea.paper-text { background:transparent !important; border:0 !important; border-bottom:1px solid #000 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
      /* Avoid breaking inside boxed sections */
      .boxed { page-break-inside: avoid; }
      
    }
  </style>
</head>

<body class="bg-gradient-to-br from-[#f8fafc] to-white min-h-screen p-6">
  <div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-lg p-8 relative">
    <div class="flex justify-between items-center mb-6">
      <a href="consultations.php" class="text-blue-600 font-semibold hover:underline fixed left-4 z-50 no-print" style="top:1cm;">← Back to Consultations</a>
    </div>

    <?php if ($consultation): ?>
    <?php if (isset($_GET['updated']) && $_GET['updated'] == '1'): ?>
      <div class="mb-4 p-3 text-sm text-green-800 bg-green-100 border border-green-200 rounded">
        Consultation updated successfully.
      </div>
    <?php endif; ?>
    <form id="addConsultationForm" method="POST" action="update-consultation-datetime.php" class="space-y-5" autocomplete="off">
      <input type="hidden" name="id" value="<?php echo htmlspecialchars($consultation['id'] ?? ''); ?>">
      <div class="container">
        <table class="paper-table">
          <tr>
            <td style="width:14%;"><img src="Brgy. San Isidro-LOGO.png" alt="logo" class="logo" /></td>
            <td style="width:62%;" class="title">
              <div style="font-weight:700; text-align:center; font-size:20px;">PAGSANJAN RURAL HEALTH UNIT</div>
              <div style="font-weight:600; font-size:13px; margin-top:2px;">PATIENT RECORD</div>
              <div class="small" style="margin-top:4px;">Brgy. San Isidro / Pagsanjan, Laguna</div>
            </td>
            <td style="width:24%;" class="right-info">
              <div><strong class="small">DATE:</strong> <input id="date_of_consultation" name="date_of_consultation" type="date" class="paper-input" style="width:140px; background:#f3f4f6;" value="<?php echo htmlspecialchars($consultation['date_of_consultation'] ?? ''); ?>"/></div>
              <div><strong class="small">TIME:</strong> <input id="consultation_time" name="consultation_time" type="time" class="paper-input" style="width:120px; background:#f3f4f6;" value="<?php echo htmlspecialchars($consultation['consultation_time'] ?? ''); ?>"/></div>
              <div><strong class="small">CONTACT NO.:</strong> <input name="contact_no" type="text" readonly tabindex="-1" class="paper-input" style="width:140px; background:transparent;" value="<?php echo htmlspecialchars($consultation['contact_number'] ?? ($consultation['contact_no'] ?? '')); ?>"/></div>
              <div><strong class="small">EMAIL:</strong> <input id="email" name="email" type="email" readonly tabindex="-1" class="paper-input" style="width:140px; background:transparent;" value="<?php echo htmlspecialchars($consultation['email'] ?? ($consultation['resident_email'] ?? '')); ?>"/></div>
            </td>
          </tr>
        </table>

        <div style="height:8px;"></div>

        <table class="paper-table" style="font-size:12px;">
          <tr>
            <td style="width:14%;"><strong class="small">Name of Patient</strong></td>
            <td style="width:86%;">
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
              ?>
              <input type="text" id="resident_name_search" class="paper-input w-full" value="<?php echo strip_tags($final_name); ?>" readonly tabindex="-1">
              <input type="hidden" id="resident_id" name="resident_id" value="<?php echo htmlspecialchars($consultation['resident_id'] ?? ''); ?>">
            </td>
          </tr>
          <tr style="height:8px;"></tr>
          <tr>
            <td style="width:15%;"><strong class="small">Birthday</strong></td>
            <td style="width:18%;">
              <input name="birthday" id="birthday" type="text" class="paper-input" style="width:100%; min-width:40px; background:transparent;" value="<?php echo htmlspecialchars($consultation['date_of_birth'] ?? ''); ?>" readonly tabindex="-1"/>
            </td>
            <td style="width:7%;"><strong class="small">Age</strong></td>
            <td style="width:7%;">
              <input name="age" id="age" type="text" class="paper-input" style="width:100%; min-width:40px; background:transparent;" value="<?php echo htmlspecialchars($consultation['age'] ?? ''); ?>" readonly tabindex="-1"/>
            </td>
            <td style="width:7%;"><strong class="small">Sex</strong></td>
            <td style="width:7%;">
              <input name="sex" id="sex" type="text" class="paper-input" style="width:100%; min-width:40px; background:transparent;" value="<?php echo htmlspecialchars($consultation['sex'] ?? ''); ?>" readonly tabindex="-1"/>
            </td>
            <td style="width:12%;"><strong class="small">Civil Status</strong></td>
            <td style="width:17%;">
              <input name="civil_status" id="civil_status" type="text" class="paper-input" style="width:100%; min-width:70px; background:transparent;" value="<?php echo htmlspecialchars($consultation['civil_status'] ?? ''); ?>" readonly tabindex="-1"/>
            </td>
          </tr>
          <tr style="height:8px;"></tr>
          <tr>
            <td><strong class="small">Address</strong></td>
            <td colspan="7"><input name="address" id="address" type="text" class="paper-input" style="width:100%; height:20px; background:transparent;" value="<?php echo htmlspecialchars(trim(($consultation['province'] ?? '') . ', ' . ($consultation['city_municipality'] ?? '') . ', ' . ($consultation['barangay'] ?? '') . (isset($consultation['address']) ? (', ' . $consultation['address']) : ''))); ?>" readonly tabindex="-1"/></td>
          </tr>
        </table>

        <div style="height:10px;"></div>

        <div class="boxed">
          <div class="section-title">RECORD DETAILS</div>
          <div class="vitals-vertical" style="margin-bottom:12px;">
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">DATE</strong></div>
              <div><input name="v_date" type="date" class="paper-input" style="width:220px; background:transparent;" id="v_date" value="<?php echo htmlspecialchars($consultation['date_of_consultation'] ?? ''); ?>" readonly tabindex="-1"/></div>
            </div>
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">TIME</strong></div>
              <div><input name="v_time" type="time" class="paper-input" style="width:120px; background:transparent;" id="v_time" value="<?php echo htmlspecialchars($consultation['consultation_time'] ?? ''); ?>" readonly tabindex="-1"/></div>
            </div>
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">TEMP</strong></div>
              <div><input name="v_temp" type="text" class="paper-input" style="width:120px; background:transparent;" value="<?php echo htmlspecialchars($consultation['temperature'] ?? ''); ?>" readonly tabindex="-1"/></div>
            </div>
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">WT</strong></div>
              <div><input id="v_wt" name="v_wt" type="text" class="paper-input" style="width:120px; background:transparent;" value="<?php echo htmlspecialchars($consultation['weight'] ?? ''); ?>" readonly tabindex="-1"/></div>
            </div>
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">HT</strong></div>
              <div><input id="v_ht" name="v_ht" type="text" class="paper-input" style="width:120px; background:transparent;" value="<?php echo htmlspecialchars($consultation['height'] ?? ''); ?>" readonly tabindex="-1"/></div>
            </div>
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">BP</strong></div>
              <div><input name="v_bp" type="text" class="paper-input" style="width:120px; background:transparent;" value="<?php echo htmlspecialchars($consultation['blood_pressure'] ?? ''); ?>" readonly tabindex="-1"/></div>
            </div>
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">RR</strong></div>
              <div><input name="v_rr" type="text" class="paper-input" style="width:120px; background:transparent;" value="<?php echo htmlspecialchars($consultation['respiratory_rate'] ?? ''); ?>" readonly tabindex="-1"/></div>
            </div>
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">PR</strong></div>
              <div><input name="v_pr" type="text" class="paper-input" style="width:120px; background:transparent;" value="<?php echo htmlspecialchars($consultation['heart_rate'] ?? ''); ?>" readonly tabindex="-1"/></div>
            </div>
          </div>

          <div class="mt-4">
            <label for="reason_for_consultation" style="font-weight:700; display:block; margin-bottom:6px;">Reason for Consultation / Complaint</label>
            <textarea id="reason_for_consultation" name="reason_for_consultation" class="paper-textarea" readonly tabindex="-1" style="width:100%; height:120px; background-image: linear-gradient(to bottom, rgba(0,0,0,0.06) 1px, transparent 1px); background-size: 100% 18px; background:transparent;"><?php echo htmlspecialchars($consultation['reason_for_consultation'] ?? ''); ?></textarea>
          </div>

          <div class="mt-4">
            <div class="section-title">Consulting Doctor / Staff</div>
            <input type="text" name="consulting_doctor" class="paper-input w-full" placeholder="Enter name of consulting doctor or staff..." value="<?php echo htmlspecialchars($consultation['consulting_doctor'] ?? ''); ?>" readonly tabindex="-1" style="background:transparent;" />
          </div>
        </div>

        <!-- Hidden fields kept for compatibility -->
        <input type="hidden" id="bmi" name="bmi" value="<?php echo htmlspecialchars($consultation['bmi'] ?? ''); ?>" />
        <input type="hidden" id="blood_sugar" name="blood_sugar" value="<?php echo htmlspecialchars($consultation['blood_sugar'] ?? ''); ?>" />
        <input type="hidden" id="treatment_prescription" name="treatment_prescription" value="<?php echo htmlspecialchars($consultation['treatment_prescription'] ?? ''); ?>" />
        <input type="hidden" id="follow_up_date" name="follow_up_date" value="<?php echo htmlspecialchars($consultation['follow_up_date'] ?? ''); ?>" />
        <div class="flex justify-end gap-3 mt-4 no-print">
  <!-- Cancel = Gray -->
  <a href="consultations.php" 
     class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 border">
     Cancel
  </a>

  <!-- Save Changes = Light Blue -->
  <button type="submit" 
          class="px-4 py-2 bg-blue-200 text-blue-900 rounded hover:bg-blue-300">
    Save Changes
  </button>

  <!-- Proceed = Blue -->
  <a href="add-records.php?resident_id=<?php echo urlencode($consultation['resident_id'] ?? ''); ?>" 
     class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
     Proceed
  </a>
</div>

      </div>
    </form>
    <?php elseif ($consultation_error): ?>
    <div class="text-center text-red-500 my-12">
      <h2 class="text-xl font-semibold mb-2">Consultation Not Found</h2>
      <p><?php echo htmlspecialchars($consultation_error); ?></p>
      <?php if (!empty($debug_info)): ?>
        <div class="mt-4 text-left text-xs text-gray-500 bg-gray-100 rounded p-2 max-w-xl mx-auto">
          <strong>Debug Info:</strong><br>
          <?php foreach ($debug_info as $dbg) echo htmlspecialchars($dbg) . '<br>'; ?>
        </div>
      <?php endif; ?>
      <a href="consultations.php" class="inline-block mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 no-print">Back to Consultations</a>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Floating print button (no-print) -->
<div class="no-print fixed bottom-4 right-4 z-50">
  <button onclick="window.print();" aria-label="Print" title="Print" class="p-3 rounded-full bg-blue-600 text-white hover:bg-blue-700 focus:outline-none shadow-lg">
    <!-- print icon -->
    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M19 21H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2z"></path>
      <path d="M17 17H7a1 1 0 0 1-1-1v-5h13v5a1 1 0 0 1-1 1z"></path>
      <rect x="7" y="3" width="10" height="4" rx="1"></rect>
    </svg>
  </button>
</div>
</body>
</html>


