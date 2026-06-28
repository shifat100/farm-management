<?php
require_once 'functions.php';

$filters = $_GET;
$filter = buildFilterWhere($filters, 'a');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("<div class='alert alert-danger'>Invalid security token.</div>");
    }

    if (isset($_POST['add'])) {
        $animal_id = $_POST['animal_id'] !== '' ? (int)$_POST['animal_id'] : null;
        $type = $_POST['activity_type'] ?? '';
        $date = $_POST['activity_date'] ?? date('Y-m-d');
        $desc = $_POST['description'] ?? '';
        $amount = $_POST['amount'] !== '' ? (float)$_POST['amount'] : 0.00;
        $user = (int)$_SESSION['user']['id'];
        $withdrawal_days = isset($_POST['withdrawal_days']) ? (int)$_POST['withdrawal_days'] : 0;
        $withdrawal_end_date = ($withdrawal_days > 0) ? date('Y-m-d', strtotime($date . ' + ' . $withdrawal_days . ' days')) : null;
        
        $allow_mating = true;
        
        if ($type === 'Kidding' && $animal_id) {
            $kid_count = isset($_POST['kid_count']) ? (int)$_POST['kid_count'] : 0;
            $kids_data = [];
            for ($i = 1; $i <= $kid_count; $i++) {
                $sex = $_POST['kid_sex_' . $i] ?? 'Female';
                $weight = (float)($_POST['kid_weight_' . $i] ?? 0);
                if ($weight > 0) $kids_data[] = ['sex' => $sex, 'weight' => $weight];
            }
            if (!empty($kids_data)) {
                registerKidding($pdo, $animal_id, $kids_data, $date);
                echo "<div class='alert alert-success'>Kidding logged. " . count($kids_data) . " kids registered.</div>";
            } else {
                echo "<div class='alert alert-warning'>Kidding logged, no valid kids data.</div>";
            }
            $desc = "Kidding occurred. " . $desc;
        }

        if ($type === 'Breeding' && $animal_id) {
            $stmt_check = $pdo->prepare("SELECT dob, weight, type FROM animals WHERE id = ?");
            $stmt_check->execute([$animal_id]);
            $an = $stmt_check->fetch();
            if ($an && $an['type'] === 'Goat') {
                $dob = new DateTime($an['dob']);
                $now = new DateTime();
                $age_m = ($now->diff($dob)->y * 12) + $now->diff($dob)->m;
                if ($age_m < 7) { echo "<div class='alert alert-danger'>Under-age.</div>"; $allow_mating = false; }
                if ((float)$an['weight'] < 12.00) { echo "<div class='alert alert-danger'>Under-weight.</div>"; $allow_mating = false; }
                if ($allow_mating) {
                    $next_heat = date('Y-m-d', strtotime($date . ' + 21 days'));
                    $pdo->prepare("UPDATE animals SET last_heat_date = ?, next_heat_date = ? WHERE id = ?")->execute([$date, $next_heat, $animal_id]);
                    $pdo->prepare("UPDATE animals SET pregnancy_status = 'Pregnant' WHERE id = ?")->execute([$animal_id]);
                    $est_kidding_start = date('Y-m-d', strtotime($date . ' + 142 days'));
                    $est_kidding_end = date('Y-m-d', strtotime($date . ' + 158 days'));
                    $desc .= " [Pregnancy window: " . $est_kidding_start . " to " . $est_kidding_end . "]";
                }
            }
        }

        if ($allow_mating) {
            $stmt_ins = $pdo->prepare("INSERT INTO activities (animal_id, activity_type, activity_date, description, amount, user_id, withdrawal_end_date) VALUES (?,?,?,?,?,?,?)");
            $stmt_ins->execute([$animal_id, $type, $date, $desc, $amount, $user, $withdrawal_end_date]);
            if ($animal_id) {
                if ($type === 'Vaccination') {
                    $pdo->prepare("UPDATE animals SET vaccination_status='Complete' WHERE id=?")->execute([$animal_id]);
                } elseif ($type === 'Sale') {
                    $check_wd = $pdo->prepare("SELECT id FROM activities WHERE animal_id = ? AND withdrawal_end_date >= CURDATE()");
                    $check_wd->execute([$animal_id]);
                    if ($check_wd->fetch()) {
                        echo "<div class='alert alert-danger'>Sale blocked: In withdrawal period.</div>";
                        return;
                    }
                    $pdo->prepare("UPDATE animals SET status='Sold', selling_price=? WHERE id=?")->execute([$amount, $animal_id]);
                    $pdo->prepare("INSERT INTO transactions (animal_id, type, category, amount, trans_date, description) VALUES (?, 'Income', 'Sale', ?, ?, ?)")->execute([$animal_id, $amount, $date, $desc]);
                } elseif ($type === 'Death') {
                    $pdo->prepare("UPDATE animals SET status='Dead' WHERE id=?")->execute([$animal_id]);
                } elseif ($type === 'Expense') {
                    $pdo->prepare("INSERT INTO animal_costs (animal_id, category, amount, cost_date, note) VALUES (?, 'Other', ?, ?, ?)")->execute([$animal_id, $amount, $date, $desc]);
                    $pdo->prepare("INSERT INTO transactions (animal_id, type, category, amount, trans_date, description) VALUES (?, 'Expense', 'General Expense', ?, ?, ?)")->execute([$animal_id, $amount, $date, $desc]);
                }
            }
            echo "<div class='alert alert-success'>Activity logged.</div>";
        }
    }

    if (isset($_POST['bulk'])) {
        $ids = $_POST['animal_ids'] ?? [];
        $btype = $_POST['bulk_type'] ?? 'Feeding';
        foreach($ids as $aid) {
            $pdo->prepare("INSERT INTO activities (animal_id, activity_type, activity_date, description, amount, user_id) VALUES (?, ?, CURDATE(), ?, 0.00, ?)")
                ->execute([(int)$aid, $btype, "Bulk " . $btype, $_SESSION['user']['id']]);
        }
        echo "<div class='alert alert-success'>Bulk operations completed.</div>";
    }

    if (isset($_POST['log_milk'])) {
        $animal_id = (int)$_POST['animal_id'];
        $liters = (float)$_POST['quantity_liters'];
        $date = $_POST['record_date'] ?? date('Y-m-d');
        $stmt = $pdo->prepare("INSERT INTO milk_production (animal_id, quantity_liters, record_date) VALUES (?,?,?)");
        $stmt->execute([$animal_id, $liters, $date]);
        echo "<div class='alert alert-success'>Milk yield logged.</div>";
    }
}

$sql_act = "SELECT a.*, an.name as animal, an.auto_id as animal_auto FROM activities a LEFT JOIN animals an ON a.animal_id = an.id {$filter['clause']} ORDER BY a.activity_date DESC LIMIT 100";
$stmt_act = $pdo->prepare($sql_act);
$stmt_act->execute($filter['params']);
$activities = $stmt_act->fetchAll();

$animals = $pdo->query("SELECT id, auto_id, name FROM animals WHERE status='Active'")->fetchAll();
?>

<div class="row g-3">
    <!-- ফর্ম সেকশন -->
    <div class="col-12 col-md-7">
        <div class="card p-3 p-md-4 shadow-sm bg-white border">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <h5><i class="bi bi-gear-wide-connected me-2"></i>Log Activity</h5>
                <div>
                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#bulkModal"><i class="bi bi-layers-half"></i> Bulk</button>
                </div>
            </div>
            
            <div id="breedingGuideline" class="alert alert-info py-2 small d-none">
                <strong>DLS Mating:</strong> Mate 12-14 hours post heat onset.
            </div>
            <div id="kiddingFields" class="d-none">
                <div class="alert alert-secondary p-2 mb-2">
                    <label class="form-label small fw-bold">Number of Kids</label>
                    <input type="number" name="kid_count" id="kid_count" class="form-control form-control-sm" min="1" max="4" onchange="generateKidFields()">
                    <div id="kid_inputs" class="mt-2"></div>
                </div>
            </div>
            <div id="withdrawalFields" class="d-none mb-2">
                <label class="form-label small">Withdrawal Days</label>
                <input type="number" name="withdrawal_days" class="form-control form-control-sm" value="0" min="0">
                <small class="text-muted">e.g., 7 for antibiotic withdrawal</small>
            </div>

            <form method="post" id="activityForm">
                <input type="hidden" name="add" value="1">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <div class="row g-2">
                    <div class="col-12 col-md-6">
                        <label class="form-label small">Activity Type</label>
                        <select name="activity_type" id="activity_type_select" class="form-select" onchange="toggleFields()">
                            <option>Feeding</option><option>Vaccination</option><option>Treatment</option>
                            <option>Breeding</option><option>Pregnancy Check</option><option>Deworming</option>
                            <option>Expense</option><option>Sale</option><option>Death</option>
                            <option value="Kidding">Kidding (Birth)</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small">Animal</label>
                        <select name="animal_id" class="form-select">
                            <option value="">Bulk / Unspecified</option>
                            <?php foreach($animals as $an) echo "<option value='{$an['id']}'>".e($an['auto_id'])." ".e($an['name'])."</option>"; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small">Date</label>
                        <input type="date" name="activity_date" class="form-control" value="<?=date('Y-m-d')?>" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small">Financial Outlay</label>
                        <input type="number" step="0.01" name="amount" class="form-control" value="0.00">
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Description</label>
                        <input name="description" class="form-control" placeholder="Notes" required>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary w-100">Save Activity</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- মিল্ক ট্র্যাকার -->
    <div class="col-12 col-md-5">
        <div class="card p-3 p-md-4 shadow-sm bg-white border h-100">
            <h5 class="mb-3 border-bottom pb-2"><i class="bi bi-droplet-half text-primary me-2"></i>Daily Milk Yield</h5>
            <form method="post">
                <input type="hidden" name="log_milk" value="1">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <div class="mb-2">
                    <label class="form-label small">Doe</label>
                    <select name="animal_id" class="form-select" required>
                        <?php 
                        $females = $pdo->query("SELECT id, auto_id, name FROM animals WHERE type='Goat' AND status='Active'")->fetchAll();
                        foreach($females as $f) echo "<option value='{$f['id']}'>".e($f['auto_id'])." ".e($f['name'])."</option>";
                        ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Liters</label>
                    <input type="number" step="0.01" name="quantity_liters" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Date</label>
                    <input type="date" name="record_date" class="form-control" value="<?=date('Y-m-d')?>" required>
                </div>
                <button class="btn btn-success w-100"><i class="bi bi-cloud-arrow-up"></i> Log Yield</button>
            </form>
        </div>
    </div>
</div>

<!-- টেবিল -->
<div class="table-responsive bg-white rounded shadow-sm p-2 p-md-3 border mt-4">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-dark">
            <tr><th>Date</th><th>Type</th><th>Animal</th><th>Description</th><th>Amount</th><th>Withdrawal</th></tr>
        </thead>
        <tbody>
            <?php foreach($activities as $a): ?>
            <tr>
                <td><?=e($a['activity_date'])?></td>
                <td><span class="badge bg-secondary"><?=e($a['activity_type'])?></span></td>
                <td><?=$a['animal_auto'] ? e($a['animal_auto']) : 'Global'?></td>
                <td><?=e($a['description'])?></td>
                <td><?=$a['amount'] > 0 ? '$' . number_format($a['amount'], 2) : '-'?></td>
                <td><?=$a['withdrawal_end_date'] ? e($a['withdrawal_end_date']) : '-'?></td>
            </tr>
            <?php endforeach; if(empty($activities)) echo "<tr><td colspan='6' class='text-center text-muted'>No entries.</td></tr>"; ?>
        </tbody>
    </table>
</div>

<!-- Bulk Modal -->
<div class="modal fade" id="bulkModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post">
      <div class="modal-content">
        <div class="modal-header"><h5>Batch Processing</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" name="bulk" value="1">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <div class="mb-3">
                <label class="form-label small">Bulk Type</label>
                <select name="bulk_type" class="form-select">
                    <option>Feeding</option><option>Vaccination</option>
                </select>
            </div>
            <label class="form-label small">Select Animals</label>
            <div class="border rounded p-3 mb-3 bg-light" style="max-height:180px; overflow-y:auto;">
                <?php foreach($animals as $an): ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="animal_ids[]" value="<?=$an['id']?>" id="chk_<?=$an['id']?>">
                    <label class="form-check-label" for="chk_<?=$an['id']?>"><?=e($an['auto_id'])?> - <?=e($an['name'])?></label>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-warning">Execute</button></div>
      </div>
    </form>
  </div>
</div>

<script>
function toggleFields() {
    var select = document.getElementById('activity_type_select');
    document.getElementById('breedingGuideline').classList.toggle('d-none', select.value !== 'Breeding');
    document.getElementById('kiddingFields').classList.toggle('d-none', select.value !== 'Kidding');
    document.getElementById('withdrawalFields').classList.toggle('d-none', select.value !== 'Treatment' && select.value !== 'Vaccination');
}
function generateKidFields() {
    var count = document.getElementById('kid_count').value;
    var container = document.getElementById('kid_inputs');
    container.innerHTML = '';
    for (var i = 1; i <= count; i++) {
        container.innerHTML += `
            <div class="row g-2 mb-1">
                <div class="col-6">
                    <label class="small">Kid ${i} Sex</label>
                    <select name="kid_sex_${i}" class="form-select form-select-sm">
                        <option value="Male">Male</option>
                        <option value="Female" selected>Female</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="small">Weight (kg)</label>
                    <input type="number" step="0.01" name="kid_weight_${i}" class="form-control form-control-sm" required>
                </div>
            </div>
        `;
    }
}
</script>