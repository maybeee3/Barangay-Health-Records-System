(function(){
  // Fetch upcoming consultations and render into the header dropdown
  const endpoint = '/Health-Monitoring/fetch_upcoming_consultations.php';
  const btn = document.getElementById('notifBtn'); // repurposed
  const countEl = document.getElementById('notifCount');
  // create dropdown if not present
  function ensureDropdown(){
    let dd = document.getElementById('upcomingDropdown');
    if (!dd){
      dd = document.createElement('div');
      dd.id = 'upcomingDropdown';
      dd.className = 'absolute right-0 mt-2 w-80 max-w-sm bg-white rounded-xl shadow-lg hidden z-50 overflow-auto';
      dd.style.maxHeight = '60vh';
      // inner content
      dd.innerHTML = '<div class="p-4 border-b"><div class="font-semibold">Upcoming Appointments</div></div><div id="upcomingList" class="p-2 space-y-2 text-sm text-gray-700"><div class="p-3 text-gray-500">Loading...</div></div><div class="p-2 text-right"><a href="consultations.php" class="text-xs text-bhms">See all</a></div>';
      // attach next to header (try to place inside header's container)
      const header = document.querySelector('header .max-w-full');
      if (header){
        header.appendChild(dd);
      } else {
        document.body.appendChild(dd);
      }
    }
    return dd;
  }

  async function fetchUpcoming(){
    try {
      const res = await fetch(endpoint, {cache: 'no-store'});
      if (!res.ok) return [];
      const data = await res.json();
      return Array.isArray(data) ? data : [];
    } catch(e){ console.error('upcoming fetch error', e); return []; }
  }

  function formatItem(it){
    const when = (it.date_of_consultation || '') + (it.consultation_time ? (' • ' + it.consultation_time) : '');
    const name = it.resident_name || ('Resident #' + (it.resident_id || ''));
    const consultant = it.consulting_doctor || 'Not specified';
    return `<div class="p-3 rounded-lg bg-yellow-50 flex gap-3 items-start">
              <div class="flex-1">
                <div class="font-semibold">${escapeHtml(name)}</div>
                <div class="text-xs text-gray-500 mt-1">${escapeHtml(when)} • ${escapeHtml(consultant)}</div>
              </div>
              <div class="text-xs text-gray-400">Soon</div>
            </div>`;
  }

  function escapeHtml(str){ if (str === null || str === undefined) return ''; return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }

  async function refreshUpcoming(){
    const dd = ensureDropdown();
    const list = document.getElementById('upcomingList');
    if (!list) return;
    list.innerHTML = '<div class="p-3 text-gray-500">Loading...</div>';
    const items = await fetchUpcoming();
    if (!items.length){
      list.innerHTML = '<div class="p-3 text-gray-500">No upcoming appointments</div>';
      if (countEl) { countEl.classList.add('hidden'); countEl.textContent = '0'; }
      return;
    }
    list.innerHTML = '';
    items.forEach(it => { list.insertAdjacentHTML('beforeend', formatItem(it)); });
    if (countEl){ countEl.textContent = String(items.length); countEl.classList.remove('hidden'); }
  }

  // Toggle dropdown
  function toggleDropdown(e){
    e && e.stopPropagation();
    const dd = ensureDropdown();
    dd.classList.toggle('hidden');
  }

  // hide old notif panel/overlay if present
  function hideOldNotif(){
    const old = document.getElementById('notifPanel'); if (old) old.style.display = 'none';
    const overlay = document.getElementById('notifOverlay'); if (overlay) overlay.style.display = 'none';
  }

  document.addEventListener('DOMContentLoaded', function(){
    hideOldNotif();
    // ensure dropdown exists
    ensureDropdown();
    // initial fetch
    refreshUpcoming();
    // attach click to button (notifBtn is repurposed)
    const b = document.getElementById('notifBtn');
    if (b){ b.setAttribute('title','Upcoming Appointments'); b.addEventListener('click', toggleDropdown); }
    // close on outside click
    document.addEventListener('click', function(ev){
      const dd = document.getElementById('upcomingDropdown');
      if (dd && !dd.classList.contains('hidden')){
        const target = ev.target;
        if (!dd.contains(target) && target !== document.getElementById('notifBtn')){
          dd.classList.add('hidden');
        }
      }
    });
    // refresh every minute
    setInterval(refreshUpcoming, 60*1000);
  });
})();
