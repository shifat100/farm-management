<?php
require_once 'functions.php';

// Construct dynamic multi-filter context [1]
$filters = $_GET;
$filter = buildFilterWhere($filters, 'a');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $animal_id = $_POST['animal_id'] !== '' ? (int)$_POST['animal_id'] : null;
    $type = $_POST['activity_type'] ?? '';
    $date = $_POST['activity_date'] ?? date('Y-m-d');
    $desc = $_POST['description'] ?? '';
    $amount = $_POST['amount'] !== '' ? (float)$_POST['amount'] : 0.00;
    $user = (int)$_SESSION['user']['id'];
    
    $allow_mating = true;
    
    // Check DLS standard requirements before logging mating activity
    if ($type === 'Breeding' && $animal_id) {
        $stmt_check = $pdo->prepare("SELECT dob, weight, type FROM animals WHERE id = ?");
        $stmt_check->execute([$animal_id]);
        $an = $stmt_check->fetch();
        
        if ($an && $an['type'] === 'Goat') {
            $dob = new DateTime($an['dob']);
            $now = new DateTime();
            $age_m = ($now->diff($dob)->y * 12) + $now->diff($dob)->m;
            
            if ($age_m < 7) {
                echo "<div class='alert alert-danger'>Mating Error: Under-age. DLS guidelines specify females must be at least 7-8 months of age to breed.</div>";
                $allow_mating = false;
            }
            if ((float)$an['weight'] < 12.00) {
                echo "<div class='alert alert-danger'>Mating Error: Under-weight. DLS guidelines specify breeding females must weight at least 12-13 kg.</div>";
                $allow_mating = false;
            }
            
            if ($allow_mating) {
                $est_kidding_start = date('Y-m-d', strtotime($date . ' + 142 days'));
                $est_kidding_end = date('Y-m-d', strtotime($date . ' + 158 days'));
                $desc .= " [Breeding logged. Estimated pregnancy window: " . $est_kidding_start . " to " . $est_kidding_end . "]";
            }
        }
    }

    if ($allow_mating) {
        $stmt_ins = $pdo->prepare("INSERT INTO activities (animal_id, activity_type, activity_date, description, amount, user_id) VALUES (?,?,?,?,?,?)");
        $stmt_ins->execute([$animal_id, $type, $date, $desc, $amount, $user]);
        
        if ($animal_id) {
            if ($type === 'Vaccination') {
                $pdo->prepare("UPDATE animals SET vaccination_status='Complete' WHERE id=?")->execute([$animal_id]);
            } elseif ($type === 'Sale') {
                $pdo->prepare("UPDATE animals SET status='Sold', selling_price=? WHERE id=?")->execute([$amount, $animal_id]);
                $pdo->prepare("INSERT INTO transactions (animal_id, type, category, amount, trans_date, description) VALUES (?, 'Income', 'Sale', ?, ?, ?)")->execute([$animal_id, $amount, $date, $desc]);
            } elseif ($type === 'Death') {
                $pdo->prepare("UPDATE animals SET status='Dead' WHERE id=?")->execute([$animal_id]);
            } elseif ($type === 'Expense') {
                $pdo->prepare("INSERT INTO animal_costs (animal_id, category, amount, cost_date, note) VALUES (?, 'Other', ?, ?, ?)")->execute([$animal_id, $amount, $date, $desc]);
                $pdo->prepare("INSERT INTO transactions (animal_id, type, category, amount, trans_date, description) VALUES (?, 'Expense', 'General Expense', ?, ?, ?)")->execute([$animal_id, $amount, $date, $desc]);
            }
        }
        echo "<div class='alert alert-success'>Activity logged successfully.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk'])) {
    $ids = $_POST['animal_ids'] ?? [];
    $btype = $_POST['bulk_type'] ?? 'Feeding';
    foreach($ids as $aid) {
        $pdo->prepare("INSERT INTO activities (animal_id, activity_type, activity_date, description, amount, user_id) VALUES (?, ?, CURDATE(), ?, 0.00, ?)")
            ->execute([(int)$aid, $btype, "Bulk Group " . $btype . " execution", $_SESSION['user']['id']]);
    }
    echo "<div class='alert alert-success'>Bulk Operations execution completed.</div>";
}

// Milk Yield Records
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_milk'])) {
    $animal_id = (int)$_POST['animal_id'];
    $liters = (float)$_POST['quantity_liters'];
    $date = $_POST['record_date'] ?? date('Y-m-d');
    
    $stmt = $pdo->prepare("INSERT INTO milk_production (animal_id, quantity_liters, record_date) VALUES (?,?,?)");
    $stmt->execute([$animal_id, $liters, $date]);
    echo "<div class='alert alert-success'>Milk yield logged successfully.</div>";
}

$sql_act = "SELECT a.*, an.name as animal, an.auto_id as animal_auto 
            FROM activities a 
            LEFT JOIN animals an ON a.animal_id = an.id 
            {$filter['clause']} 
            ORDER BY a.activity_date DESC LIMIT 100";
$stmt_act = $pdo->prepare($sql_act);
$stmt_act->execute($filter['params']);
$activities = $stmt_act->fetchAll();

$animals = $pdo->query("SELECT id, auto_id, name FROM animals WHERE status='Active'")->fetchAll();
?>

<div class="row g-3">
    <!-- Main Activity Panel -->
    <div class="col-md-7">
        <div class="card p-4 shadow-sm bg-white border">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5><i class="bi bi-gear-wide-connected me-2"></i>Log Operational Activity</h5>
                <div>
                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#bulkModal"><i class="bi bi-layers-half"></i> Bulk</button>
                </div>
            </div>
            
            <!-- Optimal breeding guideline banner dynamically visible on breeding selection -->
            <div id="breedingGuideline" class="alert alert-info py-2 small d-none">
                <strong>DLS Mating Guideline:</strong> Mating should occur 12-14 hours post heat onset. (Morning heat symptoms -> mate in afternoon; Afternoon heat -> mate next morning).
            </div>

            <form method="post">
                <input type="hidden" name="add" value="1">
                <div class="mb-2">
                    <label class="form-label small">Activity Type</label>
                    <select name="activity_type" id="activity_type_select" class="form-select" onchange="toggleBreedingInfo()">
                        <option>Feeding</option>
                        <option>Vaccination</option>
                        <option>Treatment</option>
                        <option>Breeding</option>
                        <option>Pregnancy Check</option>
                        <option>Deworming</option>
                        <option>Expense</option>
                        <option>Sale</option>
                        <option>Death</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Animal ID</label>
                    <select name="animal_id" class="form-select">
                        <option value="">Bulk / Unspecified Entity</option>
                        <?php foreach($animals as $an) echo "<option value='{$an['id']}'>".e($an['auto_id'])." ".e($an['name'])."</option>"; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Activity Date</label>
                    <input type="date" name="activity_date" class="form-control" value="<?=date('Y-m-d')?>" required>
                </div>
                <div class="mb-2"><label class="form-label small">Description</label><input name="description" class="form-control" placeholder="Event notes" required></div>
                <div class="mb-3"><label class="form-label small">Financial Outlay</label><input type="number" step="0.01" name="amount" class="form-control" value="0.00"></div>
                <button class="btn btn-primary w-100">Save Activity</button>
            </form>
        </div>
    </div>

    <!-- Daily Milk Tracker -->
    <div class="col-md-5">
        <div class="card p-4 shadow-sm bg-white border h-100">
            <h5 class="mb-3 border-bottom pb-2"><i class="bi bi-droplet-half text-primary me-2"></i>Daily Milk Yield Logger</h5>
            <form method="post">
                <input type="hidden" name="log_milk" value="1">
                <div class="mb-2">
                    <label class="form-label small">Doe (Maternal Stock Only)</label>
                    <select name="animal_id" class="form-select" required>
                        <?php 
                        $females = $pdo->query("SELECT id, auto_id, name FROM animals WHERE type='Goat' AND status='Active'")->fetchAll();
                        foreach($females as $f) {
                            echo "<option value='{$f['id']}'>".e($f['auto_id'])." ".e($f['name'])."</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Yield Quantity (Liters)</label>
                    <input type="number" step="0.01" name="quantity_liters" class="form-control" required placeholder="0.00">
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

<div class="table-responsive bg-white rounded shadow-sm p-3 border mt-4">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-dark">
            <tr><th>Date</th><th>Type</th><th>Animal ID</th><th>Description</th><th>Financial Outlay</th></tr>
        </thead>
        <tbody>
            <?php foreach($activities as $a): ?>
            <tr>
                <td><?=e($a['activity_date'])?></td>
                <td><span class="badge bg-secondary"><?=e($a['activity_type'])?></span></td>
                <td><?=$a['animal_auto'] ? e($a['animal_auto'] . " (" . $a['animal'] . ")") : 'Global/Bulk'?></td>
                <td><?=e($a['description'])?></td>
                <td><?=$a['amount'] > 0 ? '$' . number_format($a['amount'], 2) : '-'?></td>
            </tr>
            <?php endforeach; if(empty($activities)) echo "<tr><td colspan='5' class='text-center text-muted'>No entries registered.</td></tr>"; ?>
        </tbody>
    </table>
</div>

<!-- Bulk Modal -->
<div class="modal fade" id="bulkModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post">
      <div class="modal-content">
        <div class="modal-header"><h5>Batch Processing Engine</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" name="bulk" value="1">
            <div class="mb-3">
                <label class="form-label small">Bulk Activity Type</label>
                <select name="bulk_type" class="form-select">
                    <option>Feeding</option>
                    <option>Vaccination</option>
                </select>
            </div>
            <label class="form-label small">Select Multiple Animals</label>
            <div class="border rounded p-3 mb-3 bg-light" style="max-height:180px; overflow-y:auto;">
                <?php foreach($animals as $an): ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="animal_ids[]" value="<?=$an['id']?>" id="chk_<?=$an['id']?>">
                    <label class="form-check-label" for="chk_<?=$an['id']?>"><?=e($an['auto_id'])?> - <?=e($an['name'])?></label>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-warning">Execute Batch Command</button></div>
      </div>
    </form>
  </div>
</div>

<script>
function toggleBreedingInfo() {
    var select = document.getElementById('activity_type_select');
    var guideline = document.getElementById('breedingGuideline');
    if (select.value === 'Breeding') {
        guideline.classList.remove('d-none');
    } else {
        guideline.classList.add('d-none');
    }
}
</script>