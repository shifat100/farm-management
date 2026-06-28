<?php
require_once 'functions.php';

$filt = buildFilterWhere($_GET);

// Live Statistical Quantifications
$total = $pdo->query("SELECT COUNT(*) FROM animals WHERE status='Active'")->fetchColumn();
$kids = $pdo->query("SELECT COUNT(*) FROM animals WHERE status='Active' AND TIMESTAMPDIFF(MONTH, dob, CURDATE()) <= 3")->fetchColumn();
$breeding_ready = $pdo->query("SELECT COUNT(*) FROM animals WHERE status='Active' AND health_status='Healthy' AND pregnancy_status='Not Pregnant' AND TIMESTAMPDIFF(MONTH, dob, CURDATE()) >= 8")->fetchColumn();
$ready_for_sale = $pdo->query("SELECT COUNT(*) FROM animals WHERE status='Active' AND sale_readiness='Ready'")->fetchColumn();
$sick_animals = $pdo->query("SELECT COUNT(*) FROM animals WHERE status='Active' AND health_status IN ('Sick','Critical')")->fetchColumn();

// নতুন ডেটা
$expiring_vaccines = getExpiringVaccines($pdo);
$withdrawal_animals = getAnimalsInWithdrawal($pdo);
$upcoming_heats = getUpcomingHeatCycles($pdo);

$breeding_suggestions = getBreedingSuggestions($pdo);
$upcoming_vaccs = getUpcomingVaccinations($pdo);
?>

<!-- সারাংশ কার্ড (col-md-2.4 → সঠিক ক্লাস) -->
<div class="row g-3 mb-4 text-center">
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card bg-primary text-white shadow-sm p-3 border-0 h-100 dashboard-card">
            <h6>Active Stocks</h6>
            <h3><?=$total?></h3>
        </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card bg-success text-white shadow-sm p-3 border-0 h-100 dashboard-card">
            <h6>Ready for Sale</h6>
            <h3><?=$ready_for_sale?></h3>
        </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card bg-info text-dark shadow-sm p-3 border-0 h-100 dashboard-card">
            <h6>Total Kids</h6>
            <h3><?=$kids?></h3>
        </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card bg-warning text-dark shadow-sm p-3 border-0 h-100 dashboard-card">
            <h6>Breeding Ready</h6>
            <h3><?=$breeding_ready?></h3>
        </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2 offset-md-0 offset-lg-1">
        <div class="card bg-danger text-white shadow-sm p-3 border-0 h-100 dashboard-card">
            <h6>Sick Animals</h6>
            <h3><?=$sick_animals?></h3>
        </div>
    </div>
</div>

<div class="row">
    <!-- এক্সপায়ারি ভ্যাকসিন -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm h-100 border-left border-danger">
            <div class="card-header bg-danger text-white"><i class="bi bi-exclamation-triangle me-2"></i>Near Expiry Vaccines (30 Days)</div>
            <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                <?php if(empty($expiring_vaccines)): ?>
                    <p class="text-muted text-center py-3">No vaccines expiring soon.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach($expiring_vaccines as $ev): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span><strong><?=e($ev['vaccine_name'])?></strong> (Batch: <?=e($ev['batch_number'])?>)</span>
                            <span class="badge bg-warning">Expires: <?=e($ev['expiry_date'])?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- উইথড্রয়াল পিরিয়ড -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm h-100 border-left border-warning">
            <div class="card-header bg-warning text-dark"><i class="bi bi-clock-history me-2"></i>Animals in Withdrawal</div>
            <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                <?php if(empty($withdrawal_animals)): ?>
                    <p class="text-muted text-center py-3">No animals in withdrawal period.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach($withdrawal_animals as $wa): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span><strong><?=e($wa['auto_id'])?></strong> (<?=e($wa['name'])?>)</span>
                            <span class="badge bg-danger">Until <?=e($wa['withdrawal_end_date'])?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- আসন্ন হিট সাইকেল -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm h-100 border-left border-success">
            <div class="card-header bg-success text-white"><i class="bi bi-heart-pulse me-2"></i>Upcoming Heat Cycles (Next 7 Days)</div>
            <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                <?php if(empty($upcoming_heats)): ?>
                    <p class="text-muted text-center py-3">No upcoming heat cycles predicted.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach($upcoming_heats as $uh): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span><strong><?=e($uh['auto_id'])?></strong> (<?=e($uh['name'])?>)</span>
                            <span class="badge bg-success"><?=e($uh['next_heat_date'])?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Vaccines Queue -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white"><i class="bi bi-shield-plus me-2"></i>Upcoming Group Vaccinations (Next 15 Days)</div>
            <div class="card-body">
                <?php if (empty($upcoming_vaccs)): ?>
                    <p class="text-muted text-center py-4">No upcoming group vaccination targets found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr><th>Tag ID</th><th>Animal Name</th><th>Vaccine</th><th>Estimated Due</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($upcoming_vaccs as $uv): ?>
                                <tr>
                                    <td><strong><?=e($uv['auto_id'])?></strong></td>
                                    <td><?=e($uv['animal_name'])?></td>
                                    <td><?=e($uv['vaccine_name'])?></td>
                                    <td><?=e($uv['estimated_due_date'])?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Lineage Breeding Suggester -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-success text-white"><i class="bi bi-heart-pulse me-2"></i>Lineage Breeding Match Finder (Safe Crosses)</div>
            <div class="card-body">
                <?php if (empty($breeding_suggestions)): ?>
                    <p class="text-muted text-center py-4">No matching active cross-breeding pairs detected.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush" style="max-height: 280px; overflow-y: auto;">
                        <?php foreach($breeding_suggestions as $bs): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong class="text-primary"><?=e($bs['female']['auto_id'])?> (<?=e($bs['female']['name'])?>)</strong> Recommended Mates:
                            </div>
                            <div>
                                <?php foreach($bs['bucks'] as $buck): ?>
                                    <span class="badge bg-secondary"><?=e($buck['auto_id'])?></span>
                                <?php endforeach; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>