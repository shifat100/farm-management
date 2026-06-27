<?php
require_once 'functions.php';

$action = $_GET['action'] ?? 'list';
$filters = $_GET;
$filter = buildFilterWhere($filters);

// ---- INLINE COST SUBMISSION FOR PROFILE VIEW ----
$id = (int)($_GET['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_profile_cost'])) {
    $cost_category = $_POST['cost_category'] ?? 'Other';
    $cost_amount = (float)($_POST['cost_amount'] ?? 0.00);
    $cost_note = trim($_POST['cost_note'] ?? '');
    $cost_date = $_POST['cost_date'] ?? date('Y-m-d');
    
    if ($cost_amount > 0 && $id > 0) {
        // Log to animal costs ledger [1]
        $stmt_ac = $pdo->prepare("INSERT INTO animal_costs (animal_id, category, amount, cost_date, note) VALUES (?, ?, ?, ?, ?)");
        $stmt_ac->execute([$id, $cost_category, $cost_amount, $cost_date, $cost_note]);
        
        // Log to general financial transactions ledger [1]
        $stmt_tx = $pdo->prepare("INSERT INTO transactions (animal_id, type, category, amount, trans_date, description) VALUES (?, 'Expense', ?, ?, ?, ?)");
        $stmt_tx->execute([$id, $cost_category, $cost_amount, $cost_date, "Profile Log: " . $cost_note]);
        
        echo "<div class='alert alert-success'>Cost entry logged successfully.</div>";
    }
}

// ---- LIST VIEW ----
if ($action === 'list') {
    $search = trim($_GET['q'] ?? '');
    $clause = $filter['clause'];
    $params = $filter['params'];

    if ($search !== '') {
        if ($clause === '') {
            $clause = " WHERE (a.name LIKE :search1 OR a.auto_id LIKE :search2 OR a.breed LIKE :search3)";
        } else {
            $clause .= " AND (a.name LIKE :search1 OR a.auto_id LIKE :search2 OR a.breed LIKE :search3)";
        }
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

    $sql = "SELECT a.*, s.name as shed 
            FROM animals a 
            LEFT JOIN sheds s ON a.shed_id = s.id 
            {$clause} 
            ORDER BY a.id DESC 
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $animals = $stmt->fetchAll();

    function getPageUrl($pageNum) {
        $params = $_GET;
        $params['p'] = $pageNum;
        return '?' . http_build_query($params);
    }
    ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Animals Registry (<?=$total_records?> Records Found)</h4>
        <a href="?page=animals&action=add" class="btn btn-success"><i class="bi bi-plus-circle"></i> Add Animal Stock</a>
    </div>

    <!-- Inline Filter & Search Toolbar -->
    <div class="card p-3 mb-3 bg-white shadow-sm border">
        <form method="get" class="row g-2 align-items-center">
            <input type="hidden" name="page" value="animals">
            <input type="hidden" name="action" value="list">
            
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Search ID, Name, Breed..." value="<?=e($search)?>">
                </div>
            </div>
            
            <div class="col-md-2">
                <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="Goat" <?=($filters['type']??'')==='Goat'?'selected':''?>>Goat</option>
                    <option value="Buck" <?=($filters['type']??'')==='Buck'?'selected':''?>>Buck</option>
                    <option value="Castrated" <?=($filters['type']??'')==='Castrated'?'selected':''?>>Castrated</option>
                </select>
            </div>

            <div class="col-md-2">
                <select name="health_status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Health Statuses</option>
                    <option value="Healthy" <?=($filters['health_status']??'')==='Healthy'?'selected':''?>>Healthy</option>
                    <option value="Sick" <?=($filters['health_status']??'')==='Sick'?'selected':''?>>Sick</option>
                    <option value="Critical" <?=($filters['health_status']??'')==='Critical'?'selected':''?>>Critical</option>
                </select>
            </div>

            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="Active" <?=($filters['status']??'')==='Active'?'selected':''?>>Active</option>
                    <option value="Sold" <?=($filters['status']??'')==='Sold'?'selected':''?>>Sold</option>
                    <option value="Dead" <?=($filters['status']??'')==='Dead'?'selected':''?>>Dead</option>
                </select>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-filter"></i> Apply</button>
                <a href="?page=animals" class="btn btn-sm btn-secondary"><i class="bi bi-x-circle"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Data Presentation Layout -->
    <div class="table-responsive bg-white rounded shadow-sm p-3 border mb-3">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Auto ID</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Breed</th>
                    <th>Age</th>
                    <th>Weight</th>
                    <th>Health</th>
                    <th>Min Sell (10%)</th>
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
                <td><?=getAge($a['dob'])?></td>
                <td><?=e($a['weight'])?> kg</td>
                <td>
                    <span class="badge bg-<?=$a['health_status'] === 'Healthy' ? 'success' : ($a['health_status'] === 'Sick' ? 'warning' : 'danger')?>">
                        <?=e($a['health_status'])?>
                    </span>
                </td>
                <td>$<?=number_format($minSell, 2)?></td>
                <td>
                    <a href="?page=animals&action=profile&id=<?=(int)$a['id']?>" class="btn btn-sm btn-info py-1 px-2"><i class="bi bi-eye"></i></a>
                    <a href="?page=animals&action=edit&id=<?=(int)$a['id']?>" class="btn btn-sm btn-warning py-1 px-2"><i class="bi bi-pencil"></i></a>
                </td>
            </tr>
            <?php endforeach; if(empty($animals)) echo "<tr><td colspan='9' class='text-center text-muted py-3'>No animal records matching these filter inputs found.</td></tr>"; ?>
            </tbody>
        </table>
    </div>

    <!-- Dynamic Pagination Links Footer -->
    <?php if ($total_pages > 1): ?>
    <nav>
        <ul class="pagination pagination-sm justify-content-center">
            <li class="page-item <?=$current_page <= 1 ? 'disabled' : ''?>">
                <a class="page-link" href="<?=getPageUrl(1)?>"><i class="bi bi-chevron-double-left"></i> First</a>
            </li>
            <li class="page-item <?=$current_page <= 1 ? 'disabled' : ''?>">
                <a class="page-link" href="<?=getPageUrl($current_page - 1)?>"><i class="bi bi-chevron-left"></i> Previous</a>
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
                <a class="page-link" href="<?=getPageUrl($current_page + 1)?>">Next <i class="bi bi-chevron-right"></i></a>
            </li>
            <li class="page-item <?=$current_page >= $total_pages ? 'disabled' : ''?>">
                <a class="page-link" href="<?=getPageUrl($total_pages)?>">Last <i class="bi bi-chevron-double-right"></i></a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
    <?php
}

// ---- ANIMAL PROFILE VIEW ----
elseif ($action === 'profile') {
    $stmt = $pdo->prepare("SELECT a.*, s.name as shed, m.name as mother, f.name as father 
                           FROM animals a 
                           LEFT JOIN sheds s ON a.shed_id = s.id 
                           LEFT JOIN animals m ON a.mother_id = m.id 
                           LEFT JOIN animals f ON a.father_id = f.id 
                           WHERE a.id = ?");
    $stmt->execute([$id]);
    $a = $stmt->fetch();
    
    if (!$a) {
        die("<div class='alert alert-danger'>Animal record not identified.</div>");
    }
    
    $cost = calcTotalCost($pdo, $id);
    $minSell = $cost * 1.10;
    
    // FIX: Strict variable check to calculate margin for pre-sale/unsold records
    $profit = ($a['selling_price'] !== null && $a['selling_price'] !== '') ? (float)$a['selling_price'] - $cost : null;
    $roi = ($profit !== null && $cost > 0) ? ($profit / $cost) * 100 : 0;
    
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
    $qrDataUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?page=public_profile&id=' . $a['id'];
    
    $isBlackBengal = (stripos($a['breed'] ?? '', 'black bengal') !== false);
    $estMeat = $isBlackBengal ? ($a['weight'] * 0.60) : ($a['weight'] * 0.50);
    $estSkin = $isBlackBengal ? ($a['weight'] * (1.3 / 20.0)) : 0.00;
    ?>
    <div class="row">
        <div class="col-md-4 text-center mb-4">
            <div class="card p-3 shadow-sm bg-white">
                <img src="assets/images/<?=e($a['image'])?>" class="img-fluid rounded mb-3 shadow" style="max-height:220px; object-fit: cover;" onerror="this.src='assets/images/default.png'">
                <h3><?=e($a['name'])?></h3>
                <h5 class="text-muted"><?=e($a['auto_id'])?></h5>
                <hr>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?=urlencode($qrDataUrl)?>" class="img-thumbnail" alt="Animal QR ID">
                <p class="small text-muted mt-2">Scan code for dynamic public verification page.</p>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card p-4 shadow-sm bg-white mb-3">
                <h5 class="mb-3 text-primary border-bottom pb-2"><i class="bi bi-info-circle-fill"></i> Core Metrics & Physiological Vitals</h5>
                <table class="table table-bordered align-middle">
                    <tr><th>Type</th><td><?=e($a['type'])?></td><th>Breed</th><td><?=e($a['breed'])?></td></tr>
                    <tr><th>DOB</th><td><?=e($a['dob'])?> (<?=getAge($a['dob'])?>)</td><th>Weight</th><td><?=e($a['weight'])?> kg</td></tr>
                    <tr><th>Color</th><td><?=e($a['color'] ?? 'Not Logged')?></td><th>Vaccination Status</th><td><?=e($a['vaccination_status'])?></td></tr>
                    <tr><th>Pregnancy</th><td><?=e($a['pregnancy_status'])?></td><th>Sale Readiness</th><td><?=e($a['sale_readiness'])?></td></tr>
                    <tr><th>System Status</th><td><?=e($a['status'])?></td><th>Shed Allocation</th><td><?=e($a['shed'] ?? 'Unallocated')?></td></tr>
                    <tr><th>Maternal Line</th><td><?=e($a['mother'] ?? 'Unrecorded')?></td><th>Paternal Line</th><td><?=e($a['father'] ?? 'Unrecorded')?></td></tr>
                </table>
                
                <h6 class="mt-3 text-muted">Vitals Assessment (Standard: Temp: 39.5°C, Resp: 25-40, Pulse: 70-90)</h6>
                <table class="table table-bordered table-sm align-middle mb-0">
                    <tr>
                        <th>Last Temperature</th>
                        <td><?=$a['temp_celsius'] ? e($a['temp_celsius'])."°C" : "Not Logged"?></td>
                        <th>Last Pulse Rate</th>
                        <td><?=$a['pulse_rate'] ? e($a['pulse_rate'])." bpm" : "Not Logged"?></td>
                        <th>Last Respiration</th>
                        <td><?=$a['resp_rate'] ? e($a['resp_rate'])." breaths/min" : "Not Logged"?></td>
                    </tr>
                </table>
            </div>

            <!-- Estimated Dressing & Commercial Yield Estimates -->
            <div class="card p-4 shadow-sm bg-white mb-3">
                <h5 class="mb-3 text-secondary border-bottom pb-2"><i class="bi bi-clipboard-data"></i> DLS Production Yield Estimations</h5>
                <div class="row text-center">
                    <div class="col">
                        <div class="border rounded p-2 bg-light">
                            <h6>Est. Dressing Meat (<?=$isBlackBengal ? '60%' : '50%'?>)</h6>
                            <strong class="text-success fs-5"><?=number_format($estMeat, 2)?> kg</strong>
                        </div>
                    </div>
                    <?php if($isBlackBengal): ?>
                    <div class="col">
                        <div class="border rounded p-2 bg-light">
                            <h6>Est. Leather Skin Yield</h6>
                            <strong class="text-success fs-5"><?=number_format($estSkin, 2)?> kg</strong>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <small class="text-muted d-block mt-2">Estimations compiled dynamically using DLS agricultural output standard matrices [1].</small>
            </div>

            <!-- Interactive Financial Cost and Profit Engine -->
            <div class="card p-4 shadow-sm bg-white mb-3">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <h5 class="mb-0 text-success"><i class="bi bi-wallet2"></i> Cost and Profit Engine</h5>
                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#profileCostModal"><i class="bi bi-plus-circle me-1"></i> Log Expense</button>
                </div>
                <div class="row text-center mb-3">
                    <div class="col"><div class="border rounded p-2 bg-light"><h6>Lifetime Costs</h6><strong class="fs-5">$<?=number_format($cost, 2)?></strong></div></div>
                    <div class="col"><div class="border rounded p-2 bg-light"><h6>Min Target Sell (10% Rule)</h6><strong class="text-primary fs-5">$<?=number_format($minSell, 2)?></strong></div></div>
                    <?php if ($profit !== null): ?>
                    <div class="col">
                        <div class="border rounded p-2 bg-light">
                            <h6>Realized Margin</h6>
                            <strong class="<?=$profit >= 0 ? 'text-success' : 'text-danger'?> fs-5">
                                $<?=number_format($profit, 2)?> (<?=number_format($roi, 1)?>% ROI)
                            </strong>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <h6 class="mt-4 text-secondary">Ledger Cost Entries</h6>
                <table class="table table-sm table-striped">
                    <thead><tr><th>Date</th><th>Category</th><th>Amount</th><th>Note</th></tr></thead>
                    <tbody>
                        <?php foreach($costs as $c): ?>
                        <tr><td><?=e($c['cost_date'])?></td><td><?=e($c['category'])?></td><td>$<?=number_format($c['amount'], 2)?></td><td><?=e($c['note'])?></td></tr>
                        <?php endforeach; if(empty($costs)) echo "<tr><td colspan='4' class='text-center text-muted'>No entries registered.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>

            <div class="card p-4 shadow-sm bg-white mb-3">
                <h5 class="mb-3 text-info border-bottom pb-2"><i class="bi bi-activity"></i> Logged Activities</h5>
                <ul class="list-group list-group-flush">
                    <?php foreach($activities as $act): ?>
                    <li class="list-group-item"><strong><?=e($act['activity_date'])?></strong> - <?=e($act['activity_type'])?>: <?=e($act['description'])?></li>
                    <?php endforeach; if(empty($activities)) echo "<li class='list-group-item text-muted text-center'>No operations run.</li>"; ?>
                </ul>
            </div>

            <div class="card p-4 shadow-sm bg-white">
                <h5 class="mb-3 text-danger border-bottom pb-2"><i class="bi bi-shield-check"></i> Vaccine Immunization Records</h5>
                <table class="table table-sm">
                    <thead><tr><th>Vaccine Target</th><th>Scheduled</th><th>Administered</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach($vaccs as $v): ?>
                        <tr>
                            <td><?=e($v['vname'])?></td>
                            <td><?=e($v['due_date'])?></td>
                            <td><?=e($v['given_date'] ?? '-')?></td>
                            <td><span class="badge bg-<?=$v['status'] === 'Completed'?'success':($v['status'] === 'Overdue'?'danger':'warning')?>"><?=e($v['status'])?></span></td>
                        </tr>
                        <?php endforeach; if(empty($vaccs)) echo "<tr><td colspan='4' class='text-center text-muted'>No dynamic records configured.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Inline Cost Log Modal -->
    <div class="modal fade" id="profileCostModal" tabindex="-1">
      <div class="modal-dialog">
        <form method="post" action="?page=animals&action=profile&id=<?=$id?>">
          <div class="modal-content">
            <div class="modal-header"><h5>Log Direct Expense</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="add_profile_cost" value="1">
                <div class="mb-2">
                    <label class="form-label small">Cost Category</label>
                    <select name="cost_category" class="form-select form-select-sm" required>
                        <option>Feed</option>
                        <option>Medicine</option>
                        <option>Vaccine</option>
                        <option>Labor</option>
                        <option>Other</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Cost Outlay Amount ($)</label>
                    <input type="number" step="0.01" name="cost_amount" class="form-control form-control-sm" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Expense Date</label>
                    <input type="date" name="cost_date" class="form-control form-control-sm" value="<?=date('Y-m-d')?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Note Details</label>
                    <input name="cost_note" class="form-control form-control-sm" required placeholder="e.g. Purchased alfalfa feed bags">
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary btn-sm">Save Expense</button></div>
          </div>
        </form>
      </div>
    </div>
    <?php
}

// ---- ADD / EDIT FORM ----
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
        
        if ($temp !== null && ($temp < 38.5 || $temp > 40.5)) {
            $health = 'Sick';
        }
        if ($pulse !== null && ($pulse < 60 || $pulse > 100)) {
            $health = 'Sick';
        }
        if ($resp !== null && ($resp < 20 || $resp > 45)) {
            $health = 'Sick';
        }

        $img = $animal['image'] ?? 'default.png';
        if (!empty($_FILES['image']['name'])) {
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed_exts, true)) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
                finfo_close($finfo);
                
                if (strpos($mime, 'image/') === 0) {
                    $img = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (!is_dir('assets/images/')) {
                        mkdir('assets/images/', 0755, true);
                    }
                    move_uploaded_file($_FILES['image']['tmp_name'], 'assets/images/' . $img);
                } else {
                    echo "<div class='alert alert-danger'>Mime Verification failed. Only valid images allowed.</div>";
                }
            } else {
                echo "<div class='alert alert-danger'>File Upload Aborted. Invalid extension wrapper.</div>";
            }
        }
        
        if ($id) {
            $stmt_upd = $pdo->prepare("UPDATE animals SET name=?, type=?, breed=?, color=?, dob=?, weight=?, temp_celsius=?, pulse_rate=?, resp_rate=?, health_status=?, pregnancy_status=?, sale_readiness=?, status=?, shed_id=?, mother_id=?, father_id=?, purchase_type=?, purchase_price=?, selling_price=?, image=?, notes=? WHERE id=?");
            $stmt_upd->execute([$name, $type, $breed, $color, $dob, $weight, $temp, $pulse, $resp, $health, $preg, $sale, $status, $shed, $mom, $dad, $pur_type, $pur_price, $sell_price, $img, $notes, $id]);
            
            // FIX: Dynamic Synchronization of Purchase Expense inside animal_costs upon update [1]
            if ($pur_type === 'Purchased' && $pur_price > 0) {
                $stmt_chk_cost = $pdo->prepare("SELECT id FROM animal_costs WHERE animal_id = ? AND note = 'Purchase Expense'");
                $stmt_chk_cost->execute([$id]);
                $cost_id = $stmt_chk_cost->fetchColumn();
                
                if ($cost_id) {
                    $pdo->prepare("UPDATE animal_costs SET amount = ? WHERE id = ?")->execute([$pur_price, $cost_id]);
                } else {
                    $pdo->prepare("INSERT INTO animal_costs (animal_id, category, amount, cost_date, note) VALUES (?, 'Other', ?, CURDATE(), 'Purchase Expense')")
                        ->execute([$id, $pur_price]);
                }
            } else {
                $pdo->prepare("DELETE FROM animal_costs WHERE animal_id = ? AND note = 'Purchase Expense'")->execute([$id]);
            }
            
            echo "<div class='alert alert-success'>Profile saved successfully. <a href='?page=animals'>Back to list</a></div>";
        } else {
            $auto = generateAutoID($pdo, $type);
            $stmt_ins = $pdo->prepare("INSERT INTO animals (auto_id, name, type, breed, color, dob, weight, temp_celsius, pulse_rate, resp_rate, health_status, pregnancy_status, sale_readiness, status, shed_id, mother_id, father_id, purchase_type, purchase_price, image, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt_ins->execute([$auto, $name, $type, $breed, $color, $dob, $weight, $temp, $pulse, $resp, $health, $preg, $sale, $status, $shed, $mom, $dad, $pur_type, $pur_price, $img, $notes]);
            $newId = $pdo->lastInsertId();
            
            if ($pur_type === 'Purchased' && $pur_price > 0) {
                $pdo->prepare("INSERT INTO animal_costs (animal_id, category, amount, cost_date, note) VALUES (?, 'Other', ?, CURDATE(), 'Purchase Expense')")->execute([$newId, $pur_price]);
                $pdo->prepare("INSERT INTO transactions (animal_id, type, category, amount, trans_date, description) VALUES (?, 'Expense', 'Purchase', ?, CURDATE(), ?)")->execute([$newId, $pur_price, "Purchase of stock ID " . $auto]);
            }
            echo "<div class='alert alert-success'>Animal {$auto} logged. <a href='?page=animals'>Back to list</a></div>";
        }
    }
    
    $sheds = $pdo->query("SELECT id, name FROM sheds")->fetchAll();
    $allAnimals = $pdo->query("SELECT id, auto_id, name, type FROM animals WHERE status = 'Active' ORDER BY name")->fetchAll();
    ?>
    <div class="card p-4 shadow-sm bg-white border">
        <h4><?=$id ? 'Edit ' . e($animal['name']) : 'Add Stock Record'?></h4>
        <form method="post" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-4 mb-3"><label class="form-label">Name / Identification</label><input name="name" class="form-control" value="<?=e($animal['name']??'')?>" required></div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option <?=($animal['type']??'')==='Goat'?'selected':''?>>Goat</option>
                        <option <?=($animal['type']??'')==='Buck'?'selected':''?>>Buck</option>
                        <option <?=($animal['type']??'')==='Castrated'?'selected':''?>>Castrated</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3"><label class="form-label">Color</label><input name="color" class="form-control" value="<?=e($animal['color']??'')?>" placeholder="e.g. Black, Brown, Mixed"></div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3"><label class="form-label">Breed</label><input name="breed" class="form-control" value="<?=e($animal['breed']??'')?>" placeholder="e.g. Black Bengal, Jamunapari"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Date of Birth</label><input type="date" name="dob" class="form-control" value="<?=e($animal['dob']??'')?>" required></div>
                <div class="col-md-4 mb-3"><label class="form-label">Weight (kg)</label><input type="number" step="0.01" name="weight" class="form-control" value="<?=e($animal['weight']??'')?>"></div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3"><label class="form-label">Body Temp (°C) [Std: 39.5]</label><input type="number" step="0.01" name="temp_celsius" class="form-control" value="<?=e($animal['temp_celsius']??'')?>"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Pulse Rate (bpm) [Std: 70-90]</label><input type="number" name="pulse_rate" class="form-control" value="<?=e($animal['pulse_rate']??'')?>"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Respiration Rate [Std: 25-40]</label><input type="number" name="resp_rate" class="form-control" value="<?=e($animal['resp_rate']??'')?>"></div>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Health Status (Auto-managed if vitals entered)</label>
                    <select name="health_status" class="form-select">
                        <option <?=($animal['health_status']??'')==='Healthy'?'selected':''?>>Healthy</option>
                        <option <?=($animal['health_status']??'')==='Sick'?'selected':''?>>Sick</option>
                        <option <?=($animal['health_status']??'')==='Critical'?'selected':''?>>Critical</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Pregnancy Status</label>
                    <select name="pregnancy_status" class="form-select">
                        <option <?=($animal['pregnancy_status']??'')==='Not Pregnant'?'selected':''?>>Not Pregnant</option>
                        <option <?=($animal['pregnancy_status']??'')==='Pregnant'?'selected':''?>>Pregnant</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Sale Ready</label>
                    <select name="sale_readiness" class="form-select">
                        <option <?=($animal['sale_readiness']??'')==='Not Ready'?'selected':''?>>Not Ready</option>
                        <option <?=($animal['sale_readiness']??'')==='Ready'?'selected':''?>>Ready</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option <?=($animal['status']??'')==='Active'?'selected':''?>>Active</option>
                        <option <?=($animal['status']??'')==='Sold'?'selected':''?>>Sold</option>
                        <option <?=($animal['status']??'')==='Dead'?'selected':''?>>Dead</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Shed Location</label>
                    <select name="shed_id" class="form-select">
                        <option value="">Unassigned</option>
                        <?php foreach($sheds as $s): ?>
                        <option value="<?=$s['id']?>" <?=($animal['shed_id']??'')==$s['id']?'selected':''?>><?=e($s['name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Dam (Mother)</label>
                    <select name="mother_id" class="form-select">
                        <option value="">Unknown</option>
                        <?php foreach($allAnimals as $aa): if($aa['type']==='Goat'): ?>
                        <option value="<?=$aa['id']?>" <?=($animal['mother_id']??'')==$aa['id']?'selected':''?>><?=e($aa['auto_id'])?> - <?=e($aa['name'])?></option>
                        <?php endif; endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Sire (Father)</label>
                    <select name="father_id" class="form-select">
                        <option value="">Unknown</option>
                        <?php foreach($allAnimals as $aa): if($aa['type']==='Buck'): ?>
                        <option value="<?=$aa['id']?>" <?=($animal['father_id']??'')==$aa['id']?'selected':''?>><?=e($aa['auto_id'])?> - <?=e($aa['name'])?></option>
                        <?php endif; endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Purchase Type</label>
                    <select name="purchase_type" class="form-select">
                        <option <?=($animal['purchase_type']??'')==='Born'?'selected':''?>>Born</option>
                        <option <?=($animal['purchase_type']??'')==='Purchased'?'selected':''?>>Purchased</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3"><label class="form-label">Purchase Cost</label><input type="number" step="0.01" name="purchase_price" class="form-control" value="<?=e($animal['purchase_price']??'')?>"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Selling Cost (Dynamic Expected Price)</label><input type="number" step="0.01" name="selling_price" class="form-control" value="<?=e($animal['selling_price']??'')?>"></div>
            </div>
            <div class="mb-3"><label class="form-label">Photo Identity</label><input type="file" name="image" class="form-control" accept="image/*"></div>
            <div class="mb-3"><label class="form-label">Notes & Medical Log</label><textarea name="notes" class="form-control" rows="3"><?=e($animal['notes']??'')?></textarea></div>
            <button class="btn btn-primary"><i class="bi bi-save"></i> Save Profile Details</button>
        </form>
    </div>
    <?php
}
?>