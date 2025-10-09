// Attendance checker: builds list from Employees (formerly Workers) table and allows marking statuses per day
(function(){
  function qs(sel, el=document){ return el.querySelector(sel) }
  function qsa(sel, el=document){ return Array.from(el.querySelectorAll(sel)) }

  // Parse existing employees rows into a simple model
  function getWorkers(){
  // Find the specific card that has the "Employees List" (or legacy "Workers List") header
  const listHeaderMatch = (txt) => /Employees List|Workers List/i.test(txt || '');
  const listCard = qsa('.card').find(c => listHeaderMatch(qs('.card-header', c)?.textContent));
    if(!listCard) return [];
    const rows = qsa('table tbody tr', listCard);
    return rows.map(r=>{
      const tds = qsa('td', r);
      const name = tds[1]?.textContent?.trim() || 'Unknown';
      const position = tds[2]?.textContent?.trim() || '';
      const store = tds[3]?.textContent?.trim() || '';
      const base = `${name}|${position}|${store}`;
      // Prefer real numeric id from data-worker-id if present
      const numericId = r.getAttribute('data-worker-id');
      const id = numericId ? String(numericId) : (base ? base.toLowerCase().replace(/\s+/g,'-') : Math.random().toString(36).slice(2));
      return {
        id,
        name,
        position,
        store,
        avatar: qs('img', r)?.getAttribute('src') || ''
      }
    })
  }

  // Local storage key per date
  function key(date){ return 'attendance:'+date }
  function loadLocal(date){ try { return JSON.parse(localStorage.getItem(key(date))||'{}') } catch(e){ return {} } }
  function saveLocal(date, data){ localStorage.setItem(key(date), JSON.stringify(data)) }

  async function loadServer(date){
    try{
      const res = await fetch('workers.php?action=load_attendance&date='+encodeURIComponent(date), { credentials:'same-origin' });
      const j = await res.json();
      if(j && j.ok) return j.data || {};
    }catch(e){}
    return null; // indicates server load failed
  }

  async function saveServer(date, records){
    try{
      const res = await fetch('workers.php?action=save_attendance', {
        method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ date, records })
      });
      const j = await res.json();
      return !!(j && j.ok);
    }catch(e){ return false }
  }

  async function render(date){
    const table = qs('#attendanceTable tbody'); if(!table) return;
    let data = await loadServer(date);
    if(data === null) { data = loadLocal(date); }
  const workers = getWorkers();
    const search = (qs('#attSearch')?.value||'').toLowerCase();
    const selectAll = qs('#attSelectAll'); if(selectAll) selectAll.checked = false;
    table.innerHTML = '';
  workers.filter(w=>!search || w.name.toLowerCase().includes(search) || w.position.toLowerCase().includes(search) || w.store.toLowerCase().includes(search))
      .forEach(w=>{
        const rec = data[w.id] || { status:'', in:'', out:'', notes:'' };
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><input type="checkbox" class="att-select"></td>
          <td><img src="${w.avatar}" class="avatar me-2"> ${w.name}</td>
          <td>${w.position}</td>
          <td>${w.store}</td>
          <td>
            <select class="form-select form-select-sm att-status">
              <option value="" ${rec.status===''?'selected':''}>—</option>
              <option ${rec.status==='Present'?'selected':''}>Present</option>
              <option ${rec.status==='Late'?'selected':''}>Late</option>
              <option ${rec.status==='Absent'?'selected':''}>Absent</option>
              <option ${rec.status==='Leave'?'selected':''}>Leave</option>
            </select>
          </td>
          <td><input type="time" class="form-control form-control-sm att-in" value="${rec.in||''}"></td>
          <td><input type="time" class="form-control form-control-sm att-out" value="${rec.out||''}"></td>
          <td><input type="text" class="form-control form-control-sm att-notes" value="${rec.notes||''}"></td>`;
          tr.dataset.id = w.id; // numeric when available
        table.appendChild(tr);
      });
  }

  function collectLocal(date){
    const data = loadLocal(date);
    qsa('#attendanceTable tbody tr').forEach(tr=>{
      const id = tr.dataset.id;
      data[id] = {
        status: qs('.att-status', tr).value,
        in: qs('.att-in', tr).value,
        out: qs('.att-out', tr).value,
        notes: qs('.att-notes', tr).value
      };
    });
    saveLocal(date, data);
    return data;
  }

  function setBulkStatus(status){
    qsa('#attendanceTable tbody tr').forEach(tr=>{
      const cb = qs('.att-select', tr);
      if(cb && cb.checked){ qs('.att-status', tr).value = status; }
    });
  }

  function wire(){
    const dateInput = qs('#attDate'); if(dateInput){
      const today = new Date(); dateInput.value = today.toISOString().slice(0,10);
      dateInput.addEventListener('change', ()=>render(dateInput.value));
    }

  const search = qs('#attSearch'); if(search){ search.addEventListener('input', ()=> render(qs('#attDate').value)) }

    const selectAll = qs('#attSelectAll'); if(selectAll){
      selectAll.addEventListener('change', ()=>{
        qsa('.att-select').forEach(cb=> cb.checked = selectAll.checked);
      })
    }

    qsa('.btn-group [data-status]').forEach(btn=>{
      btn.addEventListener('click', async ()=>{
        setBulkStatus(btn.dataset.status);
        const date = qs('#attDate').value;
        const records = toRecords(date);
        const ok = await saveServer(date, records);
        if(!ok){ collectLocal(date); }
      })
    })

    const markAll = qs('#markAllPresent'); if(markAll){
      markAll.addEventListener('click', async ()=>{
        setBulkStatus('Present');
        const date = qs('#attDate').value;
        const records = toRecords(date);
        const ok = await saveServer(date, records);
        if(!ok){ collectLocal(date); }
      })
    }

    const clear = qs('#clearAttendance'); if(clear){
      clear.addEventListener('click', ()=>{
        localStorage.removeItem(key(qs('#attDate').value));
        render(qs('#attDate').value);
      })
    }

    // Autosave on changes
    document.addEventListener('change', async (e)=>{
      if(e.target.closest('#attendanceTable')){
        const date = qs('#attDate').value;
        const records = toRecords(date);
        const ok = await saveServer(date, records);
        if(!ok){ collectLocal(date); setSaveStatus('Saved locally (offline)', 'text-warning'); } else { setSaveStatus('Saved', 'text-light'); }
      }
    });
  }

  function toRecords(date){
    const rows = qsa('#attendanceTable tbody tr');
    const res = [];
    rows.forEach((tr, idx)=>{
      const id = tr.dataset.id;
      const wid = parseInt(id, 10);
      res.push({
        worker_id: Number.isFinite(wid) ? wid : null,
        status: qs('.att-status', tr).value,
        in: qs('.att-in', tr).value,
        out: qs('.att-out', tr).value,
        notes: qs('.att-notes', tr).value
      })
    });
    return res;
  }

  async function loadHistory(){
    const body = qs('#attHistoryBody'); if(!body) return;
    body.innerHTML = '<tr><td colspan="6">Loading...</td></tr>';
    try{
      const res = await fetch('workers.php?action=list_attendance_history', { credentials:'same-origin' });
      const j = await res.json();
      if(!(j && j.ok)) throw new Error('bad');
      const rows = j.rows || [];
      body.innerHTML = rows.map(r=>`
        <tr class="att-history-row" data-date="${r.date}">
          <td>${r.date}</td>
          <td>${r.present||0}</td>
          <td>${r.late||0}</td>
          <td>${r.absent||0}</td>
          <td>${r.leave||0}</td>
          <td>${r.total||0}</td>
        </tr>`).join('') || '<tr><td colspan="6">No history</td></tr>';
      // Make rows clickable to open modal
      qsa('.att-history-row').forEach(tr=>{
        tr.style.cursor = 'pointer';
        tr.addEventListener('click', ()=> openHistory(tr.dataset.date));
      })
    }catch(e){
      body.innerHTML = '<tr><td colspan="6">No history available</td></tr>';
    }
  }

  async function openHistory(date){
    if(!date) return;
    const tbody = qs('#historyModalBody'); if(!tbody) return;
    qs('#historyModalDate').textContent = date;
    tbody.innerHTML = '<tr><td colspan="7">Loading...</td></tr>';
    try{
      const res = await fetch('workers.php?action=get_attendance_by_date&date='+encodeURIComponent(date), { credentials:'same-origin' });
      const j = await res.json();
      if(!(j && j.ok)) throw new Error('bad');
      const rows = j.rows || [];
      tbody.innerHTML = rows.map(r=>`
        <tr>
          <td><img src="${r.avatar_url || ('https://ui-avatars.com/api/?name='+encodeURIComponent(r.name||''))}" class="avatar me-2"> ${r.name||''}</td>
          <td>${r.role||''}</td>
          <td>${r.store||''}</td>
          <td>${r.status||''}</td>
          <td>${r.time_in||''}</td>
          <td>${r.time_out||''}</td>
          <td>${r.notes||''}</td>
        </tr>
      `).join('') || '<tr><td colspan="7">No entries for this date</td></tr>';
    }catch(e){
      tbody.innerHTML = '<tr><td colspan="7">Failed to load</td></tr>';
    }
    const modalEl = document.getElementById('historyModal');
    if(modalEl){ new bootstrap.Modal(modalEl).show(); }
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    wire();
    const d = (qs('#attDate')?.value) || new Date().toISOString().slice(0,10);
    render(d);
    const btn = qs('#refreshHistory'); if(btn){ btn.addEventListener('click', loadHistory) }
    loadHistory();
    const saveBtn = qs('#saveAttendance'); if(saveBtn){ saveBtn.addEventListener('click', async ()=>{
      const date = qs('#attDate').value;
      const records = toRecords(date);
      setSaveStatus('Saving...', 'text-light');
      const ok = await saveServer(date, records);
      if(!ok){ collectLocal(date); setSaveStatus('Saved locally (offline)', 'text-warning'); }
      else { setSaveStatus('Saved', 'text-light'); loadHistory(); }
    })}
    const openBtn = qs('#openHistory'); if(openBtn){
      openBtn.addEventListener('click', ()=>{
        const hd = qs('#historyDate')?.value;
        if(hd) openHistory(hd);
      })
    }
  })

  function setSaveStatus(msg, cls){
    const el = document.getElementById('saveStatus'); if(!el) return;
    el.className = cls||'';
    el.textContent = msg||'';
    if(msg){ setTimeout(()=>{ el.textContent=''; }, 3000) }
  }
})();