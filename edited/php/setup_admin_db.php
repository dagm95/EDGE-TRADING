<?php
// One-click installer for Admin core tables (Stores, Workers, Projects, Notifications)
// Run in your browser after configuring php/db.php: /edited/php/setup_admin_db.php

require_once __DIR__ . '/db.php';

function out($msg) { echo htmlspecialchars($msg) . "<br>\n"; }

$db = get_db();
$sqlFile = __DIR__ . '/../migrations/010_create_core_admin_tables.sql';

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
<p>This will create the core tables: <code>stores</code>, <code>workers</code>, <code>projects</code>, <code>notifications</code> in the database configured in <code>edited/php/db.php</code>.</p>
<div class="card">
<div class="card-body">
<?php
if (!file_exists($sqlFile)) {
    out("SQL file not found: " . $sqlFile);
    exit;
}
$sql = file_get_contents($sqlFile);
if ($sql === false) {
    out("Failed to read SQL file.");
    exit;
}

// Execute with multi_query to handle multiple CREATE statements
if ($db->multi_query($sql)) {
    do {
        // flush results for each statement
        if ($result = $db->store_result()) { $result->free(); }
    } while ($db->more_results() && $db->next_result());

    // Check for errors after the last statement
    if ($db->errno) {
        echo '<div class="alert alert-warning">Completed with warnings: ' . htmlspecialchars($db->error) . "</div>";
    } else {
        echo '<div class="alert alert-success">Core tables created or already present.</div>';
    }
} else {
    echo '<div class="alert alert-danger">Execution failed: ' . htmlspecialchars($db->error) . "</div>";
}

// Verify presence of tables
$expected = ['stores','workers','projects','notifications'];
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
?>
<p class="mt-3 mb-0">Next steps:</p>
<ol class="mb-0">
  <li>Open the dashboard pages:
    <ul>
      <li><a href="../admin/stores.php">Stores</a></li>
      <li><a href="../admin/workers.php">Workers</a></li>
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