<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$date = $_POST['date'] ?? '';
$time = $_POST['time'] ?? '';

if (!$date || !$time) {
    echo json_encode(['available' => false, 'error' => 'Missing date or time']);
    exit;
}

$mysqli = get_db();

$stmt = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM appointments WHERE scheduled_date = ? AND scheduled_time = ? AND status = 'scheduled'");
if (!$stmt) {
    echo json_encode(['available' => false, 'error' => 'DB prepare failed']);
    exit;
}
$stmt->bind_param('ss', $date, $time);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$count = intval($row['cnt'] ?? 0);
$stmt->close();
$mysqli->close();

echo json_encode(['available' => ($count === 0)]);
