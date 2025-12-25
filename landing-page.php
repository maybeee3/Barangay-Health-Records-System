<?php
session_start();

// Try load server-side data files if present
$residentsFile = __DIR__ . '/residents.json';
$consultsFile  = __DIR__ . '/consultations.json';

$residents = [];
$consults  = [];

if (file_exists($residentsFile)) {
    $residents = json_decode(file_get_contents($residentsFile), true) ?: [];
}
if (file_exists($consultsFile)) {
    $consults = json_decode(file_get_contents($consultsFile), true) ?: [];
}

$residentsCount = count($residents);
$consultsCount  = count($consults);

// If user session exists (app login), expose name for display
$sessionUser = isset($_SESSION['username']) ? $_SESSION['username'] : null;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Brgy San Isidro Health Monitoring System</title>
  <meta name="description" content="The Barangay Health Monitoring System introduces the purpose, features, and benefits of the system while providing quick access to login and essential information. It organizes resident health records, improves consultation management, and supports efficient healthcare delivery at the barangay level.">

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
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
    body { font-family: Inter, sans-serif; }
    .hero-bg {
      /* gradient overlay on top of Background.jpeg (place file in project root or adjust path) */
      background-image:
        linear-gradient(180deg, rgba(37,99,235,0.06), rgba(37,99,235,0.02)),
        url('Background.jpeg');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      /* make hero fill the viewport below the fixed header */
      min-height: calc(100vh - 4rem); /* default header spacer h-16 = 4rem */
    }
    @media (min-width: 768px) {
      .hero-bg { min-height: calc(100vh - 5rem); /* md header spacer h-20 = 5rem */ }
    }
    .feature-icon { background: linear-gradient(135deg, rgba(37,99,235,0.12), rgba(37,99,235,0.04)); }
  </style>
</head>
<body class="bg-white text-gray-700 min-h-screen">

  <!-- Top navigation (full-width blue header; nav pushed to the right) -->
  <header class="bg-blue-700 text-white fixed inset-x-0 top-0 z-40">
    <div class="max-w-full mx-auto flex items-center gap-4 py-4 px-6">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-white/10 shadow flex items-center justify-center">
          <img src="Brgy. San Isidro-LOGO.png" alt="Brgy. San Isidro Logo" class="w-full h-full object-cover rounded-full">
        </div>
        <div>
          <div class="text-sm text-white/90">Brgy. San Isidro Health</div>
          <div class="text-lg font-semibold text-white">Monitoring System</div>
        </div>
      </div>

      <nav class="hidden md:flex items-center gap-6 ml-auto">
        <a href="#features" class="text-sm text-white/90 hover:text-white">Features</a>
        <a href="#how-it-works" class="text-sm text-white/90 hover:text-white">How it works</a>
        <a href="#contact" class="text-sm text-white/90 hover:text-white">Contact</a>
        <a href="login.php" class="text-sm font-semibold text-white bg-bhms px-4 py-2 rounded-lg shadow hover:bg-blue-700">Log in</a>
      </nav>

      <div class="md:hidden ml-auto">
        <button id="mobileMenuBtn" class="p-2 rounded-md bg-white/10 text-white">Menu</button>
      </div>
    </div>
  </header>

  <!-- spacer for fixed header -->
  <div class="h-16 md:h-20"></div>

  <!-- Mobile menu (hidden by default) -->
  <div id="mobileMenu" class="hidden px-6 pb-6">
    <div class="flex flex-col gap-3 max-w-6xl mx-auto">
      <a href="#features" class="text-gray-700">Features</a>
      <a href="#how-it-works" class="text-gray-700">How it works</a>
      <a href="#contact" class="text-gray-700">Contact</a>
      <a href="consultations.php" class="text-white bg-bhms px-4 py-2 rounded-lg inline-block mt-2">Appointment set</a>
    </div>
  </div>

  <!-- Hero -->
  <section class="hero-bg flex items-center">
    <div class="max-w-6xl mx-auto px-6 grid md:grid-cols-2 gap-8 items-center">
      <div>
        <h1 class="text-4xl md:text-5xl font-extrabold text-white leading-tight">Brgy San Isidro Health Monitoring</h1>
        <p class="mt-4 text-white/75 text-lg">is a digital tool designed to organize residents’ health records, track medical updates, and assist local health workers in providing faster and more efficient healthcare services. It helps ensure that the community receives timely support and accurate health information.</p>

       

        <div class="mt-6">
          <button id="scheduleBtn" type="button" aria-controls="scheduleModal" aria-expanded="false" class="bg-bhms text-white px-5 py-3 rounded-lg shadow hover:bg-blue-700">Schedule Consultation</button>
        </div>
      </div>

      <!-- RIGHT COLUMN: right-aligned card -->
      <div class="flex justify-end">
        <div class="w-full max-w-sm rounded-2xl shadow-lg overflow-hidden bg-gray-900 bg-opacity-60 text-white p-4"
        style="background: rgba(255,255,255,0.45);">
          <img src="Brgy. San Isidro-LOGO.png" alt="Brgy. San Isidro" class="w-28 h-28 object-cover rounded-full mx-auto border border-white/20">
          <div class="mt-4 text-center">
            <h3 class="font-semibold text-white">At-a-glance resident list</h3>
            <p class="text-sm text-gray-200 mt-1">Quickly find residents, open profiles, and update records.</p>
            <div class="mt-3 text-xs text-gray-300">Secure • Role-based access</div>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- Features -->
   <!-- Features -->
  <section id="features" class="max-w-6xl mx-auto px-6 py-12">
    <h2 class="text-2xl font-semibold text-gray-800">Features built for barangay health workers</h2>
    <p class="text-gray-500 mt-2">Designed to reduce paperwork and speed up service delivery.</p>

    <div class="mt-6 grid md:grid-cols-3 gap-6">
      <div class="bg-white p-6 rounded-2xl shadow-sm hover:shadow-md transition">
        <div class="w-12 h-12 rounded-lg flex items-center justify-center feature-icon mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-bhms" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h8m-8 4h6" /></svg>
        </div>
        <h3 class="font-semibold">Resident Registry</h3>
        <p class="text-sm text-gray-500 mt-2">Add, edit, and search residents with a single view.</p>
      </div>

      <div class="bg-white p-6 rounded-2xl shadow-sm hover:shadow-md transition">
        <div class="w-12 h-12 rounded-lg flex items-center justify-center feature-icon mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-bhms" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 1.343-3 3 0 2.25 3 4 3 4s3-1.75 3-4c0-1.657-1.343-3-3-3z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
        </div>
        <h3 class="font-semibold">Consultation Tracking</h3>
        <p class="text-sm text-gray-500 mt-2">Log consultations, diagnoses, and treatments per resident.</p>
      </div>

      <div class="bg-white p-6 rounded-2xl shadow-sm hover:shadow-md transition">
        <div class="w-12 h-12 rounded-lg flex items-center justify-center feature-icon mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-bhms" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a3 3 0 013-3h0a3 3 0 013 3v6"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 9h14"/></svg>
        </div>
        <h3 class="font-semibold">Reports & Exports</h3>
        <p class="text-sm text-gray-500 mt-2">Generate printable reports for health programs and funding.</p>
      </div>
    </div>
  </section>
        

  <!-- How it works -->
  <section id="how-it-works" class="max-w-6xl mx-auto px-6 py-12">
    <h2 class="text-2xl font-semibold text-gray-800">How it works</h2>
    <div class="mt-6 grid md:grid-cols-3 gap-6">
      <div class="bg-white p-6 rounded-xl shadow-sm">
        <div class="text-bhms font-semibold text-xl">1</div>
        <h4 class="mt-3 font-semibold">Register or Login</h4>
        <p class="text-sm text-gray-500 mt-1">Use your barangay credentials to access the system or try the demo to explore.</p>
      </div>

      <div class="bg-white p-6 rounded-xl shadow-sm">
        <div class="text-bhms font-semibold text-xl">2</div>
        <h4 class="mt-3 font-semibold">Manage Residents</h4>
        <p class="text-sm text-gray-500 mt-1">Add residents, Update medical history, and search quickly.</p>
      </div>

      <div class="bg-white p-6 rounded-xl shadow-sm">
        <div class="text-bhms font-semibold text-xl">3</div>
        <h4 class="mt-3 font-semibold">Track Consultations</h4>
        <p class="text-sm text-gray-500 mt-1">Log consultations and export summaries for meetings and reports.</p>
      </div>
    </div>
  </section>

  <!-- Contact / CTA -->
  
  <section id="contact" class="max-w-6xl mx-auto px-6 py-12">
    <div class="bg-bhms-light rounded-2xl p-8 grid md:grid-cols-2 gap-6 items-center">
      <div></div>

      <div class="flex items-center justify-center md:justify-end">
        <div class="w-full max-w-full md:max-w-2xl bg-white p-6 rounded-lg shadow-sm text-right">
          <h3 class="text-2xl font-semibold text-gray-800 mb-3">How to Request a Consultation?</h3>
          <div class="text-lg text-gray-700 space-y-3">
            <div>Send an email to: <a href="mailto:brgysanisidrohealth@gmail.com" class="text-bhms font-medium">brgysanisidrohealth@gmail.com</a> for an online consultation request.</div>
            <div>Or visit the Barangay Health Center in person to make a walk-in request.</div>
            <div class="mt-2 text-sm text-gray-500">All requests are scheduled and monitored within the system to ensure timely follow-up by the barangay health staff.</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="w-full border-t border-gray-200 mt-12">
    <div class="max-w-6xl mx-auto px-6 py-8 text-center text-gray-600 text-sm space-y-1">
      <div class="font-semibold">Brgy. San Isidro</div>
      <div>© <span id="year"></span> Brgy. San Isidro. All rights reserved.</div>
      <div>Brgy. San Isidro Health Monitoring System</div>
      <div id="yearFooter" class="text-xs text-gray-400"></div>
    </div>
  </footer>

  <!-- Demo modal -->
  <div id="demoModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6">
      <h4 class="text-lg font-semibold">Try the Demo</h4>
      <p class="text-sm text-gray-500 mt-2">Enter a display name to start a demo session (stored locally).</p>

      <label class="block mt-4">
        <span class="text-sm text-gray-600">Name</span>
        <input id="demoName" class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="e.g., Nurse Maria" />
      </label>

      <label class="block mt-3">
        <span class="text-sm text-gray-600">Role</span>
        <select id="demoRole" class="mt-1 w-full border rounded-lg px-3 py-2">
          <option>Health Worker</option>
          <option>Admin</option>
          <option>Viewer</option>
        </select>
      </label>

      <div class="mt-6 flex justify-end gap-3">
        <button id="demoCancel" class="px-4 py-2 rounded-lg border">Cancel</button>
        <button id="demoStart" class="px-4 py-2 rounded-lg bg-bhms text-white">Start Demo</button>
      </div>
    </div>
  </div>

  <!-- Schedule by Email Modal -->
  <div id="scheduleModal" class="fixed inset-0 bg-black/40 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 max-h-[90vh] overflow-y-auto">
      <h4 class="text-lg font-semibold">Schedule Consultation via Email</h4>
      <p class="text-sm text-gray-600 mt-1">Fill in the details below. Clicking "Open Email" will open your mail client with a prefilled message to the barangay health center.</p>

      <div class="mt-3 text-sm text-gray-700">
        <strong>To:</strong> brgysanisidrohealth@gmail.com
      </div>

      <label class="block mt-4">
        <span class="text-sm text-gray-600">Resident Name</span>
        <input id="schedName" class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="e.g., Juan dela Cruz" value="<?php echo isset($sessionUser) ? htmlspecialchars($sessionUser) : ''; ?>" />
      </label>

      <label class="block mt-3">
        <span class="text-sm text-gray-600">Residents Email (required)</span>
        <input id="schedEmail" type="email" required class="mt-1 w-full border rounded-lg px-3 py-2" placeholder="your.email@example.com" value="<?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : ''; ?>" />
      </label>

      <div class="grid grid-cols-2 gap-3 mt-3">
        <label class="block">
          <span class="text-sm text-gray-600">Preferred Date</span>
          <input id="schedDate" type="date" class="mt-1 w-full border rounded-lg px-3 py-2" />
        </label>
        <label class="block">
          <span class="text-sm text-gray-600">Preferred Time</span>
          <input id="schedTime" type="time" class="mt-1 w-full border rounded-lg px-3 py-2" />
        </label>
      </div>

      <label class="block mt-3">
        <span class="text-sm text-gray-600">Complaint</span>
        <textarea id="schedMsg" class="mt-1 w-full border rounded-lg px-3 py-2" rows="3" placeholder="Describe your complaint"></textarea>
      </label>

      <div class="mt-6 flex justify-end gap-3">
        <button id="schedCancel" class="px-4 py-2 rounded-lg border">Cancel</button>
        <button id="schedSend" class="px-4 py-2 rounded-lg bg-bhms text-white">Open Email</button>
      </div>
    </div>
  </div>

  <!-- Simple toast -->
  <div id="toast" class="fixed bottom-6 right-6 bg-bhms text-white px-4 py-2 rounded-lg shadow hidden"></div>

  <script>
    // UI controls
    const currentYear = new Date().getFullYear();
    const yEl = document.getElementById('year');
    const yFooter = document.getElementById('yearFooter');
    if (yEl) yEl.textContent = currentYear;
    if (yFooter) yFooter.textContent = currentYear;

    // Mobile menu toggle
    const mobileBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    if (mobileBtn) mobileBtn.addEventListener('click', () => mobileMenu.classList.toggle('hidden'));

    // Demo modal
    const demoModal = document.getElementById('demoModal');
    const tryDemoBtn = document.getElementById('tryDemoBtn');
    const demoCancelImmediate = document.getElementById('demoCancel');
    if (tryDemoBtn) tryDemoBtn.addEventListener('click', () => demoModal.classList.remove('hidden'));
    if (demoCancelImmediate) demoCancelImmediate.addEventListener('click', () => demoModal.classList.add('hidden'));

    // Start demo: store a small demo state in sessionStorage and redirect to residents page
    document.getElementById('demoStart').addEventListener('click', () => {
      const name = document.getElementById('demoName').value.trim() || 'Demo User';
      const role = document.getElementById('demoRole').value || 'Health Worker';
      const demo = { name, role, demo: true, startedAt: new Date().toISOString() };
      sessionStorage.setItem('bhms_demo', JSON.stringify(demo));
      showToast('Demo started — redirecting to app...');
      setTimeout(() => window.location.href = 'residents.php', 1000);
    });

    // Contact button: opens default email client (simple interaction)
    const contactBtnEl = document.getElementById('contactBtn');
    if (contactBtnEl) {
      contactBtnEl.addEventListener('click', () => {
        window.location.href = 'mailto:barangay.health@example.org?subject=BHMS%20Demo%20Request';
      });
    }

    // Schedule by email modal handlers
    const scheduleBtn = document.getElementById('scheduleBtn');
    const scheduleModal = document.getElementById('scheduleModal');
    const schedCancel = document.getElementById('schedCancel');
    const schedSend = document.getElementById('schedSend');

    function openScheduleModal() {
      if (scheduleModal) {
        scheduleModal.classList.remove('hidden');
        scheduleModal.setAttribute('aria-hidden', 'false');
      }
      if (scheduleBtn) scheduleBtn.setAttribute('aria-expanded', 'true');
      const nameInput = document.getElementById('schedName');
      if (nameInput) nameInput.focus();
    }
    function closeScheduleModal() {
      if (scheduleModal) {
        scheduleModal.classList.add('hidden');
        scheduleModal.setAttribute('aria-hidden', 'true');
      }
      if (scheduleBtn) scheduleBtn.setAttribute('aria-expanded', 'false');
    }

    // Close when clicking the overlay/background
    if (scheduleModal) {
      scheduleModal.addEventListener('click', (e) => {
        if (e.target === scheduleModal) closeScheduleModal();
      });
    }

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeScheduleModal();
    });

    if (scheduleBtn) scheduleBtn.addEventListener('click', openScheduleModal);
    if (schedCancel) schedCancel.addEventListener('click', closeScheduleModal);

    if (schedSend) {
      schedSend.addEventListener('click', async () => {
        const name = document.getElementById('schedName').value.trim();
        const date = document.getElementById('schedDate').value;
        const time = document.getElementById('schedTime').value;
        const complaint = document.getElementById('schedMsg').value.trim();
        const residentEmail = document.getElementById('schedEmail') ? document.getElementById('schedEmail').value.trim() : '';

        if (!name) {
          showToast('Please enter the resident name');
          return;
        }
        if (!residentEmail) {
          showToast('Please enter your email (required)');
          return;
        }

        // Optional: Try to lookup resident by name to auto-fill email if available
        let emailLower = (residentEmail || '').toLowerCase();
        let emailExists = emailLower && RESIDENTS.some(r => r.email && r.email.toLowerCase() === emailLower);
        if (!emailExists && name) {
          // Attempt server-side lookup by resident name (use first suggestion's email if present)
          try {
            const resp = await fetch('fetch_resident_suggestions.php?q=' + encodeURIComponent(name));
            if (resp.ok) {
              const list = await resp.json().catch(() => []);
              if (Array.isArray(list) && list.length && list[0].email) {
                // Found a matching resident - suggest their email
                const suggestedEmail = list[0].email;
                if (!residentEmail || residentEmail.trim() === '') {
                  residentEmail = suggestedEmail;
                  document.getElementById('schedEmail').value = residentEmail;
                }
              }
            }
          } catch (e) { console.error('server lookup failed', e); }
        }

        // Allow any valid email format (no strict validation against resident records)

        const payload = { name, date, time, complaint, email: residentEmail };

        try {
          const res = await fetch('send-consultation-request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          });
          const data = await res.json().catch(() => ({}));
          if (res.ok && data.success) {
            showToast(data.message || 'Successfully sent');
            closeScheduleModal();
            return;
          }
          // If server did not confirm, fallback to opening mail client
          const to = 'brgysanisidrohealth@gmail.com';
          const subject = 'Consultation Request' + (name ? ' - ' + name : '');
          const bodyLines = [];
          bodyLines.push('Name: ' + name);
          bodyLines.push('Requester Email: ' + residentEmail);
          if (date || time) bodyLines.push('Preferred schedule: ' + (date ? date : '') + (time ? ' ' + time : ''));
          if (complaint) bodyLines.push('Complaint: ' + complaint);
          const body = encodeURIComponent(bodyLines.join('\r\n'));
          const gmailUrl = `https://mail.google.com/mail/u/0/?view=cm&fs=1&to=${encodeURIComponent(to)}&su=${encodeURIComponent(subject)}&body=${body}`;
          window.open(gmailUrl, '_blank');
          showToast('Opening Gmail compose...');
          closeScheduleModal();
        } catch (err) {
          console.error(err);
          const to = 'brgysanisidrohealth@gmail.com';
          const subject = 'Consultation Request' + (name ? ' - ' + name : '');
          const bodyLines = [];
          bodyLines.push('Name: ' + name);
          bodyLines.push('Requester Email: ' + residentEmail);
          if (date || time) bodyLines.push('Preferred schedule: ' + (date ? date : '') + (time ? ' ' + time : ''));
          if (complaint) bodyLines.push('Complaint: ' + complaint);
          const body = encodeURIComponent(bodyLines.join('\r\n'));
          const gmailUrl = `https://mail.google.com/mail/u/0/?view=cm&fs=1&to=${encodeURIComponent(to)}&su=${encodeURIComponent(subject)}&body=${body}`;
          window.open(gmailUrl, '_blank');
          showToast('Unable to send from server — opening Gmail compose');
          closeScheduleModal();
        }
      });
    }

    // Toast helper
    function showToast(msg, ms = 2500) {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.classList.remove('hidden');
      setTimeout(() => t.classList.add('hidden'), ms);
    }

    // Server-provided counts (from PHP)
    const SERVER_RESIDENTS_COUNT = <?php echo json_encode($residentsCount); ?>;
    const SERVER_CONSULTS_COUNT  = <?php echo json_encode($consultsCount); ?>;
    const SESSION_USER = <?php echo json_encode($sessionUser); ?>;

    // Resident list (name + email) for autofill in the schedule modal
    const RESIDENTS = <?php
      // prepare a small array of name/email pairs
      $pairs = array_map(function($r){
        return [
          'name' => isset($r['name']) ? $r['name'] : (isset($r['full_name']) ? $r['full_name'] : ''),
          'email' => isset($r['email']) ? $r['email'] : ''
        ];
      }, $residents ?: []);
      echo json_encode($pairs);
    ?>;

    // When user enters a resident name, try to autofill the requester email if we have a match.
    // If the inlined `RESIDENTS` array is empty (no residents.json), fall back to the server
    // endpoint `fetch_resident_suggestions.php?q=...` and use the first suggestion's email.
    (function residentNameAutofill(){
      const nameEl = document.getElementById('schedName');
      const emailEl = document.getElementById('schedEmail');
      if (!nameEl || !emailEl) return;

      async function serverLookup(q) {
        try {
          const res = await fetch('fetch_resident_suggestions.php?q=' + encodeURIComponent(q));
          if (!res.ok) return null;
          const list = await res.json().catch(() => []);
          if (Array.isArray(list) && list.length) return list[0];
        } catch (e) { console.error('resident lookup failed', e); }
        return null;
      }

      async function tryFill() {
        const v = nameEl.value.trim();
        if (!v) return;
        const vLower = v.toLowerCase();

        // Try in-memory list first (server-provided `residents.json` mapping)
        let found = RESIDENTS.find(r => {
          const n = (r.name || r.full_name || '').toLowerCase();
          return n && (n === vLower || n.startsWith(vLower) || n.includes(vLower)) && r.email;
        });

        // If not found in-memory, query server for suggestions
        if (!found) {
          const s = await serverLookup(v);
          if (s && (s.email || s.contact_no)) found = { name: s.full_name || s.name || (s.first_name ? (s.first_name + ' ' + s.last_name) : ''), email: s.email || '' };
        }

        if (found && found.email) {
          emailEl.value = found.email;
        }
      }

      nameEl.addEventListener('blur', tryFill);
      nameEl.addEventListener('input', (() => {
        let t = null;
        return function(){
          clearTimeout(t);
          t = setTimeout(tryFill, 400);
        }
      })());
    })();

    // Demo counters: reads localStorage/residents (if present) then fallback to server counts
    (function loadCounts(){
      // If there is a logged-in session user on server, reflect that (small visual
      if (SESSION_USER) {
        // optionally show username in UI later
      }

      // Try session storage first (demo)
      const demo = sessionStorage.getItem('bhms_demo');
      if (demo) {
        document.getElementById('countResidents').textContent = '—';
        document.getElementById('countConsults').textContent = '—';
        return;
      }

      // If the app has localStorage 'residents', use it; otherwise use server counts provided by PHP
      const ls = JSON.parse(localStorage.getItem('residents') || '[]');
      if (ls.length) {
        document.getElementById('countResidents').textContent = ls.length;
        //
        const lc = JSON.parse(localStorage.getItem('consultations') || '[]');
        document.getElementById('countConsults').textContent = lc.length;
        return;
      }

      // Use server-side counts from PHP as fallback
      document.getElementById('countResidents').textContent = SERVER_RESIDENTS_COUNT;
      document.getElementById('countConsults').textContent = SERVER_CONSULTS_COUNT;
    })();
  </script>
</body>
</html>