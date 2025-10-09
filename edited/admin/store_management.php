<?php
session_start();
if (empty($_SESSION['admin_logged'])) {
  header('Location: admin_login.php');
  exit;
}
require_once __DIR__ . '/../php/db.php';
$db = get_db();

// Helpers
function post($k, $d = null) { return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }
function dec($v) { return is_numeric($v) ? (float)$v : 0; }

// Handle actions
$err = null; $ok = null;
try {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entity = $_POST['entity'] ?? '';
    $action = $_POST['action'] ?? '';
    if ($entity === 'store' && $action === 'create') {
      $stmt = $db->prepare("INSERT INTO stores (name, location, manager, status) VALUES (?,?,?,?)");
      $stmt->bind_param('ssss', $_POST['name'], $_POST['location'], $_POST['manager'], $_POST['status']);
      $stmt->execute();
      $ok = 'Store created';
    }
    if ($entity === 'material' && $action === 'create') {
      $stmt = $db->prepare("INSERT INTO materials (code, name, unit, min_stock, max_stock, status) VALUES (?,?,?,?,?,?)");
      $min = dec($_POST['min_stock']); $max = dec($_POST['max_stock']);
      $stmt->bind_param('sssdds', $_POST['code'], $_POST['name'], $_POST['unit'], $min, $max, $_POST['status']);
      $stmt->execute();
      $ok = 'Material created';
    }
    if ($entity === 'inventory' && $action === 'upsert') {
      $store_id = (int)$_POST['store_id'];
      $material_id = (int)$_POST['material_id'];
      $qty = dec($_POST['quantity']);
      $warehouse = trim($_POST['warehouse'] ?? '');
      $workspace = trim($_POST['workspace'] ?? '');
      // Upsert inventory
      $db->begin_transaction();
      // Backward-compatible check: include warehouse/workspace if columns exist
      $hasCols = $db->query("SHOW COLUMNS FROM inventory LIKE 'warehouse'");
      $useLoc = ($hasCols && $hasCols->num_rows > 0);
      if ($useLoc) {
        $stmt = $db->prepare("SELECT id, quantity FROM inventory WHERE store_id=? AND material_id=? AND warehouse=? AND workspace=? FOR UPDATE");
        $stmt->bind_param('iiss', $store_id, $material_id, $warehouse, $workspace);
      } else {
        $stmt = $db->prepare("SELECT id, quantity FROM inventory WHERE store_id=? AND material_id=? FOR UPDATE");
        $stmt->bind_param('ii', $store_id, $material_id);
      }
      $stmt->execute(); $res = $stmt->get_result();
      if ($row = $res->fetch_assoc()) {
        $stmt2 = $db->prepare("UPDATE inventory SET quantity=?, last_update=NOW() WHERE id=?");
        $stmt2->bind_param('di', $qty, $row['id']);
        $stmt2->execute();
      } else {
        if ($useLoc) {
          $stmt2 = $db->prepare("INSERT INTO inventory (store_id, material_id, warehouse, workspace, quantity, last_update) VALUES (?,?,?,?,?,NOW())");
          $stmt2->bind_param('iissd', $store_id, $material_id, $warehouse, $workspace, $qty);
        } else {
          $stmt2 = $db->prepare("INSERT INTO inventory (store_id, material_id, quantity, last_update) VALUES (?,?,?,NOW())");
          $stmt2->bind_param('iid', $store_id, $material_id, $qty);
        }
        $stmt2->execute();
      }
      $db->commit();
      $ok = 'Inventory saved';
    }
    if ($entity === 'movement' && $action === 'create') {
      $store_id = (int)$_POST['store_id'];
      $material_id = (int)$_POST['material_id'];
      $qty = dec($_POST['quantity']);
      $type = $_POST['movement_type'];
      $date = $_POST['movement_date'] ?: date('Y-m-d');
      $ref = post('reference'); $user = post('user_name'); $notes = post('notes');
      $warehouse = trim($_POST['warehouse'] ?? '');
      $workspace = trim($_POST['workspace'] ?? '');
      // Apply inventory effect in a transaction
      $db->begin_transaction();
      // Check if new columns exist
      $hasColsMv = $db->query("SHOW COLUMNS FROM stock_movements LIKE 'warehouse'");
      $useLocMv = ($hasColsMv && $hasColsMv->num_rows > 0);
      if ($useLocMv) {
        $stmt = $db->prepare("INSERT INTO stock_movements (movement_date, store_id, material_id, movement_type, quantity, reference, user_name, warehouse, workspace, notes) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('siisdsssss', $date, $store_id, $material_id, $type, $qty, $ref, $user, $warehouse, $workspace, $notes);
      } else {
        $stmt = $db->prepare("INSERT INTO stock_movements (movement_date, store_id, material_id, movement_type, quantity, reference, user_name, notes) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('siisdsss', $date, $store_id, $material_id, $type, $qty, $ref, $user, $notes);
      }
      $stmt->execute();
      // Lock inventory row
      if ($useLocMv) {
        $stmt2 = $db->prepare("SELECT id, quantity FROM inventory WHERE store_id=? AND material_id=? AND warehouse=? AND workspace=? FOR UPDATE");
        $stmt2->bind_param('iiss', $store_id, $material_id, $warehouse, $workspace);
      } else {
        $stmt2 = $db->prepare("SELECT id, quantity FROM inventory WHERE store_id=? AND material_id=? FOR UPDATE");
        $stmt2->bind_param('ii', $store_id, $material_id);
      }
      $stmt2->execute(); $res = $stmt2->get_result();
      $delta = $qty;
      if ($type === 'Receipt') $delta = $qty;
      elseif ($type === 'Issue') $delta = -$qty;
      elseif ($type === 'Adjust') $delta = $qty; // treat as signed delta
      if ($row = $res->fetch_assoc()) {
        $newQty = (float)$row['quantity'] + $delta;
        $stmt3 = $db->prepare("UPDATE inventory SET quantity=?, last_update=NOW() WHERE id=?");
        $stmt3->bind_param('di', $newQty, $row['id']);
        $stmt3->execute();
      } else {
        $newQty = max(0.0, $delta);
        if ($useLocMv) {
          $stmt3 = $db->prepare("INSERT INTO inventory (store_id, material_id, warehouse, workspace, quantity, last_update) VALUES (?,?,?,?,?,NOW())");
          $stmt3->bind_param('iissd', $store_id, $material_id, $warehouse, $workspace, $newQty);
        } else {
          $stmt3 = $db->prepare("INSERT INTO inventory (store_id, material_id, quantity, last_update) VALUES (?,?,?,NOW())");
          $stmt3->bind_param('iid', $store_id, $material_id, $newQty);
        }
        $stmt3->execute();
      }
      $db->commit();
      $ok = 'Movement recorded and inventory updated';
    }
    if ($entity === 'supplier' && $action === 'create') {
      $stmt = $db->prepare("INSERT INTO suppliers (name, contact, status) VALUES (?,?,?)");
      $stmt->bind_param('sss', $_POST['name'], $_POST['contact'], $_POST['status']);
      $stmt->execute();
      $ok = 'Supplier created';
    }
    if ($entity === 'store_user' && $action === 'create') {
      $stmt = $db->prepare("INSERT INTO store_users (username, role, status) VALUES (?,?,?)");
      $stmt->bind_param('sss', $_POST['username'], $_POST['role'], $_POST['status']);
      $stmt->execute();
      $ok = 'User created';
    }
    if ($entity === 'reporter' && $action === 'create') {
      $stmt = $db->prepare("INSERT INTO reporters (name, role, status) VALUES (?,?,?)");
      $stmt->bind_param('sss', $_POST['name'], $_POST['role'], $_POST['status']);
      $stmt->execute();
      $ok = 'Reporter created';
    }
  }
  // Deletes via GET for simplicity
  if (isset($_GET['delete_store'])) { $id=(int)$_GET['delete_store']; $db->query("DELETE FROM stores WHERE id=$id"); $ok='Store deleted'; }
  if (isset($_GET['delete_material'])) { $id=(int)$_GET['delete_material']; $db->query("DELETE FROM materials WHERE id=$id"); $ok='Material deleted'; }
  if (isset($_GET['delete_inventory'])) { $id=(int)$_GET['delete_inventory']; $db->query("DELETE FROM inventory WHERE id=$id"); $ok='Inventory row deleted'; }
  if (isset($_GET['delete_movement'])) { $id=(int)$_GET['delete_movement']; $db->query("DELETE FROM stock_movements WHERE id=$id"); $ok='Movement deleted'; }
  if (isset($_GET['delete_supplier'])) { $id=(int)$_GET['delete_supplier']; $db->query("DELETE FROM suppliers WHERE id=$id"); $ok='Supplier deleted'; }
  if (isset($_GET['delete_user'])) { $id=(int)$_GET['delete_user']; $db->query("DELETE FROM store_users WHERE id=$id"); $ok='User deleted'; }
  if (isset($_GET['delete_reporter'])) { $id=(int)$_GET['delete_reporter']; $db->query("DELETE FROM reporters WHERE id=$id"); $ok='Reporter deleted'; }
} catch (Throwable $e) {
  $err = $e->getMessage();
}

// Fetch data for lists and selects
$stores = $db->query("SELECT id, name, location, manager, status FROM stores ORDER BY id DESC");
$materials = $db->query("SELECT id, code, name, unit, min_stock, max_stock, status FROM materials ORDER BY id DESC");
$inventory = $db->query("SELECT * FROM v_inventory_status ORDER BY last_update DESC");
if ($inventory === false) {
  // Include warehouse/workspace if present
  $hasCols = $db->query("SHOW COLUMNS FROM inventory LIKE 'warehouse'");
  $hasLoc = ($hasCols && $hasCols->num_rows > 0);
  $selLoc = $hasLoc ? ", i.warehouse, i.workspace" : "";
  $sqlInv = "SELECT i.id, i.store_id, s.name AS store_name, i.material_id, m.code AS material_code, m.name AS material_name, m.unit, m.min_stock, m.max_stock, i.quantity, i.last_update" . $selLoc . ", CASE WHEN i.quantity < m.min_stock THEN 'Low' WHEN i.quantity > m.max_stock THEN 'High' ELSE 'OK' END AS status FROM inventory i JOIN stores s ON s.id=i.store_id JOIN materials m ON m.id=i.material_id ORDER BY i.last_update DESC";
  $inventory = $db->query($sqlInv);
}
// Movements include warehouse/workspace if present
$hasColsMv = $db->query("SHOW COLUMNS FROM stock_movements LIKE 'warehouse'");
$hasLocMv = ($hasColsMv && $hasColsMv->num_rows > 0);
$selLocMv = $hasLocMv ? ", sm.warehouse, sm.workspace" : "";
$movements = $db->query("SELECT sm.*, s.name AS store_name, m.name AS material_name" . $selLocMv . " FROM stock_movements sm JOIN stores s ON s.id=sm.store_id JOIN materials m ON m.id=sm.material_id ORDER BY sm.id DESC LIMIT 100");
$suppliers = $db->query("SELECT * FROM suppliers ORDER BY id DESC");
$users = $db->query("SELECT * FROM store_users ORDER BY id DESC");
$reporters = $db->query("SELECT * FROM reporters ORDER BY id DESC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Store Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .section-card .card-header { display:flex; align-items:center; justify-content:space-between; }
  </style>
  <meta http-equiv="Cache-Control" content="no-store" />
  <meta http-equiv="Pragma" content="no-cache" />
  <meta http-equiv="Expires" content="0" />
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="store.css">
  <link rel="stylesheet" href="admin managment.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script>
    function confirmDel(url){ if(confirm('Are you sure?')) location.href=url; }
  </script>
  <style> .badge.status-OK{background:#198754} .badge.status-Low{background:#ffc107;color:#000} .badge.status-High{background:#0dcaf0;color:#000} </style>
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Store Management</h3>
    <div>
      <a href="admin_management.php" class="btn btn-outline-secondary">Back to Dashboard</a>
      <a href="admin_logout.php" class="btn btn-outline-danger">Logout</a>
    </div>
  </div>

  <?php if ($err): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
  <?php elseif ($ok): ?>
    <div class="alert alert-success"><?= htmlspecialchars($ok) ?></div>
  <?php endif; ?>

  <!-- STORES -->
  <div class="card section-card mb-4">
    <div class="card-header bg-primary text-white">
      <span>Stores</span>
      <button class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#addStore">Add Store</button>
    </div>
    <div id="addStore" class="collapse card-body">
      <form method="post" class="row g-2">
        <input type="hidden" name="entity" value="store">
        <input type="hidden" name="action" value="create">
        <div class="col-md-3"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Location</label><input name="location" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Manager</label><input name="manager" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Status</label>
          <select name="status" class="form-select"><option>Open</option><option>Closed</option><option>Maintenance</option></select>
        </div>
        <div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary w-100">Save</button></div>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Name</th><th>Location</th><th>Manager</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <?php while($s=$stores->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($s['name']) ?></td>
            <td><?= htmlspecialchars($s['location']) ?></td>
            <td><?= htmlspecialchars($s['manager']) ?></td>
            <td><span class="badge <?= $s['status']==='Open'?'bg-success':($s['status']==='Closed'?'bg-secondary':'bg-warning text-dark') ?>"><?= htmlspecialchars($s['status']) ?></span></td>
            <td class="text-end"><button class="btn btn-sm btn-outline-danger" onclick="confirmDel('?delete_store=<?= (int)$s['id'] ?>')">Delete</button></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- MATERIALS -->
  <div class="card section-card mb-4">
    <div class="card-header bg-success text-white">
      <span>Raw Materials</span>
      <button class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#addMaterial">Add Material</button>
    </div>
    <div id="addMaterial" class="collapse card-body">
      <form method="post" class="row g-2">
        <input type="hidden" name="entity" value="material">
        <input type="hidden" name="action" value="create">
        <div class="col-md-2"><label class="form-label">Code</label><input name="code" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
        <div class="col-md-2"><label class="form-label">Unit</label><input name="unit" class="form-control" placeholder="kg, m, pcs" required></div>
        <div class="col-md-2"><label class="form-label">Min</label><input name="min_stock" type="number" step="0.001" class="form-control" value="0"></div>
        <div class="col-md-2"><label class="form-label">Max</label><input name="max_stock" type="number" step="0.001" class="form-control" value="0"></div>
        <div class="col-md-1"><label class="form-label">Status</label>
          <select name="status" class="form-select"><option>OK</option><option>Low</option><option>Inactive</option></select>
        </div>
        <div class="col-12 d-flex justify-content-end"><button class="btn btn-success">Save</button></div>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Code</th><th>Name</th><th>Unit</th><th>Min</th><th>Max</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <?php while($m=$materials->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($m['code']) ?></td>
            <td><?= htmlspecialchars($m['name']) ?></td>
            <td><?= htmlspecialchars($m['unit']) ?></td>
            <td><?= htmlspecialchars($m['min_stock']) ?></td>
            <td><?= htmlspecialchars($m['max_stock']) ?></td>
            <td><span class="badge <?= $m['status']==='OK'?'bg-success':($m['status']==='Low'?'bg-warning text-dark':'bg-secondary') ?>"><?= htmlspecialchars($m['status']) ?></span></td>
            <td class="text-end"><button class="btn btn-sm btn-outline-danger" onclick="confirmDel('?delete_material=<?= (int)$m['id'] ?>')">Delete</button></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- INVENTORY -->
  <div class="card section-card mb-4">
    <div class="card-header bg-info text-white">
      <span>Inventory</span>
      <button class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#addInventory">Add/Update Inventory</button>
    </div>
    <div id="addInventory" class="collapse card-body">
      <form method="post" class="row g-2">
        <input type="hidden" name="entity" value="inventory">
        <input type="hidden" name="action" value="upsert">
        <div class="col-md-3"><label class="form-label">Store</label><select name="store_id" class="form-select" required>
          <option value="">Select store</option>
          <?php $storesSel = $db->query("SELECT id,name FROM stores ORDER BY name"); while($s=$storesSel->fetch_assoc()): ?>
            <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
          <?php endwhile; ?>
        </select></div>
        <div class="col-md-3"><label class="form-label">Material</label><select name="material_id" class="form-select" required>
          <option value="">Select material</option>
          <?php $matSel = $db->query("SELECT id,code,name FROM materials ORDER BY name"); while($m=$matSel->fetch_assoc()): ?>
            <option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['code'].' - '.$m['name']) ?></option>
          <?php endwhile; ?>
        </select></div>
        <div class="col-md-2"><label class="form-label">Warehouse</label><input name="warehouse" class="form-control" placeholder="e.g., WH-A"></div>
        <div class="col-md-2"><label class="form-label">Workspace</label><input name="workspace" class="form-control" placeholder="e.g., Bay-12"></div>
        <div class="col-md-3"><label class="form-label">Quantity</label><input name="quantity" type="number" step="0.001" class="form-control" required></div>
        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-info text-white w-100">Save</button></div>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Store</th><th>Warehouse</th><th>Workspace</th><th>Material</th><th>Unit</th><th>Qty</th><th>Min</th><th>Max</th><th>Status</th><th>Updated</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <?php while($i=$inventory->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($i['store_name']) ?></td>
            <td><?= htmlspecialchars($i['warehouse'] ?? '') ?></td>
            <td><?= htmlspecialchars($i['workspace'] ?? '') ?></td>
            <td><?= htmlspecialchars($i['material_code'].' - '.$i['material_name']) ?></td>
            <td><?= htmlspecialchars($i['unit']) ?></td>
            <td><?= htmlspecialchars($i['quantity']) ?></td>
            <td><?= htmlspecialchars($i['min_stock']) ?></td>
            <td><?= htmlspecialchars($i['max_stock']) ?></td>
            <td><span class="badge status-<?= htmlspecialchars($i['status']) ?>"><?= htmlspecialchars($i['status']) ?></span></td>
            <td><?= htmlspecialchars($i['last_update']) ?></td>
            <td class="text-end"><button class="btn btn-sm btn-outline-danger" onclick="confirmDel('?delete_inventory=<?= (int)$i['id'] ?>')">Delete</button></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- STOCK MOVEMENTS -->
  <div class="card section-card mb-4">
    <div class="card-header bg-secondary text-white">
      <span>Stock Movements</span>
      <button class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#addMovement">Add Movement</button>
    </div>
    <div id="addMovement" class="collapse card-body">
      <form method="post" class="row g-2">
        <input type="hidden" name="entity" value="movement">
        <input type="hidden" name="action" value="create">
        <div class="col-md-2"><label class="form-label">Date</label><input type="date" name="movement_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
        <div class="col-md-3"><label class="form-label">Store</label><select name="store_id" class="form-select" required>
          <option value="">Select store</option>
          <?php $storesSel2 = $db->query("SELECT id,name FROM stores ORDER BY name"); while($s=$storesSel2->fetch_assoc()): ?>
            <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
          <?php endwhile; ?>
        </select></div>
        <div class="col-md-3"><label class="form-label">Material</label><select name="material_id" class="form-select" required>
          <option value="">Select material</option>
          <?php $matSel2 = $db->query("SELECT id,code,name FROM materials ORDER BY name"); while($m=$matSel2->fetch_assoc()): ?>
            <option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['code'].' - '.$m['name']) ?></option>
          <?php endwhile; ?>
        </select></div>
        <div class="col-md-2"><label class="form-label">Type</label><select name="movement_type" class="form-select">
          <option>Receipt</option><option>Issue</option><option>Adjust</option>
        </select></div>
        <div class="col-md-2"><label class="form-label">Quantity</label><input name="quantity" type="number" step="0.001" class="form-control" placeholder="e.g. 50 or -5 for Adjust" required></div>
        <div class="col-md-2"><label class="form-label">Reference</label><input name="reference" class="form-control" placeholder="INV/REQ"></div>
        <div class="col-md-2"><label class="form-label">User</label><input name="user_name" class="form-control" placeholder="who"></div>
        <div class="col-md-2"><label class="form-label">Warehouse</label><input name="warehouse" class="form-control" placeholder="e.g., WH-A"></div>
        <div class="col-md-2"><label class="form-label">Workspace</label><input name="workspace" class="form-control" placeholder="e.g., Bay-12"></div>
        <div class="col-md-4"><label class="form-label">Notes</label><input name="notes" class="form-control"></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-secondary text-white w-100">Save</button></div>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Date</th><th>Store</th><th>Warehouse</th><th>Workspace</th><th>Material</th><th>Type</th><th>Qty</th><th>Ref</th><th>User</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <?php while($mv=$movements->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($mv['movement_date']) ?></td>
            <td><?= htmlspecialchars($mv['store_name']) ?></td>
            <td><?= htmlspecialchars($mv['warehouse'] ?? '') ?></td>
            <td><?= htmlspecialchars($mv['workspace'] ?? '') ?></td>
            <td><?= htmlspecialchars($mv['material_name']) ?></td>
            <td><span class="badge <?= $mv['movement_type']==='Receipt'?'bg-success':($mv['movement_type']==='Issue'?'bg-danger':'bg-info text-dark') ?>"><?= htmlspecialchars($mv['movement_type']) ?></span></td>
            <td><?= htmlspecialchars($mv['quantity']) ?></td>
            <td><?= htmlspecialchars($mv['reference']) ?></td>
            <td><?= htmlspecialchars($mv['user_name']) ?></td>
            <td class="text-end"><button class="btn btn-sm btn-outline-danger" onclick="confirmDel('?delete_movement=<?= (int)$mv['id'] ?>')">Delete</button></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- SUPPLIERS -->
  <div class="card section-card mb-4">
    <div class="card-header bg-warning text-dark">
      <span>Suppliers</span>
      <button class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#addSupplier">Add Supplier</button>
    </div>
    <div id="addSupplier" class="collapse card-body">
      <form method="post" class="row g-2">
        <input type="hidden" name="entity" value="supplier">
        <input type="hidden" name="action" value="create">
        <div class="col-md-4"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
        <div class="col-md-5"><label class="form-label">Contact</label><input name="contact" class="form-control" placeholder="phone / email"></div>
        <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option>Active</option><option>Inactive</option></select></div>
        <div class="col-md-1 d-flex align-items-end"><button class="btn btn-warning text-dark w-100">Save</button></div>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Name</th><th>Contact</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <?php while($sp=$suppliers->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($sp['name']) ?></td>
            <td><?= htmlspecialchars($sp['contact']) ?></td>
            <td><span class="badge <?= $sp['status']==='Active'?'bg-success':'bg-secondary' ?>"><?= htmlspecialchars($sp['status']) ?></span></td>
            <td class="text-end"><button class="btn btn-sm btn-outline-danger" onclick="confirmDel('?delete_supplier=<?= (int)$sp['id'] ?>')">Delete</button></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- STORE USERS -->
  <div class="card section-card mb-4">
    <div class="card-header bg-dark text-white">
      <span>Users</span>
      <button class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#addUser">Add User</button>
    </div>
    <div id="addUser" class="collapse card-body">
      <form method="post" class="row g-2">
        <input type="hidden" name="entity" value="store_user">
        <input type="hidden" name="action" value="create">
        <div class="col-md-4"><label class="form-label">Username</label><input name="username" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Role</label><input name="role" class="form-control" placeholder="Storekeeper, Manager, Security" required></div>
        <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option>Active</option><option>Inactive</option></select></div>
        <div class="col-md-1 d-flex align-items-end"><button class="btn btn-light w-100">Save</button></div>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Username</th><th>Role</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <?php while($u=$users->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($u['username']) ?></td>
            <td><?= htmlspecialchars($u['role']) ?></td>
            <td><span class="badge <?= $u['status']==='Active'?'bg-success':'bg-secondary' ?>"><?= htmlspecialchars($u['status']) ?></span></td>
            <td class="text-end"><button class="btn btn-sm btn-outline-danger" onclick="confirmDel('?delete_user=<?= (int)$u['id'] ?>')">Delete</button></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- REPORTERS -->
  <div class="card section-card mb-5">
    <div class="card-header bg-light">
      <span>Reporters</span>
      <button class="btn btn-dark btn-sm" data-bs-toggle="collapse" data-bs-target="#addReporter">Add Reporter</button>
    </div>
    <div id="addReporter" class="collapse card-body">
      <form method="post" class="row g-2">
        <input type="hidden" name="entity" value="reporter">
        <input type="hidden" name="action" value="create">
        <div class="col-md-4"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Role</label><input name="role" class="form-control" placeholder="e.g., Security"></div>
        <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option>Active</option><option>Inactive</option></select></div>
        <div class="col-md-1 d-flex align-items-end"><button class="btn btn-dark w-100">Save</button></div>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Name</th><th>Role</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <?php while($r=$reporters->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($r['name']) ?></td>
            <td><?= htmlspecialchars($r['role']) ?></td>
            <td><span class="badge <?= $r['status']==='Active'?'bg-success':'bg-secondary' ?>"><?= htmlspecialchars($r['status']) ?></span></td>
            <td class="text-end"><button class="btn btn-sm btn-outline-danger" onclick="confirmDel('?delete_reporter=<?= (int)$r['id'] ?>')">Delete</button></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="text-muted small">Tip: Use the sections above to manage all store-related entities. Stock movements automatically update Inventory.</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
