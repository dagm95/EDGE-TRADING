<?php
session_start();
if (empty($_SESSION['admin_logged'])) {
  header('Location: admin_login.php');
    exit;
}
require_once __DIR__ . '/../php/db.php';
$db = get_db();

// Fetch stores for select
$stores = [];
$r = $db->query("SELECT id, name FROM stores ORDER BY name");
while ($row = $r->fetch_assoc()) $stores[] = $row;

// Create project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_project'])) {
    $name = trim($_POST['name'] ?? '');
    $store_id = isset($_POST['store_id']) ? (int)$_POST['store_id'] : null;
    $owner = trim($_POST['owner'] ?? '');
    $due = $_POST['due_date'] ?? null;
    $status = $_POST['status'] ?? 'Planning';
    if ($name !== '') {
        $stmt = $db->prepare("INSERT INTO projects (name, store_id, owner, due_date, status) VALUES (?,?,?,?,?)");
        $stmt->bind_param('sisss', $name, $store_id, $owner, $due, $status);
        $stmt->execute();
        header('Location: projects.php');
        exit;
    }
}

// Delete project
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header('Location: projects.php');
    exit;
}

// Fetch projects
$sql = "SELECT p.id, p.name, p.owner, p.due_date, p.status, s.name AS store
        FROM projects p LEFT JOIN stores s ON s.id = p.store_id ORDER BY p.id DESC";
$projects = [];
$res = $db->query($sql);
while ($row = $res->fetch_assoc()) $projects[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Projects</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Projects</h3>
    <div>
  <a href="admin_management.php" class="btn btn-outline-secondary">Back to Dashboard</a>
  <a href="admin_logout.php" class="btn btn-outline-danger">Logout</a>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">Add Project</div>
    <div class="card-body">
      <form method="post" class="row g-3">
        <input type="hidden" name="create_project" value="1">
        <div class="col-md-4">
          <label class="form-label">Project Name</label>
          <input name="name" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Store</label>
          <select name="store_id" class="form-select">
            <option value="">-- none --</option>
            <?php foreach ($stores as $s): ?>
            <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Owner</label>
          <input name="owner" class="form-control">
        </div>
        <div class="col-md-2">
          <label class="form-label">Due Date</label>
          <input type="date" name="due_date" class="form-control">
        </div>
        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option>Planning</option>
            <option>In Progress</option>
            <option>Blocked</option>
            <option>Done</option>
            <option>Cancelled</option>
          </select>
        </div>
        <div class="col-12">
          <button class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Projects List</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>ID</th><th>Name</th><th>Store</th><th>Owner</th><th>Due</th><th>Status</th><th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($projects as $p): ?>
          <tr>
            <td><?= (int)$p['id'] ?></td>
            <td><?= htmlspecialchars($p['name']) ?></td>
            <td><?= htmlspecialchars($p['store']) ?></td>
            <td><?= htmlspecialchars($p['owner']) ?></td>
            <td><?= htmlspecialchars($p['due_date']) ?></td>
            <td><span class="badge <?= $p['status']==='In Progress'?'bg-primary':($p['status']==='Planning'?'bg-secondary':($p['status']==='Done'?'bg-success':($p['status']==='Blocked'?'bg-warning text-dark':'bg-danger'))) ?>"><?= htmlspecialchars($p['status']) ?></span></td>
            <td class="text-end">
              <a href="?delete=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this project?')">Delete</a>
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