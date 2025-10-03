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
