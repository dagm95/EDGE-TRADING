<?php
require_once __DIR__ . '/db.php';

function next_saturday($from_date = null) {
    $from = $from_date ? strtotime($from_date) : time();
    $day = (int)date('N', $from);
    $daysUntil = (6 - $day + 7) % 7;
    if ($daysUntil === 0) $daysUntil = 7;
    return date('Y-m-d', strtotime("+$daysUntil days", $from));
}

$mysqli = get_db();
$action = $_POST['action'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!$id) {
    header('Location: admin.php');
    exit;
}

if ($action === 'checkin') {
    $stmt = $mysqli->prepare("UPDATE appointments SET status = 'checked_in' WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

if ($action === 'mark_missed' || $action === 'reschedule') {
    // send to next Saturday
    $nextSat = next_saturday(date('Y-m-d'));
    $stmt = $mysqli->prepare("UPDATE appointments SET scheduled_date = ?, reschedule_count = reschedule_count + 1 WHERE id = ?");
    $stmt->bind_param('si', $nextSat, $id);
    $stmt->execute();
    $stmt->close();
}

if ($action === 'delete') {
    $stmt = $mysqli->prepare("DELETE FROM appointments WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

// After any action, recompute queues for affected dates
$recompute = function($mysqli) {
    $dates = $mysqli->query("SELECT DISTINCT scheduled_date FROM appointments WHERE status = 'scheduled'");
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
};

$recompute($mysqli);

header('Location: admin.php');
exit;
