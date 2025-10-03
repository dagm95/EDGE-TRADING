<?php
require_once __DIR__ . '/db.php';

$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$date    = $_POST['date'] ?? '';
$time    = $_POST['time'] ?? '';
$message = trim($_POST['message'] ?? '');

$mysqli = get_db();

// Basic validation
if (!$name || !$email || !$phone || !$date || !$time) {
    echo '<h2>Missing required fields.</h2><a href="../appointment.html">Back</a>';
    exit;
}

// Compute next queue number for this scheduled date (first-come-first-served)
$queue = 1;
$q = $mysqli->prepare("SELECT COALESCE(MAX(queue), 0) AS maxq FROM appointments WHERE scheduled_date = ? AND status = 'scheduled'");
$q->bind_param('s', $date);
if ($q->execute()) {
    $res = $q->get_result();
    if ($row = $res->fetch_assoc()) {
        $queue = intval($row['maxq']) + 1;
    }
}
$q->close();

// Check availability for this specific date+time if the column exists
$available = true;
$checkStmt = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM appointments WHERE scheduled_date = ? AND scheduled_time = ? AND status = 'scheduled'");
if ($checkStmt) {
    $checkStmt->bind_param('ss', $date, $time);
    $checkStmt->execute();
    $cres = $checkStmt->get_result();
    if ($crow = $cres->fetch_assoc()) {
        if (intval($crow['cnt']) > 0) $available = false;
    }
    $checkStmt->close();
} else {
    // If scheduled_time column doesn't exist, fallback to date-only check
    $chk = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM appointments WHERE scheduled_date = ? AND status = 'scheduled'");
    if ($chk) {
        $chk->bind_param('s', $date);
        $chk->execute();
        $cres = $chk->get_result();
        if ($crow = $cres->fetch_assoc()) {
            // If there is already someone that day, still allow multiple if desired.
            // Here we will allow multiple per day but this branch exists only if scheduled_time column is missing.
            $available = true;
        }
        $chk->close();
    }
}

if (!$available) {
    echo '<h2>Selected slot is already taken. Please choose another time.</h2><a href="../appointment.html">Back</a>';
    $mysqli->close();
    exit;
}

// Insert appointment with computed queue and scheduled_time
$sql = "INSERT INTO appointments (name, email, phone, scheduled_date, scheduled_time, message, queue) VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('ssssssi', $name, $email, $phone, $date, $time, $message, $queue);

if ($stmt->execute()) {
    $stmt->close();
    $mysqli->close();
    header('Location: ../appointment.html?status=success');
    exit;
} else {
    $err = urlencode('DB error');
    $stmt->close();
    $mysqli->close();
    header('Location: ../appointment.html?status=error&msg=' . $err);
    exit;
}
