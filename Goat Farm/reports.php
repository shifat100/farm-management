<?php
require_once 'functions.php';

$filters = $_GET;
$filter = buildFilterWhere($filters, 'a');

$sql = "SELECT a.id, a.auto_id, a.name, a.type, a.selling_price, s.name as shed_name,
        (SELECT COALESCE(SUM(amount), 0.00) FROM animal_costs WHERE animal_id = a.id) as cost,
        (a.selling_price - (SELECT COALESCE(SUM(amount), 0.00) FROM animal_costs WHERE animal_id = a.id)) as profit
        FROM animals a
        LEFT JOIN sheds s ON a.shed_id = s.id
        {$filter['clause']} ORDER BY profit DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($filter['params']);
$data = $stmt->fetchAll();

if (isset($_GET['export'])) {
    ob_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Farm_Report_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Auto ID', 'Name', 'Type', 'Shed', 'Total Cost', 'Market Value', 'Profit', 'Min Sell']);
    foreach ($data as $row) {
        $min_sell = (float)$row['cost'] * 1.10;
        fputcsv($out, [
            $row['auto_id'], $row['name'], $row['type'], $row['shed_name'] ?? 'N/A',
            number_format($row['cost'], 2, '.', ''),
            $row['selling_price'] ? number_format($row['selling_price'], 2, '.', '') : '0.00',
            $row['selling_price'] ? number_format($row['profit'], 2, '.', '') : 'N/A',
            number_format($min_sell, 2, '.', '')
        ]);
    }
    fclose($out); exit;
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h4 class="mb-0">Profit Reports</h4>
    <div class="d-flex flex-wrap gap-2">
        <a href="?page=reports&export=1&<?=http_build_query($filters)?>" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel"></i> Export CSV</a>
        <button onclick="window.print()" class="btn btn-secondary btn-sm"><i class="bi bi-printer"></i> Print</button>
    </div>
</div>

<div class="table-responsive bg-white rounded shadow-sm p-2 p-md-3">
    <table class="table table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>ID</th><th>Name</th><th>Type</th>
                <th class="d-none d-sm-table-cell">Shed</th>
                <th>Cost</th>
                <th class="d-none d-sm-table-cell">Value</th>
                <th>Profit</th>
                <th class="d-none d-md-table-cell">Min Sell</th>
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
            <td class="d-none d-sm-table-cell"><?=e($r['shed_name'] ?? 'N/A')?></td>
            <td>$<?=number_format($r['cost'], 2)?></td>
            <td class="d-none d-sm-table-cell"><?=$r['selling_price'] ? '$' . number_format($r['selling_price'], 2) : '-'?></td>
            <td class="<?=$r['selling_price'] ? ($r['profit'] >= 0 ? 'text-success' : 'text-danger') : 'text-muted'?>">
                <?=$r['selling_price'] ? '$' . number_format($r['profit'], 2) : 'N/A'?>
            </td>
            <td class="d-none d-md-table-cell">$<?=number_format($minSell, 2)?></td>
        </tr>
        <?php endforeach; if(empty($data)) echo "<tr><td colspan='8' class='text-center text-muted'>No records.</td></tr>"; ?>
        </tbody>
    </table>
</div>