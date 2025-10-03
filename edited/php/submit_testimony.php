<?php
// Simple handler: append submissions to a JSON file (not a DB).
// This is lightweight and safe for small sites. For production, move to DB and add sanitization.

header('Content-Type: application/json');

$name = trim($_POST['name'] ?? '');
$role = trim($_POST['role'] ?? '');
$message = trim($_POST['message'] ?? '');

if (!$name || !$message) {
    echo json_encode(['success' => false, 'error' => 'Name and message are required']);
    exit;
}

$entry = [
    'name' => $name,
    'role' => $role,
    'message' => $message,
    'created_at' => date('c')
];

$dir = __DIR__;
$file = $dir . '/testimonials.json';

// Attempt to read existing
$data = [];
if (file_exists($file)) {
    $raw = @file_get_contents($file);
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = [];
}

$data[] = $entry;

if (@file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Could not save testimony']);
}
