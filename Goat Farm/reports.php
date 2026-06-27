<?php
require_once 'functions.php';

$filters = $_GET;
$filter = buildFilterWhere($filters, 'a');

// SQL queries structured dynamically to run on all hosting environments [1]
$sql = "SELECT a.id, a.auto_id, a.name, a.type, a.selling_price,
        (SELECT COALESCE(SUM(amount), 0.00) FROM animal_costs WHERE animal_id = a.id) as cost,
        (a.selling_price - (SELECT COALESCE(SUM(amount), 0.00) FROM animal_costs WHERE animal_id = a.id)) as profit
        FROM animals a {$filter['clause']} ORDER BY profit DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($filter['params']);
$data = $stmt->fetchAll();

// Dynamic CSV Output compilation
if (isset($_GET['export'])) {
    ob_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Farm_Performance_Report_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Animal Auto ID', 'Name Identification', 'Classification Type', 'Aggregate Costs', 'Current Realized Market Value', 'Profit margin output', 'Min Safe Sell-Margin (10% target)']);
    
    foreach ($data as $row) {
        $min_sell = (float)$row['cost'] * 1.10;
        fputcsv($out, [
            $row['auto_id'], 
            $row['name'], 
            $row['type'], 
            number_format($row['cost'], 2, '.', ''), 
            $row['selling_price'] ? number_format($row['selling_price'], 2, '.', '') : '0.00', 
            $row['selling_price'] ? number_format($row['profit'], 2, '.', '') : 'N/A', 
            number_format($min_sell, 2, '.', '')
        ]);
    }
    fclose($out); 
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Animal Bookkeeping & Profit Reports</h4>
    <div>
        <a href="?page=reports&export=1&<?=http_build_query($filters)?>" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel"></i> Export CSV</a>
        <button onclick="window.print()" class="btn btn-secondary btn-sm"><i class="bi bi-printer"></i> Print Ledger</button>
    </div>
</div>

<div class="table-responsive bg-white rounded shadow-sm p-3">
    <table class="table table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>Auto ID</th>
                <th>Name</th>
                <th>Type</th>
                <th>Total Cost</th>
                <th>Expected Market Value</th>
                <th>Profit Margin</th>
                <th>Min Sell Target (10%)</th>
                <th>ROI Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($data as $r): 
            $minSell = (float)$r['cost'] * 1.10;
            $roi = ($r['selling_price'] && $r['cost'] > 0) ? ($r['profit'] / $r['cost']) * 100 : 0;
        ?>
        <tr>
            <td><strong><?=e($r['auto_id'])?></strong></td>
            <td><?=e($r['name'])?></td>
            <td><?=e($r['type'])?></td>
            <td>$<?=number_format($r['cost'], 2)?></td>
            <td><?=$r['selling_price'] ? '$' . number_format($r['selling_price'], 2) : 'Unsold'?></td>
            <td class="<?=$r['selling_price'] ? ($r['profit'] >= 0 ? 'text-success' : 'text-danger') : 'text-muted'?>">
                <?=$r['selling_price'] ? '$' . number_format($r['profit'], 2) : 'N/A'?>
            </td>
            <td>$<?=number_format($minSell, 2)?></td>
            <td>
                <?php if ($r['selling_price'] && $r['cost'] > 0): ?>
                <span class="badge bg-<?=$roi >= 10 ? 'success' : 'warning'?>"><?=number_format($roi, 1)?>% ROI</span>
                <?php else: ?>
                <span class="text-muted">-</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; if(empty($data)) echo "<tr><td colspan='8' class='text-center text-muted'>No dynamic entries verified.</td></tr>"; ?>
        </tbody>
    </table>
</div>