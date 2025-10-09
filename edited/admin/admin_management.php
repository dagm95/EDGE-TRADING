<?php
session_start();
// Protect this dashboard behind the existing admin session
if (empty($_SESSION['admin_logged'])) {
    header('Location: admin_login.php');
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Store Management Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Your custom CSS -->
    <link rel="stylesheet" href="admin managment.css">
    <!-- Chart.js for simple charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <!-- Optional: small inline style to avoid FOUC on some elements -->
    <style>.kpi-row{margin-top:.5rem}</style>
    <?php /* Prevent caching for admin pages */ ?>
    <meta http-equiv="Cache-Control" content="no-store" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <!-- Security headers (best-effort via meta for static hosting) -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff" />
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar flex-shrink-0">
            <div class="sidebar-header">
                <span>Admin Panel</span>
            </div>
            <a href="#" class="active"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            <a href="store_management.php"><i class="bi bi-shop me-2"></i>Store Management</a>
            <a href="workers.php"><i class="bi bi-people me-2"></i>Employees</a>
            <a href="projects.php"><i class="bi bi-kanban me-2"></i>Projects</a>
            <a href="project_management.php"><i class="bi bi-diagram-3 me-2"></i>Project Management</a>
            <a href="notifications.php"><i class="bi bi-bell me-2"></i>Notifications</a>
            <a href="admin_logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
        </div>
        <!-- Main content -->
        <div class="flex-grow-1">
            <!-- Top navbar -->
            <nav class="navbar navbar-expand navbar-light px-4">
                <div class="container-fluid">
                    <div class="d-flex align-items-center gap-3">
                        <span class="navbar-brand mb-0 h1">Dashboard</span>
                        <select id="timeframe" class="form-select form-select-sm w-auto">
                            <option value="7" selected>Last 7 days</option>
                            <option value="30">Last 30 days</option>
                            <option value="90">Last 90 days</option>
                        </select>
                    </div>
                    <div class="d-flex align-items-center">
                        <a class="btn btn-outline-primary btn-sm me-3" href="../php/admin.php"><i class="bi bi-calendar2-check me-1"></i>Appointments Admin</a>
                        <a class="position-relative me-3" href="notifications.php" title="Notifications">
                            <i class="bi bi-bell" style="font-size:1.4rem"></i>
                            <?php
                            require_once __DIR__ . '/../php/db.php';
                            $db = get_db();
                            $cntRes = $db->query("SELECT COUNT(*) AS c FROM notifications WHERE is_read = 0");
                            $unread = ($cntRes && ($row=$cntRes->fetch_assoc())) ? (int)$row['c'] : 0;
                            if ($unread > 0) {
                                echo '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">' . $unread . '</span>';
                            }
                            ?>
                        </a>
                        <span class="me-3">Welcome, Admin</span>
                        <img src="https://ui-avatars.com/api/?name=Admin" alt="Admin" class="rounded-circle" width="38" height="38">
                    </div>
                </div>
            </nav>
            <!-- Page content -->
            <div class="content">
                <h2>Overview</h2>
                <div class="row g-4 kpi-row">
                    <div class="col-sm-6 col-lg-3">
                        <a href="store_management.php" class="text-decoration-none text-reset">
                            <div class="card kpi kpi-blue shadow-sm">
                                <div class="card-body">
                                    <div class="kpi-header"><i class="bi bi-shop"></i><span>Stores</span></div>
                                    <div class="kpi-value" id="kpi-stores">0</div>
                                    <div class="kpi-sub">Active locations</div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a href="workers.php" class="text-decoration-none text-reset">
                            <div class="card kpi kpi-green shadow-sm">
                                <div class="card-body">
                                    <div class="kpi-header"><i class="bi bi-people"></i><span>Employees</span></div>
                                    <div class="kpi-value" id="kpi-workers">0</div>
                                    <div class="kpi-sub">On roster</div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a href="projects.php" class="text-decoration-none text-reset">
                            <div class="card kpi kpi-purple shadow-sm">
                                <div class="card-body">
                                    <div class="kpi-header"><i class="bi bi-kanban"></i><span>Projects</span></div>
                                    <div class="kpi-value" id="kpi-projects">0</div>
                                    <div class="kpi-sub">In progress</div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions my-4">
                    <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#modalStore"><i class="bi bi-plus-circle me-1"></i>New Store</button>
                    <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#modalWorker"><i class="bi bi-person-plus me-1"></i>New Employee</button>
                    <button class="btn btn-info text-white" data-bs-toggle="modal" data-bs-target="#modalProject"><i class="bi bi-kanban me-1"></i>New Project</button>
                </div>

                <!-- Charts and Activity -->
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="mb-0">Workforce & Projects Trend</h5>
                                    <small class="text-muted" id="chart-range">Last 7 days</small>
                                </div>
                                <canvas id="mainChart" height="120"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card shadow-sm mb-4">
                            <div class="card-body">
                                <h5 class="mb-3">Recent Activity</h5>
                                <ul class="list-group list-group-flush activity-list" id="activityList">
                                    <!-- populated by JS -->
                                </ul>
                            </div>
                        </div>
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="mb-3">Quick Links</h5>
                                <a class="btn btn-outline-secondary w-100 mb-2" href="store_management.php"><i class="bi bi-shop me-1"></i>Store Management</a>
                                <a class="btn btn-outline-secondary w-100 mb-2" href="workers.php"><i class="bi bi-people me-1"></i>Manage Employees</a>
                                <a class="btn btn-outline-secondary w-100 mb-2" href="notifications.php"><i class="bi bi-bell me-1"></i>Manage Notifications</a>
                                <a class="btn btn-outline-secondary w-100 mb-2" href="projects.php"><i class="bi bi-kanban me-1"></i>Manage Projects</a>
                                <a class="btn btn-outline-secondary w-100" href="project_management.php"><i class="bi bi-diagram-3 me-1"></i>Project Management</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sections (placeholders) -->
                <div class="mt-5" id="stores">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="section-title mb-0"><i class="bi bi-shop me-2"></i>Stores</h3>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalStore"><i class="bi bi-plus"></i> Add Store</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th><th>Location</th><th>Manager</th><th>Status</th><th></th>
                                </tr>
                            </thead>
                            <tbody id="storesTable">
                                <!-- populated by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-5" id="workers">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="section-title mb-0"><i class="bi bi-people me-2"></i>Employees</h3>
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalWorker"><i class="bi bi-person-plus"></i> Add Employee</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th><th>Role</th><th>Store</th><th>Status</th><th></th>
                                </tr>
                            </thead>
                            <tbody id="workersTable"></tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-5" id="projects">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="section-title mb-0"><i class="bi bi-kanban me-2"></i>Projects</h3>
                        <button class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#modalProject"><i class="bi bi-plus"></i> New Project</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Project</th><th>Store</th><th>Owner</th><th>Due</th><th>Status</th><th></th>
                                </tr>
                            </thead>
                            <tbody id="projectsTable"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Modals (simple placeholders) -->
                <div class="modal fade" id="modalStore" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header"><h5 class="modal-title">Add Store</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body">
                                <div class="mb-3"><label class="form-label">Name</label><input class="form-control" id="storeName"></div>
                                <div class="mb-3"><label class="form-label">Location</label><input class="form-control" id="storeLocation"></div>
                                <div class="mb-3"><label class="form-label">Manager</label><input class="form-control" id="storeManager"></div>
                            </div>
                            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button class="btn btn-primary" id="saveStore">Save</button></div>
                        </div>
                    </div>
                </div>
                <div class="modal fade" id="modalWorker" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header"><h5 class="modal-title">Add Employee</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body">
                                <div class="mb-3"><label class="form-label">Name</label><input class="form-control" id="workerName"></div>
                                <div class="mb-3"><label class="form-label">Role</label><input class="form-control" id="workerRole"></div>
                                <div class="mb-3"><label class="form-label">Store</label><input class="form-control" id="workerStore"></div>
                            </div>
                            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button class="btn btn-success" id="saveWorker">Save</button></div>
                        </div>
                    </div>
                </div>
                
                <div class="modal fade" id="modalProject" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header"><h5 class="modal-title">New Project</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body">
                                <div class="mb-3"><label class="form-label">Project Name</label><input class="form-control" id="projectName"></div>
                                <div class="mb-3"><label class="form-label">Store</label><input class="form-control" id="projectStore"></div>
                                <div class="mb-3"><label class="form-label">Due Date</label><input type="date" class="form-control" id="projectDue"></div>
                            </div>
                            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button class="btn btn-info text-white" id="saveProject">Save</button></div>
                        </div>
                    </div>
                </div>
                <!-- Add more dashboard widgets or charts below -->
            </div>
        </div>
    </div>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="admin managment.js"></script>
</body>
</html>