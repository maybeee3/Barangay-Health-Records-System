<?php
session_start();
include 'config.php'; // Include database connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $resident_id = $_POST['resident_id'];
    $email = $_POST['email'];
    $date_of_consultation = $_POST['date_of_consultation'];
    $consultation_time = $_POST['consultation_time'];

    // Find the next available 15-min slot if the selected time is already taken
    $start_time = DateTime::createFromFormat('H:i', $consultation_time);
    // If the selected date is today, do not allow times that are already past.
    // If the requested time has already passed, bump the start_time to the
    // next 15-minute interval from 'now' so users can still schedule today.
    $now = new DateTime();
    if ($start_time && $date_of_consultation === $now->format('Y-m-d')) {
      // construct a full datetime for comparison
      $requestedDt = DateTime::createFromFormat('Y-m-d H:i', $date_of_consultation . ' ' . $start_time->format('H:i'));
      if ($requestedDt && $requestedDt < $now) {
        // round 'now' up to the next 15-minute boundary
        $m = (int)$now->format('i');
        $add = (15 - ($m % 15)) % 15;
        if ($add === 0 && (int)$now->format('s') > 0) $add = 15;
        if ($add > 0) $now->modify("+{$add} minutes");
        $start_time = DateTime::createFromFormat('H:i', $now->format('H:i')) ?: $start_time;
      }
    }
    $min_time = DateTime::createFromFormat('H:i', '09:00');
    $max_time = DateTime::createFromFormat('H:i', '17:00');
    $found_slot = false;
    while ($start_time <= $max_time) {
        $time_str = $start_time->format('H:i');
        $sql_check = "SELECT COUNT(*) FROM consultations WHERE date_of_consultation = ? AND consultation_time = ?";
        $stmt_check_time = $conn->prepare($sql_check);
        $stmt_check_time->bind_param('ss', $date_of_consultation, $time_str);
        $stmt_check_time->execute();
        $stmt_check_time->bind_result($count);
        $stmt_check_time->fetch();
        $stmt_check_time->close();
        if ($count == 0) {
            $consultation_time = $time_str;
            $found_slot = true;
            break;
        }
        $start_time->modify('+15 minutes');
    }
    if (!$found_slot) {
        echo "<script>alert('No available 15-minute slots left for this date.'); window.location.href='consultations.php';</script>";
        exit();
    }

    // Validate time is between 9:00 AM and 5:00 PM, and in 15-minute intervals
    $minutes = (int)DateTime::createFromFormat('H:i', $consultation_time)->format('i');
    if ($start_time < $min_time || $start_time > $max_time) {
      echo "<script>alert('Error: Consultation time must be between 9:00 AM and 5:00 PM.');</script>";
      // Don't process the form further
    } elseif (!in_array($minutes, [0, 15, 30, 45])) {
      echo "<script>alert('Error: Consultation time must be in 15-minute intervals (e.g., 9:00 AM, 9:15 AM, 9:30 AM, 9:45 AM).');</script>";
      // Don't process the form further
    } else {
    // Map form fields to DB columns (use existing form input names)
    $blood_pressure = $_POST['v_bp'] ?? null;
    $heart_rate = $_POST['v_pr'] ?? null;
    $respiratory_rate = $_POST['v_rr'] ?? null;
    $temperature = $_POST['v_temp'] ?? null;
    $blood_sugar = $_POST['blood_sugar'] ?? null;
    $weight = $_POST['v_wt'] ?? null;
    $height = $_POST['v_ht'] ?? null;
    $bmi = $_POST['bmi'] ?? null;
    $treatment_prescription = $_POST['treatment_prescription'] ?? null;
    $reason_for_consultation = $_POST['reason_for_consultation'] ?? null;
    $consulting_doctor = $_POST['consulting_doctor'] ?? null;
    $follow_up_date = $_POST['follow_up_date'] ?? null;
    
    // Verify email: only block if the email exists in users AND is not verified.
    $allow_insert = true;
    if (!empty($email)) {
      $stmt_check = $conn->prepare("SELECT id, is_email_verified FROM users WHERE email = ?");
      if ($stmt_check) {
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();
        $stmt_check->bind_result($uid_check, $is_email_verified_check);
        if ($stmt_check->num_rows > 0) {
          $stmt_check->fetch();
          if (!$is_email_verified_check) {
            echo "<script>alert('Error: This email exists but is not verified. Please use a verified email account.');</script>";
            $allow_insert = false;
          }
        }
        $stmt_check->close();
      }
    }

    // Enforce that the provided email exists in residents (we send reminders to resident emails)
    if ($allow_insert) {
      if (empty($email)) {
        echo "<script>alert('Error: Email is required and must be an existing resident email.');</script>";
        $allow_insert = false;
      } else {
        $stmt_r = $conn->prepare("SELECT id FROM residents WHERE email = ? LIMIT 1");
        if ($stmt_r) {
          $stmt_r->bind_param('s', $email);
          $stmt_r->execute();
          $stmt_r->store_result();
          if ($stmt_r->num_rows === 0) {
            echo "<script>alert('Error: The provided email is not found in residents. Please use the resident\'s registered email.');</script>";
            $allow_insert = false;
          }
          $stmt_r->close();
        }
      }
    }

    if ($allow_insert) {
    $stmt = $conn->prepare("INSERT INTO consultations (resident_id, email, date_of_consultation, consultation_time, blood_pressure, heart_rate, respiratory_rate, temperature, blood_sugar, weight, height, bmi, reason_for_consultation, treatment_prescription, follow_up_date, consulting_doctor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
      echo "<script>alert('Prepare failed: " . addslashes($conn->error) . "');</script>";
    } else {
      $stmt->bind_param("isssssssssssssss", $resident_id, $email, $date_of_consultation, $consultation_time, $blood_pressure, $heart_rate, $respiratory_rate, $temperature, $blood_sugar, $weight, $height, $bmi, $reason_for_consultation, $treatment_prescription, $follow_up_date, $consulting_doctor);

      if ($stmt->execute()) {
        // Log activity: consultation added
        try {
          $usernameLog = $_SESSION['username'] ?? 'System';
          $residentName = 'Resident #' . ($resident_id ?? '');
          $rstmt = $conn->prepare('SELECT CONCAT(first_name, " ", last_name) AS name FROM residents WHERE id = ? LIMIT 1');
          if ($rstmt) {
            $rstmt->bind_param('i', $resident_id);
            $rstmt->execute();
            $rr = $rstmt->get_result();
            if ($rr && $rr->num_rows > 0) {
              $rn = $rr->fetch_assoc();
              if (!empty($rn['name'])) $residentName = $rn['name'];
            }
            $rstmt->close();
          }
          if (function_exists('log_activity')) {
            $msg = "Added consultation for {$residentName} (ID {$resident_id}) on {$date_of_consultation} {$consultation_time}";
            log_activity($conn, null, $usernameLog, 'consultation_added', $msg, $resident_id);
          }
        } catch (Exception $e) {}

        echo "<script>alert('Consultation record added successfully!'); window.location.href='consultations.php';</script>";
      } else {
        echo "<script>alert('Error: " . addslashes($stmt->error) . "');</script>";
      }

      $stmt->close();
    }

    }
    }
}

// Fetch residents for dropdown

$residents = [];
$sql_residents = "SELECT id, CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name, ''), ' ', COALESCE(name_extension, '')) AS full_name FROM residents ORDER BY last_name ASC, first_name ASC";
$result_residents = $conn->query($sql_residents);
if ($result_residents && $result_residents->num_rows > 0) {
  while($row = $result_residents->fetch_assoc()) {
    $residents[] = $row;
  }
} else if ($result_residents === false) {
  // Query failed, handle error
  echo "<script>alert('Error fetching residents: " . addslashes($conn->error) . "');</script>";
}
$conn->close();

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Add Consultation | Barangay Health Monitoring System</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @page { size: A4; margin: 18mm; }
    body { font-family: Arial, Helvetica, sans-serif; color: #000; font-size:12px; }
    .container { max-width: 800px; margin: 0 auto; }
    .paper-table { width:100%; border-collapse:collapse; }
    .paper-table td { vertical-align:top; padding:2px 6px; }
    .paper-underline { border-bottom:1px solid #000; display:inline-block; }
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
      
      </div>

        <form method="POST" class="space-y-5" id="addConsultationForm">
      		<!-- ...existing code... -->

            <!-- PATIENT RECORD FORM (from add-records.php, without Treatment and Assessment) -->
    <div class="container">
        
        <table class="paper-table">
          <tr>
            <td style="width:20%;"><img src="Brgy. San Isidro-LOGO.png" alt="logo" class="logo" /></td>
            <td style="width:62%;" class="title">
              <div style="font-weight:700; text-align:center; font-size:20px;">PAGSANJAN RURAL HEALTH UNIT</div>
              <div style="font-weight:600; font-size:13px; margin-top:2px;">PATIENT RECORD</div>
              <div class="small" style="margin-top:4px;">Brgy. San Isidro / Pagsanjan, Laguna</div>
            </td>
            <td style="width:24%;" class="right-info">
              <div><strong class="small">DATE:</strong> <input id="date_of_consultation" name="date_of_consultation" type="date" class="paper-input" style="width:140px;"/></div>
              <?php
              // PHP: Find the next available 15-min slot for the selected date (or today by default)
              $auto_consult_time = '';
              $auto_consult_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
              $min_time = '09:00';
              $max_time = '17:00';
              $current_time = $min_time;
              $conn2 = new mysqli($servername, $username, $password, $dbname);
              while (true) {
                $sql = "SELECT COUNT(*) FROM consultations WHERE date_of_consultation = ? AND consultation_time = ?";
                $stmt = $conn2->prepare($sql);
                $stmt->bind_param('ss', $auto_consult_date, $current_time);
                $stmt->execute();
                $stmt->bind_result($count);
                $stmt->fetch();
                $stmt->close();
                if ($count == 0) {
                  $auto_consult_time = $current_time;
                  break;
                }
                $dt = DateTime::createFromFormat('H:i', $current_time);
                $dt->modify('+15 minutes');
                if ($dt->format('H:i') > $max_time) {
                  $auto_consult_time = '';
                  break;
                }
                $current_time = $dt->format('H:i');
              }
              $conn2->close();
              ?>
              <div><strong class="small">TIME:</strong></div>
              <div><input id="consultation_time" name="consultation_time" type="time" class="paper-input" style="width:140px; background:#f3f4f6;" value="<?php echo htmlspecialchars($auto_consult_time); ?>" readonly required/></div>
              <div><strong class="small">CONTACT NO.:</strong> <input name="contact_no" type="text" class="paper-input" style="width:140px;"/></div>
              <div><strong class="small">EMAIL:</strong> <input id="email" name="email" type="email" class="paper-input" style="width:140px;"/></div>
              <div id="email_error" class="text-xs text-red-600 mt-1 hidden"></div>
            </td>
          </tr>
        </table>

        <div style="height:8px;"></div>

        <table class="paper-table" style="font-size:12px;">
          <tr>
            <td style="width:14%;"><strong class="small">Name of Patient</strong></td>
            <td style="width:86%;">
              <input type="text" id="resident_name_search" class="paper-input w-full" placeholder="Search resident name..." autocomplete="off">
              <input type="hidden" id="resident_id" name="resident_id">
              <div id="resident_suggestions" class="absolute bg-white border rounded shadow z-10 w-full hidden"></div>
              <div id="resident_name_error" class="text-xs text-red-600 mt-1 hidden">Please select a valid resident from the suggestions.</div>
            </td>
          </tr>
          <tr style="height:8px;"></tr>
      <tr>
        <tr>
  <td style="width:15%;"><strong class="small">Birthday</strong></td>
  <td style="width:18%;">
            <input name="birthday" id="birthday" type="text" class="paper-input" style="width:100%; min-width:40px;" readonly placeholder="mm/dd/yyyy"/>
  </td>
  <td style="width:7%;"><strong class="small">Age</strong></td>
  <td style="width:7%;">
    <input name="age" id="age" type="text" class="paper-input" style="width:100%; min-width:40px;" readonly/>
  </td>
  <td style="width:7%;"><strong class="small">Sex</strong></td>
  <td style="width:7%;">
    <input name="sex" id="sex" type="text" class="paper-input" style="width:100%; min-width:40px;" readonly/>
  </td>
  <td style="width:12%;"><strong class="small">Civil Status</strong></td>
  <td style="width:17%;">
    <input name="civil_status" id="civil_status" type="text" class="paper-input" style="width:100%; min-width:70px;" readonly/>
  </td>
</tr>
          <tr style="height:8px;"></tr>
          <tr>
            <td><strong class="small">Address</strong></td>
            <td colspan="7"><input name="address" id="address" type="text" class="paper-input" style="width:100%; height:20px;" readonly/></td>
          </tr>
        </table>

        <div style="height:10px;"></div>

        <div class="boxed">
          <div class="section-title">RECORD DETAILS</div>
          <div class="vitals-vertical" style="margin-bottom:12px;">
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">DATE</strong></div>
              <div><input name="v_date" type="date" class="paper-input" style="width:220px;" id="v_date" readonly/></div>
            </div>
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">TIME</strong></div>
              <div><input name="v_time" type="time" class="paper-input" style="width:120px;" id="v_time" readonly/></div>
            <script>
            // Auto-fill and lock RECORD DETAILS date and time to current
            document.addEventListener('DOMContentLoaded', function() {
              var now = new Date();
              var dateStr = now.toISOString().slice(0,10);
              var timeStr = now.toTimeString().slice(0,5);
              var vDate = document.getElementById('v_date');
              var vTime = document.getElementById('v_time');
              if (vDate) {
                vDate.value = dateStr;
                vDate.readOnly = true;
                vDate.disabled = true;
              }
              if (vTime) {
                vTime.value = timeStr;
                vTime.readOnly = true;
                vTime.disabled = true;
              }
            });
            </script>
            </div>
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">TEMP</strong></div>
              <div><input name="v_temp" type="text" class="paper-input" style="width:120px;"/></div>
            </div>
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">WT</strong></div>
              <div><input id="v_wt" name="v_wt" type="text" class="paper-input" style="width:120px;"/></div>
            </div>
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">HT</strong></div>
              <div><input id="v_ht" name="v_ht" type="text" class="paper-input" style="width:120px;"/></div>
            </div>
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">BP</strong></div>
              <div><input name="v_bp" type="text" class="paper-input" style="width:120px;"/></div>
            </div>
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">RR</strong></div>
              <div><input name="v_rr" type="text" class="paper-input" style="width:120px;"/></div>
            </div>
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">PR</strong></div>
              <div><input name="v_pr" type="text" class="paper-input" style="width:120px;"/></div>
            </div>
            
          </div>

          <!-- Reason for Consultation / Complaint -->
          <div class="mt-4">
            <label for="reason_for_consultation" style="font-weight:700; display:block; margin-bottom:6px;">Reason for Consultation / Complaint</label>
            <textarea id="reason_for_consultation" name="reason_for_consultation" class="paper-textarea" style="width:100%; height:120px; background-image: linear-gradient(to bottom, rgba(0,0,0,0.06) 1px, transparent 1px); background-size: 100% 18px;"></textarea>
          </div>

          <!-- Diagnosis / Treatment -->
          <div class="mt-4">
            <div class="section-title">Diagnosis / Treatment / Prescription</div>
            <textarea name="treatment_prescription" class="paper-input w-full" rows="3" placeholder="Enter diagnosis, treatment plan, or prescription..."></textarea>
          </div>

          <!-- Consulting Doctor / Staff -->
          <div class="mt-4">
            <div class="section-title">Consulting Doctor / Staff</div>
            <input type="text" name="consulting_doctor" class="paper-input w-full" placeholder="Enter name of consulting doctor or staff..." value="Lhee Za Milanez" readonly>
          </div>
        </div>
        <!-- Hidden fields so POST contains optional fields even if not shown -->
        <input type="hidden" id="bmi" name="bmi" value="" />
        <input type="hidden" id="blood_sugar" name="blood_sugar" value="" />
        <input type="hidden" id="follow_up_date" name="follow_up_date" value="" />
      <!-- removed inner form tag to keep a single POST form on the page -->
    </div>

            <div class="flex justify-end gap-4">
              <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md shadow-md hover:bg-blue-700 transition-colors">
                Save Consultation
              </button>
              <a href="consultations.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md shadow-md hover:bg-gray-300 transition-colors">
                Cancel
              </a>
            </div>
        </form>
    </div>
  </div>

  <script>
    // Resident Search Autocomplete
        // Auto-set date if ?date= is in the URL (for calendar integration)
        document.addEventListener('DOMContentLoaded', function() {
          const urlParams = new URLSearchParams(window.location.search);
          const dateParam = urlParams.get('date');
          const residentIdParam = urlParams.get('id');
          if (dateParam) {
            const dateInput = document.getElementById('date_of_consultation');
            if (dateInput) {
              dateInput.value = dateParam;
              dateInput.readOnly = true;
              dateInput.style.background = '#f3f4f6'; // subtle gray to indicate fixed
            }
          }
          if (residentIdParam) {
            // Auto-fill resident info and lock selection
            const residentIdInput = document.getElementById('resident_id');
            const residentSearchInput = document.getElementById('resident_name_search');
            if (residentIdInput && residentSearchInput) {
              residentIdInput.value = residentIdParam;
              // Fetch resident details and fill all fields
              fetch(`fetch_resident_for_consultation_suggestions.php?id=${encodeURIComponent(residentIdParam)}`)
                .then(r => r.json())
                .then(res => {
                  if (res.name && residentSearchInput) {
                    residentSearchInput.value = res.name;
                    residentSearchInput.readOnly = true;
                    residentSearchInput.style.background = '#f3f4f6';
                  }
                  if (res.birthday && document.getElementById('birthday')) {
                    let dob = res.birthday;
                    if (/^\d{4}-\d{2}-\d{2}$/.test(dob)) {
                      const [y, m, d] = dob.split('-');
                      dob = `${m}/${d}/${y}`;
                    }
                    document.getElementById('birthday').value = dob;
                  }
                  if (res.age && document.getElementById('age')) document.getElementById('age').value = res.age;
                  if (res.sex && document.getElementById('sex')) document.getElementById('sex').value = res.sex;
                  if (res.civil_status && document.getElementById('civil_status')) {
                    let cs = res.civil_status;
                    if (cs) cs = cs.charAt(0).toUpperCase() + cs.slice(1).toLowerCase();
                    document.getElementById('civil_status').value = cs;
                  }
                  if (res.address && document.getElementById('address')) document.getElementById('address').value = res.address;
                  const contactEl2 = document.getElementsByName('contact_no')[0];
                  if (res.contact_number && contactEl2) contactEl2.value = res.contact_number;
                  const emailEl2 = document.getElementsByName('email')[0];
                  if (res.email && emailEl2) emailEl2.value = res.email;
                  if (res.v_temp && document.getElementsByName('v_temp')[0]) document.getElementsByName('v_temp')[0].value = res.v_temp;
                  if (res.v_wt && document.getElementsByName('v_wt')[0]) document.getElementsByName('v_wt')[0].value = res.v_wt;
                  if (res.v_ht && document.getElementsByName('v_ht')[0]) document.getElementsByName('v_ht')[0].value = res.v_ht;
                  if (res.v_bp && document.getElementsByName('v_bp')[0]) document.getElementsByName('v_bp')[0].value = res.v_bp;
                  if (res.v_pr && document.getElementsByName('v_pr')[0]) document.getElementsByName('v_pr')[0].value = res.v_pr;
                  if (res.v_rr && document.getElementsByName('v_rr')[0]) document.getElementsByName('v_rr')[0].value = res.v_rr;
                  if (res.reason_for_consultation && document.getElementById('reason_for_consultation')) document.getElementById('reason_for_consultation').value = res.reason_for_consultation;
                })
                .catch(err => console.error('Error fetching resident details:', err));
              // Hide suggestions and error
              const residentSuggestionsBox = document.getElementById('resident_suggestions');
              const residentNameError = document.getElementById('resident_name_error');
              if (residentSuggestionsBox) residentSuggestionsBox.classList.add('hidden');
              if (residentNameError) residentNameError.classList.add('hidden');
            }
          }
        });
    const residentSearchInput = document.getElementById('resident_name_search');
    const residentIdInput = document.getElementById('resident_id');
    const residentSuggestionsBox = document.getElementById('resident_suggestions');
    const residentNameError = document.getElementById('resident_name_error');
    let residentSearchDebounceTimer;

    residentSearchInput.addEventListener('input', function() {
        clearTimeout(residentSearchDebounceTimer);
        const query = this.value.trim();
        residentIdInput.value = ''; // Clear selected ID if input changes
        residentSearchInput.setCustomValidity('Please select a valid resident from the suggestions.');
        residentNameError.classList.remove('hidden');

        if (query.length === 0) {
            residentSuggestionsBox.classList.add('hidden');
            return;
        }

        residentSearchDebounceTimer = setTimeout(() => {
            fetch(`fetch_resident_for_consultation_suggestions.php?query=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    residentSuggestionsBox.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(item => {
                            const suggestionItem = document.createElement('div');
                            suggestionItem.classList.add('p-2', 'cursor-pointer', 'hover:bg-blue-100');
                                suggestionItem.textContent = item.name;
                                suggestionItem.dataset.id = item.id;
                                suggestionItem.dataset.full = item.name; // keep full display name available
                            suggestionItem.addEventListener('click', function() {
                                residentSearchInput.value = item.name;
                                residentIdInput.value = item.id;
                                // Fetch and autofill all fields for selected resident
                                fetch(`fetch_resident_for_consultation_suggestions.php?id=${item.id}`)
                                  .then(r => r.json())
                                  .then(res => {
                                    try {
                                      if (res.birthday && document.getElementById('birthday')) {
                                        let dob = res.birthday;
                                        // Convert yyyy-mm-dd to mm/dd/yyyy
                                        if (/^\d{4}-\d{2}-\d{2}$/.test(dob)) {
                                          const [y, m, d] = dob.split('-');
                                          dob = `${m}/${d}/${y}`;
                                        }
                                        document.getElementById('birthday').value = dob;
                                      }
                                      if (res.age && document.getElementById('age')) document.getElementById('age').value = res.age;
                                      if (res.sex && document.getElementById('sex')) document.getElementById('sex').value = res.sex;
                                      if (res.civil_status && document.getElementById('civil_status')) {
                                        let cs = res.civil_status;
                                        if (cs) cs = cs.charAt(0).toUpperCase() + cs.slice(1).toLowerCase();
                                        document.getElementById('civil_status').value = cs;
                                      }
                                      if (res.address && document.getElementById('address')) document.getElementById('address').value = res.address;
                                      const contactEl2 = document.getElementsByName('contact_no')[0];
                                      if (res.contact_number && contactEl2) contactEl2.value = res.contact_number;
                                      const emailEl2 = document.getElementsByName('email')[0];
                                      if (res.email && emailEl2) emailEl2.value = res.email;
                                      if (res.v_temp && document.getElementsByName('v_temp')[0]) document.getElementsByName('v_temp')[0].value = res.v_temp;
                                      if (res.v_wt && document.getElementsByName('v_wt')[0]) document.getElementsByName('v_wt')[0].value = res.v_wt;
                                      if (res.v_ht && document.getElementsByName('v_ht')[0]) document.getElementsByName('v_ht')[0].value = res.v_ht;
                                      if (res.v_bp && document.getElementsByName('v_bp')[0]) document.getElementsByName('v_bp')[0].value = res.v_bp;
                                      if (res.v_pr && document.getElementsByName('v_pr')[0]) document.getElementsByName('v_pr')[0].value = res.v_pr;
                                      if (res.v_rr && document.getElementsByName('v_rr')[0]) document.getElementsByName('v_rr')[0].value = res.v_rr;
                                      if (res.reason_for_consultation && document.getElementById('reason_for_consultation')) document.getElementById('reason_for_consultation').value = res.reason_for_consultation;
                                    } catch(err){ console.error('Error applying resident data:', err); }
                                  })
                                  .catch(err => console.error('Error fetching resident details:', err));
                                residentSuggestionsBox.classList.add('hidden');
                                residentSearchInput.setCustomValidity(''); // Valid selection
                                residentNameError.classList.add('hidden');
                            });
                            residentSuggestionsBox.appendChild(suggestionItem);
                        });
                        residentSuggestionsBox.classList.remove('hidden');
                    } else {
                        residentSuggestionsBox.classList.add('hidden');
                    }
                })
                .catch(error => {
                    console.error('Error fetching resident suggestions:', error);
                    residentSuggestionsBox.classList.add('hidden');
                });
        }, 300);
    });

    // Hide suggestions when clicking outside
    document.addEventListener('click', (e) => {
        if (!residentSearchInput.contains(e.target) && !residentSuggestionsBox.contains(e.target)) {
            residentSuggestionsBox.classList.add('hidden');
        }
    });

    // Basic email helpers to avoid JS errors if other validation functions are not present
    const emailInput = document.getElementById('email');
    const emailError = document.getElementById('email_error');
    let emailValidationDebounceTimer;

    async function validateEmail(email) {
      if (!email) {
        showEmailError('This field is required.');
        return false;
      }
      // simple pattern check
      const re = /^\S+@\S+\.\S+$/;
      if (!re.test(email)) {
        showEmailError('Please enter a valid email address.');
        return false;
      }
      // Verify email exists in residents table via AJAX
      try {
        const res = await fetch(`verify_email.php?email=${encodeURIComponent(email)}`);
        const data = await res.json();
        if (!data.exists) {
          showEmailError('Email not registered for any resident. Please use the resident\'s registered email.');
          return false;
        }
      } catch (e) {
        console.error('Error verifying email:', e);
        showEmailError('Unable to verify email right now.');
        return false;
      }
      hideEmailError();
      return true;
    }

    // Minimal follow-up date validator placeholder to avoid runtime errors
    const followUpDateInput = document.getElementsByName('follow_up_date')[0] || { value: '' };
    const followUpDateError = document.getElementById('follow_up_date_error') || null;
    async function validateFollowUpDate(val) {
      // If empty, it's valid (optional field). If present, accept for now.
      return true;
    }

    // Date Validation
    const dateOfConsultationInput = document.getElementById('date_of_consultation');
    const dateOfConsultationError = document.getElementById('date_of_consultation_error');
    let dateValidationDebounceTimer;

    async function validateDate(date) {
      if (!date) {
        showDateError('This field is required.');
        return false;
      }
      // Add your date validation logic here, or call the existing one if needed
      // For now, just return true for valid input
      hideDateError();
      return true;
    }

    function showEmailError(message) {
        emailError.textContent = message;
        emailError.classList.remove('hidden');
        emailInput.setCustomValidity('Invalid');
    }

    function hideEmailError() {
        emailError.classList.add('hidden');
        emailInput.setCustomValidity('');
    }

    emailInput.addEventListener('blur', function() {
        clearTimeout(emailValidationDebounceTimer);
        emailValidationDebounceTimer = setTimeout(() => {
            validateEmail(this.value);
        }, 300);
    });

    // Time Validation
    const consultationTimeInput = document.getElementById('consultation_time');
    const consultationTimeError = document.getElementById('consultation_time_error');
    
    function validateTime(time) {
        if (!time) {
            showTimeError('This field is required.');
            return false;
        }
        
        // Check if time is between 9:00 AM and 5:00 PM
        const selectedTime = time.split(':');
        const hours = parseInt(selectedTime[0], 10);
        const minutes = parseInt(selectedTime[1], 10);
        
        if (hours < 9 || (hours === 17 && minutes > 0) || hours > 17) {
          showTimeError('Time must be between 9:00 AM and 5:00 PM.');
          return false;
        }
        
        // Check if the time is in 15-minute intervals (00, 15, 30, 45)
        if (![0, 15, 30, 45].includes(minutes)) {
          showTimeError('Time must be in 15-minute intervals (e.g., 9:00 AM, 9:15 AM, 9:30 AM, 9:45 AM).');
          return false;
        }
        
        // If the selected date is today, ensure time is not in the past
        try {
          const dateInputEl = document.getElementById('date_of_consultation');
          if (dateInputEl) {
            const todayStr = new Date().toISOString().slice(0,10);
            if (dateInputEl.value === todayStr) {
              const now = new Date();
              let m = now.getMinutes();
              let add = (15 - (m % 15)) % 15;
              if (add === 0 && now.getSeconds() > 0) add = 15;
              if (add > 0) now.setMinutes(now.getMinutes() + add);
              const minH = now.getHours();
              const minM = now.getMinutes();
              if (hours < minH || (hours === minH && minutes < minM)) {
                showTimeError('Selected time is already past for today. Choose a future time.');
                return false;
              }
            }
          }
        } catch(e) {
          // ignore and continue
        }
        hideTimeError();
        return true;
    }
    
    function showTimeError(message) {
        consultationTimeError.textContent = message;
        consultationTimeError.classList.remove('hidden');
        consultationTimeInput.setCustomValidity('Invalid');
    }
    
    function hideTimeError() {
        consultationTimeError.classList.add('hidden');
        consultationTimeInput.setCustomValidity('');
    }
    
    // Keep the original change event for direct time input changes
    consultationTimeInput.addEventListener('change', function() {
        validateTime(this.value);
    });
    
    // The primary interaction will now be through our custom dropdown
    
    // Client-side validation for form submission
    const consultationForm = document.getElementById('addConsultationForm');
    if (consultationForm) {
        consultationForm.addEventListener('submit', async function(event) {
            let isValid = true;

            if (!residentIdInput.value) {
                isValid = false;
                residentSearchInput.setCustomValidity('Please select a valid resident from the suggestions.');
                residentNameError.classList.remove('hidden');
                residentSearchInput.focus();
            }
            
            // Validate consultation time
            const timeIsValid = validateTime(consultationTimeInput.value);
            if (!timeIsValid) {
                isValid = false;
            }

            // Validate email
            const emailIsValid = await validateEmail(emailInput.value);
            if (!emailIsValid) {
                isValid = false;
            }

            // Perform date validation on submit for Consultation Date
            const dateIsValid = await validateDate(dateOfConsultationInput.value, dateOfConsultationInput, dateOfConsultationError);
            if (!dateIsValid) {
                isValid = false;
            }

            // Perform date validation on submit for Follow-up Date (if provided)
            const followUpDateIsValid = await validateFollowUpDate(followUpDateInput.value, followUpDateInput, followUpDateError);
            if (!followUpDateIsValid) {
                isValid = false;
            }

            if (!isValid) {
                event.preventDefault();
                // Focus on the first invalid field
                const firstInvalidField = document.querySelector('#addConsultationForm :invalid');
                if (firstInvalidField) {
                    firstInvalidField.focus();
                }
            }
        });
    }
    
    // Remove JS time dropdown logic since time is now auto-set and readonly
    document.addEventListener('DOMContentLoaded', function() {
        if (consultationTimeInput.value === '') {
            consultationTimeInput.value = '09:00';
        }
        
        // Create a custom dropdown for time selection with 15-minute intervals
        const createTimeOptions = function() {
          // Remove existing selector if present
          const existing = document.getElementById('time-selector-helper');
          if (existing) existing.remove();

          const timeSelect = document.createElement('select');
          timeSelect.className = consultationTimeInput.className;
          timeSelect.id = 'time-selector-helper';

          // Determine minimum allowed time for today (round now up to next 15-min)
          const dateInputEl = document.getElementById('date_of_consultation');
          const todayStr = new Date().toISOString().slice(0,10);
          let minTimeValue = '09:00';
          if (dateInputEl && dateInputEl.value === todayStr) {
            const now = new Date();
            let m = now.getMinutes();
            let add = (15 - (m % 15)) % 15;
            if (add === 0 && now.getSeconds() > 0) add = 15;
            if (add > 0) now.setMinutes(now.getMinutes() + add);
            const hh = now.getHours().toString().padStart(2, '0');
            const mm = now.getMinutes().toString().padStart(2, '0');
            const candidate = `${hh}:${mm}`;
            if (candidate > minTimeValue) minTimeValue = candidate;
          }

          // Add time options from 9:00 AM to 5:00 PM in 15-minute intervals
          for (let h = 9; h <= 17; h++) {
            for (let m = 0; m < 60; m += 15) {
              // Skip 5:15 PM, 5:30 PM, 5:45 PM as it's past the max time
              if (h === 17 && m > 0) continue;
              const hour = h.toString().padStart(2, '0');
              const minute = m.toString().padStart(2, '0');
              const timeValue = `${hour}:${minute}`;
              // Skip times earlier than minTimeValue when scheduling today
              if (timeValue < minTimeValue) continue;
              // Format time for display (12-hour format)
              let displayHour = h;
              const period = h >= 12 ? 'PM' : 'AM';
              if (displayHour > 12) displayHour -= 12;
              const displayTime = `${displayHour}:${minute} ${period}`;
              const option = document.createElement('option');
              option.value = timeValue;
              option.textContent = displayTime;
              if (timeValue === consultationTimeInput.value) {
                option.selected = true;
              }
              timeSelect.appendChild(option);
            }
          }
          // Insert the select element before the time input
          consultationTimeInput.parentNode.insertBefore(timeSelect, consultationTimeInput);
          // Hide the original time input but keep it for form submission
          consultationTimeInput.style.display = 'none';
          // Update the hidden time input when the select changes
          timeSelect.addEventListener('change', function() {
            consultationTimeInput.value = this.value;
            validateTime(consultationTimeInput.value);
          });
          // If there are no options (no future slots today), show the original input
          if (timeSelect.options.length === 0) {
            timeSelect.remove();
            consultationTimeInput.style.display = '';
            consultationTimeInput.value = '';
          }
        };

        // Recreate time options when date changes so "today" logic applies
        const dateInputElement = document.getElementById('date_of_consultation');
        if (dateInputElement) {
          dateInputElement.addEventListener('change', function() {
            createTimeOptions();
          });
        }
        // Initialize the time dropdown
        createTimeOptions();
    });

    function calculateBmi() {
      const wtEl = document.getElementById('v_wt');
      const htEl = document.getElementById('v_ht');
      const bmiEl = document.getElementById('bmi');
      if (!wtEl || !htEl || !bmiEl) return;

      const weight = parseFloat(wtEl.value);
      const height = parseFloat(htEl.value);

      if (weight > 0 && height > 0) {
        const heightInMeters = height / 100; // convert cm to meters
        const bmi = weight / (heightInMeters * heightInMeters);
        bmiEl.value = bmi.toFixed(2);
      } else {
        bmiEl.value = '';
      }
    }

    // Recalculate BMI when weight or height inputs change
    const wtInput = document.getElementById('v_wt');
    const htInput = document.getElementById('v_ht');
    if (wtInput) wtInput.addEventListener('input', calculateBmi);
    if (htInput) htInput.addEventListener('input', calculateBmi);
  </script>
</body>
</html>


