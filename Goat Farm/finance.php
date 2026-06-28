<?php
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("<div class='alert alert-danger'>Invalid security token. Please refresh the page and try again.</div>");
    }

    if (isset($_POST['trans_action'])) {
        $animal_id = $_POST['animal_id'] !== '' ? (int)$_POST['animal_id'] : null;
        $type = $_POST['type'] ?? 'Expense';
        $category = $_POST['category'] ?? 'Other';
        $amount = (float)$_POST['amount'];
        $date = $_POST['trans_date'] ?? date('Y-m-d');
        $description = $_POST['description'] ?? '';
        
        $pdo->prepare("INSERT INTO transactions (animal_id, type, category, amount, trans_date, description) VALUES (?,?,?,?,?,?)")
            ->execute([$animal_id, $type, $category, $amount, $date, $description]);
            
        if ($type === 'Expense' && $animal_id) {
            $pdo->prepare("INSERT INTO animal_costs (animal_id, category, amount, cost_date, note) VALUES (?,?,?,?,?)")
                ->execute([$animal_id, $category, $amount, $date, $description]);
        }
        echo "<div class='alert alert-success'>Transaction logged securely.</div>";
    }
}

$filters = $_GET;
$filter = buildFilterWhere($filters, 'a');

$sql_t = "SELECT t.*, a.name as animal, a.auto_id 
          FROM transactions t 
          LEFT JOIN animals a ON t.animal_id = a.id 
          {$filter['clause']} 
          ORDER BY t.trans_date DESC LIMIT 100";
$stmt_t = $pdo->prepare($sql_t);
$stmt_t->execute($filter['params']);
$transactions = $stmt_t->fetchAll();

$sql_inc_tot = "SELECT COALESCE(SUM(t.amount),0.00) FROM transactions t LEFT JOIN animals a ON t.animal_id = a.id {$filter['clause']}" . ($filter['clause'] ? " AND " : " WHERE ") . "t.type='Income'";
$sql_exp_tot = "SELECT COALESCE(SUM(t.amount),0.00) FROM transactions t LEFT JOIN animals a ON t.animal_id = a.id {$filter['clause']}" . ($filter['clause'] ? " AND " : " WHERE ") . "t.type='Expense'";

$stmt_it = $pdo->prepare($sql_inc_tot); $stmt_it->execute($filter['params']); $income = $stmt_it->fetchColumn();
$stmt_et = $pdo->prepare($sql_exp_tot); $stmt_et->execute($filter['params']); $expense = $stmt_et->fetchColumn();
$capital = $income - $expense;

$monthly = $pdo->query("SELECT DATE_FORMAT(trans_date,'%Y-%m') m, SUM(IF(type='Income',amount,0)) inc, SUM(IF(type='Expense',amount,0)) exp FROM transactions GROUP BY m ORDER BY m")->fetchAll();
?>

<div class="row g-3 mb-4 text-center">
    <div class="col-md-4"><div class="card bg-success text-white shadow-sm"><div class="card-body"><h6>Active Ledger Income</h6><h3>$<?=number_format($income,2)?></h3></div></div></div>
    <div class="col-md-4"><div class="card bg-danger text-white shadow-sm"><div class="card-body"><h6>Active Ledger Expenses</h6><h3>$<?=number_format($expense,2)?></h3></div></div></div>
    <div class="col-md-4"><div class="card bg-info text-dark shadow-sm"><div class="card-body"><h6>Realized Net Capital</h6><h3>$<?=number_format($capital,2)?></h3></div></div></div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5>Bookkeeping Ledger Record Matrix</h5>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#transModal"><i class="bi bi-wallet2"></i> Add Transaction</button>
</div>

<div class="table-responsive bg-white rounded shadow-sm p-3 mb-4">
    <table class="table table-hover align-middle">
        <thead class="table-dark">
            <tr><th>Date</th><th>Type</th><th>Category</th><th>Amount</th><th>Animal Connection</th></tr>
        </thead>
        <tbody>
            <?php foreach($transactions as $t): ?>
            <tr>
                <td><?=e($t['trans_date'])?></td>
                <td><span class="badge bg-<?=$t['type']==='Income'?'success':'danger'?>"><?=e($t['type'])?></span></td>
                <td><?=e($t['category'])?></td>
                <td>$<?=number_format($t['amount'], 2)?></td>
                <td><?=$t['auto_id'] ? e($t['auto_id'] . " (" . $t['animal'] . ")") : '-'?></td>
            </tr>
            <?php endforeach; if(empty($transactions)) echo "<tr><td colspan='5' class='text-center text-muted'>No entries match active filters.</td></tr>"; ?>
        </tbody>
    </table>
</div>

<div class="card p-3 shadow-sm bg-white">
    <div class="card-header bg-dark text-white mb-2">Monthly Cashflow Performance Charts</div>
    <div style="width: 100%; max-height: 250px;">
        <canvas id="monthlyChart"></canvas>
    </div>
</div>

<!-- Transaction Modal -->
<div class="modal fade" id="transModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post">
      <div class="modal-content">
        <div class="modal-header"><h5>Post Ledger Log Transaction</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" name="trans_action" value="1">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <div class="mb-2">
                <label class="form-label small">Transaction Directional Flow</label>
                <select name="type" class="form-select">
                    <option>Income</option>
                    <option>Expense</option>
                </select>
            </div>
            <div class="mb-2">
                <label class="form-label small">Ledger Classification Category</label>
                <select name="category" class="form-select">
                    <option>Feed</option>
                    <option>Medicine</option>
                    <option>Vaccine</option>
                    <option>Labor</option>
                    <option>Sale</option>
                    <option>Purchase</option>
                    <option>Other</option>
                </select>
            </div>
            <div class="mb-2"><label class="form-label small">Amount</label><input type="number" step="0.01" name="amount" class="form-control" required></div>
            <div class="mb-2"><label class="form-label small">Transaction Timestamp</label><input type="date" name="trans_date" class="form-control" value="<?=date('Y-m-d')?>" required></div>
            <div class="mb-2">
                <label class="form-label small">Target Animal Association (Optional)</label>
                <select name="animal_id" class="form-select">
                    <option value="">Global Transaction</option>
                    <?php 
                    foreach ($pdo->query("SELECT id, auto_id, name FROM animals WHERE status='Active'") as $an) {
                        echo "<option value='{$an['id']}'>" . e($an['auto_id']) . " " . e($an['name']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="mb-2"><label class="form-label small">Brief Context Statement</label><input name="description" class="form-control"></div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">Commit Transaction</button></div>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var months = <?=json_encode(array_column($monthly,'m'))?>;
    var inc = <?=json_encode(array_column($monthly,'inc'))?>;
    var exp = <?=json_encode(array_column($monthly,'exp'))?>;
    new Chart(document.getElementById('monthlyChart'), {
        type: 'line',
        data: { labels: months, datasets: [
            { label: 'Income flow', data: inc, borderColor: '#2ec4b6', fill: false },
            { label: 'Expenses flow', data: exp, borderColor: '#e71d36', fill: false }
        ]},
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
});
</script>