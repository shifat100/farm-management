<?php
require_once 'functions.php';
$id = $_GET['id'];
$animal = $pdo->query("SELECT a.*, s.name as shed, m.name as mother, f.name as father FROM animals a LEFT JOIN sheds s ON a.shed_id=s.id LEFT JOIN animals m ON a.mother_id=m.id LEFT JOIN animals f ON a.father_id=f.id WHERE a.id=$id")->fetch();
if (!$animal) die("Not found");
$totalCost = calcTotalCost($pdo, $id);
$minSell = $totalCost * 1.10;
$profit = $animal['selling_price'] ? $animal['selling_price'] - $totalCost : null;
$costs = $pdo->query("SELECT * FROM animal_costs WHERE animal_id=$id ORDER BY cost_date DESC")->fetchAll();
$activities = $pdo->query("SELECT * FROM activities WHERE animal_id=$id ORDER BY activity_date DESC LIMIT 10")->fetchAll();
?>
<div class="row">
    <div class="col-md-4"><div class="card"><div class="card-body text-center"><h3><?=$animal['name']?> (<?=$animal['auto_id']?>)</h3>
        <span class="badge bg-<?=$animal['health_status']=='Healthy'?'success':'danger'?>"><?=$animal['health_status']?></span>
        <span class="badge bg-secondary"><?=$animal['status']?></span></div></div>
        <div class="card mt-2"><div class="card-header">Finance</div><div class="card-body">
            <p>Total Cost: <strong>$<?=number_format($totalCost,2)?></strong></p>
            <p>Min Selling (10%): <strong>$<?=number_format($minSell,2)?></strong></p>
            <?php if($profit!==null): ?><p>Profit: <strong class="<?=$profit>=0?'text-success':'text-danger'?>">$<?=number_format($profit,2)?></strong></p><?php endif; ?>
        </div></div>
    </div>
    <div class="col-md-8">
        <table class="table"><tr><th>Type</th><td><?=$animal['type']?></td><th>Breed</th><td><?=$animal['breed']?></td></tr>
        <tr><th>DOB</th><td><?=$animal['dob']?> (<?=getAge($animal['dob'])?>)</td><th>Weight</th><td><?=$animal['weight']?> kg</td></tr>
        <tr><th>Mother</th><td><?=$animal['mother']??'Unknown'?></td><th>Father</th><td><?=$animal['father']??'Unknown'?></td></tr>
        </table>
        <h5>Costs</h5><table class="table table-sm"><tr><th>Date</th><th>Category</th><th>Amount</th><th>Note</th></tr>
        <?php foreach($costs as $c) echo "<tr><td>{$c['cost_date']}</td><td>{$c['category']}</td><td>\${$c['amount']}</td><td>{$c['note']}</td></tr>"; ?></table>
        <h5>Recent Activities</h5><ul><?php foreach($activities as $a) echo "<li>{$a['activity_date']} - {$a['activity_type']}: {$a['description']}</li>"; ?></ul>
    </div>
</div>