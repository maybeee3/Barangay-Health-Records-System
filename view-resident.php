<?php
session_start();
include 'config.php'; // Include database connection

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
  header('Location: login.php'); // Redirect to login page if not authenticated
  exit();
}

$resident = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $resident_id = $_GET['id'];

    // Prepare and execute statement to fetch resident record (include all form fields)
    $stmt = $conn->prepare("SELECT id,  first_name, middle_name, last_name, name_extension, date_of_birth, sex, civil_status, contact_no, email, address, province, city_municipality, barangay, father_name, mother_name, guardian_name, guardian_relationship, guardian_contact_no, postal_code, years_of_residency, registration_status, existing_conditions, allergies, maintenance_medicines, blood_type, occupation, educational_attainment, created_at FROM residents WHERE id = ?");
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
  <title>View Resident | Barangay Health Monitoring System</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
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
  padding: 8px;
  border: none;
  border-radius: 5px;
}
</style>
</head>

<body class="bg-gradient-to-br from-[#f8fafc] to-white min-h-screen p-6">
  <div class="max-w-4xl mx-auto p-6">
    <header class="mb-6">
      <h1 class="text-2xl font-semibold">View Resident</h1>
      <p class="text-sm text-gray-500">Resident information (read-only view, paper-style layout)</p>
    </header>

    <?php if ($resident): ?>
    <div class="bg-white p-6 rounded-lg shadow">
      <h2 class="text-xl font-semibold text-center mb-4">RESIDENTS INFORMATION FORM</h2>
      <div class="section">
        <h2><strong> Personal Information</strong></h2>
        <div class="row">
          <label><strong>First Name</strong> <input type="text" readonly value="<?php echo htmlspecialchars($resident['first_name']); ?>"></label>
          <label><strong>Middle Name</strong> <input type="text" readonly value="<?php echo htmlspecialchars($resident['middle_name']); ?>"></label>
          <label><strong>Last Name</strong> <input type="text" readonly value="<?php echo htmlspecialchars($resident['last_name']); ?>"></label>
          <label><strong>Name Suffix</strong> <input type="text" readonly value="<?php echo htmlspecialchars($resident['name_extension']); ?>" style="width: 100px; max-width: 100%;"></label>
        </div>
        <div class="row">
          <label><strong>Date of Birth</strong> <input type="date" readonly value="<?php echo htmlspecialchars($resident['date_of_birth']); ?>"></label>
          <label><strong>Sex</strong> <input type="text" readonly value="<?php echo htmlspecialchars($resident['sex']); ?>" style="width: 80px; max-width: 100%;"></label>
          <label><strong>Civil Status</strong> <input type="text" readonly value="<?php echo htmlspecialchars($resident['civil_status']); ?>" style="width: 120px; max-width: 100%;"></label>
          <label><strong>Contact No.</strong> <input type="text" readonly value="<?php echo htmlspecialchars($resident['contact_no']); ?>" maxlength="11" inputmode="numeric" pattern="[0-9]*" style="width: 120px; max-width: 100%;"></label>
          <label><strong>Email</strong> <input type="email" readonly value="<?php echo htmlspecialchars($resident['email']); ?>" style="width: 300px; max-width: 100%;"></label>
        </div>
        
          <strong>Address (House/Block/Lot/Street/Barangay)</strong>
          <input type="text" readonly value="<?php echo htmlspecialchars($resident['address']); ?>" style="width: 100%;">
        
        <div class="row">
          <label><strong>Province</strong> <input type="text" readonly value="<?php echo htmlspecialchars($resident['province']); ?>"></label>
          <label><strong>City / Municipality</strong> <input type="text" readonly value="<?php echo htmlspecialchars($resident['city_municipality']); ?>"></label>
          <label><strong>Barangay</strong> <input type="text" readonly value="<?php echo htmlspecialchars($resident['barangay']); ?>"></label>
        </div>
      </div>
      <div class="section">
        <h2> <strong>Family Information</strong></h2>
        <div class="row">
          <label><strong>Name of Father</strong> <input type="text" readonly value="<?php echo htmlspecialchars($resident['father_name']); ?>"></label>
          <label><strong>Name of Mother</strong> <input type="text" readonly value="<?php echo htmlspecialchars($resident['mother_name']); ?>"></label>
        </div>
        <h3>Guardian / Emergency Contact</h3>
        <div class="row">
          <label><strong>Guardian Name</strong> <input type="text" readonly value="<?php echo htmlspecialchars($resident['guardian_name']); ?>"></label>
          <label><strong>Relationship</strong> <input type="text" readonly value="<?php echo htmlspecialchars($resident['guardian_relationship']); ?>"></label>
          <label><strong>Contact No.</strong> <input type="text" readonly value="<?php echo htmlspecialchars($resident['guardian_contact_no']); ?>" maxlength="11" inputmode="numeric" pattern="[0-9]*"></label>
        </div>
      </div>
      <div class="section">
        <h2><strong>Residency Details</strong></h2>
        <div class="row">
          <label><strong>Postal Code</strong> <input type="text" readonly value="<?php echo htmlspecialchars($resident['postal_code']); ?>"></label>
          <label><strong>Years of Residency</strong> <input type="text" readonly value="<?php echo htmlspecialchars($resident['years_of_residency']); ?>" min="0"></label>
        </div>
        <label>Registration Status:</label>
        <strong>Registration Status:</strong>
        <div>
          <label><input type="radio" name="registration_status" value="Registered" disabled <?php echo ($resident['registration_status']==='Registered') ? 'checked' : ''; ?>> Registered</label>
          <label><input type="radio" name="registration_status" value="Not Registered" disabled <?php echo ($resident['registration_status']==='Not Registered') ? 'checked' : ''; ?>> Not Registered</label>
        </div>
      </div>
      <div class="section">
        <h2><strong>Health Information</strong></h2>
        <label><strong>Existing Medical Conditions</strong> <textarea readonly class="paper-textarea w-full" rows="3"><?php echo htmlspecialchars($resident['existing_conditions']); ?></textarea></label>
        <label><strong>Allergies</strong> <textarea readonly class="paper-textarea w-full" rows="3"><?php echo htmlspecialchars($resident['allergies']); ?></textarea></label>
        <label><strong>Maintenance Medicines</strong> <textarea readonly class="paper-textarea w-full" rows="3"><?php echo htmlspecialchars($resident['maintenance_medicines']); ?></textarea></label>
        <label><strong>Blood Type</strong> <input type="text" readonly value="<?php echo htmlspecialchars($resident['blood_type']); ?>"></label>
      </div>
      <div class="section">
        <h2><strong>Additional Information</strong></h2>
        <label><strong>Occupation</strong> <input type="text" readonly value="<?php echo htmlspecialchars($resident['occupation']); ?>"></label>
        <h4>
          <label><strong>Educational Attainment:</strong></label>
          <div></h4>
            <label><input type="checkbox" disabled value="Elementary" <?php echo strpos($resident['educational_attainment'], 'Elementary') !== false ? 'checked' : ''; ?>> Elementary</label>
            <label><input type="checkbox" disabled value="High School" <?php echo strpos($resident['educational_attainment'], 'High School') !== false ? 'checked' : ''; ?>> High School</label>
            <label><input type="checkbox" disabled value="Senior High" <?php echo strpos($resident['educational_attainment'], 'Senior High') !== false ? 'checked' : ''; ?>> Senior High</label>
            <label><input type="checkbox" disabled value="College Level" <?php echo strpos($resident['educational_attainment'], 'College Level') !== false ? 'checked' : ''; ?>> College Level</label>
            <label><input type="checkbox" disabled value="College Graduate" <?php echo strpos($resident['educational_attainment'], 'College Graduate') !== false ? 'checked' : ''; ?>> College Graduate</label>
            <label><input type="checkbox" disabled value="Vocational" <?php echo strpos($resident['educational_attainment'], 'Vocational') !== false ? 'checked' : ''; ?>> Vocational</label>
            <label><input type="checkbox" disabled value="Post-Graduate" <?php echo strpos($resident['educational_attainment'], 'Post-Graduate') !== false ? 'checked' : ''; ?>> Post-Graduate</label>
          </div>
        </h4>
      </div>
      <div class="pt-4 text-right">
        <a href="residents.php" class="inline-block mr-2 text-sm bg-blue-100 text-blue-700 px-4 py-2 rounded hover:bg-blue-200">Back to list</a>
        <a href="edit-resident.php?id=<?php echo urlencode($resident['id']); ?>" class="inline-block bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700">Edit</a>
      </div>
    </div>
    <?php else: ?>
    <div class="text-center text-red-500">
      <p>Error: Resident could not be loaded or found.</p>
    </div>
    <?php endif; ?>
  </div>
</body>
</html>


