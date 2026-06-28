<?php
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("<div class='alert alert-danger'>Invalid security token.</div>");
    }

    if (isset($_POST['add_vaccine_rule'])) {
        $vname = trim($_POST['vname'] ?? '');
        $vage = (int)($_POST['vage'] ?? 0);
        $vrepeat = (int)($_POST['vrepeat'] ?? 0);
        if ($vname !== '') {
            $stmt = $pdo->prepare("INSERT INTO vaccines (name, recommended_age_months, repeat_months) VALUES (?, ?, ?)");
            $stmt->execute([$vname, $vage, $vrepeat]);
            echo "<div class='alert alert-success'>Vaccine rule created.</div>";
            runAutomation($pdo);
        }
    }

    if (isset($_POST['schedule'])) {
        $animal_id = (int)$_POST['animal_id'];
        $vaccine_id = (int)$_POST['vaccine_id'];
        $due_date = $_POST['due_date'] ?? date('Y-m-d');
        $stmt_ins = $pdo->prepare("INSERT INTO vaccination_records (animal_id, vaccine_id, due_date) VALUES (?, ?, ?)");
        $stmt_ins->execute([$animal_id, $vaccine_id, $due_date]);
        echo "<div class='alert alert-success'>Schedule registered.</div>";
    }

    if (isset($_POST['complete'])) {
        $record_id = (int)$_POST['record_id'];
        $pdo->prepare("UPDATE vaccination_records SET given_date = CURDATE(), status = 'Completed' WHERE id = ?")->execute([$record_id]);
        $stmt_fetch = $pdo->prepare("SELECT animal_id, vaccine_id FROM vaccination_records WHERE id = ?");
        $stmt_fetch->execute([$record_id]);
        $rec = $stmt_fetch->fetch();
        if ($rec) {
            $pdo->prepare("UPDATE animals SET vaccination_status = 'Complete' WHERE id = ?")->execute([$rec['animal_id']]);
            $stmt_cost = $pdo->prepare("SELECT name FROM vaccines WHERE id = ?");
            $stmt_cost->execute([$rec['vaccine_id']]);
            $vName = $stmt_cost->fetchColumn() ?? 'Vaccine';
            $pdo->prepare("INSERT INTO animal_costs (animal_id, category, amount, cost_date, note) VALUES (?, 'Vaccine', 10.00, CURDATE(), ?)")
                ->execute([$rec['animal_id'], "Administered " . $vName]);
            $pdo->prepare("INSERT INTO transactions (animal_id, type, category, amount, trans_date, description) VALUES (?, 'Expense', 'Vaccine', 10.00, CURDATE(), ?)")
                ->execute([$rec['animal_id'], "Administered " . $vName]);
            runAutomation($pdo);
        }
        echo "<div class='alert alert-success'>Record completed.</div>";
    }
}

$filters = $_GET;
$filter = buildFilterWhere($filters, 'a');

$sql_records = "SELECT vr.*, a.auto_id, a.name as animal, v.name as vname 
                FROM vaccination_records vr 
                JOIN animals a ON vr.animal_id = a.id 
                JOIN vaccines v ON vr.vaccine_id = v.id 
                {$filter['clause']} 
                ORDER BY vr.due_date DESC";
$stmt_rec = $pdo->prepare($sql_records);
$stmt_rec->execute($filter['params']);
$records = $stmt_rec->fetchAll();

$animals = $pdo->query("SELECT id, auto_id, name FROM animals WHERE status = 'Active'")->fetchAll();
$vaccines = $pdo->query("SELECT id, name, recommended_age_months, repeat_months FROM vaccines")->fetchAll();
?>

<div class="row g-3">
    <!-- টেবিল সেকশন -->
    <div class="col-12 col-md-8">
        <div class="card p-3 p-md-4 shadow-sm bg-white border h-100">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <h5><i class="bi bi-shield-check me-2"></i>Immunization Log</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#schedModal"><i class="bi bi-calendar-plus"></i> Schedule</button>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr><th>Animal</th><th>Vaccine</th><th>Due</th><th>Given</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($records as $r): ?>
                        <tr class="<?=$r['status']==='Overdue'?'table-danger':''?>">
                            <td><strong><?=e($r['auto_id'])?></strong></td>
                            <td><?=e($r['vname'])?></td>
                            <td><?=e($r['due_date'])?></td>
                            <td><?=e($r['given_date'] ?? '-')?></td>
                            <td><span class="badge bg-<?=$r['status']==='Completed'?'success':($r['status']==='Overdue'?'danger':'warning')?>"><?=e($r['status'])?></span></td>
                            <td>
                                <?php if($r['status'] !== 'Completed'): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="complete" value="1">
                                    <input type="hidden" name="record_id" value="<?=(int)$r['id']?>">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <button class="btn btn-sm btn-success py-1 px-2"><i class="bi bi-patch-check"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; if(empty($records)) echo "<tr><td colspan='6' class='text-center text-muted py-3'>No records.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ডান পাশের ফর্ম -->
    <div class="col-12 col-md-4">
        <!-- Add Vaccine -->
        <div class="card p-3 p-md-4 shadow-sm bg-white border mb-3">
            <h5 class="mb-3 border-bottom pb-2"><i class="bi bi-shield-plus me-2"></i>Add Custom Vaccine</h5>
            <form method="post">
                <input type="hidden" name="add_vaccine_rule" value="1">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <div class="mb-2">
                    <label class="form-label small">Vaccine Name</label>
                    <input name="vname" class="form-control form-control-sm" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Age (Months)</label>
                    <input type="number" name="vage" class="form-control form-control-sm" required min="0">
                </div>
                <div class="mb-3">
                    <label class="form-label small">Repeat (Months)</label>
                    <input type="number" name="vrepeat" class="form-control form-control-sm" required min="0">
                </div>
                <button class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-circle"></i> Save</button>
            </form>
        </div>

        <!-- Rules List -->
        <div class="card p-3 p-md-4 shadow-sm bg-white border">
            <h5 class="mb-3 border-bottom pb-2"><i class="bi bi-info-circle me-2"></i>Vaccine Rules</h5>
            <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                <table class="table table-sm table-striped mb-0">
                    <thead><tr><th>Vaccine</th><th>Age</th><th>Repeat</th></tr></thead>
                    <tbody>
                        <?php foreach($vaccines as $v): ?>
                        <tr>
                            <td><?=e($v['name'])?></td>
                            <td><?=e($v['recommended_age_months'])?>m</td>
                            <td><?=$v['repeat_months'] > 0 ? e($v['repeat_months'])."m" : "Once"?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Modal -->
<div class="modal fade" id="schedModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post">
      <div class="modal-content">
        <div class="modal-header"><h5>Schedule Vaccination</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" name="schedule" value="1">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <div class="mb-2">
                <label class="form-label small">Animal</label>
                <select name="animal_id" class="form-select" required>
                    <?php foreach($animals as $a) echo "<option value='{$a['id']}'>".e($a['auto_id'])."</option>"; ?>
                </select>
            </div>
            <div class="mb-2">
                <label class="form-label small">Vaccine</label>
                <select name="vaccine_id" class="form-select" required>
                    <?php foreach($vaccines as $v) echo "<option value='{$v['id']}'>".e($v['name'])."</option>"; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label small">Due Date</label>
                <input type="date" name="due_date" class="form-control" required>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">Schedule</button></div>
      </div>
    </form>
  </div>
</div>