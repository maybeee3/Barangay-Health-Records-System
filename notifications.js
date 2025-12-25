(function(){
  // Shared notifications script: fetch upcoming consultations (next 2 hours)
  // and add them to any notification panels found on the page.

  async function fetchUpcoming(){
    try {
      const res = await fetch('/Health-Monitoring/fetch_upcoming_consultations.php', {cache: 'no-store'});
      if (!res.ok) return [];
      const data = await res.json();
      return Array.isArray(data) ? data : [];
    } catch(e){ console.error('fetchUpcoming error', e); return []; }
  }

  function escapeHtml(str){ if (str === null || str === undefined) return ''; return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }

  function insertUpcomingItems(items){
    if (!items || !items.length) return;
    let dismissed = [];
    let seen = [];
    try { dismissed = JSON.parse(localStorage.getItem('dismissed_consults') || '[]'); } catch(e){ dismissed = []; }
    try { seen = JSON.parse(localStorage.getItem('seen_consults') || '[]'); } catch(e){ seen = []; }

    const itemsToShow = items.filter(it => !dismissed.includes(String(it.id)));
    if (!itemsToShow.length) return;

    const itemsToNotify = itemsToShow.filter(it => !seen.includes(String(it.id)));

    const notifPanelList = document.getElementById('notifPanelList');
    const recentContainer = document.getElementById('recentNotifications');
    const notifCountEl = document.getElementById('notifCount');

    itemsToShow.forEach(item => {
      const when = (item.date_of_consultation || '') + (item.consultation_time ? (' • ' + item.consultation_time) : '');
      const name = item.resident_name || ('Resident #' + (item.resident_id || ''));
      const consultant = item.consulting_doctor || 'Not specified';

      const icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-bhms" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/></svg>';
      const node = document.createElement('div');
      node.className = 'p-3 rounded-lg bg-yellow-50 flex gap-3 items-start hover:bg-yellow-100 cursor-pointer border border-yellow-100';
      node.innerHTML = `<div class="flex-shrink-0">${icon}</div>
                        <div class="flex-1">
                          <div class="font-semibold">${escapeHtml(name)} <span class="text-xs text-gray-400">• Upcoming</span></div>
                          <div class="text-xs text-gray-500 mt-1">${escapeHtml(when)} • Consultant: ${escapeHtml(consultant)}</div>
                        </div>
                        <div class="text-xs text-gray-400">Soon</div>`;

      if (item.resident_id) {
        node.addEventListener('click', ()=>{
          try {
            const list = JSON.parse(localStorage.getItem('seen_consults') || '[]');
            if (!list.includes(String(item.id))) { list.push(String(item.id)); localStorage.setItem('seen_consults', JSON.stringify(list)); }
          } catch(e){}
          // keep visible but mark seen
          window.open('view-resident.php?id=' + encodeURIComponent(item.resident_id), '_blank');
        });
      }

      if (notifPanelList) {
        node.setAttribute('data-consult-id', String(item.id));
        notifPanelList.insertBefore(node, notifPanelList.firstChild);
      }

      if (recentContainer){
        const li = document.createElement('li');
        li.className = 'p-3 rounded-lg bg-yellow-50 flex justify-between items-start';
        li.innerHTML = `<div><strong>${escapeHtml(name)}</strong> — <span class="text-sm text-gray-500">Upcoming consultation</span><div class="text-xs text-gray-400 mt-1">${escapeHtml(when)} • ${escapeHtml(consultant)}</div></div><div class="text-xs text-gray-400">Soon</div>`;
        li.addEventListener('click', ()=>{
          try {
            const list = JSON.parse(localStorage.getItem('seen_consults') || '[]');
            if (!list.includes(String(item.id))) { list.push(String(item.id)); localStorage.setItem('seen_consults', JSON.stringify(list)); }
          } catch(e){}
          if (item.resident_id) window.open('view-resident.php?id=' + encodeURIComponent(item.resident_id), '_blank');
        });
        li.setAttribute('data-consult-id', String(item.id));
        recentContainer.insertBefore(li, recentContainer.firstChild);
      }
    });

    // update badge with number of unseen items
    if (notifCountEl){
      const delta = itemsToNotify.length;
      notifCountEl.textContent = (parseInt(notifCountEl.textContent || '0', 10) + delta).toString();
      if (delta > 0) notifCountEl.classList.remove('hidden');
    }

    // Do NOT auto-open the panel or show popups; only update the badge/count.
    // This keeps notifications visible but prevents automatic popups across pages.
    // (Manual open via the bell button still displays the panel.)
  }

  // Ensure items present in recentNotifications also exist in notifPanelList.
  function syncRecentToPanel(){
    const recentContainer = document.getElementById('recentNotifications');
    const notifPanelList = document.getElementById('notifPanelList');
    if (!recentContainer || !notifPanelList) return;

    const panelTexts = Array.from(notifPanelList.children).map(n => (n.textContent||'').trim().slice(0,200));

    Array.from(recentContainer.children).forEach(li => {
      try {
        const id = li.getAttribute && li.getAttribute('data-consult-id');
        // if we have an id and panel already has it, skip
        if (id && notifPanelList.querySelector('[data-consult-id="' + id + '"]')) return;

        const text = (li.textContent||'').trim().slice(0,200);
        if (panelTexts.some(t => t && text && (t.includes(text) || text.includes(t)))) return;

        // create a panel-style node from the recent li
        const node = document.createElement('div');
        node.className = 'p-3 rounded-lg bg-gray-50 flex gap-3 items-start hover:bg-gray-100 cursor-pointer';
        node.innerHTML = li.innerHTML;
        if (id) node.setAttribute('data-consult-id', id);
        notifPanelList.insertBefore(node, notifPanelList.firstChild);
      } catch(e){/* ignore individual sync errors */}
    });
  }

  // Observe recentNotifications for changes and keep the panel synced.
  function observeRecentAndSync(){
    const recentContainer = document.getElementById('recentNotifications');
    if (!recentContainer) return;

    // Initial sync in case recent items were already rendered after this script loaded
    try { syncRecentToPanel(); } catch(e){}

    // Debounce helper
    let timer = null;
    const debouncedSync = ()=>{
      if (timer) clearTimeout(timer);
      timer = setTimeout(()=>{ try { syncRecentToPanel(); } catch(e){}; timer = null; }, 120);
    };

    // If MutationObserver is available, use it to respond to dynamic renders
    if (window.MutationObserver){
      const mo = new MutationObserver(debouncedSync);
      mo.observe(recentContainer, { childList: true, subtree: true });
      // also listen for attribute changes that may contain data-consult-id
      mo.observe(recentContainer, { attributes: true, subtree: true });
    } else {
      // Fallback: poll occasionally
      setInterval(debouncedSync, 1000);
    }
  }

  document.addEventListener('DOMContentLoaded', async function(){
    // Always observe & sync recent -> panel so items shown in Recent are also in the Notifications panel
    try { observeRecentAndSync(); } catch(e){}

    // If the page already provided serverUpcoming via inline code, skip fetching to avoid duplication
    if (window._serverUpcomingProvided) return;

    const upcoming = await fetchUpcoming();
    insertUpcomingItems(upcoming);
    try { syncRecentToPanel(); } catch(e){}
  });

})();