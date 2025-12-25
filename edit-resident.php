<?php
session_start();
include 'config.php'; // Include database connection

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
  header('Location: login.php'); // Redirect to login page if not authenticated
  exit();
}

$resident = null;

// Handle form submission for updating the record
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $resident_id = intval($_POST['resident_id'] ?? 0);
  $first_name = trim($_POST['first_name'] ?? '');
  $middle_name = trim($_POST['middle_name'] ?? '');
  $last_name = trim($_POST['last_name'] ?? '');
  $name_extension = trim($_POST['name_extension'] ?? '');
  $date_of_birth = trim($_POST['date_of_birth'] ?? '');
  $sex = trim($_POST['sex'] ?? '');
  $civil_status = trim($_POST['civil_status'] ?? '');
  // Debug: log civil_status value
  error_log("Civil Status received: " . $civil_status);
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
  $postal_code = isset($_POST['postal_code']) && $_POST['postal_code'] !== '' ? (int)$_POST['postal_code'] : 0;
  $years_of_residency = isset($_POST['years_of_residency']) && $_POST['years_of_residency'] !== '' ? (int)$_POST['years_of_residency'] : 0;
  $registration_status = trim($_POST['registration_status'] ?? '');
  $existing_conditions = trim($_POST['existing_conditions'] ?? '');
  $allergies = trim($_POST['allergies'] ?? '');
  $maintenance_medicines = trim($_POST['maintenance_medicines'] ?? '');
  $blood_type = trim($_POST['blood_type'] ?? '');
  $occupation = trim($_POST['occupation'] ?? '');
  $educational_attainment = isset($_POST['educational_attainment']) ? implode(', ', (array)$_POST['educational_attainment']) : '';

  // Server-side validation for name fields
  $name_regex = '/^[a-zA-Z\s.\-]*$/';
  $errors = [];
  if (empty($last_name)) $errors[] = 'Last Name is required.';
  if (empty($first_name)) $errors[] = 'First Name is required.';
  if (!preg_match($name_regex, $last_name)) $errors[] = 'Letters, spaces, hyphens, and periods only are allowed in Last Name.';
  if (!preg_match($name_regex, $first_name)) $errors[] = 'Letters, spaces, hyphens, and periods only are allowed in First Name.';
  if (!empty($middle_name) && !preg_match($name_regex, $middle_name)) $errors[] = 'Letters, spaces, hyphens, and periods only are allowed in Middle Name.';

  // Basic validation for required address fields
  if (empty($province)) $errors[] = 'Province is required.';
  if (empty($city_municipality)) $errors[] = 'City/Municipality is required.';
  if (empty($barangay)) $errors[] = 'Barangay is required.';

 
// Re-validate Contact Number
// Re-validate Contact Number
if (!empty($contact_no)) {
  if (!ctype_digit($contact_no)) {
    $errors[] = 'Contact Number must contain numbers only.';
  } elseif (strlen($contact_no) !== 11) {
    $errors[] = 'Contact Number must be exactly 11 digits.';
  }
}
// Re-validate Emergency Contact Number
if (!empty($guardian_contact_no)) {
  if (!ctype_digit($guardian_contact_no)) {
    $errors[] = 'Guardian Contact Number must contain numbers only.';
  } elseif (strlen($guardian_contact_no) !== 11) {
    $errors[] = 'Guardian Contact Number must be exactly 11 digits.';
  }
}

if (!empty($errors)) {
  echo "<script>alert('" . htmlspecialchars(implode('\\n', $errors)) . "'); window.history.back();</script>";
  exit();
}

  // Prepare update statement (include all columns)
  $sql = "UPDATE residents SET 
          first_name = ?, middle_name = ?, last_name = ?, name_extension = ?, date_of_birth = ?, sex = ?, civil_status = ?, 
          contact_no = ?, email = ?, address = ?, province = ?, city_municipality = ?, barangay = ?, 
          father_name = ?, mother_name = ?, guardian_name = ?, guardian_relationship = ?, guardian_contact_no = ?, 
          postal_code = ?, years_of_residency = ?, registration_status = ?, existing_conditions = ?, allergies = ?, 
          maintenance_medicines = ?, blood_type = ?, occupation = ?, educational_attainment = ? 
        WHERE id = ?";
  $stmt = $conn->prepare($sql);
  if ($stmt === false) {
    echo "<script>alert('Database prepare error: " . htmlspecialchars($conn->error) . "'); window.history.back();</script>";
    exit();
  }

  $stmt->bind_param(
    "ssssssssssssssssssiisssssssi",
    $first_name,
    $middle_name,
    $last_name,
    $name_extension,
    $date_of_birth,
    $sex,
    $civil_status,
    $contact_no,
    $email,
    $address,
    $province,
    $city_municipality,
    $barangay,
    $father_name,
    $mother_name,
    $guardian_name,
    $guardian_relationship,
    $guardian_contact_no,
    $postal_code,
    $years_of_residency,
    $registration_status,
    $existing_conditions,
    $allergies,
    $maintenance_medicines,
    $blood_type,
    $occupation,
    $educational_attainment,
    $resident_id
  );
  if ($stmt->execute()) {
    // Verify the update by fetching the record again
    $verify_stmt = $conn->prepare("SELECT civil_status FROM residents WHERE id = ?");
    $verify_stmt->bind_param("i", $resident_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    $verify_row = $verify_result->fetch_assoc();
    error_log("Update successful. Civil Status in POST: " . $civil_status . ", Civil Status in DB: " . ($verify_row['civil_status'] ?? 'NULL'));
    $verify_stmt->close();
    
    $stmt->close();
    $conn->close();
    $_SESSION['success_message'] = 'Resident information updated successfully!';
    header('Location: residents.php');
    exit();
  } else {
    error_log("Update failed: " . $stmt->error);
    $error_message = 'Error updating resident: ' . htmlspecialchars($stmt->error);
    $stmt->close();
    $conn->close();
  }
}

// Fetch existing record data for pre-filling the form
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $resident_id = $_GET['id'];

    $stmt = $conn->prepare("SELECT id, first_name, middle_name, last_name, name_extension, date_of_birth, sex, civil_status, contact_no, email, address, province, city_municipality, barangay, father_name, mother_name, guardian_name, guardian_relationship, guardian_contact_no, postal_code, years_of_residency, registration_status, existing_conditions, allergies, maintenance_medicines, blood_type, occupation, educational_attainment, created_at FROM residents WHERE id = ?");
    $stmt->bind_param("i", $resident_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $resident = $result->fetch_assoc();
    } else {
        echo "<script>alert('Resident not found.'); window.location.href='residents.php';</script>";
        exit();
    }
    $stmt->close();
} else {
    echo "<script>alert('Invalid resident ID.'); window.location.href='residents.php';</script>";
    exit();
}

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Edit Resident | Barangay Health Monitoring System</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="cascading-address-dropdown.js" defer></script>
  <script src="name-fields-component.js" defer></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: { bhms: { DEFAULT: '#2563eb', light: '#e6f0ff' } },
          fontFamily: { inter: ['Inter', 'sans-serif'] }
        }
      }
    }
  </script>
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
font-size: 25px;
}
.row {
display: flex;
gap: 20px;
margin-bottom: 15px;
}
.row label {
flex: 1;
display: flex;
flex-direction: column;
}
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
</style>
</head>

<body class="bg-gradient-to-br from-[#f8fafc] to-white min-h-screen text-gray-700 font-inter">
  <div class="max-w-[1100px] mx-auto p-6">
    <!-- Header -->
    <header class="flex items-center justify-between mb-6">
      
    </header>

    <!-- Edit Resident Form -->
    <main class="space-y-6">
      <section class="bg-white p-6 rounded-2xl shadow-md">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-semibold text-gray-700">Edit Resident Information</h2>
          <button onclick="window.history.back()" class="text-bhms hover:underline font-medium">← Back</button>
        </div>

        <?php if (isset($error_message)): ?>
          <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error_message; ?>
          </div>
        <?php endif; ?>

        <form method="POST" id="editResidentForm" class="space-y-6">
          <input type="hidden" name="resident_id" value="<?php echo htmlspecialchars($resident['id']); ?>">
          <h2 class="text-xl font-semibold text-center mb-4">RESIDENTS INFORMATION FORM</h2>

          <div class="section">
            <h2><strong>Personal Information</strong></h2>
            <div class="row">
              <label><strong>First Name</strong> <input type="text" name="first_name" value="<?php echo htmlspecialchars($resident['first_name']); ?>"></label>
              <label><strong>Middle Name</strong> <input type="text" name="middle_name" value="<?php echo htmlspecialchars($resident['middle_name']); ?>"></label>
              <label><strong>Last Name</strong> <input type="text" name="last_name" value="<?php echo htmlspecialchars($resident['last_name']); ?>"></label>
              <label><strong>Name Suffix</strong>
                <select name="name_extension" style="width: 120px; max-width: 100%;">
                  <option value="" <?php echo empty($resident['name_extension']) ? 'selected' : ''; ?>>None</option>
                  <option value="Jr." <?php echo ($resident['name_extension'] === 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                  <option value="Sr." <?php echo ($resident['name_extension'] === 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                  <option value="II" <?php echo ($resident['name_extension'] === 'II') ? 'selected' : ''; ?>>II</option>
                  <option value="III" <?php echo ($resident['name_extension'] === 'III') ? 'selected' : ''; ?>>III</option>
                  <option value="IV" <?php echo ($resident['name_extension'] === 'IV') ? 'selected' : ''; ?>>IV</option>
                  <option value="V" <?php echo ($resident['name_extension'] === 'V') ? 'selected' : ''; ?>>V</option>
                </select>
              </label>
            </div>
            <div class="row">
              <label><strong>Date of Birth</strong> <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($resident['date_of_birth']); ?>"></label>
              <label><strong>Sex</strong>
                <select name="sex">
                  <option value="Female" <?php echo ($resident['sex']==='Female')?'selected':''; ?>>Female</option>
                  <option value="Male" <?php echo ($resident['sex']==='Male')?'selected':''; ?>>Male</option>
                </select>
              </label>
              <label><strong>Civil Status</strong>
                <select name="civil_status">
                  <option value="Single" <?php echo ($resident['civil_status']==='Single')?'selected':''; ?>>Single</option>
                  <option value="Married" <?php echo ($resident['civil_status']==='Married')?'selected':''; ?>>Married</option>
                  <option value="Widowed" <?php echo ($resident['civil_status']==='Widowed')?'selected':''; ?>>Widowed</option>
                  <option value="Separated" <?php echo ($resident['civil_status']==='Separated')?'selected':''; ?>>Separated</option>
                </select>
              </label>
              <label><strong>Contact No.</strong> <input type="text" name="contact_no" maxlength="11" inputmode="numeric" pattern="[0-9]*" style="width: 120px; max-width: 100%;" value="<?php echo htmlspecialchars($resident['contact_no']); ?>"></label>
              <label><strong>Email</strong> <input type="email" name="email" value="<?php echo htmlspecialchars($resident['email']); ?>" style="width: 300px; max-width: 100%;"></label>
            </div>
            <label>
              <strong>Address (House/Block/Lot/Street/Barangay)</strong>
              <input type="text" name="address" style="width: 100%;" value="<?php echo htmlspecialchars($resident['address']); ?>">
            </label>
            <div class="row">
              <label><strong>Province</strong> <input type="text" name="province" value="<?php echo htmlspecialchars($resident['province']); ?>"></label>
              <label><strong>City / Municipality</strong> <input type="text" name="city_municipality" value="<?php echo htmlspecialchars($resident['city_municipality']); ?>"></label>
              <label><strong>Barangay</strong>
                <select name="barangay">
                  <option value="">Select Barangay</option>
                  <option value="Crisostomo Ext" <?php echo ($resident['barangay']==='Crisostomo Ext')?'selected':''; ?>>Crisostomo Ext</option>
                  <option value="General Taino St" <?php echo ($resident['barangay']==='General Taino St')?'selected':''; ?>>General Taino St</option>
                  <option value="Maligaya" <?php echo ($resident['barangay']==='Maligaya')?'selected':''; ?>>Maligaya</option>
                  <option value="National High way" <?php echo ($resident['barangay']==='National High way')?'selected':''; ?>>National High way</option>
                </select>
              </label>
            </div>
          </div>

          <div class="section">
            <h2><strong>Family Information</strong></h2>
            <div class="row">
              <label><strong>Name of Father</strong> <input type="text" name="father_name" value="<?php echo htmlspecialchars($resident['father_name']); ?>"></label>
              <label><strong>Name of Mother</strong> <input type="text" name="mother_name" value="<?php echo htmlspecialchars($resident['mother_name']); ?>"></label>
            </div>
            <h3>Guardian / Emergency Contact</h3>
            <div class="row">
              <label><strong>Guardian Name</strong> <input type="text" name="guardian_name" value="<?php echo htmlspecialchars($resident['guardian_name'] ?? ''); ?>"></label>
              <label><strong>Relationship</strong>
                <?php 
                  $rel = $resident['guardian_relationship'] ?? '';
                  $predefined = ['Father', 'Mother', 'Husband', 'Wife', 'Son', 'Daughter', 'Sibling'];
                  $isOther = !empty($rel) && !in_array($rel, $predefined);
                ?>
                <select name="guardian_relationship" id="guardianRelationshipEdit" onchange="toggleOtherRelationshipEdit()">
                  <option value="">Select</option>
                  <option value="Father" <?php echo ($rel === 'Father') ? 'selected' : ''; ?>>Father</option>
                  <option value="Mother" <?php echo ($rel === 'Mother') ? 'selected' : ''; ?>>Mother</option>
                  <option value="Husband" <?php echo ($rel === 'Husband') ? 'selected' : ''; ?>>Husband</option>
                  <option value="Wife" <?php echo ($rel === 'Wife') ? 'selected' : ''; ?>>Wife</option>
                  <option value="Son" <?php echo ($rel === 'Son') ? 'selected' : ''; ?>>Son</option>
                  <option value="Daughter" <?php echo ($rel === 'Daughter') ? 'selected' : ''; ?>>Daughter</option>
                  <option value="Sibling" <?php echo ($rel === 'Sibling') ? 'selected' : ''; ?>>Sibling</option>
                  <option value="Other" <?php echo $isOther ? 'selected' : ''; ?>>Other</option>
                </select>
              </label>
              <label id="otherRelationshipLabelEdit" style="display:<?php echo $isOther ? 'flex' : 'none'; ?>;"><strong>Specify Relationship</strong>
                <input type="text" name="guardian_relationship_other" id="guardianRelationshipOtherEdit" value="<?php echo $isOther ? htmlspecialchars($rel) : ''; ?>">
              </label>
              <label><strong>Contact No.</strong> <input type="text" name="guardian_contact_no" maxlength="11" inputmode="numeric" pattern="[0-9]*" value="<?php echo htmlspecialchars($resident['guardian_contact_no']); ?>"></label>
            </div>

            <script>
            function toggleOtherRelationshipEdit() {
              const select = document.getElementById('guardianRelationshipEdit');
              const otherLabel = document.getElementById('otherRelationshipLabelEdit');
              const otherInput = document.getElementById('guardianRelationshipOtherEdit');
              
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

          <div class="section">
            <h2><strong>Residency Details</strong></h2>
            <div class="row">
              <label><strong>Postal Code</strong> <input type="text" name="postal_code" value="<?php echo htmlspecialchars($resident['postal_code']); ?>"></label>
              <label><strong>Years of Residency</strong> <input type="number" name="years_of_residency" min="0" value="<?php echo htmlspecialchars($resident['years_of_residency']); ?>"></label>
            </div>
          
            <strong>Registration Status:</strong>
            <div>
              <label><input type="radio" name="registration_status" value="Registered" <?php echo ($resident['registration_status']==='Registered')?'checked':''; ?>> Registered</label>
              <label><input type="radio" name="registration_status" value="Not Registered" <?php echo ($resident['registration_status']==='Not Registered')?'checked':''; ?>> Not Registered</label>
            </div>
          </div>

          <div class="section">
            <h2><strong>Health Information</strong></h2>
            <label><strong>Existing Medical Conditions</strong> <textarea name="existing_conditions" class="paper-textarea w-full" rows="3"><?php echo htmlspecialchars($resident['existing_conditions']); ?></textarea></label>
            <label><strong>Allergies</strong> <textarea name="allergies" class="paper-textarea w-full" rows="3"><?php echo htmlspecialchars($resident['allergies']); ?></textarea></label>
            <label><strong>Maintenance Medicines</strong> <textarea name="maintenance_medicines" class="paper-textarea w-full" rows="3"><?php echo htmlspecialchars($resident['maintenance_medicines']); ?></textarea></label>
            <label><strong>Blood Type</strong> <input type="text" name="blood_type" class="paper-input" value="<?php echo htmlspecialchars($resident['blood_type']); ?>"></label>
          </div>

          <div class="section">
            <h2><strong>Additional Information</strong></h2>
            <label><strong>Occupation</strong> <input type="text" name="occupation" value="<?php echo htmlspecialchars($resident['occupation']); ?>"></label>
            <h4>
              <label><strong>Educational Attainment:</strong></label>
              <div></h4>
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
            <a href="residents.php" class="inline-block mr-2 text-sm bg-blue-100 text-blue-700 px-4 py-2 rounded hover:bg-blue-200">Cancel</a>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save Changes</button>
          </div>
        </form>
      </section>
    </main>
  </div>

  <script>
    // Re-initialize all JS components on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function() {
      // Initialize name fields component
      setupNameFields('editResidentForm');
      
      // Initialize cascading dropdowns with pre-filled values
      loadAddressData(function() {
          // Function to set selected value if it exists
          function setSelectedValue(elementId, value) {
              const element = document.getElementById(elementId);
              if (element && value) {
                  element.value = value;
                  element.setAttribute('data-valid-selection', 'true');
              }
          }
          setSelectedValue('province', '<?php echo htmlspecialchars($resident['province']); ?>');
          setSelectedValue('city_municipality', '<?php echo htmlspecialchars($resident['city_municipality']); ?>');
          setSelectedValue('barangay', '<?php echo htmlspecialchars($resident['barangay']); ?>');
          setSelectedValue('purok', '<?php echo htmlspecialchars($resident['purok']); ?>');
          initCascadingAddressDropdowns(); // Re-run init to attach event listeners
      });

      // Re-initialize numeric validations
      setupNumericValidation('age', 'age_error');
      setupPhoneNumberValidation('contact_no', 'contact_no_error');
   });

    function calculateAge() {
      const dobInput = document.getElementById('date_of_birth').value;
      if (dobInput) {
        const birthDate = new Date(dobInput);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
          age--;
        }
        document.getElementById('age').value = age;
      } else {
        document.getElementById('age').value = '';
      }
    }
    // Real-time numeric input validation (from add-residents.php)
    function setupNumericValidation(inputElementId, errorElementId, allowEmpty = false) {
        const input = document.getElementById(inputElementId);
        const errorSpan = document.getElementById(errorElementId);

        if (!input || !errorSpan) return;

        input.addEventListener('input', function() {
            const value = this.value;
            if (!/^[0-9]*$/.test(value)) {
                errorSpan.textContent = 'Numbers only are allowed for this field.';
                errorSpan.classList.remove('hidden');
                this.setCustomValidity('Invalid');
            } else if (!allowEmpty && value.length === 0) {
                 errorSpan.textContent = 'This field cannot be empty.';
                 errorSpan.classList.remove('hidden');
                 this.setCustomValidity('Invalid');
            } else {
                errorSpan.classList.add('hidden');
                this.setCustomValidity('');
            }
        });
    }

    // Real-time phone number validation (from add-residents.php)
    function setupPhoneNumberValidation(inputElementId, errorElementId) {
        const input = document.getElementById(inputElementId);
        const errorSpan = document.getElementById(errorElementId);

        if (!input || !errorSpan) return;

        input.addEventListener('input', function() {
            const value = this.value;
            if (!/^[0-9]*$/.test(value)) {
                errorSpan.textContent = 'Numbers only are allowed for this field.';
                errorSpan.classList.remove('hidden');
                this.setCustomValidity('Invalid');
            } else if (value.length > 0 && value.length < 11) {
                errorSpan.textContent = 'Phone number must be 11 digits long.';
                errorSpan.classList.remove('hidden');
                this.setCustomValidity('Invalid');
            } else if (value.length > 11) {
                errorSpan.textContent = 'Phone number must be exactly 11 digits.';
                errorSpan.classList.remove('hidden');
                this.setCustomValidity('Invalid');
            } else {
                errorSpan.classList.add('hidden');
                this.setCustomValidity('');
            }
        });
    }
  </script>
</body>
</html>
