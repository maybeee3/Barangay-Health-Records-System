<?php
session_start();
include 'config.php';

// Require an id (from GET) or redirect back
if (!isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: consultations.php');
    exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process update
    $id = intval($_POST['id']);
    $resident_id = intval($_POST['resident_id']);
    $email = $_POST['email'] ?? '';
    $date_of_consultation = $_POST['date_of_consultation'] ?? '';
    $consultation_time = $_POST['consultation_time'] ?? '';

    // vitals and other fields
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

    // Basic validation
    if (empty($date_of_consultation) || empty($consultation_time)) {
        $errors[] = 'Date and time are required.';
    }

    // Check slot conflict with other consultations
    if (empty($errors)) {
        $sql_check = "SELECT COUNT(*) FROM consultations WHERE date_of_consultation = ? AND consultation_time = ? AND id <> ?";
        $stmt_check = $conn->prepare($sql_check);
        if ($stmt_check) {
            $stmt_check->bind_param('ssi', $date_of_consultation, $consultation_time, $id);
            $stmt_check->execute();
            $stmt_check->bind_result($count_conflict);
            $stmt_check->fetch();
            $stmt_check->close();
            if ($count_conflict > 0) {
                $errors[] = 'Selected date/time is already booked by another consultation.';
            }
        } else {
            $errors[] = 'Database error: ' . $conn->error;
        }
    }

    if (empty($errors)) {
        $sql = "UPDATE consultations SET resident_id = ?, email = ?, date_of_consultation = ?, consultation_time = ?, blood_pressure = ?, heart_rate = ?, respiratory_rate = ?, temperature = ?, blood_sugar = ?, weight = ?, height = ?, bmi = ?, reason_for_consultation = ?, treatment_prescription = ?, follow_up_date = ?, consulting_doctor = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('isssssssssssssssi', $resident_id, $email, $date_of_consultation, $consultation_time, $blood_pressure, $heart_rate, $respiratory_rate, $temperature, $blood_sugar, $weight, $height, $bmi, $reason_for_consultation, $treatment_prescription, $follow_up_date, $consulting_doctor, $id);
            if ($stmt->execute()) {
                $stmt->close();
                $conn->close();
                header('Location: view-consultation.php?id=' . $id . '&updated=1');
                exit();
            } else {
                $errors[] = 'Update failed: ' . $stmt->error;
                $stmt->close();
            }
        } else {
            $errors[] = 'Prepare failed: ' . $conn->error;
        }
    }

}

// If GET, load consultation
$consultation = null;
$residents = [];
$id = isset($_GET['id']) ? intval($_GET['id']) : ($id ?? 0);
if ($id > 0) {
    $stmt = $conn->prepare("SELECT id, resident_id, email, date_of_consultation, consultation_time, blood_pressure, heart_rate, respiratory_rate, temperature, blood_sugar, weight, height, bmi, reason_for_consultation, treatment_prescription, follow_up_date, consulting_doctor FROM consultations WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $consultation = $result->fetch_assoc();
        }
        $stmt->close();
    }
}

// Fetch residents for dropdown
$sql_residents = "SELECT id, CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name, ''), ' ', COALESCE(name_extension, '')) AS full_name FROM residents ORDER BY last_name ASC, first_name ASC";
$result_residents = $conn->query($sql_residents);
if ($result_residents && $result_residents->num_rows > 0) {
    while ($row = $result_residents->fetch_assoc()) {
        $residents[] = $row;
    }
}

// Determine resident full name and initialize resident detail variables for initial display (if available)
$resident_full_name = '';
$resident_birthday = '';
$resident_age = '';
$resident_sex = '';
$resident_civil_status = '';
$resident_address = '';
$resident_contact = '';
$resident_email = '';
if (!empty($consultation) && !empty($consultation['resident_id'])) {
  foreach ($residents as $r) {
    if ($r['id'] == $consultation['resident_id']) {
      $resident_full_name = $r['full_name'];
      break;
    }
  }

  // Fetch resident details for prefill
  $resident_birthday = '';
  $resident_age = '';
  $resident_sex = '';
  $resident_civil_status = '';
  $resident_address = '';
  $resident_contact = '';
  $resident_email = '';
  $res_id = intval($consultation['resident_id']);
  $stmtR = $conn->prepare("SELECT first_name, middle_name, last_name, name_extension, birthday, contact_number, email, sex, civil_status, address FROM residents WHERE id = ? LIMIT 1");
  if ($stmtR) {
      $stmtR->bind_param('i', $res_id);
      $stmtR->execute();
      $resDet = $stmtR->get_result();
      if ($resDet && $resDet->num_rows > 0) {
          $rrow = $resDet->fetch_assoc();
          $resident_birthday = $rrow['birthday'] ?? '';
          if ($resident_birthday && preg_match('/^\d{4}-\d{2}-\d{2}$/', $resident_birthday)) {
              $dparts = explode('-', $resident_birthday);
              $resident_birthday = $dparts[1] . '/' . $dparts[2] . '/' . $dparts[0];
              try {
                  $dob = new DateTime($rrow['birthday']);
                  $today = new DateTime();
                  $ageInterval = $today->diff($dob);
                  $resident_age = $ageInterval->y;
              } catch (Exception $e) {
                  $resident_age = '';
              }
          }
          $resident_contact = $rrow['contact_number'] ?? '';
          $resident_email = $rrow['email'] ?? '';
          $resident_sex = $rrow['sex'] ?? '';
          $resident_civil_status = $rrow['civil_status'] ?? '';
          $resident_address = $rrow['address'] ?? '';
      }
      $stmtR->close();
  }
}

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Edit Consultation</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .paper-input{border:0;border-bottom:1px solid #000;background:transparent;padding:4px}
  </style>
</head>
<body class="p-6 bg-gray-50">
  <div class="max-w-3xl mx-auto bg-white p-6 rounded shadow">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold">Edit Consultation</h2>
      <a href="view-consultation.php?id=<?php echo htmlspecialchars($id); ?>" class="text-sm text-blue-600">← Back</a>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700">
        <ul class="list-disc pl-5">
          <?php foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if (!$consultation && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
      <div class="p-4 bg-yellow-50 border">Consultation not found.</div>
    <?php else: ?>

    <form method="POST" class="space-y-5" id="addConsultationForm">
          <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>" />

            <!-- PATIENT RECORD FORM (from add-records.php, without Treatment and Assessment) -->
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
              <div><strong class="small">DATE:</strong> <input id="date_of_consultation" name="date_of_consultation" type="date" class="paper-input" style="width:140px;" value="<?php echo htmlspecialchars($consultation['date_of_consultation'] ?? ''); ?>"/></div>
              <?php
              // For edit, we keep the saved time value
              $auto_consult_time = htmlspecialchars($consultation['consultation_time'] ?? '');
              ?>
              <div><strong class="small">TIME:</strong></div>
              <div><input id="consultation_time" name="consultation_time" type="time" class="paper-input" style="width:140px; background:#f3f4f6;" value="<?php echo $auto_consult_time; ?>" required/></div>
              <div><strong class="small">CONTACT NO.:</strong> <input name="contact_no" type="text" class="paper-input" style="width:140px;" value="<?php echo htmlspecialchars($consultation['contact_number'] ?? ($consultation['contact_no'] ?? $resident_contact)); ?>"/></div>
              <div><strong class="small">EMAIL:</strong> <input id="email" name="email" type="email" class="paper-input" style="width:140px;" value="<?php echo htmlspecialchars($consultation['email'] ?? ($consultation['resident_email'] ?? $resident_email)); ?>"/></div>
            </td>
          </tr>
        </table>

        <div style="height:8px;"></div>

        <table class="paper-table" style="font-size:12px;">
          <tr>
            <td style="width:14%;"><strong class="small">Name of Patient</strong></td>
            <td style="width:86%;">
              <input type="text" id="resident_name_search" class="paper-input w-full" placeholder="Search resident name..." autocomplete="off" value="<?php echo htmlspecialchars($resident_full_name); ?>">
              <input type="hidden" id="resident_id" name="resident_id" value="<?php echo htmlspecialchars($consultation['resident_id'] ?? ''); ?>">
              <div id="resident_suggestions" class="absolute bg-white border rounded shadow z-10 w-full hidden"></div>
              <div id="resident_name_error" class="text-xs text-red-600 mt-1 hidden">Please select a valid resident from the suggestions.</div>
            </td>
          </tr>
          <tr style="height:8px;"></tr>
      <tr>
        <tr>
  <td style="width:15%;"><strong class="small">Birthday</strong></td>
  <td style="width:18%;">
            <input name="birthday" id="birthday" type="text" class="paper-input" style="width:100%; min-width:40px;" placeholder="mm/dd/yyyy" value="<?php echo htmlspecialchars($resident_birthday); ?>"/>
  </td>
  <td style="width:7%;"><strong class="small">Age</strong></td>
  <td style="width:7%;">
    <input name="age" id="age" type="text" class="paper-input" style="width:100%; min-width:40px;" value="<?php echo htmlspecialchars($resident_age); ?>"/>
  </td>
  <td style="width:7%;"><strong class="small">Sex</strong></td>
  <td style="width:7%;">
    <input name="sex" id="sex" type="text" class="paper-input" style="width:100%; min-width:40px;" value="<?php echo htmlspecialchars($resident_sex); ?>"/>
  </td>
  <td style="width:12%;"><strong class="small">Civil Status</strong></td>
  <td style="width:17%;">
    <input name="civil_status" id="civil_status" type="text" class="paper-input" style="width:100%; min-width:70px;" value="<?php echo htmlspecialchars($resident_civil_status); ?>"/>
  </td>
</tr>
          <tr style="height:8px;"></tr>
          <tr>
            <td><strong class="small">Address</strong></td>
            <td colspan="7"><input name="address" id="address" type="text" class="paper-input" style="width:100%; height:20px;" value="<?php echo htmlspecialchars($resident_address); ?>"/></td>
          </tr>
        </table>

        <div style="height:10px;"></div>

        <div class="boxed">
          <div class="section-title">RECORD DETAILS</div>
          <div class="vitals-vertical" style="margin-bottom:12px;">
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">DATE</strong></div>
              <div><input name="v_date" type="date" class="paper-input" style="width:220px;" id="v_date" value="<?php echo htmlspecialchars($consultation['v_date'] ?? $consultation['date_of_consultation'] ?? ''); ?>" /></div>
            </div>
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">TIME</strong></div>
              <div><input name="v_time" type="time" class="paper-input" style="width:120px;" id="v_time" value="<?php echo htmlspecialchars($consultation['v_time'] ?? $consultation['consultation_time'] ?? ''); ?>" /></div>
            </div>
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">TEMP</strong></div>
              <div><input name="v_temp" type="text" class="paper-input" style="width:120px;" value="<?php echo htmlspecialchars($consultation['temperature'] ?? ''); ?>"/></div>
            </div>
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">WT</strong></div>
              <div><input id="v_wt" name="v_wt" type="text" class="paper-input" style="width:120px;" value="<?php echo htmlspecialchars($consultation['weight'] ?? ''); ?>"/></div>
            </div>
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">HT</strong></div>
              <div><input id="v_ht" name="v_ht" type="text" class="paper-input" style="width:120px;" value="<?php echo htmlspecialchars($consultation['height'] ?? ''); ?>"/></div>
            </div>
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">BP</strong></div>
              <div><input name="v_bp" type="text" class="paper-input" style="width:120px;" value="<?php echo htmlspecialchars($consultation['blood_pressure'] ?? ''); ?>"/></div>
            </div>
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">RR</strong></div>
              <div><input name="v_rr" type="text" class="paper-input" style="width:120px;" value="<?php echo htmlspecialchars($consultation['respiratory_rate'] ?? ''); ?>"/></div>
            </div>
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
              <div style="width:120px;"><strong class="small">PR</strong></div>
              <div><input name="v_pr" type="text" class="paper-input" style="width:120px;" value="<?php echo htmlspecialchars($consultation['heart_rate'] ?? ''); ?>"/></div>
            </div>
            
          </div>

          <!-- Reason for Consultation / Complaint -->
          <div class="mt-4">
            <label for="reason_for_consultation" style="font-weight:700; display:block; margin-bottom:6px;">Reason for Consultation / Complaint</label>
            <textarea id="reason_for_consultation" name="reason_for_consultation" class="paper-textarea" style="width:100%; height:120px; background-image: linear-gradient(to bottom, rgba(0,0,0,0.06) 1px, transparent 1px); background-size: 100% 18px;"><?php echo htmlspecialchars($consultation['reason_for_consultation'] ?? ''); ?></textarea>
          </div>

          <!-- Diagnosis / Treatment -->
          <div class="mt-4">
            <div class="section-title">Diagnosis / Treatment / Prescription</div>
            <textarea name="treatment_prescription" class="paper-input w-full" rows="3" placeholder="Enter diagnosis, treatment plan, or prescription..."><?php echo htmlspecialchars($consultation['treatment_prescription'] ?? ''); ?></textarea>
          </div>

          <!-- Consulting Doctor / Staff -->
          <div class="mt-4">
            <div class="section-title">Consulting Doctor / Staff</div>
            <input type="text" name="consulting_doctor" class="paper-input w-full" placeholder="Enter name of consulting doctor or staff..." value="<?php echo htmlspecialchars($consultation['consulting_doctor'] ?? 'Lhee Za Milanez'); ?>">
          </div>
        </div>
        <!-- Hidden fields so POST contains optional fields even if not shown -->
        <input type="hidden" id="bmi" name="bmi" value="<?php echo htmlspecialchars($consultation['bmi'] ?? ''); ?>" />
        <input type="hidden" id="blood_sugar" name="blood_sugar" value="<?php echo htmlspecialchars($consultation['blood_sugar'] ?? ''); ?>" />
        <input type="hidden" id="follow_up_date" name="follow_up_date" value="<?php echo htmlspecialchars($consultation['follow_up_date'] ?? ''); ?>" />
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

    <?php endif; ?>
  </div>

  <script>
    // Resident Search Autocomplete (copied from add-consultation)
    document.addEventListener('DOMContentLoaded', function() {
      const residentSearchInput = document.getElementById('resident_name_search');
      const residentIdInput = document.getElementById('resident_id');
      const residentSuggestionsBox = document.getElementById('resident_suggestions');
      const residentNameError = document.getElementById('resident_name_error');
      let residentSearchDebounceTimer;

      if (residentSearchInput) {
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
      }

      // Minimal client-side helpers copied from add-consultation
      const emailInput = document.getElementById('email');
      const emailError = document.getElementById('email_error');
      let emailValidationDebounceTimer;

      async function validateEmail(email) {
        if (!email) {
          showEmailError('This field is required.');
          return false;
        }
        const re = /^\S+@\S+\.\S+$/;
        if (!re.test(email)) {
          showEmailError('Please enter a valid email address.');
          return false;
        }
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

      function showEmailError(message) {
          if (!emailError || !emailInput) return;
          emailError.textContent = message;
          emailError.classList.remove('hidden');
          emailInput.setCustomValidity('Invalid');
      }

      function hideEmailError() {
          if (!emailError || !emailInput) return;
          emailError.classList.add('hidden');
          emailInput.setCustomValidity('');
      }

      if (emailInput) {
        emailInput.addEventListener('blur', function() {
            clearTimeout(emailValidationDebounceTimer);
            emailValidationDebounceTimer = setTimeout(() => {
                validateEmail(this.value);
            }, 300);
        });
      }

      // Time validation and custom time dropdown
      const consultationTimeInput = document.getElementById('consultation_time');
      const consultationTimeError = document.getElementById('consultation_time_error');

      function validateTime(time) {
          if (!time) {
              return false;
          }
          const selectedTime = time.split(':');
          const hours = parseInt(selectedTime[0], 10);
          const minutes = parseInt(selectedTime[1], 10);
          if (hours < 9 || (hours === 17 && minutes > 0) || hours > 17) {
            return false;
          }
          if (![0, 15, 30, 45].includes(minutes)) {
            return false;
          }
          return true;
      }

      // Create time dropdown helper
      const createTimeOptions = function() {
          const existing = document.getElementById('time-selector-helper');
          if (existing) existing.remove();

          if (!consultationTimeInput) return;

          const timeSelect = document.createElement('select');
          timeSelect.className = consultationTimeInput.className;
          timeSelect.id = 'time-selector-helper';

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

          for (let h = 9; h <= 17; h++) {
            for (let m = 0; m < 60; m += 15) {
              if (h === 17 && m > 0) continue;
              const hour = h.toString().padStart(2, '0');
              const minute = m.toString().padStart(2, '0');
              const timeValue = `${hour}:${minute}`;
              if (timeValue < minTimeValue) continue;
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
          consultationTimeInput.parentNode.insertBefore(timeSelect, consultationTimeInput);
          consultationTimeInput.style.display = 'none';
          timeSelect.addEventListener('change', function() {
            consultationTimeInput.value = this.value;
          });
          if (timeSelect.options.length === 0) {
            timeSelect.remove();
            consultationTimeInput.style.display = '';
            consultationTimeInput.value = '';
          }
      };

      const dateInputElement = document.getElementById('date_of_consultation');
      if (dateInputElement) dateInputElement.addEventListener('change', function() { createTimeOptions(); });
      createTimeOptions();

      // BMI calculator
      function calculateBmi() {
        const wtEl = document.getElementById('v_wt');
        const htEl = document.getElementById('v_ht');
        const bmiEl = document.getElementById('bmi');
        if (!wtEl || !htEl || !bmiEl) return;

        const weight = parseFloat(wtEl.value);
        const height = parseFloat(htEl.value);

        if (weight > 0 && height > 0) {
          const heightInMeters = height / 100;
          const bmi = weight / (heightInMeters * heightInMeters);
          bmiEl.value = bmi.toFixed(2);
        } else {
          bmiEl.value = '';
        }
      }

      const wtInput = document.getElementById('v_wt');
      const htInput = document.getElementById('v_ht');
      if (wtInput) wtInput.addEventListener('input', calculateBmi);
      if (htInput) htInput.addEventListener('input', calculateBmi);
    });
  </script>
</body>
</html>
