<?php
session_start();
if (empty($_SESSION['admin_logged'])) {
  header('Location: admin_login.php');
    exit;
}
require_once __DIR__ . '/../php/db.php';
$db = get_db();

// Helper to ensure attendance table exists (safe no-op if already present)
function ensure_attendance_table($db) {
    $db->query("CREATE TABLE IF NOT EXISTS attendance (
      date date NOT NULL,
      worker_id int NOT NULL,
      status enum('Present','Late','Absent','Leave') DEFAULT NULL,
      time_in time DEFAULT NULL,
      time_out time DEFAULT NULL,
      notes varchar(255) DEFAULT NULL,
      PRIMARY KEY (date, worker_id),
      KEY idx_worker_date (worker_id, date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// Fetch stores for select
$stores = [];
$r = $db->query("SELECT id, name FROM stores ORDER BY name");
while ($row = $r->fetch_assoc()) $stores[] = $row;

// Create worker
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_worker'])) {
    $name = trim($_POST['name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $store_id = isset($_POST['store_id']) ? (int)$_POST['store_id'] : null;
    $contact = trim($_POST['contact'] ?? '');
    $status = $_POST['status'] ?? 'Active';
    $start = $_POST['start_date'] ?? null;
    $prob = $_POST['probation_end'] ?? null;
    $avatar = trim($_POST['avatar_url'] ?? '');
    if ($name !== '') {
        $stmt = $db->prepare("INSERT INTO workers (name, role, store_id, contact, status, start_date, probation_end, avatar_url) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('ssisssss', $name, $role, $store_id, $contact, $status, $start, $prob, $avatar);
        $stmt->execute();
        header('Location: workers.php');
        exit;
    }
}

// Delete worker
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM workers WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header('Location: workers.php');
    exit;
}

// Fetch workers
$sql = "SELECT w.id, w.name, w.role, w.contact, w.status, w.start_date, w.probation_end, w.avatar_url, s.name AS store
        FROM workers w LEFT JOIN stores s ON s.id = w.store_id ORDER BY w.id DESC";
$workers = [];
$res = $db->query($sql);
while ($row = $res->fetch_assoc()) $workers[] = $row;

// Attendance AJAX endpoints
if (isset($_GET['action'])) {
  header('Content-Type: application/json');
  $action = $_GET['action'];
  if ($action === 'load_attendance') {
    ensure_attendance_table($db);
    $date = $_GET['date'] ?? '';
    if (!$date) { echo json_encode(['ok'=>false, 'error'=>'Missing date']); exit; }
    $stmt = $db->prepare("SELECT worker_id, status, time_in, time_out, notes FROM attendance WHERE date = ?");
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = [];
    while ($r = $res->fetch_assoc()) {
      $data[(string)$r['worker_id']] = [
        'status' => $r['status'] ?? '',
        'in' => $r['time_in'] ?? '',
        'out' => $r['time_out'] ?? '',
        'notes' => $r['notes'] ?? ''
      ];
    }
    echo json_encode(['ok'=>true, 'data'=>$data]);
    exit;
  } elseif ($action === 'save_attendance') {
    ensure_attendance_table($db);
    $body = file_get_contents('php://input');
    $json = json_decode($body, true);
    if (!$json || empty($json['date']) || !isset($json['records']) || !is_array($json['records'])) {
      echo json_encode(['ok'=>false, 'error'=>'Invalid payload']); exit;
    }
    $date = $json['date'];
    // Upsert each record
    $stmt = $db->prepare("INSERT INTO attendance (date, worker_id, status, time_in, time_out, notes) VALUES (?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE status=VALUES(status), time_in=VALUES(time_in), time_out=VALUES(time_out), notes=VALUES(notes)");
    $ok = true;
    $errors = [];
    foreach ($json['records'] as $rec) {
      $wid = (int)($rec['worker_id'] ?? 0);
      if ($wid <= 0) continue;
            $status = $rec['status'] ?? null; $status = ($status === '') ? null : $status;
      $tin = $rec['in'] ?? null; $tin = ($tin === '') ? null : $tin;
      $tout = $rec['out'] ?? null; $tout = ($tout === '') ? null : $tout;
      $notes = $rec['notes'] ?? null; $notes = ($notes === '') ? null : $notes;
      $stmt->bind_param('sissss', $date, $wid, $status, $tin, $tout, $notes);
      if (!$stmt->execute()) { $ok = false; $errors[] = $stmt->error; }
    }
    echo json_encode(['ok'=>$ok, 'errors'=>$errors]);
    exit;
  } elseif ($action === 'list_attendance_history') {
    ensure_attendance_table($db);
    // Last 30 days with summary counts
    $res = $db->query("SELECT date,
      SUM(status='Present') AS present,
      SUM(status='Late') AS late,
      SUM(status='Absent') AS absent,
      SUM(status='Leave') AS `leave`,
      COUNT(*) AS total
      FROM attendance GROUP BY date ORDER BY date DESC LIMIT 30");
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode(['ok'=>true, 'rows'=>$rows]);
    exit;
  } elseif ($action === 'get_attendance_by_date') {
    ensure_attendance_table($db);
    $date = $_GET['date'] ?? '';
    if (!$date) { echo json_encode(['ok'=>false, 'error'=>'Missing date']); exit; }
    $stmt = $db->prepare("SELECT a.worker_id, a.status, a.time_in, a.time_out, a.notes,
                   w.name, w.role, w.contact, w.avatar_url, s.name AS store
                FROM attendance a
                LEFT JOIN workers w ON w.id = a.worker_id
                LEFT JOIN stores s ON s.id = w.store_id
                WHERE a.date = ?
                ORDER BY w.name");
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($r = $result->fetch_assoc()) $rows[] = $r;
    echo json_encode(['ok'=>true, 'rows'=>$rows]);
    exit;
  }
  echo json_encode(['ok'=>false, 'error'=>'Unknown action']);
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Workers</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="workers.css">
  <style>
    .avatar{width:32px;height:32px;border-radius:50%;object-fit:cover}
    .avatar-lg{width:96px;height:96px;border-radius:50%;object-fit:cover}
  </style>
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Workers</h3>
    <div>
  <a href="admin_management.php" class="btn btn-outline-secondary">Back to Dashboard</a>
  <a href="admin_logout.php" class="btn btn-outline-danger">Logout</a>
    </div>
  </div>

  <!-- Search & Filter Bar -->
  <?php
    // Build unique role list from current workers for filter options
    $roles = [];
    foreach ($workers as $w) {
      $r = trim((string)$w['role']);
      if ($r !== '' && !in_array($r, $roles, true)) $roles[] = $r;
    }
    sort($roles);
  ?>
  <form class="row g-2 mb-3" id="filterForm" onsubmit="return false;">
    <div class="col-md-3">
      <input type="text" id="filterName" class="form-control" placeholder="Search by name...">
    </div>
    <div class="col-md-2">
      <select id="filterRole" class="form-select">
        <option value="">All Positions</option>
        <?php foreach ($roles as $r): ?>
          <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select id="filterStatus" class="form-select">
        <option value="">All Statuses</option>
        <option value="Active">Active</option>
        <option value="Inactive">Inactive</option>
        <option value="On Leave">On Leave</option>
        <option value="Terminated">Terminated</option>
      </select>
    </div>
    <div class="col-md-2">
      <select id="filterStore" class="form-select">
        <option value="">All Stores</option>
        <?php foreach ($stores as $s): ?>
          <option value="<?= htmlspecialchars($s['name']) ?>"><?= htmlspecialchars($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3 text-end">
      <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#addWorkerModal">Add Worker</button>
    </div>
  </form>

  <div class="card">
    <div class="card-header bg-primary text-white">Workers List</div>
    <div class="table-responsive">
  <table class="table table-hover align-middle mb-0" id="workersTable">
        <thead class="table-light">
          <tr>
            <th>Photo</th>
            <th>Name</th>
            <th>Position</th>
            <th>Store</th>
            <th>Contact</th>
            <th>Status</th>
            <th>Start Date</th>
            <th>Probation End</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($workers as $w): ?>
          <tr data-worker-id="<?= (int)$w['id'] ?>"
              data-name="<?= htmlspecialchars(strtolower($w['name'])) ?>"
              data-role="<?= htmlspecialchars(strtolower($w['role'])) ?>"
              data-store="<?= htmlspecialchars(strtolower($w['store'])) ?>"
              data-status="<?= htmlspecialchars(strtolower($w['status'])) ?>">
            <td>
              <?php if (!empty($w['avatar_url'])): ?>
                <img src="<?= htmlspecialchars($w['avatar_url']) ?>" class="avatar">
              <?php else: ?>
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($w['name']) ?>" class="avatar">
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($w['name']) ?></td>
            <td><?= htmlspecialchars($w['role']) ?></td>
            <td><?= htmlspecialchars($w['store']) ?></td>
            <td><?= htmlspecialchars($w['contact']) ?></td>
            <td><span class="badge <?= $w['status']==='Active'?'bg-success':($w['status']==='On Leave'?'bg-warning text-dark':($w['status']==='Terminated'?'bg-danger':'bg-secondary')) ?>"><?= htmlspecialchars($w['status']) ?></span></td>
            <td><?= htmlspecialchars($w['start_date']) ?></td>
            <td><?= htmlspecialchars($w['probation_end']) ?></td>
            <td class="text-end">
              <button class="btn btn-info btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#viewWorkerModal" data-view='<?= json_encode([
                'name'=>$w['name'],
                'avatar'=> $w['avatar_url'] ?: ('https://ui-avatars.com/api/?name='.urlencode($w['name'])),
                'role'=>$w['role'],
                'store'=>$w['store'],
                'contact'=>$w['contact'],
                'status'=>$w['status'],
                'start_date'=>$w['start_date'],
                'probation_end'=>$w['probation_end']
              ], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>'>View</button>
              <a href="?delete=<?= (int)$w['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this worker?')">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Attendance Checker -->
  <div class="card mt-4">
    <div class="card-header bg-success text-white d-flex align-items-center justify-content-between">
      <span>Attendance</span>
      <div class="d-flex gap-2">
        <input type="date" id="attDate" class="form-control form-control-sm">
        <button class="btn btn-warning btn-sm" id="saveAttendance" type="button">Save</button>
        <button class="btn btn-light btn-sm" id="markAllPresent" type="button">Mark All Present</button>
        <button class="btn btn-outline-light btn-sm" id="clearAttendance" type="button">Clear</button>
        <small id="saveStatus" class="ms-2"></small>
      </div>
    </div>
    <div class="p-3">
      <div class="row g-2 mb-2">
        <div class="col-md-4">
          <input type="text" id="attSearch" class="form-control" placeholder="Search worker...">
        </div>
        <div class="col-md-8 text-end">
          <div class="btn-group btn-group-sm" role="group">
            <button class="btn btn-outline-success" data-status="Present" type="button">Present</button>
            <button class="btn btn-outline-warning" data-status="Late" type="button">Late</button>
            <button class="btn btn-outline-danger" data-status="Absent" type="button">Absent</button>
            <button class="btn btn-outline-secondary" data-status="Leave" type="button">Leave</button>
          </div>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="attendanceTable">
          <thead>
            <tr>
              <th style="width:36px"><input type="checkbox" id="attSelectAll"></th>
              <th>Worker</th>
              <th>Position</th>
              <th>Store</th>
              <th>Status</th>
              <th>Time In</th>
              <th>Time Out</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
            <!-- Populated by workers_attendance.js based on Workers List -->
          </tbody>
        </table>
      </div>
      <div class="mt-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h6 class="mb-0">Attendance History</h6>
          <div class="d-flex align-items-center gap-2">
            <input type="date" id="historyDate" class="form-control form-control-sm">
            <button class="btn btn-sm btn-outline-primary" type="button" id="openHistory">Open</button>
            <button class="btn btn-sm btn-outline-secondary" type="button" id="refreshHistory">Refresh</button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-striped mb-0" id="attHistoryTable">
            <thead class="table-light">
              <tr>
                <th>Date</th>
                <th>Present</th>
                <th>Late</th>
                <th>Absent</th>
                <th>Leave</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody id="attHistoryBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Simple client-side filtering for Workers table
  (function(){
    function qs(s,el=document){return el.querySelector(s)}
    function qsa(s,el=document){return Array.from(el.querySelectorAll(s))}
    function applyFilter(){
      const name = (qs('#filterName')?.value||'').toLowerCase();
      const role = (qs('#filterRole')?.value||'').toLowerCase();
      const status = (qs('#filterStatus')?.value||'').toLowerCase();
      const store = (qs('#filterStore')?.value||'').toLowerCase();
      qsa('#workersTable tbody tr').forEach(tr=>{
        const ok = (!name || tr.dataset.name.includes(name)) &&
                   (!role || tr.dataset.role===role) &&
                   (!status || tr.dataset.status===status) &&
                   (!store || tr.dataset.store===store);
        tr.style.display = ok ? '' : 'none';
      })
    }
    ['#filterName','#filterRole','#filterStatus','#filterStore'].forEach(id=>{
      const el = document.querySelector(id); if(el){ el.addEventListener('input', applyFilter); el.addEventListener('change', applyFilter); }
    })
    // Populate View modal on open
    const viewModal = document.getElementById('viewWorkerModal');
    if(viewModal){
      viewModal.addEventListener('show.bs.modal', function (ev) {
        const btn = ev.relatedTarget; if(!btn) return;
        try{
          const data = JSON.parse(btn.getAttribute('data-view'));
          this.querySelector('.vw-avatar').src = data.avatar || '';
          this.querySelector('.vw-name').textContent = data.name || '';
          this.querySelector('.vw-status').textContent = data.status || '';
          this.querySelector('.vw-role').textContent = data.role || '';
          this.querySelector('.vw-store').textContent = data.store || '';
          this.querySelector('.vw-contact').textContent = data.contact || '';
          this.querySelector('.vw-start').textContent = data.start_date || '';
          this.querySelector('.vw-prob').textContent = data.probation_end || '';
        }catch(e){}
      });
    }
  })();
</script>
<!-- Attendance History Modal Template -->
<div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="historyLabel">Attendance on <span id="historyModalDate"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Worker</th>
                <th>Position</th>
                <th>Store</th>
                <th>Status</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody id="historyModalBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Add Worker Modal -->
<div class="modal fade" id="addWorkerModal" tabindex="-1" aria-labelledby="addWorkerLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="create_worker" value="1">
        <div class="modal-header">
          <h5 class="modal-title" id="addWorkerLabel">Add New Worker</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Name</label>
            <input type="text" class="form-control" name="name" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Photo URL</label>
            <input type="text" class="form-control" name="avatar_url">
          </div>
          <div class="mb-2">
            <label class="form-label">Position</label>
            <input type="text" class="form-control" name="role">
          </div>
          <div class="mb-2">
            <label class="form-label">Store</label>
            <select class="form-select" name="store_id">
              <option value="">-- none --</option>
              <?php foreach ($stores as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Contact</label>
            <input type="text" class="form-control" name="contact">
          </div>
          <div class="mb-2">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
              <option>Active</option>
              <option>Inactive</option>
              <option>On Leave</option>
              <option>Terminated</option>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Start Date</label>
            <input type="date" class="form-control" name="start_date">
          </div>
          <div class="mb-2">
            <label class="form-label">Probation End Date</label>
            <input type="date" class="form-control" name="probation_end">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Worker</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Worker Modal -->
<div class="modal fade" id="viewWorkerModal" tabindex="-1" aria-labelledby="viewWorkerLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewWorkerLabel">Worker Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-3 text-center">
            <img src="" class="avatar-lg mb-3 vw-avatar" alt="avatar">
            <h6 class="vw-name"></h6>
            <span class="badge bg-secondary vw-status"></span>
          </div>
          <div class="col-md-9">
            <table class="table table-borderless mb-0">
              <tr><th>Position:</th><td class="vw-role"></td></tr>
              <tr><th>Store:</th><td class="vw-store"></td></tr>
              <tr><th>Contact:</th><td class="vw-contact"></td></tr>
              <tr><th>Start Date:</th><td class="vw-start"></td></tr>
              <tr><th>Probation End:</th><td class="vw-prob"></td></tr>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="workers_attendance.js"></script>
</body>
</html>