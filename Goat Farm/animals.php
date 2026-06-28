<?php
require_once 'functions.php';

$action = $_GET['action'] ?? 'list';
$filters = $_GET;
$filter = buildFilterWhere($filters);

$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("<div class='alert alert-danger'>Invalid security token.</div>");
    }

    if (isset($_POST['add_profile_cost'])) {
        $cost_category = $_POST['cost_category'] ?? 'Other';
        $cost_amount = (float)($_POST['cost_amount'] ?? 0.00);
        $cost_note = trim($_POST['cost_note'] ?? '');
        $cost_date = $_POST['cost_date'] ?? date('Y-m-d');
        if ($cost_amount > 0 && $id > 0) {
            $stmt_ac = $pdo->prepare("INSERT INTO animal_costs (animal_id, category, amount, cost_date, note) VALUES (?, ?, ?, ?, ?)");
            $stmt_ac->execute([$id, $cost_category, $cost_amount, $cost_date, $cost_note]);
            $stmt_tx = $pdo->prepare("INSERT INTO transactions (animal_id, type, category, amount, trans_date, description) VALUES (?, 'Expense', ?, ?, ?, ?)");
            $stmt_tx->execute([$id, $cost_category, $cost_amount, $cost_date, "Profile Log: " . $cost_note]);
            echo "<div class='alert alert-success'>Cost entry logged successfully.</div>";
        }
    }

    if (isset($_POST['add_weight_record'])) {
        $weight = (float)($_POST['weight'] ?? 0);
        $record_date = $_POST['record_date'] ?? date('Y-m-d');
        $notes = trim($_POST['weight_notes'] ?? '');
        if ($weight > 0 && $id > 0) {
            addWeightRecord($pdo, $id, $weight, $record_date, $notes);
            echo "<div class='alert alert-success'>Weight record added successfully.</div>";
        }
    }
}

// ---- LIST VIEW ----
if ($action === 'list') {
    $search = trim($_GET['q'] ?? '');
    $clause = $filter['clause'];
    $params = $filter['params'];
    if ($search !== '') {
        if ($clause === '') $clause = " WHERE (a.name LIKE :search1 OR a.auto_id LIKE :search2 OR a.breed LIKE :search3)";
        else $clause .= " AND (a.name LIKE :search1 OR a.auto_id LIKE :search2 OR a.breed LIKE :search3)";
        $params[':search1'] = '%' . $search . '%';
        $params[':search2'] = '%' . $search . '%';
        $params[':search3'] = '%' . $search . '%';
    }
    $count_sql = "SELECT COUNT(*) FROM animals a LEFT JOIN sheds s ON a.shed_id = s.id {$clause}";
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_records = (int)$stmt_count->fetchColumn();
    $limit = 10; 
    $total_pages = ($total_records > 0) ? (int)ceil($total_records / $limit) : 1;
    $current_page = max(1, min($total_pages, (int)($_GET['p'] ?? 1)));
    $offset = ($current_page - 1) * $limit;
    $sql = "SELECT a.*, s.name as shed FROM animals a LEFT JOIN sheds s ON a.shed_id = s.id {$clause} ORDER BY a.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $animals = $stmt->fetchAll();
    function getPageUrl($pageNum) { $params = $_GET; $params['p'] = $pageNum; return '?' . http_build_query($params); }
    ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h4 class="mb-0">Animals Registry (<?=$total_records?> Records)</h4>
        <a href="?page=animals&action=add" class="btn btn-success"><i class="bi bi-plus-circle"></i> Add Animal</a>
    </div>

    <div class="card p-3 mb-3 bg-white shadow-sm border">
        <form method="get" class="row g-2 align-items-center">
            <input type="hidden" name="page" value="animals">
            <input type="hidden" name="action" value="list">
            
            <div class="col-12 col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Search..." value="<?=e($search)?>">
                </div>
            </div>
            
            <div class="col-6 col-md-2">
                <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="Goat" <?=($filters['type']??'')==='Goat'?'selected':''?>>Goat</option>
                    <option value="Buck" <?=($filters['type']??'')==='Buck'?'selected':''?>>Buck</option>
                    <option value="Castrated" <?=($filters['type']??'')==='Castrated'?'selected':''?>>Castrated</option>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <select name="health_status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Health</option>
                    <option value="Healthy" <?=($filters['health_status']??'')==='Healthy'?'selected':''?>>Healthy</option>
                    <option value="Sick" <?=($filters['health_status']??'')==='Sick'?'selected':''?>>Sick</option>
                    <option value="Critical" <?=($filters['health_status']??'')==='Critical'?'selected':''?>>Critical</option>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="Active" <?=($filters['status']??'')==='Active'?'selected':''?>>Active</option>
                    <option value="Sold" <?=($filters['status']??'')==='Sold'?'selected':''?>>Sold</option>
                    <option value="Dead" <?=($filters['status']??'')==='Dead'?'selected':''?>>Dead</option>
                </select>
            </div>

            <div class="col-12 col-md-3 d-flex flex-wrap gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-filter"></i> Apply</button>
                <a href="?page=animals" class="btn btn-sm btn-secondary flex-grow-1"><i class="bi bi-x-circle"></i> Reset</a>
            </div>
        </form>
    </div>

    <div class="table-responsive bg-white rounded shadow-sm p-2 p-md-3 border mb-3">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th>ID</th><th>Name</th><th>Type</th><th>Breed</th>
                    <th class="d-none d-sm-table-cell">Age</th>
                    <th class="d-none d-sm-table-cell">Weight</th>
                    <th>Health</th>
                    <th class="d-none d-md-table-cell">Min Sell</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($animals as $a): 
                $cost = calcTotalCost($pdo, $a['id']);
                $minSell = $cost * 1.10;
            ?>
            <tr>
                <td><strong><?=e($a['auto_id'])?></strong></td>
                <td><?=e($a['name'])?></td>
                <td><span class="badge badge-light"><?=e($a['type'])?></span></td>
                <td><?=e($a['breed'])?></td>
                <td class="d-none d-sm-table-cell"><?=getAge($a['dob'])?></td>
                <td class="d-none d-sm-table-cell"><?=e($a['weight'])?> kg</td>
                <td>
                    <span class="badge bg-<?=$a['health_status'] === 'Healthy' ? 'success' : ($a['health_status'] === 'Sick' ? 'warning' : 'danger')?>">
                        <?=e($a['health_status'])?>
                    </span>
                </td>
                <td class="d-none d-md-table-cell">$<?=number_format($minSell, 2)?></td>
                <td>
                    <a href="?page=animals&action=profile&id=<?=(int)$a['id']?>" class="btn btn-sm btn-info py-1 px-2"><i class="bi bi-eye"></i></a>
                    <a href="?page=animals&action=edit&id=<?=(int)$a['id']?>" class="btn btn-sm btn-warning py-1 px-2"><i class="bi bi-pencil"></i></a>
                </td>
            </tr>
            <?php endforeach; if(empty($animals)) echo "<tr><td colspan='9' class='text-center text-muted py-3'>No records found.</td></tr>"; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav>
        <ul class="pagination pagination-sm justify-content-center flex-wrap">
            <li class="page-item <?=$current_page <= 1 ? 'disabled' : ''?>">
                <a class="page-link" href="<?=getPageUrl(1)?>"><i class="bi bi-chevron-double-left"></i></a>
            </li>
            <li class="page-item <?=$current_page <= 1 ? 'disabled' : ''?>">
                <a class="page-link" href="<?=getPageUrl($current_page - 1)?>"><i class="bi bi-chevron-left"></i></a>
            </li>
            <?php 
            $start_page = max(1, $current_page - 2);
            $end_page = min($total_pages, $current_page + 2);
            for ($i = $start_page; $i <= $end_page; $i++): 
            ?>
            <li class="page-item <?=$current_page === $i ? 'active' : ''?>">
                <a class="page-link" href="<?=getPageUrl($i)?>"><?=$i?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?=$current_page >= $total_pages ? 'disabled' : ''?>">
                <a class="page-link" href="<?=getPageUrl($current_page + 1)?>"><i class="bi bi-chevron-right"></i></a>
            </li>
            <li class="page-item <?=$current_page >= $total_pages ? 'disabled' : ''?>">
                <a class="page-link" href="<?=getPageUrl($total_pages)?>"><i class="bi bi-chevron-double-right"></i></a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
    <?php
}

// ---- ANIMAL PROFILE VIEW ----
elseif ($action === 'profile') {
    $stmt = $pdo->prepare("SELECT a.*, s.name as shed, m.name as mother, f.name as father FROM animals a LEFT JOIN sheds s ON a.shed_id = s.id LEFT JOIN animals m ON a.mother_id = m.id LEFT JOIN animals f ON a.father_id = f.id WHERE a.id = ?");
    $stmt->execute([$id]);
    $animal = $stmt->fetch();
    if (!$animal) die("<div class='alert alert-danger'>Animal not found.</div>");
    
    $cost = calcTotalCost($pdo, $id);
    $minSell = $cost * 1.10;
    $profit = ($animal['selling_price'] !== null && $animal['selling_price'] !== '') ? (float)$animal['selling_price'] - $cost : null;
    $roi = ($profit !== null && $cost > 0) ? ($profit / $cost) * 100 : 0;
    
    $weight_records = $pdo->prepare("SELECT * FROM weight_records WHERE animal_id = ? ORDER BY record_date ASC");
    $weight_records->execute([$id]);
    $weights = $weight_records->fetchAll();
    $weight_dates = json_encode(array_column($weights, 'record_date'));
    $weight_values = json_encode(array_column($weights, 'weight'));
    
    $stmt_cost = $pdo->prepare("SELECT * FROM animal_costs WHERE animal_id = ? ORDER BY cost_date DESC");
    $stmt_cost->execute([$id]);
    $costs = $stmt_cost->fetchAll();
    $stmt_act = $pdo->prepare("SELECT * FROM activities WHERE animal_id = ? ORDER BY activity_date DESC LIMIT 10");
    $stmt_act->execute([$id]);
    $activities = $stmt_act->fetchAll();
    $stmt_vac = $pdo->prepare("SELECT vr.*, v.name as vname FROM vaccination_records vr JOIN vaccines v ON vr.vaccine_id = v.id WHERE vr.animal_id = ? ORDER BY vr.due_date DESC");
    $stmt_vac->execute([$id]);
    $vaccs = $stmt_vac->fetchAll();

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $qrDataUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?page=public_profile&id=' . $animal['id'];
    
    $isBlackBengal = (stripos($animal['breed'] ?? '', 'black bengal') !== false);
    $estMeat = $isBlackBengal ? ($animal['weight'] * 0.60) : ($animal['weight'] * 0.50);
    $estSkin = $isBlackBengal ? ($animal['weight'] * (1.3 / 20.0)) : 0.00;
    ?>
    <div class="row g-3">
        <div class="col-12 col-md-4 text-center mb-3">
            <div class="card p-3 shadow-sm bg-white">
                <img src="assets/images/<?=e($animal['image'])?>" class="img-fluid rounded mb-3 shadow" style="max-height:220px; object-fit: cover;" onerror="this.src='assets/images/default.png'">
                <h3><?=e($animal['name'])?></h3>
                <h5 class="text-muted"><?=e($animal['auto_id'])?></h5>
                <hr>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?=urlencode($qrDataUrl)?>" class="img-thumbnail" alt="QR">
                <p class="small text-muted mt-2">Scan QR for public profile</p>
            </div>
        </div>
        <div class="col-12 col-md-8">
            <div class="card p-3 p-md-4 shadow-sm bg-white mb-3">
                <h5 class="mb-3 text-primary border-bottom pb-2"><i class="bi bi-info-circle-fill"></i> Core Metrics</h5>
                <div class="row g-2">
                    <div class="col-6 col-lg-3"><strong>Type:</strong> <?=e($animal['type'])?></div>
                    <div class="col-6 col-lg-3"><strong>Breed:</strong> <?=e($animal['breed'])?></div>
                    <div class="col-6 col-lg-3"><strong>DOB:</strong> <?=e($animal['dob'])?></div>
                    <div class="col-6 col-lg-3"><strong>Weight:</strong> <?=e($animal['weight'])?> kg</div>
                    <div class="col-6 col-lg-3"><strong>Color:</strong> <?=e($animal['color'] ?? 'N/A')?></div>
                    <div class="col-6 col-lg-3"><strong>Vaccination:</strong> <?=e($animal['vaccination_status'])?></div>
                    <div class="col-6 col-lg-3"><strong>Pregnancy:</strong> <?=e($animal['pregnancy_status'])?></div>
                    <div class="col-6 col-lg-3"><strong>Status:</strong> <?=e($animal['status'])?></div>
                    <div class="col-6 col-lg-3"><strong>Last Heat:</strong> <?=e($animal['last_heat_date'] ?? 'N/A')?></div>
                    <div class="col-6 col-lg-3"><strong>Next Heat:</strong> <?=e($animal['next_heat_date'] ?? 'N/A')?></div>
                    <div class="col-6 col-lg-3"><strong>Kidding:</strong> <?=e($animal['kidding_date'] ?? 'N/A')?></div>
                    <div class="col-6 col-lg-3"><strong>Shed:</strong> <?=e($animal['shed'] ?? 'Unallocated')?></div>
                </div>
            </div>

            <div class="card p-3 p-md-4 shadow-sm bg-white mb-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center border-bottom pb-2 mb-3">
                    <h5 class="mb-0"><i class="bi bi-graph-up-arrow text-primary me-2"></i>Weight Trend</h5>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#weightModal"><i class="bi bi-plus-circle"></i> Log Weight</button>
                </div>
                <?php if(empty($weights)): ?>
                    <p class="text-muted text-center py-2">No weight records yet.</p>
                <?php else: ?>
                <div style="height: 200px;">
                    <canvas id="weightChart"></canvas>
                </div>
                <?php endif; ?>
            </div>

            <div class="card p-3 p-md-4 shadow-sm bg-white mb-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <h5 class="mb-0 text-success"><i class="bi bi-wallet2"></i> Cost & Profit</h5>
                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#profileCostModal"><i class="bi bi-plus-circle me-1"></i> Log Expense</button>
                </div>
                <div class="row text-center g-2">
                    <div class="col-4"><div class="border rounded p-2 bg-light"><h6>Cost</h6><strong>$<?=number_format($cost, 2)?></strong></div></div>
                    <div class="col-4"><div class="border rounded p-2 bg-light"><h6>Min Sell</h6><strong>$<?=number_format($minSell, 2)?></strong></div></div>
                    <?php if ($profit !== null): ?>
                    <div class="col-4"><div class="border rounded p-2 bg-light"><h6>Profit</h6><strong class="<?=$profit >= 0 ? 'text-success' : 'text-danger'?>">$<?=number_format($profit, 2)?></strong></div></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="card p-3 p-md-4 shadow-sm bg-white h-100">
                        <h5 class="mb-3 text-info border-bottom pb-2"><i class="bi bi-activity"></i> Activities</h5>
                        <ul class="list-group list-group-flush">
                            <?php foreach($activities as $act): ?>
                            <li class="list-group-item"><small><strong><?=e($act['activity_date'])?></strong> - <?=e($act['activity_type'])?>: <?=e($act['description'])?></small></li>
                            <?php endforeach; if(empty($activities)) echo "<li class='list-group-item text-muted text-center'>No activities.</li>"; ?>
                        </ul>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="card p-3 p-md-4 shadow-sm bg-white h-100">
                        <h5 class="mb-3 text-danger border-bottom pb-2"><i class="bi bi-shield-check"></i> Vaccines</h5>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead><tr><th>Vaccine</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php foreach($vaccs as $v): ?>
                                    <tr>
                                        <td><?=e($v['vname'])?></td>
                                        <td><span class="badge bg-<?=$v['status'] === 'Completed'?'success':($v['status'] === 'Overdue'?'danger':'warning')?>"><?=e($v['status'])?></span></td>
                                    </tr>
                                    <?php endforeach; if(empty($vaccs)) echo "<tr><td colspan='2' class='text-center text-muted'>No records.</td></tr>"; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div class="modal fade" id="weightModal" tabindex="-1">
      <div class="modal-dialog">
        <form method="post" action="?page=animals&action=profile&id=<?=$id?>">
          <div class="modal-content">
            <div class="modal-header"><h5>Log Weight</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="add_weight_record" value="1">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <div class="mb-2"><label class="form-label small">Weight (kg)</label><input type="number" step="0.01" name="weight" class="form-control" required></div>
                <div class="mb-2"><label class="form-label small">Date</label><input type="date" name="record_date" class="form-control" value="<?=date('Y-m-d')?>" required></div>
                <div class="mb-2"><label class="form-label small">Notes</label><input name="weight_notes" class="form-control"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
          </div>
        </form>
      </div>
    </div>

    <div class="modal fade" id="profileCostModal" tabindex="-1">
      <div class="modal-dialog">
        <form method="post" action="?page=animals&action=profile&id=<?=$id?>">
          <div class="modal-content">
            <div class="modal-header"><h5>Log Expense</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="add_profile_cost" value="1">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <div class="mb-2"><label class="form-label small">Category</label><select name="cost_category" class="form-select"><option>Feed</option><option>Medicine</option><option>Vaccine</option><option>Labor</option><option>Other</option></select></div>
                <div class="mb-2"><label class="form-label small">Amount ($)</label><input type="number" step="0.01" name="cost_amount" class="form-control" required></div>
                <div class="mb-2"><label class="form-label small">Date</label><input type="date" name="cost_date" class="form-control" value="<?=date('Y-m-d')?>" required></div>
                <div class="mb-3"><label class="form-label small">Note</label><input name="cost_note" class="form-control" required></div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
          </div>
        </form>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var ctx = document.getElementById('weightChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= $weight_dates ?: '[]' ?>,
                    datasets: [{
                        label: 'Weight (kg)',
                        data: <?= $weight_values ?: '[]' ?>,
                        borderColor: '#0078d4',
                        backgroundColor: 'rgba(0,120,212,0.1)',
                        fill: true,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }
    });
    </script>
    <?php
}

// ---- ADD / EDIT FORM (ফিক্সড: খালি তারিখ NULL হবে) ----
elseif ($action === 'add' || $action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $animal = [];
    if ($id) {
        $stmt_edit = $pdo->prepare("SELECT * FROM animals WHERE id = ?");
        $stmt_edit->execute([$id]);
        $animal = $stmt_edit->fetch();
        if (!$animal) die("Invalid Target");
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            die("<div class='alert alert-danger'>Invalid security token.</div>");
        }
        $name = $_POST['name'] ?? '';
        $type = $_POST['type'] ?? 'Goat';
        $breed = $_POST['breed'] ?? '';
        $color = $_POST['color'] ?? '';
        $dob = $_POST['dob'] ?? '';
        $weight = (float)($_POST['weight'] ?? 0.00);
        $temp = $_POST['temp_celsius'] !== '' ? (float)$_POST['temp_celsius'] : null;
        $pulse = $_POST['pulse_rate'] !== '' ? (int)$_POST['pulse_rate'] : null;
        $resp = $_POST['resp_rate'] !== '' ? (int)$_POST['resp_rate'] : null;
        $health = $_POST['health_status'] ?? 'Healthy';
        $preg = $_POST['pregnancy_status'] ?? 'Not Pregnant';
        $sale = $_POST['sale_readiness'] ?? 'Not Ready';
        $status = $_POST['status'] ?? 'Active';
        $shed = $_POST['shed_id'] !== '' ? (int)$_POST['shed_id'] : null;
        $mom = $_POST['mother_id'] !== '' ? (int)$_POST['mother_id'] : null;
        $dad = $_POST['father_id'] !== '' ? (int)$_POST['father_id'] : null;
        $pur_type = $_POST['purchase_type'] ?? 'Born';
        $pur_price = $_POST['purchase_price'] !== '' ? (float)$_POST['purchase_price'] : 0.00;
        $sell_price = $_POST['selling_price'] !== '' ? (float)$_POST['selling_price'] : null;
        $notes = $_POST['notes'] ?? '';
        
        // ========== তারিখ ফিল্ডগুলো NULL হিসেবে সেট করা (সমাধান) ==========
        $kidding_date = !empty($_POST['kidding_date']) ? $_POST['kidding_date'] : null;
        $last_heat = !empty($_POST['last_heat_date']) ? $_POST['last_heat_date'] : null;
        $next_heat = !empty($_POST['next_heat_date']) ? $_POST['next_heat_date'] : null;
        // ==============================================================
        
        if ($temp !== null && ($temp < 38.5 || $temp > 40.5)) $health = 'Sick';
        if ($pulse !== null && ($pulse < 60 || $pulse > 100)) $health = 'Sick';
        if ($resp !== null && ($resp < 20 || $resp > 45)) $health = 'Sick';

        $img = $animal['image'] ?? 'default.png';
        if (!empty($_FILES['image']['name'])) {
            $max_size = 2 * 1024 * 1024;
            if ($_FILES['image']['size'] > $max_size) {
                echo "<div class='alert alert-danger'>Image size cannot exceed 2MB.</div>";
            } else {
                $check = getimagesize($_FILES['image']['tmp_name']);
                if ($check === false) echo "<div class='alert alert-danger'>Invalid image.</div>";
                else {
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
                    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, $allowed_exts, true)) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
                        finfo_close($finfo);
                        if (strpos($mime, 'image/') === 0) {
                            $img = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                            if (!is_dir('assets/images/')) mkdir('assets/images/', 0755, true);
                            move_uploaded_file($_FILES['image']['tmp_name'], 'assets/images/' . $img);
                        } else echo "<div class='alert alert-danger'>Invalid MIME type.</div>";
                    } else echo "<div class='alert alert-danger'>Invalid extension.</div>";
                }
            }
        }
        
        if ($id) {
            $stmt_upd = $pdo->prepare("UPDATE animals SET name=?, type=?, breed=?, color=?, dob=?, weight=?, temp_celsius=?, pulse_rate=?, resp_rate=?, health_status=?, pregnancy_status=?, sale_readiness=?, status=?, shed_id=?, mother_id=?, father_id=?, purchase_type=?, purchase_price=?, selling_price=?, image=?, notes=?, kidding_date=?, last_heat_date=?, next_heat_date=? WHERE id=?");
            $stmt_upd->execute([
                $name, $type, $breed, $color, $dob, $weight, $temp, $pulse, $resp, 
                $health, $preg, $sale, $status, $shed, $mom, $dad, $pur_type, $pur_price, 
                $sell_price, $img, $notes, $kidding_date, $last_heat, $next_heat, $id
            ]);
            if ($pur_type === 'Purchased' && $pur_price > 0) {
                $stmt_chk_cost = $pdo->prepare("SELECT id FROM animal_costs WHERE animal_id = ? AND note = 'Purchase Expense'");
                $stmt_chk_cost->execute([$id]);
                $cost_id = $stmt_chk_cost->fetchColumn();
                if ($cost_id) $pdo->prepare("UPDATE animal_costs SET amount = ? WHERE id = ?")->execute([$pur_price, $cost_id]);
                else $pdo->prepare("INSERT INTO animal_costs (animal_id, category, amount, cost_date, note) VALUES (?, 'Other', ?, CURDATE(), 'Purchase Expense')")->execute([$id, $pur_price]);
            } else $pdo->prepare("DELETE FROM animal_costs WHERE animal_id = ? AND note = 'Purchase Expense'")->execute([$id]);
            echo "<div class='alert alert-success'>Profile saved. <a href='?page=animals'>Back</a></div>";
        } else {
            $auto = generateAutoID($pdo, $type);
            $stmt_ins = $pdo->prepare("INSERT INTO animals (auto_id, name, type, breed, color, dob, weight, temp_celsius, pulse_rate, resp_rate, health_status, pregnancy_status, sale_readiness, status, shed_id, mother_id, father_id, purchase_type, purchase_price, image, notes, kidding_date, last_heat_date, next_heat_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt_ins->execute([
                $auto, $name, $type, $breed, $color, $dob, $weight, $temp, $pulse, $resp,
                $health, $preg, $sale, $status, $shed, $mom, $dad, $pur_type, $pur_price,
                $img, $notes, $kidding_date, $last_heat, $next_heat
            ]);
            $newId = $pdo->lastInsertId();
            if ($pur_type === 'Purchased' && $pur_price > 0) {
                $pdo->prepare("INSERT INTO animal_costs (animal_id, category, amount, cost_date, note) VALUES (?, 'Other', ?, CURDATE(), 'Purchase Expense')")->execute([$newId, $pur_price]);
                $pdo->prepare("INSERT INTO transactions (animal_id, type, category, amount, trans_date, description) VALUES (?, 'Expense', 'Purchase', ?, CURDATE(), ?)")->execute([$newId, $pur_price, "Purchase of stock ID " . $auto]);
            }
            echo "<div class='alert alert-success'>Animal {$auto} logged. <a href='?page=animals'>Back</a></div>";
        }
    }
    
    $sheds = $pdo->query("SELECT id, name FROM sheds")->fetchAll();
    $allAnimals = $pdo->query("SELECT id, auto_id, name, type FROM animals WHERE status = 'Active' ORDER BY name")->fetchAll();
    ?>
    <div class="card p-3 p-md-4 shadow-sm bg-white border">
        <h4><?=$id ? 'Edit ' . e($animal['name']) : 'Add Stock Record'?></h4>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <div class="row g-2">
                <div class="col-12 col-md-4 mb-2"><label class="form-label">Name</label><input name="name" class="form-control" value="<?=e($animal['name']??'')?>" required></div>
                <div class="col-6 col-md-4 mb-2"><label class="form-label">Type</label><select name="type" class="form-select"><option <?=($animal['type']??'')==='Goat'?'selected':''?>>Goat</option><option <?=($animal['type']??'')==='Buck'?'selected':''?>>Buck</option><option <?=($animal['type']??'')==='Castrated'?'selected':''?>>Castrated</option></select></div>
                <div class="col-6 col-md-4 mb-2"><label class="form-label">Color</label><input name="color" class="form-control" value="<?=e($animal['color']??'')?>"></div>
                <div class="col-12 col-md-4 mb-2"><label class="form-label">Breed</label><input name="breed" class="form-control" value="<?=e($animal['breed']??'')?>"></div>
                <div class="col-6 col-md-4 mb-2"><label class="form-label">DOB</label><input type="date" name="dob" class="form-control" value="<?=e($animal['dob']??'')?>" required></div>
                <div class="col-6 col-md-4 mb-2"><label class="form-label">Weight (kg)</label><input type="number" step="0.01" name="weight" class="form-control" value="<?=e($animal['weight']??'')?>"></div>
                <div class="col-6 col-md-4 mb-2"><label class="form-label">Temp (°C)</label><input type="number" step="0.01" name="temp_celsius" class="form-control" value="<?=e($animal['temp_celsius']??'')?>"></div>
                <div class="col-6 col-md-4 mb-2"><label class="form-label">Pulse</label><input type="number" name="pulse_rate" class="form-control" value="<?=e($animal['pulse_rate']??'')?>"></div>
                <div class="col-6 col-md-4 mb-2"><label class="form-label">Respiration</label><input type="number" name="resp_rate" class="form-control" value="<?=e($animal['resp_rate']??'')?>"></div>
                <div class="col-6 col-md-4 mb-2"><label class="form-label">Last Heat</label><input type="date" name="last_heat_date" class="form-control" value="<?=e($animal['last_heat_date']??'')?>"></div>
                <div class="col-6 col-md-4 mb-2"><label class="form-label">Next Heat</label><input type="date" name="next_heat_date" class="form-control" value="<?=e($animal['next_heat_date']??'')?>"></div>
                <div class="col-6 col-md-4 mb-2"><label class="form-label">Kidding</label><input type="date" name="kidding_date" class="form-control" value="<?=e($animal['kidding_date']??'')?>"></div>
                <div class="col-6 col-md-3 mb-2"><label class="form-label">Health</label><select name="health_status" class="form-select"><option <?=($animal['health_status']??'')==='Healthy'?'selected':''?>>Healthy</option><option <?=($animal['health_status']??'')==='Sick'?'selected':''?>>Sick</option><option <?=($animal['health_status']??'')==='Critical'?'selected':''?>>Critical</option></select></div>
                <div class="col-6 col-md-3 mb-2"><label class="form-label">Pregnancy</label><select name="pregnancy_status" class="form-select"><option <?=($animal['pregnancy_status']??'')==='Not Pregnant'?'selected':''?>>Not Pregnant</option><option <?=($animal['pregnancy_status']??'')==='Pregnant'?'selected':''?>>Pregnant</option></select></div>
                <div class="col-6 col-md-3 mb-2"><label class="form-label">Sale Ready</label><select name="sale_readiness" class="form-select"><option <?=($animal['sale_readiness']??'')==='Not Ready'?'selected':''?>>Not Ready</option><option <?=($animal['sale_readiness']??'')==='Ready'?'selected':''?>>Ready</option></select></div>
                <div class="col-6 col-md-3 mb-2"><label class="form-label">Status</label><select name="status" class="form-select"><option <?=($animal['status']??'')==='Active'?'selected':''?>>Active</option><option <?=($animal['status']??'')==='Sold'?'selected':''?>>Sold</option><option <?=($animal['status']??'')==='Dead'?'selected':''?>>Dead</option></select></div>
                <div class="col-6 col-md-4 mb-2"><label class="form-label">Shed</label><select name="shed_id" class="form-select"><option value="">Unassigned</option><?php foreach($sheds as $s) echo "<option value='{$s['id']}' ".($s['id']==($animal['shed_id']??'')?'selected':'').">".e($s['name'])."</option>"; ?></select></div>
                <div class="col-6 col-md-4 mb-2"><label class="form-label">Mother</label><select name="mother_id" class="form-select"><option value="">Unknown</option><?php foreach($allAnimals as $aa) if($aa['type']==='Goat') echo "<option value='{$aa['id']}' ".($aa['id']==($animal['mother_id']??'')?'selected':'').">".e($aa['auto_id'])."</option>"; ?></select></div>
                <div class="col-6 col-md-4 mb-2"><label class="form-label">Father</label><select name="father_id" class="form-select"><option value="">Unknown</option><?php foreach($allAnimals as $aa) if($aa['type']==='Buck') echo "<option value='{$aa['id']}' ".($aa['id']==($animal['father_id']??'')?'selected':'').">".e($aa['auto_id'])."</option>"; ?></select></div>
                <div class="col-6 col-md-4 mb-2"><label class="form-label">Purchase Type</label><select name="purchase_type" class="form-select"><option <?=($animal['purchase_type']??'')==='Born'?'selected':''?>>Born</option><option <?=($animal['purchase_type']??'')==='Purchased'?'selected':''?>>Purchased</option></select></div>
                <div class="col-6 col-md-4 mb-2"><label class="form-label">Purchase Price</label><input type="number" step="0.01" name="purchase_price" class="form-control" value="<?=e($animal['purchase_price']??'')?>"></div>
                <div class="col-6 col-md-4 mb-2"><label class="form-label">Selling Price</label><input type="number" step="0.01" name="selling_price" class="form-control" value="<?=e($animal['selling_price']??'')?>"></div>
                <div class="col-12 mb-2"><label class="form-label">Photo</label><input type="file" name="image" class="form-control" accept="image/*"></div>
                <div class="col-12 mb-2"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?=e($animal['notes']??'')?></textarea></div>
            </div>
            <button class="btn btn-primary w-100"><i class="bi bi-save"></i> Save</button>
        </form>
    </div>
    <?php
}
?>