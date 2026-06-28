<?php
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("<div class='alert alert-danger'>Invalid security token. Please refresh the page and try again.</div>");
    }

    if (isset($_POST['add_stock'])) {
        $vaccine_id = (int)$_POST['vaccine_id'];
        $batch = trim($_POST['batch_number'] ?? '');
        $qty = (int)$_POST['quantity'];
        $expiry = $_POST['expiry_date'] ?? date('Y-m-d');
        
        if ($qty > 0) {
            $stmt = $pdo->prepare("INSERT INTO vaccine_stock (vaccine_id, batch_number, stock_quantity, expiry_date) VALUES (?,?,?,?)");
            $stmt->execute([$vaccine_id, $batch, $qty, $expiry]);
            echo "<div class='alert alert-success'>Vaccine stock levels registered successfully.</div>";
        }
    }
    
    if (isset($_POST['log_feed'])) {
        $shed_id = $_POST['shed_id'] !== '' ? (int)$_POST['shed_id'] : null;
        $feed_type = trim($_POST['feed_type'] ?? '');
        $qty_kg = (float)$_POST['quantity_kg'];
        $date = $_POST['record_date'] ?? date('Y-m-d');
        
        $allow_ums = true;
        if ($feed_type === 'UMS') {
            $stmt_kids = $pdo->prepare("SELECT COUNT(*) FROM animals WHERE shed_id = ? AND TIMESTAMPDIFF(MONTH, dob, CURDATE()) < 6 AND status='Active'");
            $stmt_kids->execute([$shed_id]);
            $kid_count = (int)$stmt_kids->fetchColumn();
            if ($kid_count > 0) {
                echo "<div class='alert alert-danger'>Diet Error: Urea-Molasses-Straw (UMS) cannot be allocated to sheds containing kids under 6 months of age.</div>";
                $allow_ums = false;
            }
        }

        if ($allow_ums && $qty_kg > 0 && $feed_type !== '') {
            $stmt = $pdo->prepare("INSERT INTO feed_consumption (shed_id, feed_type, quantity_kg, record_date) VALUES (?,?,?,?)");
            $stmt->execute([$shed_id, $feed_type, $qty_kg, $date]);
            echo "<div class='alert alert-success'>Feed consumption logged successfully.</div>";
        }
    }
}

$feed_shed_id = isset($_GET['feed_shed_id']) && $_GET['feed_shed_id'] !== '' ? (int)$_GET['feed_shed_id'] : null;
$total_weight = 0;
if ($feed_shed_id) {
    $stmt_weight = $pdo->prepare("SELECT COALESCE(SUM(weight), 0) FROM animals WHERE shed_id = ? AND status='Active'");
    $stmt_weight->execute([$feed_shed_id]);
    $total_weight = (float)$stmt_weight->fetchColumn();
}

$required_grass = $total_weight * 0.10;
$required_concentrate = $total_weight * 0.015;

$stocks = $pdo->query("SELECT vs.*, v.name as vaccine_name FROM vaccine_stock vs JOIN vaccines v ON vs.vaccine_id = v.id ORDER BY vs.expiry_date ASC")->fetchAll();
$vaccines = $pdo->query("SELECT id, name FROM vaccines")->fetchAll();
$sheds = $pdo->query("SELECT id, name FROM sheds")->fetchAll();
$consumption = $pdo->query("SELECT fc.*, s.name as shed_name FROM feed_consumption fc LEFT JOIN sheds s ON fc.shed_id = s.id ORDER BY fc.record_date DESC LIMIT 50")->fetchAll();
?>

<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card p-4 shadow-sm bg-white border">
            <h5><i class="bi bi-calculator me-2"></i>DLS Smart Diet Planner</h5>
            <form method="get" class="row g-2 align-items-center mb-3">
                <input type="hidden" name="page" value="inventory">
                <div class="col-md-8">
                    <select name="feed_shed_id" class="form-select form-select-sm">
                        <option value="">Select Shed to calculate target feed parameters...</option>
                        <?php foreach($sheds as $sh): ?>
                        <option value="<?=$sh['id']?>" <?=($feed_shed_id == $sh['id']) ? 'selected' : ''?>><?=e($sh['name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-sm btn-primary w-100">Calculate Feed Mix</button>
                </div>
            </form>
            
            <?php if($feed_shed_id && $total_weight > 0): ?>
            <div class="row">
                <div class="col-md-6 border-end">
                    <h6 class="text-primary">Daily Shed Ration Outputs</h6>
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between"><span>Shed Total Weight:</span> <strong><?=number_format($total_weight, 2)?> kg</strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Required Green Grass (10%):</span> <strong><?=number_format($required_grass, 2)?> kg</strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Required Concentrate Mix (1.5%):</span> <strong><?=number_format($required_concentrate, 2)?> kg</strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Max Daily UMS Intake:</span> <strong><?=number_format($total_weight * 0.03, 2)?> kg</strong></li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="text-secondary">DLS Standard Concentrate Mix Recipe (scaled)</h6>
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between"><span>Crushed Maize/Wheat (30%):</span> <strong><?=number_format($required_concentrate * 0.3, 2)?> kg</strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Rice Bran (30%):</span> <strong><?=number_format($required_concentrate * 0.3, 2)?> kg</strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Wheat Bran (20%):</span> <strong><?=number_format($required_concentrate * 0.2, 2)?> kg</strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Oil Cake (15%):</span> <strong><?=number_format($required_concentrate * 0.15, 2)?> kg</strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Oyster Shell (2%):</span> <strong><?=number_format($required_concentrate * 0.02, 2)?> kg</strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Salt (3%):</span> <strong><?=number_format($required_concentrate * 0.03, 2)?> kg</strong></li>
                    </ul>
                </div>
            </div>
            <?php elseif($feed_shed_id): ?>
            <p class="text-muted text-center py-2 mb-0">No active animal stock found in the selected shed allocation.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card p-4 shadow-sm bg-white border h-100">
            <h5 class="mb-3 border-bottom pb-2"><i class="bi bi-capsules me-2"></i>Vaccine Stock Inventory</h5>
            <form method="post" class="row g-2 mb-3">
                <input type="hidden" name="add_stock" value="1">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <div class="col-md-3">
                    <select name="vaccine_id" class="form-select form-select-sm" required>
                        <option value="">Select Vaccine</option>
                        <?php foreach($vaccines as $v) echo "<option value='{$v['id']}'>".e($v['name'])."</option>"; ?>
                    </select>
                </div>
                <div class="col-md-3"><input name="batch_number" class="form-control form-control-sm" placeholder="Batch No" required></div>
                <div class="col-md-2"><input type="number" name="quantity" class="form-control form-control-sm" placeholder="Qty" required></div>
                <div class="col-md-3"><input type="date" name="expiry_date" class="form-control form-control-sm" required></div>
                <div class="col-md-1"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus"></i></button></div>
            </form>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead><tr><th>Vaccine</th><th>Batch</th><th>Stock Qty</th><th>Expiry Date</th></tr></thead>
                    <tbody>
                        <?php foreach($stocks as $s): 
                            $isExpired = strtotime($s['expiry_date']) <= strtotime('+30 days');
                        ?>
                        <tr class="<?=$isExpired ? 'table-warning' : ''?>">
                            <td><?=e($s['vaccine_name'])?></td>
                            <td><strong><?=e($s['batch_number'])?></strong></td>
                            <td><?=e($s['stock_quantity'])?> doses</td>
                            <td>
                                <?=e($s['expiry_date'])?>
                                <?=$isExpired ? ' <span class="badge bg-danger">Near Expiry</span>' : ''?>
                            </td>
                        </tr>
                        <?php endforeach; if(empty($stocks)) echo "<tr><td colspan='4' class='text-center text-muted'>No vaccine stock recorded.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card p-4 shadow-sm bg-white border h-100">
            <h5 class="mb-3 border-bottom pb-2"><i class="bi bi-basket3 me-2"></i>Feed Consumption Tracker</h5>
            <form method="post" class="row g-2 mb-3">
                <input type="hidden" name="log_feed" value="1">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <div class="col-md-3">
                    <select name="shed_id" class="form-select form-select-sm" required>
                        <option value="">Select Shed</option>
                        <?php foreach($sheds as $sh) echo "<option value='{$sh['id']}'>".e($sh['name'])."</option>"; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="feed_type" class="form-select form-select-sm" required>
                        <option value="">Feed Type</option>
                        <option>Green Grass</option>
                        <option>Concentrate Mix</option>
                        <option>UMS</option>
                    </select>
                </div>
                <div class="col-md-2"><input type="number" step="0.1" name="quantity_kg" class="form-control form-control-sm" placeholder="Kg" required></div>
                <div class="col-md-3"><input type="date" name="record_date" class="form-control form-control-sm" value="<?=date('Y-m-d')?>" required></div>
                <div class="col-md-1"><button class="btn btn-sm btn-success w-100"><i class="bi bi-plus"></i></button></div>
            </form>

            <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead><tr><th>Date</th><th>Shed</th><th>Feed Type</th><th>Qty Used</th></tr></thead>
                    <tbody>
                        <?php foreach($consumption as $c): ?>
                        <tr>
                            <td><?=e($c['record_date'])?></td>
                            <td><?=e($c['shed_name'] ?? 'Global')?></td>
                            <td><?=e($c['feed_type'])?></td>
                            <td><?=e($c['quantity_kg'])?> kg</td>
                        </tr>
                        <?php endforeach; if(empty($consumption)) echo "<tr><td colspan='4' class='text-center text-muted'>No feed records found.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>