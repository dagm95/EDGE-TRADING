<?php
session_start();
// Protect this page behind existing admin session
if (empty($_SESSION['admin_logged'])) {
    header('Location: admin_login.php');
    exit;
}

require_once __DIR__ . '/db.php';
$db = get_db();

// Handle check-in action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkin') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id > 0) {
        $stmt = $db->prepare("UPDATE appointments SET status='checked_in' WHERE id=?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
    exit;
}

// Filters
$flt_date = isset($_GET['date']) ? trim($_GET['date']) : '';
$flt_status = isset($_GET['status']) ? trim($_GET['status']) : 'all';

// Build query
$sql = "SELECT id, name, email, phone, scheduled_date, scheduled_time, queue, status, created_at
        FROM appointments WHERE 1=1";
$types = '';
$params = [];

if ($flt_date !== '') {
    $sql .= " AND scheduled_date = ?";
    $types .= 's';
    $params[] = $flt_date;
}
if (in_array($flt_status, ['scheduled','checked_in'], true)) {
    $sql .= " AND status = ?";
    $types .= 's';
    $params[] = $flt_status;
}

$sql .= " ORDER BY scheduled_date ASC, scheduled_time ASC, queue ASC, id ASC";

$stmt = $db->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    die('Failed to prepare query.');
}
if ($types !== '') {
    // PHP 7+ supports argument unpacking
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Appointments Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <meta name="robots" content="noindex, nofollow">
  <style>
    body { background:#f7f7f9; }
    .container-narrow { max-width: 1100px; }
    .table thead th { white-space: nowrap; }
  </style>
  <meta http-equiv="Cache-Control" content="no-store" />
  <meta http-equiv="Pragma" content="no-cache" />
  <meta http-equiv="Expires" content="0" />
  <meta http-equiv="X-Content-Type-Options" content="nosniff" />
  <link rel="icon" href="data:,"> 
  </head>
<body>
  <div class="container container-narrow py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 mb-0">Appointments Admin</h1>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="../admin/admin%20magment.php">Back to Admin Dashboard</a>
        <a class="btn btn-outline-danger btn-sm" href="admin_logout.php">Logout</a>
      </div>
    </div>

    <form class="row g-2 align-items-end mb-3" method="get" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
      <div class="col-auto">
        <label for="date" class="form-label mb-0">Date</label>
        <input type="date" id="date" name="date" value="<?= htmlspecialchars($flt_date) ?>" class="form-control form-control-sm">
      </div>
      <div class="col-auto">
        <label for="status" class="form-label mb-0">Status</label>
        <select id="status" name="status" class="form-select form-select-sm">
          <option value="all" <?= $flt_status==='all'?'selected':'' ?>>All</option>
          <option value="scheduled" <?= $flt_status==='scheduled'?'selected':'' ?>>Scheduled</option>
          <option value="checked_in" <?= $flt_status==='checked_in'?'selected':'' ?>>Checked-in</option>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a class="btn btn-secondary btn-sm" href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">Reset</a>
      </div>
    </form>

    <div class="card shadow-sm">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Date</th>
                <th>Time</th>
                <th>Queue</th>
                <th>Status</th>
                <th>Created</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
<?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= (int)$row['id'] ?></td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['phone']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['scheduled_date']) ?></td>
                <td><?= htmlspecialchars($row['scheduled_time'] ?? '') ?></td>
                <td><?= htmlspecialchars((string)$row['queue']) ?></td>
                <td>
                  <?php if ($row['status'] === 'scheduled'): ?>
                    <span class="badge bg-warning text-dark">Scheduled</span>
                  <?php else: ?>
                    <span class="badge bg-success">Checked-in</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($row['created_at']) ?></td>
                <td class="text-end">
                  <?php if ($row['status'] === 'scheduled'): ?>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="checkin">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-success">Check-in</button>
                  </form>
                  <?php else: ?>
                    <button class="btn btn-sm btn-outline-secondary" disabled>Checked-in</button>
                  <?php endif; ?>
                </td>
              </tr>
<?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <p class="text-muted small mt-2 mb-0">Tip: filter by date to see a specific day’s schedule; click Check-in to mark an arrival.</p>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$stmt->close();
$db->close();
?>
<?php
session_start();
// Require admin login
require_once __DIR__ . '/admin_config.php';
if (empty($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once __DIR__ . '/db.php';

// Helper: compute next Saturday from a given date (returns Y-m-d)
function next_saturday($from_date = null) {
    $from = $from_date ? strtotime($from_date) : time();
    $day = (int)date('N', $from); // 1 (Mon) .. 7 (Sun)
    $daysUntil = (6 - $day + 7) % 7;
    if ($daysUntil === 0) $daysUntil = 7; // always next Saturday
    return date('Y-m-d', strtotime("+$daysUntil days", $from));
}

$mysqli = get_db();
$today = date('Y-m-d');

// Auto-reschedule any scheduled appointments whose scheduled_date < today
$res = $mysqli->prepare("SELECT id, scheduled_date, reschedule_count FROM appointments WHERE status = 'scheduled' AND scheduled_date < ?");
$res->bind_param('s', $today);
$res->execute();
$result = $res->get_result();
$to_update = [];
while ($row = $result->fetch_assoc()) {
    $id = (int)$row['id'];
    $nextSat = next_saturday($today);
    $to_update[] = ['id' => $id, 'next' => $nextSat];
}
$res->close();

foreach ($to_update as $u) {
    $stmt = $mysqli->prepare("UPDATE appointments SET scheduled_date = ?, reschedule_count = reschedule_count + 1 WHERE id = ?");
    $stmt->bind_param('si', $u['next'], $u['id']);
    $stmt->execute();
    $stmt->close();
}

// Recompute queues per scheduled_date to maintain first-come-first-serve order
$dates = $mysqli->query("SELECT DISTINCT scheduled_date FROM appointments WHERE status = 'scheduled' ORDER BY scheduled_date");
if ($dates) {
    while ($d = $dates->fetch_assoc()) {
        $sd = $d['scheduled_date'];
        $q = $mysqli->prepare("SELECT id FROM appointments WHERE status = 'scheduled' AND scheduled_date = ? ORDER BY created_at, id");
        $q->bind_param('s', $sd);
        $q->execute();
        $r = $q->get_result();
        $pos = 1;
        while ($a = $r->fetch_assoc()) {
            $upd = $mysqli->prepare("UPDATE appointments SET queue = ? WHERE id = ?");
            $upd->bind_param('ii', $pos, $a['id']);
            $upd->execute();
            $upd->close();
            $pos++;
        }
        $q->close();
    }
}

// Fetch all appointments for admin view
$list = $mysqli->query("SELECT * FROM appointments ORDER BY scheduled_date, queue");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Appointments Admin</title>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;margin:20px}
        table{border-collapse:collapse;width:100%}
        th,td{border:1px solid #ddd;padding:8px}
        th{background:#f4f4f4}
        form{display:inline}
        .btn{padding:6px 8px;border:1px solid #ccc;background:#eee;border-radius:4px;cursor:pointer}
        .checkin{background:#4caf50;color:white}
        .missed{background:#f39c12;color:white}
        .delete{background:#e74c3c;color:white}
    </style>
</head>
<body>
    <h2>Appointments — Admin</h2>
    <p>Auto-rescheduled missed appointments to the next Saturday. Use actions to Check-in, Mark missed, Reschedule or Delete.</p>
    <p><a href="admin_logout.php">Logout</a></p>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Scheduled Date</th>
                <th>Queue</th>
                <th>Status</th>
                <th>Reschedules</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $list->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['id']); ?></td>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo htmlspecialchars($row['email']); ?></td>
                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                <td><?php echo htmlspecialchars($row['scheduled_date']); ?></td>
                <td><?php echo htmlspecialchars($row['queue']); ?></td>
                <td><?php echo htmlspecialchars($row['status']); ?></td>
                <td><?php echo htmlspecialchars($row['reschedule_count']); ?></td>
                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                <td>
                    <?php if ($row['status'] === 'scheduled'): ?>
                        <form method="post" action="actions.php" style="display:inline">
                            <input type="hidden" name="action" value="checkin">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <button class="btn checkin">Check In</button>
                        </form>
                        <form method="post" action="actions.php" style="display:inline">
                            <input type="hidden" name="action" value="mark_missed">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <button class="btn missed">Mark Missed</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="actions.php" style="display:inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                        <button class="btn delete" onclick="return confirm('Delete this appointment?')">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
