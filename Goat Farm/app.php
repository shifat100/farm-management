<?php
require_once 'config.php';
require_once 'functions.php';

$page = $_GET['page'] ?? 'dashboard';

// Intercept Public Profile first to allow unauthenticated QR views
if ($page === 'public_profile') {
    include 'public_profile.php';
    exit;
}

if (!isset($_SESSION['user']) && $page !== 'login') { 
    header('Location: login.php'); 
    exit; 
}
if ($page === 'login') { 
    include 'login.php'; 
    exit; 
}

require_once 'auth.php';

// Intercept CSV exports before HTML output to prevent 'headers already sent' issues
if (isset($_GET['export']) && $page === 'reports') {
    include 'reports.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goat Farm Manager Pro</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Glassmorphism Theme -->
    <link href="assets/css/glassmorphism.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        #wrapper { overflow-x: hidden; }
        #sidebar { transition: all 0.3s ease; }
    </style>
</head>
<body>
<div class="d-flex" id="wrapper">
    
    <!-- ===== সাইডবার ===== -->
    <div class="bg-white text-dark p-3 d-flex flex-column" style="width:260px; min-height:100vh;" id="sidebar">
        <div class="text-center mb-4">
            <i class="bi bi-goat fs-1 text-primary"></i>
            <h5 class="mt-2 text-uppercase tracking-wider fw-bold">Farm Management</h5>
        </div>
        <nav class="nav flex-column mb-auto">
            <?php
            $links = [
                'dashboard'   => ['label' => 'Dashboard', 'icon' => 'speedometer2'],
                'animals'     => ['label' => 'Animals', 'icon' => 'people'],
                'activities'  => ['label' => 'Activities', 'icon' => 'list-check'],
                'finance'     => ['label' => 'Finance', 'icon' => 'cash-stack'],
                'vaccination' => ['label' => 'Vaccination', 'icon' => 'shield-check'],
                'reports'     => ['label' => 'Reports', 'icon' => 'file-bar-graph'],
                'inventory'   => ['label' => 'Inventory', 'icon' => 'box-seam'],
                'tasks'       => ['label' => 'Tasks Board', 'icon' => 'clipboard-check'],
                'scanner'     => ['label' => 'QR Scanner', 'icon' => 'qr-code-scan'],
                'backup'      => ['label' => 'Database Backup', 'icon' => 'cloud-arrow-down-fill']
            ];
            if ($_SESSION['user']['role'] === 'admin') {
                $links['users'] = ['label' => 'Users', 'icon' => 'people-fill'];
            }
            foreach ($links as $k => $v) {
                $isActive = ($page === $k || ($k === 'animals' && in_array($page, ['animal_profile', 'animals']))) ? 'active' : '';
                echo "<a class='nav-link text-dark my-1 rounded p-2 {$isActive}' href='?page={$k}'><i class='bi bi-{$v['icon']} me-2'></i> {$v['label']}</a>";
            }
            ?>
        </nav>
        <div class="pt-3 border-top border-light">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="small text-muted"><i class="bi bi-circle-fill text-success" id="network-dot"></i> <span id="network-text">Online</span></span>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="darkModeToggle">
                    <label class="form-check-label small" for="darkModeToggle">Dark</label>
                </div>
            </div>
            <a href="logout.php" class="btn btn-danger btn-sm w-100"><i class="bi bi-box-arrow-left"></i> Logout (<?=e($_SESSION['user']['username'])?>)</a>
        </div>
    </div>

    <!-- ===== সাইডবার ওভারলে (মোবাইলের জন্য) ===== -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <!-- ===== মেইন কন্টেন্ট ===== -->
    <div class="flex-grow-1 d-flex flex-column">
        <!-- নেভবার (হ্যামবার্গার বাটন সহ) -->
        <nav class="navbar navbar-expand navbar-light shadow-sm">
            <div class="container-fluid">
                <!-- হ্যামবার্গার বাটন (শুধু মোবাইলে) -->
                <button class="btn btn-toggle-sidebar d-md-none me-2" type="button" onclick="toggleSidebar()">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <span class="navbar-brand mb-0 h4"><i class="bi me-2"></i><?=ucfirst($page)?></span>
                <button class="btn btn-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#filterCanvas">
                    <i class="bi bi-funnel"></i> Filters
                </button>
            </div>
        </nav>

        <!-- Global Offcanvas Filters -->
        <div class="offcanvas offcanvas-end" id="filterCanvas">
            <div class="offcanvas-header border-bottom">
                <h5><i class="bi bi-funnel-fill me-2"></i>Advanced Filters</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body">
                <form id="filterForm" method="get">
                    <input type="hidden" name="page" value="<?=e($page)?>">
                    <div class="mb-2">
                        <label class="form-label small mb-1">Type</label>
                        <select name="type" class="form-select">
                            <option value="">All Types</option>
                            <option <?=($_GET['type']??'')=='Goat'?'selected':''?>>Goat</option>
                            <option <?=($_GET['type']??'')=='Buck'?'selected':''?>>Buck</option>
                            <option <?=($_GET['type']??'')=='Castrated'?'selected':''?>>Castrated</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Breed</label>
                        <input name="breed" class="form-control" value="<?=e($_GET['breed']??'')?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Health Status</label>
                        <select name="health_status" class="form-select">
                            <option value="">All</option>
                            <option <?=($_GET['health_status']??'')=='Healthy'?'selected':''?>>Healthy</option>
                            <option <?=($_GET['health_status']??'')=='Sick'?'selected':''?>>Sick</option>
                            <option <?=($_GET['health_status']??'')=='Critical'?'selected':''?>>Critical</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Vaccination Status</label>
                        <select name="vaccination_status" class="form-select">
                            <option value="">All</option>
                            <option <?=($_GET['vaccination_status']??'')=='Complete'?'selected':''?>>Complete</option>
                            <option <?=($_GET['vaccination_status']??'')=='Pending'?'selected':''?>>Pending</option>
                            <option <?=($_GET['vaccination_status']??'')=='Overdue'?'selected':''?>>Overdue</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Pregnancy Status</label>
                        <select name="pregnancy_status" class="form-select">
                            <option value="">All</option>
                            <option <?=($_GET['pregnancy_status']??'')=='Pregnant'?'selected':''?>>Pregnant</option>
                            <option <?=($_GET['pregnancy_status']??'')=='Not Pregnant'?'selected':''?>>Not Pregnant</option>
                        </select>
                    </div>
                    <div class="row mb-2">
                        <div class="col"><label class="form-label small mb-1">Weight Min</label><input type="number" step="0.1" name="weight_min" class="form-control" value="<?=e($_GET['weight_min']??'')?>"></div>
                        <div class="col"><label class="form-label small mb-1">Max</label><input type="number" step="0.1" name="weight_max" class="form-control" value="<?=e($_GET['weight_max']??'')?>"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col"><label class="form-label small mb-1">Age Min (m)</label><input type="number" name="age_min" class="form-control" value="<?=e($_GET['age_min']??'')?>"></div>
                        <div class="col"><label class="form-label small mb-1">Max</label><input type="number" name="age_max" class="form-control" value="<?=e($_GET['age_max']??'')?>"></div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Sale Readiness</label>
                        <select name="sale_readiness" class="form-select">
                            <option value="">All</option>
                            <option <?=($_GET['sale_readiness']??'')=='Ready'?'selected':''?>>Ready</option>
                            <option <?=($_GET['sale_readiness']??'')=='Not Ready'?'selected':''?>>Not Ready</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            <option <?=($_GET['status']??'')=='Active'?'selected':''?>>Active</option>
                            <option <?=($_GET['status']??'')=='Sold'?'selected':''?>>Sold</option>
                            <option <?=($_GET['status']??'')=='Dead'?'selected':''?>>Dead</option>
                        </select>
                    </div>
                    <div class="row mb-2">
                        <div class="col"><label class="form-label small mb-1">Date From</label><input type="date" name="date_from" class="form-control" value="<?=e($_GET['date_from']??'')?>"></div>
                        <div class="col"><label class="form-label small mb-1">To</label><input type="date" name="date_to" class="form-control" value="<?=e($_GET['date_to']??'')?>"></div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Shed</label>
                        <select name="shed_id" class="form-select">
                            <option value="">All Sheds</option>
                            <?php foreach ($pdo->query("SELECT id, name FROM sheds") as $s) {
                                $selected = (isset($_GET['shed_id']) && $_GET['shed_id'] == $s['id']) ? 'selected' : '';
                                echo "<option value='{$s['id']}' {$selected}>" . e($s['name']) . "</option>";
                            } ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Profit/Loss</label>
                        <select name="profit_loss" class="form-select">
                            <option value="">All</option>
                            <option value="profit" <?=($_GET['profit_loss']??'')=='profit'?'selected':''?>>Profit</option>
                            <option value="loss" <?=($_GET['profit_loss']??'')=='loss'?'selected':''?>>Loss</option>
                        </select>
                    </div>
                    <button class="btn btn-primary w-100 mb-2"><i class="bi bi-search"></i> Apply Filters</button>
                    <a href="?page=<?=e($page)?>" class="btn btn-secondary w-100"><i class="bi bi-x-circle"></i> Clear Filters</a>
                </form>
            </div>
        </div>

        <!-- ===== কন্টেন্ট এরিয়া ===== -->
        <div class="container-fluid p-4">
            <?php
            $file = $page . '.php';
            if (file_exists($file)) { 
                include $file; 
            } else { 
                echo "<div class='alert alert-danger'><h3><i class='bi bi-exclamation-triangle'></i> Resource endpoint missing.</h3></div>"; 
            }
            ?>
        </div>
    </div>
</div>

<!-- ===== স্ক্রিপ্ট ===== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="assets/js/main.js"></script>

<script>
// ===== সাইডবার টগল (মোবাইল) =====
function toggleSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.querySelector('.sidebar-overlay');
    sidebar.classList.toggle('show');
    if (overlay) overlay.classList.toggle('active');
}

// ওভারলে ক্লিক করলে সাইডবার বন্ধ
document.addEventListener('click', function(e) {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.querySelector('.sidebar-overlay');
    if (sidebar.classList.contains('show') && !sidebar.contains(e.target) && !e.target.closest('.btn-toggle-sidebar')) {
        sidebar.classList.remove('show');
        if (overlay) overlay.classList.remove('active');
    }
});

// ===== ডার্ক মোড =====
const themeToggle = document.getElementById("darkModeToggle");
const htmlElement = document.documentElement;

if (themeToggle) {
    const activeTheme = localStorage.getItem("system-ui-theme") || "light";
    htmlElement.setAttribute("data-bs-theme", activeTheme);
    themeToggle.checked = (activeTheme === "dark");

    themeToggle.addEventListener("change", function() {
        const selectedTheme = themeToggle.checked ? "dark" : "light";
        htmlElement.setAttribute("data-bs-theme", selectedTheme);
        localStorage.setItem("system-ui-theme", selectedTheme);
    });
}

// ===== নেটওয়ার্ক স্ট্যাটাস =====
function updateNetworkStatus() {
    var dot = document.getElementById('network-dot');
    var text = document.getElementById('network-text');
    if (navigator.onLine) {
        if (dot) dot.className = "bi bi-circle-fill text-success";
        if (text) text.textContent = "Online";
    } else {
        if (dot) dot.className = "bi bi-circle-fill text-danger";
        if (text) text.textContent = "Offline";
    }
}
window.addEventListener("online", updateNetworkStatus);
window.addEventListener("offline", updateNetworkStatus);
updateNetworkStatus();

// ===== Service Worker =====
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('sw.js').catch(function(err) {
            console.log("Service Worker registration skipped: ", err);
        });
    });
}
</script>
</body>
</html>