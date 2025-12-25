<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include 'config.php'; // Include database connection

// Load residents.json for client-side email validation/autofill
$residentsFile = __DIR__ . '/residents.json';
$residents = [];
if (file_exists($residentsFile)) {
  $residents = json_decode(file_get_contents($residentsFile), true) ?: [];
}
// If no residents.json present or it contained no entries, try fetching emails from DB
if (empty($residents) && isset($conn)) {
  $residents = [];
  $q = $conn->query("SELECT email FROM residents WHERE email IS NOT NULL AND email <> ''");
  if ($q) {
    while ($r = $q->fetch_assoc()) {
      $residents[] = ['email' => $r['email']];
    }
  }
}

$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize form data
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $name_extension = trim($_POST['name_extension'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $sex = trim($_POST['sex'] ?? '');
    $civil_status = trim($_POST['civil_status'] ?? '');
    $contact_no = trim($_POST['contact_no'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $city_municipality = trim($_POST['city_municipality'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $father_name = trim($_POST['father_name'] ?? '');
    $mother_name = trim($_POST['mother_name'] ?? '');
    $guardian_name = trim($_POST['guardian_name'] ?? '');
    $guardian_relationship = trim($_POST['guardian_relationship'] ?? '');
    // If "Other" is selected, use the custom input value
    if ($guardian_relationship === 'Other' && !empty($_POST['guardian_relationship_other'])) {
        $guardian_relationship = trim($_POST['guardian_relationship_other']);
    }
    $guardian_contact_no = trim($_POST['guardian_contact_no'] ?? '');
    $postal_code = isset($_POST['postal_code']) ? (int)$_POST['postal_code'] : null;
    $years_of_residency = isset($_POST['years_of_residency']) ? (int)$_POST['years_of_residency'] : null;
    $registration_status = trim($_POST['registration_status'] ?? '');
    $existing_conditions = trim($_POST['existing_conditions'] ?? '');
    $allergies = trim($_POST['allergies'] ?? '');
    $maintenance_medicines = trim($_POST['maintenance_medicines'] ?? '');
    $blood_type = trim($_POST['blood_type'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');
    $educational_attainment = trim($_POST['educational_attainment'] ?? '');

    // Email must be @gmail.com
    if (!preg_match('/@gmail\.com$/', $email)) {
        $message = 'Email must be a @gmail.com address.';
        $message_type = 'error';
    }

    // Required fields check
    if (empty($last_name) || empty($first_name) || empty($date_of_birth) || empty($sex) || empty($province) || empty($city_municipality) || empty($barangay) || empty($contact_no) || empty($email)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    }

    if (empty($message)) {
        $sql = "INSERT INTO residents (
            first_name, middle_name, last_name, name_extension, date_of_birth, sex, civil_status,
            contact_no, email, address, province, city_municipality, barangay, father_name, mother_name,
            guardian_name, guardian_relationship, guardian_contact_no, postal_code, years_of_residency,
            registration_status, existing_conditions, allergies, maintenance_medicines, blood_type, occupation, educational_attainment
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            $message = 'Database prepare error: ' . htmlspecialchars($conn->error);
            $message_type = 'error';
        } else {
            // Use "s" for strings (contact_no as string to preserve leading 0), "i" for integers
            $stmt->bind_param(
                "ssssssssssssssssssissssssss",
                $first_name,
                $middle_name,
                $last_name,
                $name_extension,
                $date_of_birth,
                $sex,
                $civil_status,
                $contact_no,          // string
                $email,
                $address,
                $province,
                $city_municipality,
                $barangay,
                $father_name,
                $mother_name,
                $guardian_name,
                $guardian_relationship,
                $guardian_contact_no, // string
                $postal_code,         // int
                $years_of_residency,  // int
                $registration_status,
                $existing_conditions,
                $allergies,
                $maintenance_medicines,
                $blood_type,
                $occupation,
                $educational_attainment
            );

            if ($stmt->execute()) {
              // Attempt to send a welcome email if email is present and valid
              $newResidentId = $conn->insert_id;
              $to = trim($email);
              $full_name = trim($first_name . ' ' . $middle_name . ' ' . $last_name . ' ' . $name_extension);
              if ($full_name === '') $full_name = 'Resident';
              $subject = "Welcome to the Barangay Health Unit";
              $body = "Dear {$first_name},\n\n" .
                  "Welcome to the Barangay Health Unit. Your registration has been received and recorded in our system.\n\n" .
                  "If you have any questions, please contact us.\n\n" .
                  "Regards,\nBarangay Health Unit";

              // Send mail using PHPMailer if available
              if (!empty($to) && filter_var($to, FILTER_VALIDATE_EMAIL)) {
                if (file_exists(__DIR__ . '/phpmailer/src/PHPMailer.php')) {
                  require __DIR__ . '/phpmailer/src/Exception.php';
                  require __DIR__ . '/phpmailer/src/PHPMailer.php';
                  require __DIR__ . '/phpmailer/src/SMTP.php';
                  $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                  try {
                    $mail->isSMTP();
                    $mail->SMTPAuth = true;
                    $mail->Host = SMTP_HOST;
                    $mail->Username = SMTP_USERNAME;
                    $mail->Password = SMTP_PASSWORD;
                    $mail->SMTPSecure = SMTP_SECURE;
                    $mail->Port = SMTP_PORT;

                    $mail->setFrom(SMTP_USERNAME, 'Barangay Health Unit');
                    $mail->addAddress($to, $full_name);
                    $mail->Subject = $subject;
                    $mail->Body = $body;
                    $mail->send();
                  } catch (\PHPMailer\PHPMailer\Exception $e) {
                    // Try SSL fallback
                    try {
                      $mail->SMTPSecure = 'ssl';
                      $mail->Port = 465;
                      $mail->send();
                    } catch (\PHPMailer\PHPMailer\Exception $e2) {
                      // ignore mail failures for now; continue with redirect
                    }
                  }
                } else {
                  // Fallback to PHP mail()
                  $headers = "From: Barangay Health Unit <" . SMTP_USERNAME . ">\r\n";
                  $headers .= "Reply-To: " . SMTP_USERNAME . "\r\n";
                  @mail($to, $subject, $body, $headers);
                }
              }
              // Log activity: new resident added
              try {
                $usernameLog = $_SESSION['username'] ?? 'System';
                $full_name_log = htmlspecialchars(trim($first_name . ' ' . $middle_name . ' ' . $last_name . ' ' . $name_extension));
                if ($full_name_log === '') $full_name_log = 'Resident';
                if (function_exists('log_activity')) {
                  log_activity($conn, null, $usernameLog, 'resident_added', "Added resident: {$full_name_log} (ID {$newResidentId})", $newResidentId);
                }
              } catch (Exception $e) {}

              echo "<script>alert('New resident added successfully!'); window.location.href='residents.php';</script>";
              $stmt->close();
              $conn->close();
              exit;
            } else {
                $message = 'Execute error: ' . htmlspecialchars($stmt->error);
                $message_type = 'error';
            }

            $stmt->close();
        }
    }
    $conn->close();
}
?>


<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Add Resident - Barangay Health Monitoring System</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>
  <style>
body {
font-family: Arial, sans-serif;
padding: 20px;
background: #dfdfdfff;
}
.section {
background: #fff;
padding: 20px;
margin-bottom: 20px;
border-radius: 8px;
box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
.section h2 {
margin-top: 0;

}
.row {
  display: flex;
  gap: 20px;
  margin-bottom: 15px;
  padding: 0 px; /* ← ADD THIS */
}

.row label {
  flex: 1;
  display: flex;
  flex-direction: column;
  padding: 0 6px;
}


.label-inline { display: inline-flex; align-items: center; gap: 6px; }

  input, select, textarea {
  padding-left: 5px !important;   /* dagdag malaking space sa loob */
  padding-right: 15px;
  padding-top: 10px;
  padding-bottom: 10px;
  
  border: 1px solid #ccc;
  border-radius: 5px;
  box-sizing: border-box;
  margin: 5px 6px;
}


input:focus, select:focus, textarea:focus {
  outline: none;
  box-shadow: 0 0 0 3px rgba(59,130,246,0.08);
}
.row label.wide {
  flex: 2 0 130px; /* allow email (or other wide fields) to take more space */
  min-width: 130px;
}
.small-field {
  flex: 0 0 200px;   /* adjust mo 160–200px depende gusto mo */
}

.small-field input,
.small-field select {
  width: 100%;
}
.status-field {
  flex: 6 3 220px;  /* adjust mo 200–260px depende sa gusto */
}

.status-field select {
  width: 100%;
}
.civil-status-field {
  flex: 0 0 160px;   /* pwede mo adjust 150–200px depende sayo */
}

.civil-status-field select {
  width: 100% !important;   /* siguradong lalapad */
  min-width: 100px;         /* fallback para di lumiit */
}



</style>
<body class="bg-gray-50 min-h-screen text-gray-700">
  <div class="max-w-4xl mx-auto p-6">
    <header class="mb-6">
      <h1 class="text-2xl font-semibold">Add New Resident</h1>
      <p class="text-sm text-gray-500">Use this form to add a new resident. Required fields are marked.</p>
    </header>

    <?php if (!empty($message)): ?>
          <div class="<?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> p-3 rounded-lg mb-4 text-sm">
              <?php echo htmlspecialchars($message); ?>
          </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow">
      <form method="POST" id="addResidentForm" class="space-y-6">
        <h2 class="text-xl font-semibold text-center mb-4">RESIDENTS INFORMATION FORM</h2>

       

        <div class="section">
<h2><strong> Personal Information</strong></h2>
<div class="row">
<label class="compact small-field">
  <div class="label-inline">First Name<span class="text-red-600">*</span></div>
  <input type="text" name="first_name" required>
</label>

<label class="compact small-field">
  <div class="label-inline">Middle Name</div>
  <input type="text" name="middle_name">
</label>

<label class="compact small-field">
  <div class="label-inline">Last Name<span class="text-red-600">*</span></div>
  <input type="text" name="last_name" required>
</label><label class="compact suffix"><div class="label-inline">Suffix</div>
  <select name="name_extension" style="width:180px; max-width:100%;">
    <option value="" <?php echo (empty($_POST['name_extension']) ? 'selected' : ''); ?>>None</option>
    <option value="Jr." <?php echo (($_POST['name_extension'] ?? '') === 'Jr.' ? 'selected' : ''); ?>>Jr.</option>
    <option value="Sr." <?php echo (($_POST['name_extension'] ?? '') === 'Sr.' ? 'selected' : ''); ?>>Sr.</option>
    <option value="II" <?php echo (($_POST['name_extension'] ?? '') === 'II' ? 'selected' : ''); ?>>II</option>
    <option value="III" <?php echo (($_POST['name_extension'] ?? '') === 'III' ? 'selected' : ''); ?>>III</option>
    <option value="IV" <?php echo (($_POST['name_extension'] ?? '') === 'IV' ? 'selected' : ''); ?>>IV</option>
    <option value="V" <?php echo (($_POST['name_extension'] ?? '') === 'V' ? 'selected' : ''); ?>>V</option>
  </select>
</label>
</div>
<div class="row">
<label><div class="label-inline">Date of Birth<span class="text-red-600">*</span></div> <input type="date" name="date_of_birth" required value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>"></label>
<label class="compact small-field">
  <div class="label-inline">Sex<span class="text-red-600">*</span></div>
<select name="sex" required>
<option value=""></option>
<option value="Female" <?php echo (($_POST['sex'] ?? '') === 'Female') ? 'selected' : ''; ?>>F</option>
<option value="Male" <?php echo (($_POST['sex'] ?? '') === 'Male') ? 'selected' : ''; ?>>M</option>
</select>
</label>
<label class="civil-status-field">Civil Status 
<select name="civil_status">

<option value="Single" <?php echo (($_POST['civil_status'] ?? '') === 'Single') ? 'selected' : ''; ?>>Single</option>
<option value="Married" <?php echo (($_POST['civil_status'] ?? '') === 'Married') ? 'selected' : ''; ?>>Married</option>
<option value="Widowed" <?php echo (($_POST['civil_status'] ?? '') === 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
<option value="Separated" <?php echo (($_POST['civil_status'] ?? '') === 'Separated') ? 'selected' : ''; ?>>Separated</option>
</select>
</label>
<label><div class="label-inline">Contact No.<span class="text-red-600">*</span></div> <input type="text" name="contact_no" maxlength="11" inputmode="numeric" pattern="[0-9]*" style="width: 120px; max-width: 100%;" id="contact_no" required value="<?php echo htmlspecialchars($_POST['contact_no'] ?? ''); ?>"></label>
<label class="wide"><div class="label-inline">Email<span class="text-red-600">*</span></div>
  <input id="addResidentEmail" type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
  <div id="addResidentEmailValidation" class="text-sm mt-1 hidden"></div>
</label>
</div>
<label>Address (House/Block/Lot/Street/Barangay)
<input type="text" name="address" style="width: 100%;" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
</label>
<div class="row">
<label><div class="label-inline">Province<span class="text-red-600">*</span></div> <input type="text" name="province" value="<?php echo htmlspecialchars($_POST['province'] ?? 'Laguna'); ?>" required></label>
<label><div class="label-inline">City / Municipality<span class="text-red-600">*</span></div> <input type="text" name="city_municipality" value="<?php echo htmlspecialchars($_POST['city_municipality'] ?? 'Pagsanjan'); ?>" required></label> 
<label><div class="label-inline">Barangay<span class="text-red-600">*</span></div>
  <select name="barangay" required>
    <option value="" <?php echo (empty($_POST['barangay']) ? 'selected' : ''); ?>>Select Barangay</option>
    <option value="Crisostomo Ext" <?php echo (($_POST['barangay'] ?? '') === 'Crisostomo Ext') ? 'selected' : ''; ?>>Crisostomo Ext</option>
    <option value="General Taino St" <?php echo (($_POST['barangay'] ?? '') === 'General Taino St') ? 'selected' : ''; ?>>General Taino St</option>
    <option value="Maligaya" <?php echo (($_POST['barangay'] ?? '') === 'Maligaya') ? 'selected' : ''; ?>>Maligaya</option>
    <option value="National High way" <?php echo (($_POST['barangay'] ?? '') === 'National High way') ? 'selected' : ''; ?>>National High way</option>
  </select>
</label>
</div>
</div>

<!-- FAMILY INFORMATION -->
<div class="section">
<h2> <strong>Family Information</strong></h2>
<div class="row">
<label>Name of Father <input type="text" name="father_name"></label>
<label>Name of Mother <input type="text" name="mother_name"></label>
</div>
<h3>Guardian / Emergency Contact</h3>
<div class="row">
<label>Guardian Name <input type="text" name="guardian_name"></label>
<label>Relationship 
  <select name="guardian_relationship" id="guardianRelationship" onchange="toggleOtherRelationship()">
    <option value="">Select</option>
    <option value="Father">Father</option>
    <option value="Mother">Mother</option>
    <option value="Husband">Husband</option>
    <option value="Wife">Wife</option>
    <option value="Son">Son</option>
    <option value="Daughter">Daughter</option>
    <option value="Sibling">Sibling</option>
    <option value="Other">Other</option>
  </select>
</label>
<label id="otherRelationshipLabel" style="display:none;">Specify Relationship 
  <input type="text" name="guardian_relationship_other" id="guardianRelationshipOther">
</label>
<label>Contact No. <input type="text" name="guardian_contact_no" maxlength="11" inputmode="numeric" pattern="[0-9]*" id="guardian_contact_no"></label>
</div>

<script>
function toggleOtherRelationship() {
  const select = document.getElementById('guardianRelationship');
  const otherLabel = document.getElementById('otherRelationshipLabel');
  const otherInput = document.getElementById('guardianRelationshipOther');
  
  if (select.value === 'Other') {
    otherLabel.style.display = 'flex';
    otherInput.required = true;
  } else {
    otherLabel.style.display = 'none';
    otherInput.required = false;
    otherInput.value = '';
  }
}
</script>
</div>

<!-- RESIDENCY DETAILS -->
<div class="section">
<h2><strong>Residency Details</strong></h2>
<div class="row">
<label>Postal Code <input type="text" name="postal_code" value="4008"></label>
<label>Years of Residency <input type="number" name="years_of_residency" min="0"></label>
</div>
<label>Registration Status:</label>
<div>
<label><input type="radio" name="registration_status" value="Registered"> Registered</label>
<label><input type="radio" name="registration_status" value="Not Registered"> Not Registered</label>
</div>
</div>

<!-- HEALTH INFORMATION -->
<div class="section">
  <h2><strong>Health Information</strong></h2>
  <label>Existing Medical Conditions <textarea name="existing_conditions" class="paper-textarea w-full" rows="3"><?php echo isset($_POST['existing_conditions'])?htmlspecialchars($_POST['existing_conditions']):''; ?></textarea></label>
  <label>Allergies <textarea name="allergies" class="paper-textarea w-full" rows="3"><?php echo isset($_POST['allergies'])?htmlspecialchars($_POST['allergies']):''; ?></textarea></label>
  <label>Maintenance Medicines <textarea name="maintenance_medicines" class="paper-textarea w-full" rows="3"><?php echo isset($_POST['maintenance_medicines'])?htmlspecialchars($_POST['maintenance_medicines']):''; ?></textarea></label>
  <label>Blood Type
    <select name="blood_type" id="blood_type" class="paper-input">
      <?php
        $bt = isset($_POST['blood_type']) ? trim($_POST['blood_type']) : '';
        $options = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
        foreach ($options as $opt) {
          $sel = ($bt !== '' && strcasecmp($bt, $opt) === 0) ? ' selected' : '';
          echo '<option value="'.htmlspecialchars($opt).'"'.$sel.'>'.htmlspecialchars($opt)."</option>";
        }
      ?>
    </select>
  </label>
</div>

<!-- ADDITIONAL INFORMATION -->
<div class="section">
<h2><strong>Additional Information</strong></h2>
<label>Occupation <input type="text" name="occupation"></label>

<h4>
<label>Educational Attainment:</label>
</h4>
<div>
<label><input type="radio" name="educational_attainment" value="Elementary"> Elementary</label>
<label><input type="radio" name="educational_attainment" value="High School"> High School</label>
<label><input type="radio" name="educational_attainment" value="Senior High"> Senior High</label>
<label><input type="radio" name="educational_attainment" value="College Level"> College Level</label>
<label><input type="radio" name="educational_attainment" value="College Graduate"> College Graduate</label>
<label><input type="radio" name="educational_attainment" value="Vocational"> Vocational</label>
<label><input type="radio" name="educational_attainment" value="Post-Graduate"> Post-Graduate</label>
</div>
</div>

        

        <div class="pt-4 text-right">
          <a href="residents.php" class="inline-block mr-2 text-sm text-blue bg-blue-100 px-4 py-2 rounded border border-blue-200 hover:bg-blue-200">Cancel</a>
          <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Only allow numeric input and max 11 digits for contact numbers
    document.addEventListener('DOMContentLoaded', function() {
      const contact = document.getElementById('contact_no');
      const emerg = document.getElementById('guardian_contact_no');
      [contact, emerg].forEach(function(inp){
        if (!inp) return;
        inp.addEventListener('input', function(){
          this.value = this.value.replace(/[^0-9]/g, '').slice(0,11);
        });
        inp.addEventListener('keypress', function(e){
          if (e.key.length === 1 && !/[0-9]/.test(e.key)) {
            e.preventDefault();
          }
        });
      });
    });
    // Real-time Gmail validation
    (function addResidentsEmailValidation(){
      const emailEl = document.getElementById('addResidentEmail');
      const validationEl = document.getElementById('addResidentEmailValidation');
      const form = document.getElementById('addResidentForm');
      if (!emailEl || !validationEl || !form) return;

      let validationTimeout = null;
      let isEmailValid = false;

      async function validateEmail() {
        const email = emailEl.value.trim();
        
        if (!email) {
          validationEl.classList.add('hidden');
          isEmailValid = false;
          return;
        }

        // Check if it's a Gmail address first
        if (!email.match(/@gmail\.com$/i)) {
          validationEl.className = 'text-sm text-red-600 mt-1';
          validationEl.textContent = '✗ Only @gmail.com addresses are allowed';
          validationEl.classList.remove('hidden');
          isEmailValid = false;
          return;
        }

        // Show loading state
        validationEl.className = 'text-sm text-gray-500 mt-1';
        validationEl.textContent = '⏳ Verifying email...';
        validationEl.classList.remove('hidden');

        try {
          const res = await fetch('verify_email_api.php?email=' + encodeURIComponent(email));
          const data = await res.json();
          
          if (data.valid) {
            validationEl.className = 'text-sm text-green-600 mt-1';
            validationEl.textContent = '✓ Valid Gmail address';
            isEmailValid = true;
          } else {
            validationEl.className = 'text-sm text-red-600 mt-1';
            validationEl.textContent = '✗ ' + (data.message || 'Could not verify Gmail account');
            isEmailValid = false;
          }
          validationEl.classList.remove('hidden');
        } catch (e) {
          console.error('Email validation failed', e);
          validationEl.className = 'text-sm text-orange-600 mt-1';
          validationEl.textContent = '⚠ Unable to verify email - please check manually';
          validationEl.classList.remove('hidden');
          isEmailValid = false;
        }
      }

      emailEl.addEventListener('input', function() {
        clearTimeout(validationTimeout);
        validationTimeout = setTimeout(validateEmail, 600);
      });

      emailEl.addEventListener('blur', validateEmail);

      form.addEventListener('submit', function(e) {
        const email = emailEl.value.trim();
        
        // Check Gmail format
        if (!email.match(/@gmail\.com$/i)) {
          e.preventDefault();
          validationEl.className = 'text-sm text-red-600 mt-1';
          validationEl.textContent = '✗ Only @gmail.com addresses are allowed';
          validationEl.classList.remove('hidden');
          emailEl.focus();
          return;
        }

        // If validation hasn't completed or failed, prevent submit
        if (!isEmailValid) {
          e.preventDefault();
          validationEl.className = 'text-sm text-red-600 mt-1';
          validationEl.textContent = '✗ Please enter a valid Gmail address';
          validationEl.classList.remove('hidden');
          emailEl.focus();
        }
      });
    })();
  </script>
</body>
</html>

