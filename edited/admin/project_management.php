<?php
session_start();
if (empty($_SESSION['admin_logged'])) { header('Location: admin_login.php'); exit; }
require_once __DIR__ . '/../php/db.php';
$db = get_db();

function d($k,$def=null){ return isset($_POST[$k])?trim($_POST[$k]):$def; }
function f($k){ $v=d($k,''); return is_numeric($v)?(float)$v:0; }
$ok=null;$err=null;

// Load selects
$stores = $db->query("SELECT id,name FROM stores ORDER BY name");
$materials = $db->query("SELECT id,code,name FROM materials ORDER BY name");
$users = $db->query("SELECT id,username FROM store_users WHERE status='Active' ORDER BY username");

try{
  if($_SERVER['REQUEST_METHOD']==='POST'){
    $entity=$_POST['entity']??''; $action=$_POST['action']??'';
    if($entity==='project' && $action==='create'){
      $stmt=$db->prepare("INSERT INTO projects (name, description, store_id, owner, due_date, start_date, end_date, duration_months, status, workspace_type, warehouse, workspace, customer_email, manager_user_id, reporter_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
      $dur = (int)($_POST['duration_months']??0);
      $store_id = $_POST['store_id']!==''?(int)$_POST['store_id']:null;
      $mgr = $_POST['manager_user_id']!==''?(int)$_POST['manager_user_id']:null;
      $rep = $_POST['reporter_user_id']!==''?(int)$_POST['reporter_user_id']:null;
      $stmt->bind_param('ssisssiisssssii', $_POST['name'], $_POST['description'], $store_id, $_POST['owner'], $_POST['due_date'], $_POST['start_date'], $_POST['end_date'], $dur, $_POST['status'], $_POST['workspace_type'], $_POST['warehouse'], $_POST['workspace'], $_POST['customer_email'], $mgr, $rep);
      $stmt->execute();
      $ok='Project created';
    }
    if($entity==='item' && $action==='add'){
      $stmt=$db->prepare("INSERT INTO project_items (project_id, material_id, quantity, warehouse, workspace, notes) VALUES (?,?,?,?,?,?)");
      $stmt->bind_param('iidsss', $_POST['project_id'], $_POST['material_id'], f('quantity'), $_POST['warehouse'], $_POST['workspace'], $_POST['notes']);
      $stmt->execute();
      $ok='Item added to project';
    }
    if($entity==='report' && $action==='create'){
      $stmt=$db->prepare("INSERT INTO project_reports (project_id, report_type, title, body, report_date, sent_to, created_by) VALUES (?,?,?,?,?,?,?)");
      $created_by = $_POST['created_by']!==''?(int)$_POST['created_by']:null;
      $stmt->bind_param('isssssi', $_POST['project_id'], $_POST['report_type'], $_POST['title'], $_POST['body'], $_POST['report_date'], $_POST['sent_to'], $created_by);
      $stmt->execute();
      $ok='Report created';
    }
    if(isset($_GET['delete_item'])){ $id=(int)$_GET['delete_item']; $db->query("DELETE FROM project_items WHERE id=$id"); $ok='Item deleted'; }
    if(isset($_GET['delete_report'])){ $id=(int)$_GET['delete_report']; $db->query("DELETE FROM project_reports WHERE id=$id"); $ok='Report deleted'; }
  }
}catch(Throwable $e){ $err=$e->getMessage(); }

// Data lists
$projects=$db->query("SELECT p.*, s.name AS store_name, u1.username AS manager_name, u2.username AS reporter_name FROM projects p LEFT JOIN stores s ON s.id=p.store_id LEFT JOIN store_users u1 ON u1.id=p.manager_user_id LEFT JOIN store_users u2 ON u2.id=p.reporter_user_id ORDER BY p.id DESC");
$items=$db->query("SELECT pi.*, m.code, m.name AS material_name FROM project_items pi JOIN materials m ON m.id=pi.material_id ORDER BY pi.id DESC");
$reports=$db->query("SELECT pr.*, p.name AS project_name FROM project_reports pr JOIN projects p ON p.id=pr.project_id ORDER BY pr.id DESC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Project Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Project Management</h3>
    <div>
      <a href="admin_management.php" class="btn btn-outline-secondary">Back to Dashboard</a>
      <a href="admin_logout.php" class="btn btn-outline-danger">Logout</a>
    </div>
  </div>

  <?php if($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php if($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

  <!-- Create Project -->
  <div class="card mb-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
      <span>New Project</span>
    </div>
    <div class="card-body">
      <form method="post" class="row g-3">
        <input type="hidden" name="entity" value="project">
        <input type="hidden" name="action" value="create">
        <div class="col-md-4"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Store</label><select name="store_id" class="form-select"><option value="">-- none --</option><?php $rs=$db->query("SELECT id,name FROM stores ORDER BY name"); while($s=$rs->fetch_assoc()): ?><option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endwhile; ?></select></div>
        <div class="col-md-4"><label class="form-label">Customer Email</label><input name="customer_email" type="email" class="form-control" placeholder="for weekly reports"></div>
        <div class="col-md-6"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
        <div class="col-md-3"><label class="form-label">Owner</label><input name="owner" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option>Planning</option><option>In Progress</option><option>Blocked</option><option>Done</option><option>Cancelled</option></select></div>
        <div class="col-md-3"><label class="form-label">Start Date</label><input type="date" name="start_date" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Finish Date</label><input type="date" name="end_date" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Duration (months)</label><select name="duration_months" class="form-select"><option value="">Custom</option><option>1</option><option>2</option><option>3</option></select></div>
        <div class="col-md-3"><label class="form-label">Workspace Type</label><select name="workspace_type" class="form-select"><option>Furniture</option><option>Aluminum & Metal</option><option>Interior Design</option><option selected>Other</option></select></div>
        <div class="col-md-3"><label class="form-label">Warehouse</label><input name="warehouse" class="form-control" placeholder="link to saved warehouse"></div>
        <div class="col-md-3"><label class="form-label">Workspace</label><input name="workspace" class="form-control" placeholder="e.g., Bay-12"></div>
        <div class="col-md-3"><label class="form-label">Manager</label><select name="manager_user_id" class="form-select"><option value="">-- none --</option><?php $ru=$db->query("SELECT id,username FROM store_users WHERE status='Active' ORDER BY username"); while($u=$ru->fetch_assoc()): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option><?php endwhile; ?></select></div>
        <div class="col-md-3"><label class="form-label">Reporter</label><select name="reporter_user_id" class="form-select"><option value="">-- none --</option><?php $ru2=$db->query("SELECT id,username FROM store_users WHERE status='Active' ORDER BY username"); while($u=$ru2->fetch_assoc()): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option><?php endwhile; ?></select></div>
        <div class="col-12"><button class="btn btn-primary">Create</button></div>
      </form>
    </div>
  </div>

  <!-- Projects List -->
  <div class="card mb-4">
    <div class="card-header">Projects</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Name</th><th>Store</th><th>Workspace</th><th>Manager</th><th>Reporter</th><th>Start</th><th>End</th><th>Status</th></tr></thead>
        <tbody>
          <?php while($p=$projects->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($p['name']) ?></td>
            <td><?= htmlspecialchars($p['store_name']) ?></td>
            <td><?= htmlspecialchars(($p['warehouse']?($p['warehouse'].' / '):'').$p['workspace']) ?></td>
            <td><?= htmlspecialchars($p['manager_name']??'') ?></td>
            <td><?= htmlspecialchars($p['reporter_name']??'') ?></td>
            <td><?= htmlspecialchars($p['start_date']) ?></td>
            <td><?= htmlspecialchars($p['end_date']) ?></td>
            <td><span class="badge <?= $p['status']==='In Progress'?'bg-primary':($p['status']==='Planning'?'bg-secondary':($p['status']==='Done'?'bg-success':($p['status']==='Blocked'?'bg-warning text-dark':'bg-danger'))) ?>"><?= htmlspecialchars($p['status']) ?></span></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Items Needed -->
  <div class="card mb-4">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
      <span>Items Needed</span>
    </div>
    <div class="card-body">
      <form method="post" class="row g-2">
        <input type="hidden" name="entity" value="item"><input type="hidden" name="action" value="add">
        <div class="col-md-3"><label class="form-label">Project</label><select name="project_id" class="form-select" required><?php $rp=$db->query("SELECT id,name FROM projects ORDER BY id DESC"); while($p=$rp->fetch_assoc()): ?><option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option><?php endwhile; ?></select></div>
        <div class="col-md-3"><label class="form-label">Material</label><select name="material_id" class="form-select" required><?php $rm=$db->query("SELECT id,code,name FROM materials ORDER BY name"); while($m=$rm->fetch_assoc()): ?><option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['code'].' - '.$m['name']) ?></option><?php endwhile; ?></select></div>
        <div class="col-md-2"><label class="form-label">Quantity</label><input name="quantity" type="number" step="0.001" class="form-control" required></div>
        <div class="col-md-2"><label class="form-label">Warehouse</label><input name="warehouse" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Workspace</label><input name="workspace" class="form-control"></div>
        <div class="col-12"><label class="form-label">Notes</label><input name="notes" class="form-control" placeholder="Optional notes"></div>
        <div class="col-12 text-end"><button class="btn btn-success">Add Item</button></div>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Project</th><th>Material</th><th>Qty</th><th>Warehouse</th><th>Workspace</th><th>Notes</th><th></th></tr></thead>
        <tbody>
          <?php while($it=$items->fetch_assoc()): $pname=$db->query("SELECT name FROM projects WHERE id=".(int)$it['project_id'])->fetch_row()[0]??''; ?>
          <tr>
            <td><?= htmlspecialchars($pname) ?></td>
            <td><?= htmlspecialchars($it['code'].' - '.$it['material_name']) ?></td>
            <td><?= htmlspecialchars($it['quantity']) ?></td>
            <td><?= htmlspecialchars($it['warehouse']) ?></td>
            <td><?= htmlspecialchars($it['workspace']) ?></td>
            <td><?= htmlspecialchars($it['notes']) ?></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-danger" href="?delete_item=<?= (int)$it['id'] ?>" onclick="return confirm('Delete item?')">Delete</a></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Reports (Analysis / Weekly) -->
  <div class="card mb-4">
    <div class="card-header bg-info text-dark">Reports</div>
    <div class="card-body">
      <form method="post" class="row g-2">
        <input type="hidden" name="entity" value="report"><input type="hidden" name="action" value="create">
        <div class="col-md-3"><label class="form-label">Project</label><select name="project_id" class="form-select" required><?php $rp2=$db->query("SELECT id,name,customer_email FROM projects ORDER BY id DESC"); while($p=$rp2->fetch_assoc()): ?><option value="<?= (int)$p['id'] ?>" data-email="<?= htmlspecialchars($p['customer_email']??'') ?>"><?= htmlspecialchars($p['name']) ?></option><?php endwhile; ?></select></div>
        <div class="col-md-2"><label class="form-label">Type</label><select name="report_type" class="form-select"><option value="analysis">Analysis</option><option value="weekly">Weekly</option></select></div>
        <div class="col-md-3"><label class="form-label">Title</label><input name="title" class="form-control" required></div>
        <div class="col-md-2"><label class="form-label">Report Date</label><input type="date" name="report_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
        <div class="col-md-2"><label class="form-label">Send To (email)</label><input name="sent_to" class="form-control" placeholder="auto-fill from project"></div>
        <div class="col-12"><label class="form-label">Body</label><textarea name="body" class="form-control" rows="3"></textarea></div>
        <div class="col-md-3"><label class="form-label">Created By</label><select name="created_by" class="form-select"><option value="">-- none --</option><?php $ru3=$db->query("SELECT id,username FROM store_users WHERE status='Active' ORDER BY username"); while($u=$ru3->fetch_assoc()): ?><option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option><?php endwhile; ?></select></div>
        <div class="col-12 text-end"><button class="btn btn-info">Create Report</button></div>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Project</th><th>Type</th><th>Title</th><th>Date</th><th>To</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php while($r=$reports->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($r['project_name']) ?></td>
            <td><span class="badge <?= $r['report_type']==='weekly'?'bg-primary':'bg-secondary' ?>"><?= htmlspecialchars(ucfirst($r['report_type'])) ?></span></td>
            <td><?= htmlspecialchars($r['title']) ?></td>
            <td><?= htmlspecialchars($r['report_date']) ?></td>
            <td><?= htmlspecialchars($r['sent_to']) ?></td>
            <td><span class="badge <?= $r['sent_status']==='sent'?'bg-success':($r['sent_status']==='failed'?'bg-danger':'bg-warning text-dark') ?>"><?= htmlspecialchars($r['sent_status']) ?></span></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-danger" href="?delete_report=<?= (int)$r['id'] ?>" onclick="return confirm('Delete report?')">Delete</a></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="text-muted small">Note: Weekly emails can be automated later via a cron script that reads project_reports with sent_status='pending' and sends to the project's customer_email.</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
