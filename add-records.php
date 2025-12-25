<?php
session_start();

// Keep auth guard if user pages require login
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}
// Pre-fill resident when `resident_id` provided in querystring
$prefillResidentId = 0;
if (isset($_GET['resident_id']) && is_numeric($_GET['resident_id'])) {
  $prefillResidentId = (int) $_GET['resident_id'];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Patient Record (Print) | Barangay Health Monitoring System</title>
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
    .section-title{font-weight:700;margin-bottom:8px;font-size:18px}
    /* Larger labels and inputs inside the record details boxed area */
    .boxed label { font-size:15px; font-weight:700; }
    .boxed .paper-input, .boxed textarea.paper-textarea { font-size:14px; }
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
      /* Reduce page margin and scale to fit single A4 page when printing */
      @page { size: A4; margin: 10mm; }
      body { margin:0; }
      .container { box-shadow:none; max-width:210mm; width:100%; }
      .no-print { display:none !important; }
      /* Ensure inputs/textarea render as plain lines on paper */
      input.paper-input, textarea.paper-textarea, textarea.paper-text { background:transparent !important; border:0 !important; border-bottom:1px solid #000 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
      /* Avoid breaking inside boxed sections */
      .boxed { page-break-inside: avoid; }

      /* Slightly scale down content to encourage single-page output */
      html, body { height:297mm; }
      body { -webkit-transform-origin: top left; transform-origin: top left; -webkit-transform: scale(0.95); transform: scale(0.95); }

      /* Reduce textarea heights and fonts for print to conserve space */
      textarea.paper-textarea { height:80px !important; }
      textarea.paper-text { height:80px !important; }
      .section-title { font-size:11px !important; }
      .paper-input, textarea { font-size:11px !important; }
    }
  </style>
</head>
<body>
  <div class="container">
    <form id="recordForm" method="post" action="save_record.php">
      <input type="hidden" name="created_by" value="<?php echo htmlspecialchars($_SESSION['username']); ?>">
     
    <table class="paper-table">
      <tr>
        <td style="width:14%;"><img src="Brgy. San Isidro-LOGO.png" alt="logo" class="logo" /></td>
        <td style="width:62%;" class="title">
          <div style="font-weight:700; font-size:14px;">PAGSANJAN RURAL HEALTH UNIT</div>
          <div style="font-weight:600; font-size:13px; margin-top:2px;">PATIENT RECORD</div>
          <div class="small" style="margin-top:4px;">Brgy. San Isidro / Pagsanjan, Laguna</div>
        </td>
        <td style="width:24%;" class="right-info">
          <div><strong class="small">DATE:</strong> <input name="record_date" type="date" class="paper-input" style="width:140px;"/></div>
          <div><strong class="small">TIME:</strong> <input name="record_time" type="time" class="paper-input" style="width:140px;"/></div>
          <div><strong class="small">CONTACT NO.:</strong> <input name="contact_no" type="text" class="paper-input" style="width:140px;"/></div>
          <div><strong class="small">EMAIL:</strong> <input name="email" type="email" class="paper-input" style="width:140px;"/></div>
        </td>
      </tr>
    </table>

    <div style="height:8px;"></div>

    <table class="paper-table" style="font-size:12px;">
      <tr>
        <td style="width:14%;"><strong class="small">NAME OF PATIENT:</strong></td>
        <td style="width:83%;">
          <select name="patient_id" id="patientDropdown" class="paper-input" style="width:100%; height:24px;">
            <option value="">-- Select Patient --</option>
          </select>
        </td>
      </tr>
      <tr>
  
          </select>
        </td>
      </tr>
      <tr style="height:8px;"></tr>
      <tr>
        <tr>
  <td style="width:15%;"><strong class="small">BIRTHDAY:</strong></td>
  <td style="width:18%;">
    <input name="birthday" id="birthday" type="text" class="paper-input" style="width:100%; min-width:90px;" readonly placeholder="mm/dd/yyyy"/>
  </td>
  <td style="width:7%;"><strong class="small">AGE:</strong></td>
  <td style="width:7%;">
    <input name="age" id="age" type="text" class="paper-input" style="width:100%; min-width:40px;" readonly/>
  </td>
  <td style="width:7%;"><strong class="small">SEX"</strong></td>
  <td style="width:7%;">
    <input name="sex" id="sex" type="text" class="paper-input" style="width:100%; min-width:40px;" readonly/>
  </td>
  <td style="width:12%;"><strong class="small">CIVIL STATUS:</strong></td>
  <td style="width:17%;">
    <input name="civil_status" id="civil_status" type="text" class="paper-input" style="width:100%; min-width:70px;" readonly/>
  </td>
</tr>


      
      <tr style="height:8px;"></tr>
      <tr>
        <td><strong class="small">ADDRESS</strong></td>
        <td colspan="7"><input name="address" id="address" type="text" class="paper-input" style="width:100%; height:20px;" readonly/></td>
      </tr>
    </table>
    <script>
      // If the page was opened with a resident_id, this variable will be set by PHP.
      const residentPrefillId = <?php echo json_encode($prefillResidentId); ?>;
      // Fetch patients for dropdown and wire selection to autofill fields
      document.addEventListener('DOMContentLoaded', function() {
        fetch('fetch_consulted_patients_dropdown.php')
          .then(response => response.json())
          .then(data => {
            const dropdown = document.getElementById('patientDropdown');
            data.forEach(res => {
              const name = res.name || '-- Unknown --';
              const opt = document.createElement('option');
              opt.value = res.id;
              opt.textContent = name;
              dropdown.appendChild(opt);
            });

            // If residentPrefillId provided, ensure option exists and select it, then trigger change handler
            if (residentPrefillId) {
              // If option for resident not present, add a placeholder option (name will be filled by fetch)
              if (!Array.from(dropdown.options).some(o => o.value == residentPrefillId)) {
                const placeholder = document.createElement('option');
                placeholder.value = residentPrefillId;
                placeholder.textContent = 'Loading patient...';
                dropdown.appendChild(placeholder);
              }
              dropdown.value = residentPrefillId;
              // trigger change to run the existing autofill logic
              const ev = new Event('change');
              dropdown.dispatchEvent(ev);
            }
          });

        // When patient is selected, fetch and autofill demographic fields and latest consultation
        const patientEl = document.getElementById('patientDropdown');
        if (patientEl) {
          patientEl.addEventListener('change', function() {
            const patientId = this.value;
            if (!patientId) {
              ['birthday','age','sex','civil_status','guardian_name','address'].forEach(id => document.getElementById(id).value = '');
              document.getElementsByName('contact_no')[0].value = '';
              document.getElementsByName('email')[0].value = '';
              document.getElementById('latestConsultationInfo')?.remove();
              return;
            }
            fetch('fetch_resident_for_consultation_suggestions.php?id=' + patientId)
              .then(response => response.json())
              .then(res => {
                // normalize possible keys
                const email = res.email || res.email_address || '';
                const contact = res.contact_number || res.contact_no || res.contact || '';
                const addr = res.address || (() => {
                  const parts = [res.address, res.barangay, res.city_municipality, res.province].filter(Boolean);
                  return parts.join(', ');
                })();

                // Fill demographic fields
                if (document.getElementById('birthday')) document.getElementById('birthday').value = res.birthday || '';
                if (document.getElementById('age')) document.getElementById('age').value = res.age || '';
                if (document.getElementById('sex')) document.getElementById('sex').value = res.sex || '';
                if (document.getElementById('civil_status')) document.getElementById('civil_status').value = res.civil_status || '';
                if (document.getElementById('guardian_name')) document.getElementById('guardian_name').value = res.guardian_name || '';
                if (document.getElementById('guardian_relationship')) document.getElementById('guardian_relationship').value = res.guardian_relationship || '';
                if (document.getElementById('guardian_contact')) document.getElementById('guardian_contact').value = res.guardian_contact || '';

                // Fill header inputs (if present)
                const headerContact = document.getElementsByName('contact_no')[0];
                if (headerContact) headerContact.value = contact || '';
                const headerEmail = document.getElementsByName('email')[0];
                if (headerEmail) headerEmail.value = email || '';

                // Fill boxed inputs (if present)
                const boxedEmail = document.getElementById('email'); if (boxedEmail) boxedEmail.value = email || '';
                const boxedContact = document.getElementById('contact_no'); if (boxedContact) boxedContact.value = contact || '';
                const boxedAddress = document.getElementById('address'); if (boxedAddress) boxedAddress.value = addr || '';

                // No visual info block; fields are filled into the form lines above
              });
            // Fetch latest consultation info
            fetch('fetch_latest_consultation.php?patient_id=' + patientId)
              .then(response => response.json())
              .then(data => {
                // Remove previous info
                document.getElementById('latestConsultationInfo')?.remove();
                if (!data || !data.id) return;

                // Set header date/time if available
                try {
                  const hdrDate = document.getElementsByName('record_date')[0];
                  const hdrTime = document.getElementsByName('record_time')[0];
                  if (hdrDate && data.date_of_consultation) hdrDate.value = data.date_of_consultation;
                  if (hdrTime && data.consultation_time) hdrTime.value = data.consultation_time;
                } catch(e){}

                // Set consulting doctor input if present
                try {
                  const consultingInput = document.querySelector('input[name="consulting_doctor"]');
                  if (consultingInput && data.consulting_doctor) consultingInput.value = data.consulting_doctor;
                } catch(e){}

                // Populate form textareas with latest consultation details
                if (document.getElementById('reason_for_consultation')) document.getElementById('reason_for_consultation').value = data.reason_for_consultation || '';
                if (document.getElementById('treatment')) document.getElementById('treatment').value = data.treatment_prescription || data.treatment || '';
              });
          });
        }

        // Fetch consultants for dropdown and wire selection to consulting_doctor input
        fetch('fetch_consultants_dropdown.php')
          .then(response => response.json())
          .then(data => {
            const dropdown = document.getElementById('consultantDropdown');
            data.forEach(res => {
              const name = res.name || ((res.first_name||'') + ' ' + (res.last_name||'')).trim() || '-- Unknown --';
              const opt = document.createElement('option');
              opt.value = res.id;
              opt.textContent = name;
              dropdown.appendChild(opt);
            });
          });

        // When consultant is selected, set the consulting_doctor input value
        const consultantEl = document.getElementById('consultantDropdown');
        if (consultantEl) {
          consultantEl.addEventListener('change', function() {
            const idx = this.selectedIndex;
            const name = idx > -1 ? (this.options[idx].text || '') : '';
            const input = document.querySelector('input[name="consulting_doctor"]');
            if (input) input.value = name;
          });
        }
      });
    </script>

    <div style="height:10px;"></div>

    <div class="boxed">
    <div class="section-title">RECORD DETAILS</div>

      <div class="vitals-vertical" style="margin-bottom:12px;">
        <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
          <div style="width:120px;"><strong class="small">DATE:</strong></div>
          <div><input name="v_date" type="date" class="paper-input" style="width:220px;" value="<?php echo date('Y-m-d'); ?>"/></div>
        </div>
        <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
          <div style="width:120px;"><strong class="small">TIME:</strong></div>
          <div><input name="v_time" type="time" class="paper-input" style="width:120px;" value="<?php echo date('H:i'); ?>"/></div>
        </div>
          <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
            <div style="width:120px;"><strong class="small">TEMPERATURE:</strong></div>
            <div style="display:flex; align-items:center; gap:8px;"><input name="v_temp" id="v_temp" type="text" class="paper-input" style="width:160px;" placeholder="e.g., 36.6" title="Temperature in degrees Celsius" /><span class="small">°C</span></div>
          </div>
          <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
            <div style="width:120px;"><strong class="small">WEIGHT:</strong></div>
            <div style="display:flex; align-items:center; gap:8px;"><input name="v_wt" id="v_wt" type="text" class="paper-input" style="width:160px;" placeholder="e.g., 55.0" title="Weight in kilograms" /><span class="small">kg</span></div>
          </div>
          <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
            <div style="width:120px;"><strong class="small">HEIGHT:</strong></div>
            <div style="display:flex; align-items:center; gap:8px;"><input name="v_ht" id="v_ht" type="text" class="paper-input" style="width:160px;" placeholder="e.g., 160" title="Height in centimeters" /><span class="small">cm</span></div>
          </div>
          <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
            <div style="width:120px;"><strong class="small">BLOOD PRESSURE:</strong></div>
            <div style="display:flex; align-items:center; gap:8px;"><input name="v_bp" id="v_bp" type="text" class="paper-input" style="width:160px;" placeholder="e.g., 120/80" title="Blood pressure in mmHg (systolic/diastolic)" /><span class="small">mmHg</span></div>
          </div>
          <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
            <div style="width:120px;"><strong class="small">RESPIRATORY RATE:</strong></div>
            <div style="display:flex; align-items:center; gap:8px;"><input name="v_rr" id="v_rr" type="text" class="paper-input" style="width:160px;" placeholder="e.g., 16" title="Respiratory rate in breaths per minute" /><span class="small">breaths/min</span></div>
          </div>
          <div style="display:flex; gap:12px; align-items:center; margin-bottom:6px;">
            <div style="width:120px;"><strong class="small">PULSE RATE:</strong></div>
            <div style="display:flex; align-items:center; gap:8px;"><input name="v_pr" id="v_pr" type="text" class="paper-input" style="width:160px;" placeholder="e.g., 72" title="Pulse rate in beats per minute" /><span class="small">bpm</span></div>
          </div>
      </div>

      <!-- Reason for Consultation / Complaint -->
      <div class="mt-4">
        <label for="reason_for_consultation" style="font-weight:700; display:block; margin-bottom:6px;">REASON OF CONSULTATION / COMPLAINT</label>
        <textarea id="reason_for_consultation" name="reason_for_consultation" class="paper-textarea" style="width:100%; height:120px; background-image: linear-gradient(to bottom, rgba(0,0,0,0.06) 1px, transparent 1px); background-size: 100% 18px;"> </textarea>
      </div>

      <div style="margin-bottom:12px;">
        <label for="treatment" style="font-weight:700; display:block; margin-bottom:6px;">DIAGNOSIS / TREATMENT</label>
        <textarea id="treatment" name="treatment" class="paper-textarea" style="width:100%; height:120px; background-image: linear-gradient(to bottom, rgba(0,0,0,0.06) 1px, transparent 1px); background-size: 100% 18px;"> </textarea>
      </div>

      <div style="margin-bottom:6px;">
        <label for="assessment" style="font-weight:700; display:block; margin-bottom:6px;">ASSESSMENT</label>
        <textarea id="assessment" name="assessment" class="paper-textarea" style="width:100%; height:120px; background-image: linear-gradient(to bottom, rgba(0,0,0,0.06) 1px, transparent 1px); background-size: 100% 18px;"> </textarea>
      </div>

      

      <!-- Consulting Doctor / Staff -->
      <div class="mt-4">
        <div class="section-title">DOCTOR/STAFF</div>
        <input type="text" name="consulting_doctor" class="paper-input w-full" placeholder="Enter name of consulting doctor or staff..." value="Lhee Za Milanez" readonly>
      </div>

    </div>

    <div class="no-print" style="display:flex; justify-content:flex-end; gap:8px; margin-top:12px;">

  <!-- Back = Gray -->
<!-- Cancel = Gray -->
<button type="button"
        onclick="window.location.href='consultations.php';"
        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-[6px] border hover:bg-gray-400">
  Cancel
</button>



<!-- Print = Light Blue -->
<button type="button" onclick="window.print();" 
        style="padding:8px 16px; background:#bfdbfe; color:#1e3a8a; border-radius:6px; border:1px solid #93c5fd; cursor:pointer;">
  Print
</button>

<!-- Save = Blue -->
<button type="submit" form="recordForm" 
        style="padding:6px 12px; border-radius:6px; border:1px solid #1d4ed8; background:#2563eb; cursor:pointer; color:white;">
  Save
</button>


</div>

    </form>
  </div>
</body>
</html>
