<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goat Farm Manager</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.0/mdb.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex" id="wrapper">
    <!-- Sidebar -->
    <div class="bg-dark text-white p-3" style="width:260px; min-height:100vh;" id="sidebar">
        <div class="text-center mb-4"><i class="bi bi-goat fs-1 text-warning"></i><h5>Farm Manager</h5></div>
        <nav class="nav flex-column">
            <a class="nav-link text-white <?=($page=='dashboard'?'active':'')?>" href="?page=dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a class="nav-link text-white <?=($page=='animals'?'active':'')?>" href="?page=animals"><i class="bi bi-people"></i> Animals</a>
            <a class="nav-link text-white <?=($page=='activities'?'active':'')?>" href="?page=activities"><i class="bi bi-list-check"></i> Activities</a>
            <a class="nav-link text-white <?=($page=='finance'?'active':'')?>" href="?page=finance"><i class="bi bi-cash-stack"></i> Finance</a>
            <a class="nav-link text-white <?=($page=='vaccination'?'active':'')?>" href="?page=vaccination"><i class="bi bi-shield-check"></i> Vaccination</a>
            <a class="nav-link text-white <?=($page=='reports'?'active':'')?>" href="?page=reports"><i class="bi bi-file-bar-graph"></i> Reports</a>
            <?php if($_SESSION['user']['role']=='admin'): ?>
            <a class="nav-link text-white <?=($page=='users'?'active':'')?>" href="?page=users"><i class="bi bi-people-fill"></i> Users</a>
            <?php endif; ?>
        </nav>
        <div class="mt-auto pt-3">
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="darkModeToggle"><label class="form-check-label text-white">Dark</label></div>
            <a href="logout.php" class="btn btn-danger btn-sm w-100">Logout (<?=$_SESSION['user']['username']?>)</a>
        </div>
    </div>
    <!-- Page Content -->
    <div class="flex-grow-1">
        <nav class="navbar navbar-expand navbar-dark bg-primary"><div class="container-fluid">
            <span class="navbar-brand"><?=ucfirst($page)?></span>
            <button class="btn btn-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#filterCanvas"><i class="bi bi-funnel"></i> Filters</button>
        </div></nav>
        <!-- Filter Offcanvas -->
        <div class="offcanvas offcanvas-end" id="filterCanvas">
            <div class="offcanvas-header"><h5>Global Filters</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div>
            <div class="offcanvas-body">
                <form id="filterForm" method="get">
                    <input type="hidden" name="page" value="<?=$page?>">
                    <div class="mb-2"><label>Type</label><select name="type" class="form-select"><option value="">All</option><option>Goat</option><option>Buck</option><option>Castrated</option></select></div>
                    <div class="mb-2"><label>Breed</label><input name="breed" class="form-control"></div>
                    <div class="mb-2"><label>Health</label><select name="health_status" class="form-select"><option value="">All</option><option>Healthy</option><option>Sick</option><option>Critical</option></select></div>
                    <div class="mb-2"><label>Vaccination</label><select name="vaccination_status" class="form-select"><option value="">All</option><option>Complete</option><option>Pending</option><option>Overdue</option></select></div>
                    <div class="mb-2"><label>Pregnancy</label><select name="pregnancy_status" class="form-select"><option value="">All</option><option>Pregnant</option><option>Not Pregnant</option></select></div>
                    <div class="row mb-2"><div class="col"><label>Weight Min</label><input type="number" step="0.1" name="weight_min" class="form-control"></div><div class="col"><label>Max</label><input type="number" step="0.1" name="weight_max" class="form-control"></div></div>
                    <div class="mb-2"><label>Sale Readiness</label><select name="sale_readiness" class="form-select"><option value="">All</option><option>Ready</option><option>Not Ready</option></select></div>
                    <div class="mb-2"><label>Status</label><select name="status" class="form-select"><option value="">All</option><option>Active</option><option>Sold</option><option>Dead</option></select></div>
                    <div class="row mb-2"><div class="col"><label>From</label><input type="date" name="date_from" class="form-control"></div><div class="col"><label>To</label><input type="date" name="date_to" class="form-control"></div></div>
                    <div class="mb-2"><label>Shed</label><select name="shed_id" class="form-select"><option value="">All</option><?php foreach($pdo->query("SELECT id,name FROM sheds") as $s) echo "<option value='{$s['id']}'>{$s['name']}</option>"; ?></select></div>
                    <div class="mb-2"><label>Profit/Loss</label><select name="profit_loss" class="form-select"><option value="">All</option><option value="profit">Profit</option><option value="loss">Loss</option></select></div>
                    <button class="btn btn-primary w-100">Apply</button>
                </form>
            </div>
        </div>
        <div class="container-fluid p-4">