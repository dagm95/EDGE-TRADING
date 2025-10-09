<?php
session_start();
if (empty($_SESSION['notifier_logged'])) {
    header('Location: notifier_login.php');
    exit;
}
require_once __DIR__ . '/../php/db.php';
$db = get_db();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_note'])) {
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $type = $_POST['type'] ?? 'info';
    if ($title !== '') {
        $stmt = $db->prepare("INSERT INTO notifications (title, body, type) VALUES (?,?,?)");
        $stmt->bind_param('sss', $title, $body, $type);
        $stmt->execute();
        $message = 'Notification sent!';
    } else {
        $message = 'Title is required.';
    }
}

$res = $db->query("SELECT id, title, type, is_read, created_at FROM notifications ORDER BY id DESC LIMIT 20");
$latest = [];
while ($r = $res->fetch_assoc()) $latest[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Notifier Panel</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Notifier Panel</h3>
    <div>
      <span class="me-3">Logged in as <?= htmlspecialchars($_SESSION['notifier_username'] ?? 'notifier') ?></span>
      <a href="notifier_logout.php" class="btn btn-outline-danger">Logout</a>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">Send Notification</div>
    <div class="card-body">
      <?php if ($message): ?><div class="alert alert-info"><?= htmlspecialchars($message) ?></div><?php endif; ?>
      <form method="post" class="row g-3">
        <input type="hidden" name="send_note" value="1">
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
          <label class="form-label">Message</label>
          <textarea name="body" class="form-control" rows="3" placeholder="Write your message..."></textarea>
        </div>
        <div class="col-12">
          <button class="btn btn-primary">Send</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Recent Notifications</div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>ID</th><th>Title</th><th>Type</th><th>Read</th><th>Created</th></tr></thead>
        <tbody>
        <?php foreach ($latest as $n): ?>
          <tr>
            <td><?= (int)$n['id'] ?></td>
            <td><?= htmlspecialchars($n['title']) ?></td>
            <td><span class="badge <?= $n['type']==='success'?'bg-success':($n['type']==='warning'?'bg-warning text-dark':($n['type']==='error'?'bg-danger':'bg-secondary')) ?>"><?= htmlspecialchars($n['type']) ?></span></td>
            <td><?= $n['is_read'] ? 'Yes' : 'No' ?></td>
            <td><?= htmlspecialchars($n['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <p class="text-muted mt-3">Admin can manage notifications at <a href="notifications.php">Notifications</a>.</p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
