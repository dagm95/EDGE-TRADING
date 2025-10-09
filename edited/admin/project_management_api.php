<?php
// Lightweight endpoints for Project Management CRUD (JSON responses)
session_start();
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['admin_logged'])) { http_response_code(401); echo json_encode(['error'=>'unauthorized']); exit; }
require_once __DIR__ . '/../php/db.php';
$db = get_db();

function jerr($msg,$code=400){ http_response_code($code); echo json_encode(['error'=>$msg]); exit; }
function ok($data=[]){ echo json_encode(['ok'=>true]+$data); exit; }

$a = $_GET['action'] ?? $_POST['action'] ?? '';
try {
  if ($a === 'list_projects') {
    $res=$db->query("SELECT p.*, s.name AS store_name FROM projects p LEFT JOIN stores s ON s.id=p.store_id ORDER BY p.id DESC LIMIT 500");
    $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r; ok(['rows'=>$rows]);
  }
  if ($a === 'create_project') {
    $name=trim($_POST['name']??''); if($name==='') jerr('name required');
    $stmt=$db->prepare("INSERT INTO projects (name, description, store_id, owner, due_date, start_date, end_date, duration_months, status, workspace_type, warehouse, workspace, customer_email, manager_user_id, reporter_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $dur = (int)($_POST['duration_months']??0);
    $store_id = $_POST['store_id']!==''?(int)$_POST['store_id']:null;
    $mgr = $_POST['manager_user_id']!==''?(int)$_POST['manager_user_id']:null;
    $rep = $_POST['reporter_user_id']!==''?(int)$_POST['reporter_user_id']:null;
    $stmt->bind_param('ssisssiisssssii', $_POST['name'], $_POST['description'], $store_id, $_POST['owner'], $_POST['due_date'], $_POST['start_date'], $_POST['end_date'], $dur, $_POST['status'], $_POST['workspace_type'], $_POST['warehouse'], $_POST['workspace'], $_POST['customer_email'], $mgr, $rep);
    $stmt->execute(); ok(['id'=>$db->insert_id]);
  }
  if ($a === 'add_item') {
    $stmt=$db->prepare("INSERT INTO project_items (project_id, material_id, quantity, warehouse, workspace, notes) VALUES (?,?,?,?,?,?)");
    $qty = is_numeric($_POST['quantity']??'')?(float)$_POST['quantity']:0;
    $stmt->bind_param('iidsss', $_POST['project_id'], $_POST['material_id'], $qty, $_POST['warehouse'], $_POST['workspace'], $_POST['notes']);
    $stmt->execute(); ok(['id'=>$db->insert_id]);
  }
  if ($a === 'list_items') {
    $pid = (int)($_GET['project_id']??0);
    $q = $pid?" WHERE pi.project_id=$pid":"";
    $res=$db->query("SELECT pi.*, m.code, m.name AS material_name FROM project_items pi JOIN materials m ON m.id=pi.material_id$q ORDER BY pi.id DESC");
    $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r; ok(['rows'=>$rows]);
  }
  if ($a === 'create_report') {
    $stmt=$db->prepare("INSERT INTO project_reports (project_id, report_type, title, body, report_date, sent_to, created_by) VALUES (?,?,?,?,?,?,?)");
    $created_by = $_POST['created_by']!==''?(int)$_POST['created_by']:null;
    $stmt->bind_param('isssssi', $_POST['project_id'], $_POST['report_type'], $_POST['title'], $_POST['body'], $_POST['report_date'], $_POST['sent_to'], $created_by);
    $stmt->execute(); ok(['id'=>$db->insert_id]);
  }
  if ($a === 'list_reports') {
    $pid = (int)($_GET['project_id']??0);
    $q = $pid?" WHERE pr.project_id=$pid":"";
    $res=$db->query("SELECT pr.*, p.name AS project_name FROM project_reports pr JOIN projects p ON p.id=pr.project_id$q ORDER BY pr.id DESC");
    $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r; ok(['rows'=>$rows]);
  }
  jerr('unknown action',404);
} catch (Throwable $e) {
  jerr($e->getMessage(), 500);
}
