<?php
// One-click installer for Admin core tables (Stores, Employees [workers], Projects, Notifications)
// Run in your browser after configuring php/db.php: /edited/php/setup_admin_db.php

require_once __DIR__ . '/db.php';

function out($msg) { echo htmlspecialchars($msg) . "<br>\n"; }

$db = get_db();
$migrations = [
    __DIR__ . '/../migrations/010_create_core_admin_tables.sql',
    __DIR__ . '/../migrations/020_create_attendance_table.sql',
    __DIR__ . '/../migrations/025_attendance_full_setup.sql',
    __DIR__ . '/../migrations/030_store_management_schema.sql',
    __DIR__ . '/../migrations/035_add_warehouse_workspace_to_stock.sql',
    __DIR__ . '/../migrations/040_project_management_extensions.sql',
    __DIR__ . '/../migrations/045_create_notifier_user.sql',
];

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin DB Setup</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container">
<h3>Admin Database Setup</h3>
<p>This will create/update all admin tables in the database configured in <code>edited/php/db.php</code>:</p>
<ul>
    <li>Core: <code>stores</code>, <code>workers</code> (employees), <code>projects</code>, <code>notifications</code></li>
    <li>Attendance: <code>attendance</code></li>
    <li>Store Mgmt: <code>materials</code>, <code>suppliers</code>, <code>store_users</code>, <code>reporters</code>, <code>inventory</code>, <code>stock_movements</code>, view <code>v_inventory_status</code></li>
    <li>Stock location: <code>warehouse</code>, <code>workspace</code> columns on inventory and movements</li>
    <li>Project Mgmt: extensions on <code>projects</code>, plus <code>project_items</code>, <code>project_reports</code></li>
    </ul>
<div class="card">
<div class="card-body">
<?php
foreach ($migrations as $file) {
    if (!file_exists($file)) {
        echo '<div class="alert alert-warning">Missing migration: ' . htmlspecialchars($file) . '</div>';
        continue;
    }
    $sql = file_get_contents($file);
    if ($sql === false) { echo '<div class="alert alert-danger">Failed to read: ' . htmlspecialchars($file) . '</div>'; continue; }
    echo '<div class="mb-1"><strong>Running:</strong> ' . htmlspecialchars(basename($file)) . '</div>';
    if ($db->multi_query($sql)) {
        do { if ($result = $db->store_result()) { $result->free(); } } while ($db->more_results() && $db->next_result());
        if ($db->errno) {
            echo '<div class="alert alert-warning">Completed with warnings: ' . htmlspecialchars($db->error) . '</div>';
        } else {
            echo '<div class="alert alert-success">Success.</div>';
        }
    } else {
        echo '<div class="alert alert-danger">Execution failed: ' . htmlspecialchars($db->error) . '</div>';
    }
}

// Verify presence of tables
$expected = ['stores','workers','projects','notifications','attendance','materials','suppliers','store_users','reporters','inventory','stock_movements','project_items','project_reports','notifier_user'];
$missing = [];
foreach ($expected as $t) {
    $res = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($t) . "'");
    if (!$res || $res->num_rows === 0) $missing[] = $t;
}
if ($missing) {
    echo '<div class="alert alert-danger">Missing tables: ' . htmlspecialchars(implode(', ', $missing)) . '</div>';
} else {
    echo '<div class="alert alert-info">All core tables exist.</div>';
}

// Seed a default notifier user if none exists
$seedQ = $db->query("SELECT COUNT(*) AS c FROM notifier_user");
if ($seedQ && ($r=$seedQ->fetch_assoc()) && (int)$r['c'] === 0) {
    $defaultUser = 'notifier';
    $defaultPass = 'notify123';
    $hash = password_hash($defaultPass, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO notifier_user (username, password_hash) VALUES (?, ?)");
    $stmt->bind_param('ss', $defaultUser, $hash);
    if ($stmt->execute()) {
        echo '<div class="alert alert-warning">Seeded default notifier account. Username: <code>' . htmlspecialchars($defaultUser) . '</code> Password: <code>' . htmlspecialchars($defaultPass) . '</code> (change it after login)</div>';
    }
}
?>
<p class="mt-3 mb-0">Next steps:</p>
<ol class="mb-0">
  <li>Open the dashboard pages:
    <ul>
      <li><a href="../admin/stores.php">Stores</a></li>
    <li><a href="../admin/workers.php">Employees</a></li>
      <li><a href="../admin/projects.php">Projects</a></li>
      <li><a href="../admin/notifications.php">Notifications</a></li>
    </ul>
  </li>
  <li>If you also use appointments, import your appointments SQL and then run <code>edited/migrations/001_add_scheduled_time.sql</code> via phpMyAdmin.</li>
</ol>
</div>
</div>
</div>
</body>
</html>