<?php
session_start();
if (empty($_SESSION['admin_logged'])) {
    header('Location: ../php/admin_login.php');
    exit;
}
require_once __DIR__ . '/../php/db.php';
$db = get_db();

// Create notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_note'])) {
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $type = $_POST['type'] ?? 'info';
    if ($title !== '') {
        $stmt = $db->prepare("INSERT INTO notifications (title, body, type) VALUES (?,?,?)");
        $stmt->bind_param('sss', $title, $body, $type);
        $stmt->execute();
        header('Location: notifications.php');
        exit;
    }
}

// Delete notification
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header('Location: notifications.php');
    exit;
}

// Mark read/unread
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $db->query("UPDATE notifications SET is_read = 1 - is_read WHERE id = $id");
    header('Location: notifications.php');
    exit;
}

// Fetch notifications
$res = $db->query("SELECT id, title, body, type, is_read, created_at FROM notifications ORDER BY id DESC");
$notes = [];
while ($r = $res->fetch_assoc()) $notes[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Notifications</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Notifications</h3>
    <div>
  <a href="admin_management.php" class="btn btn-outline-secondary">Back to Dashboard</a>
      <a href="../php/admin_logout.php" class="btn btn-outline-danger">Logout</a>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">Add Notification</div>
    <div class="card-body">
      <form method="post" class="row g-3">
        <input type="hidden" name="create_note" value="1">
        <div class="col-md-4">
          <label class="form-label">Title</label>
          <input name="title" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Type</label>
          <select name="type" class="form-select">
            <option value="info">info</option>
            <option value="success">success</option>
            <option value="warning">warning</option>
            <option value="error">error</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Body</label>
          <textarea name="body" class="form-control" rows="3"></textarea>
        </div>
        <div class="col-12">
          <button class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Notifications</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>ID</th><th>Title</th><th>Type</th><th>Read</th><th>Created</th><th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($notes as $n): ?>
          <tr>
            <td><?= (int)$n['id'] ?></td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($n['title']) ?></div>
              <?php if (!empty($n['body'])): ?><div class="text-muted small"><?= nl2br(htmlspecialchars($n['body'])) ?></div><?php endif; ?>
            </td>
            <td><span class="badge <?= $n['type']==='success'?'bg-success':($n['type']==='warning'?'bg-warning text-dark':($n['type']==='error'?'bg-danger':'bg-secondary')) ?>"><?= htmlspecialchars($n['type']) ?></span></td>
            <td><?= $n['is_read'] ? 'Yes' : 'No' ?></td>
            <td><?= htmlspecialchars($n['created_at']) ?></td>
            <td class="text-end">
              <a href="?toggle=<?= (int)$n['id'] ?>" class="btn btn-sm btn-outline-primary">Mark <?= $n['is_read'] ? 'Unread' : 'Read' ?></a>
              <a href="?delete=<?= (int)$n['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this notification?')">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>