// Simple admin dashboard script powering KPIs, tables, chart, and activity feed
(function() {
    // Sidebar active link highlighting
    document.querySelectorAll('.sidebar a').forEach(link => {
        link.addEventListener('click', function() {
            document.querySelectorAll('.sidebar a').forEach(l => l.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Dummy in-memory data (replace with real API calls later)
    const state = {
        stores: [
            { name: 'Main Street', location: 'Downtown', manager: 'Ava Cole', status: 'Open' },
            { name: 'North Hub', location: 'Uptown', manager: 'Liam Park', status: 'Open' },
            { name: 'East Point', location: 'Riverside', manager: 'Noah Kim', status: 'Closed' },
            { name: 'West Gate', location: 'Westside', manager: 'Mia Chen', status: 'Open' },
            { name: 'South Bay', location: 'Harbor', manager: 'Ethan Diaz', status: 'Open' }
        ],
        workers: [
            { name: 'John Doe', role: 'Cashier', store: 'Main Street', status: 'Active' },
            { name: 'Jane Smith', role: 'Manager', store: 'North Hub', status: 'Active' },
            { name: 'Sam Green', role: 'Stock', store: 'East Point', status: 'On Leave' },
            { name: 'Sara Lee', role: 'Security', store: 'West Gate', status: 'Active' },
            { name: 'Tom Brown', role: 'Cashier', store: 'South Bay', status: 'Active' }
            // ... add more to reach 32 if desired
        ],
        
        projects: [
            { project: 'Renovation', store: 'Main Street', owner: 'Ava Cole', due: '2025-11-01', status: 'In Progress' },
            { project: 'New Signage', store: 'West Gate', owner: 'Design Team', due: '2025-10-20', status: 'Planning' },
            { project: 'Inventory Revamp', store: 'North Hub', owner: 'Ops', due: '2025-12-10', status: 'In Progress' }
        ],
        activity: []
    };

    const API_URL = '../php/admin_api.php'; // relative to admin pages

    async function safeFetch(entity) {
        try {
            const res = await fetch(`${API_URL}?entity=${encodeURIComponent(entity)}`, { credentials: 'include' });
            if (!res.ok) return [];
            return await res.json();
        } catch (e) {
            return [];
        }
    }

    // Helpers
    function qs(id) { return document.getElementById(id); }
    function el(tag, cls, html) { const e = document.createElement(tag); if (cls) e.className = cls; if (html!==undefined) e.innerHTML = html; return e; }

    function renderKpis() {
        qs('kpi-stores').textContent = state.stores.length;
        qs('kpi-workers').textContent = state.workers.length;
        
        qs('kpi-projects').textContent = state.projects.length;
    }

    function badge(text) {
        const t = text.toLowerCase();
        if (['open','active','on duty','in progress','planning'].includes(t)) return `<span class="badge badge-live">${text}</span>`;
        if (['closed','off','on leave'].includes(t)) return `<span class="badge badge-off">${text}</span>`;
        return `<span class="badge badge-soft">${text}</span>`;
    }

    function renderTables() {
        const storesT = qs('storesTable');
        storesT.innerHTML = '';
        state.stores.forEach(s => {
            const tr = el('tr','',
                `<td>${s.name}</td><td>${s.location}</td><td>${s.manager}</td><td>${badge(s.status)}</td>` +
                `<td class="text-end"><button class="btn btn-sm btn-outline-secondary">Edit</button></td>`);
            storesT.appendChild(tr);
        });

        const workersT = qs('workersTable');
        workersT.innerHTML = '';
        state.workers.forEach(w => {
            const tr = el('tr','',
                `<td>${w.name}</td><td>${w.role}</td><td>${w.store}</td><td>${badge(w.status)}</td>` +
                `<td class="text-end"><button class="btn btn-sm btn-outline-secondary">Edit</button></td>`);
            workersT.appendChild(tr);
        });

        const projT = qs('projectsTable');
        projT.innerHTML = '';
        state.projects.forEach(p => {
            const tr = el('tr','',
                `<td>${p.project || p.name}</td><td>${p.store}</td><td>${p.owner}</td><td>${p.due || p.due_date || ''}</td><td>${badge(p.status)}</td>` +
                `<td class="text-end"><button class="btn btn-sm btn-outline-secondary">Edit</button></td>`);
            projT.appendChild(tr);
        });
    }

    function addActivity(iconCls, colorCls, text) {
        state.activity.unshift({ iconCls, colorCls, text, ts: new Date() });
        if (state.activity.length > 8) state.activity.pop();
        renderActivity();
    }

    function timeAgo(date) {
        const diff = Math.floor((Date.now() - date.getTime()) / 60000); // mins
        if (diff < 1) return 'just now';
        if (diff < 60) return `${diff}m ago`;
        const h = Math.floor(diff/60); if (h < 24) return `${h}h ago`;
        const d = Math.floor(h/24); return `${d}d ago`;
    }

    function renderActivity() {
        const ul = qs('activityList');
        ul.innerHTML = '';
        state.activity.forEach(a => {
            const li = el('li','list-group-item',
                `<span class="icon ${a.colorCls}"><i class="${a.iconCls}"></i></span>
                 <div>
                     <div>${a.text}</div>
                     <small class="text-muted">${timeAgo(a.ts)}</small>
                 </div>`);
            ul.appendChild(li);
        });
    }

    // Chart
    let chart;
    function generateSeries(days) {
        // simple demo series: workers and projects counts varying
        const labels = [];
        const workers = [];
        const projects = [];
        for (let i = days-1; i >= 0; i--) {
            const d = new Date(); d.setDate(d.getDate()-i);
            labels.push(`${d.getMonth()+1}/${d.getDate()}`);
            workers.push(20 + Math.round(Math.random()*15));
            projects.push(2 + Math.round(Math.random()*3));
        }
        return { labels, workers, projects };
    }

    function initChart(days) {
        const ctx = document.getElementById('mainChart');
        const { labels, workers, projects } = generateSeries(days);
        chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    { label: 'Workers', data: workers, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.15)', tension:.35, fill:true },
                    { label: 'Projects', data: projects, borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,.15)', tension:.35, fill:true }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: true } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    function updateChart(days) {
        const s = generateSeries(days);
        chart.data.labels = s.labels;
        chart.data.datasets[0].data = s.workers;
        chart.data.datasets[1].data = s.projects;
        chart.update();
    }

    function bindFormEvents() {
        const storeBtn = document.getElementById('saveStore');
        if (storeBtn) storeBtn.addEventListener('click', () => {
            const name = document.getElementById('storeName').value.trim();
            const location = document.getElementById('storeLocation').value.trim();
            const manager = document.getElementById('storeManager').value.trim();
            if (!name) return;
            state.stores.push({ name, location, manager, status: 'Open' });
            renderKpis(); renderTables();
            addActivity('bi bi-shop', 'icon-blue', `New store "${name}" added`);
            bootstrap.Modal.getInstance(document.getElementById('modalStore')).hide();
        });

        const workerBtn = document.getElementById('saveWorker');
        if (workerBtn) workerBtn.addEventListener('click', () => {
            const name = document.getElementById('workerName').value.trim();
            const role = document.getElementById('workerRole').value.trim();
            const store = document.getElementById('workerStore').value.trim();
            if (!name) return;
            state.workers.push({ name, role, store, status: 'Active' });
            renderKpis(); renderTables();
            addActivity('bi bi-person-plus', 'icon-green', `Worker ${name} added`);
            bootstrap.Modal.getInstance(document.getElementById('modalWorker')).hide();
        });

        

        const projBtn = document.getElementById('saveProject');
        if (projBtn) projBtn.addEventListener('click', () => {
            const project = document.getElementById('projectName').value.trim();
            const store = document.getElementById('projectStore').value.trim();
            const due = document.getElementById('projectDue').value;
            if (!project) return;
            state.projects.push({ project, store, owner: 'You', due, status: 'In Progress' });
            renderKpis(); renderTables();
            addActivity('bi bi-kanban', 'icon-purple', `Project "${project}" created`);
            bootstrap.Modal.getInstance(document.getElementById('modalProject')).hide();
        });
    }

    function bindTimeframe() {
        const tf = document.getElementById('timeframe');
        const label = document.getElementById('chart-range');
        if (!tf) return;
        tf.addEventListener('change', () => {
            const days = parseInt(tf.value, 10) || 7;
            label.textContent = `Last ${days} days`;
            updateChart(days);
        });
    }

    async function bootstrapDashboard() {
        // Try live data first; fallback to dummy state if API unavailable/unauthorized
        const [stores, workers, projects, notes] = await Promise.all([
            safeFetch('stores'), safeFetch('workers'), safeFetch('projects'), safeFetch('notifications')
        ]);
        if (stores.length) {
            state.stores = stores.map(s => ({
                name: s.name, location: s.location || '', manager: s.manager || '', status: s.status || 'Open'
            }));
        }
        if (workers.length) {
            state.workers = workers.map(w => ({
                name: w.name, role: w.role || '', store: w.store || '', status: w.status || 'Active'
            }));
        }
        if (projects.length) {
            state.projects = projects.map(p => ({
                project: p.name || p.project, store: p.store || '', owner: p.owner || '', due: p.due_date || p.due || '', status: p.status || 'Planning'
            }));
        }

        renderKpis();
        renderTables();

        // Seed activity: use notifications if available, else fallback examples
        if (notes.length) {
            const iconMap = { info: 'bi bi-info-circle', success: 'bi bi-check-circle', warning: 'bi bi-exclamation-triangle', error: 'bi bi-x-circle' };
            const colorMap = { info: 'icon-blue', success: 'icon-green', warning: 'icon-orange', error: 'icon-red' };
            notes.slice(0, 6).forEach(n => addActivity(iconMap[n.type] || 'bi bi-bell', colorMap[n.type] || 'icon-blue', n.title));
        } else {
            addActivity('bi bi-shop', 'icon-blue', 'West Gate store reopened');
            addActivity('bi bi-people', 'icon-green', '3 new workers onboarded');
            addActivity('bi bi-kanban', 'icon-purple', 'Inventory Revamp moved to In Progress');
        }

        // Chart
        initChart(7);
        // Events
        bindFormEvents();
        bindTimeframe();
    }

    document.addEventListener('DOMContentLoaded', bootstrapDashboard);
})();