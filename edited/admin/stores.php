<?php
session_start();
if (empty($_SESSION['admin_logged'])) {
  header('Location: admin_login.php');
    exit;
}
require_once __DIR__ . '/../php/db.php';
$db = get_db();

// Handle create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_store'])) {
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $manager = trim($_POST['manager'] ?? '');
    $status = $_POST['status'] ?? 'Open';
    if ($name !== '') {
        $stmt = $db->prepare("INSERT INTO stores (name, location, manager, status) VALUES (?,?,?,?)");
        $stmt->bind_param('ssss', $name, $location, $manager, $status);
        $stmt->execute();
        header('Location: stores.php');
        exit;
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM stores WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header('Location: stores.php');
    exit;
}

// Fetch stores
$res = $db->query("SELECT id, name, location, manager, status, created_at FROM stores ORDER BY id DESC");
$stores = [];
while ($r = $res->fetch_assoc()) $stores[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Stores</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Stores</h3>
    <div>
  <a href="admin_management.php" class="btn btn-outline-secondary">Back to Dashboard</a>
  <a href="admin_logout.php" class="btn btn-outline-danger">Logout</a>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">Add Store</div>
    <div class="card-body">
      <form method="post" class="row g-3">
        <input type="hidden" name="create_store" value="1">
        <div class="col-md-4">
          <label class="form-label">Name</label>
          <input name="name" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Location</label>
          <input name="location" class="form-control">
        </div>
        <div class="col-md-4">
          <label class="form-label">Manager</label>
          <input name="manager" class="form-control">
        </div>
        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option>Open</option>
            <option>Closed</option>
            <option>Maintenance</option>
          </select>
        </div>
        <div class="col-12">
          <button class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Stores List</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>ID</th><th>Name</th><th>Location</th><th>Manager</th><th>Status</th><th>Created</th><th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($stores as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['id']) ?></td>
            <td><?= htmlspecialchars($s['name']) ?></td>
            <td><?= htmlspecialchars($s['location']) ?></td>
            <td><?= htmlspecialchars($s['manager']) ?></td>
            <td><span class="badge <?= $s['status']==='Open'?'bg-success':($s['status']==='Closed'?'bg-secondary':'bg-warning text-dark') ?>"><?= htmlspecialchars($s['status']) ?></span></td>
            <td><?= htmlspecialchars($s['created_at']) ?></td>
            <td class="text-end">
              <a href="?delete=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this store?')">Delete</a>
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