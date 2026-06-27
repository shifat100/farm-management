<?php
require_once 'functions.php';

$filt = buildFilterWhere($_GET);

// Live Statistical Quantifications
$total = $pdo->query("SELECT COUNT(*) FROM animals WHERE status='Active'")->fetchColumn();
$kids = $pdo->query("SELECT COUNT(*) FROM animals WHERE status='Active' AND TIMESTAMPDIFF(MONTH, dob, CURDATE()) <= 3")->fetchColumn();
$breeding_ready = $pdo->query("SELECT COUNT(*) FROM animals WHERE status='Active' AND health_status='Healthy' AND pregnancy_status='Not Pregnant' AND TIMESTAMPDIFF(MONTH, dob, CURDATE()) >= 8")->fetchColumn();
$ready_for_sale = $pdo->query("SELECT COUNT(*) FROM animals WHERE status='Active' AND sale_readiness='Ready'")->fetchColumn();
$sick_animals = $pdo->query("SELECT COUNT(*) FROM animals WHERE status='Active' AND health_status IN ('Sick','Critical')")->fetchColumn();

// Inbreeding-free breeding recommendations
$breeding_suggestions = getBreedingSuggestions($pdo);

// Group Vaccine Queues
$upcoming_vaccs = getUpcomingVaccinations($pdo);
?>

<div class="row g-3 mb-4 text-center">
    <div class="col-6 col-md-2.4">
        <div class="card bg-primary text-white shadow-sm p-3 border-0 h-100">
            <h6>Active Stocks</h6>
            <h3><?=$total?></h3>
        </div>
    </div>
    <div class="col-6 col-md-2.4">
        <div class="card bg-success text-white shadow-sm p-3 border-0 h-100">
            <h6>Ready for Sale</h6>
            <h3><?=$ready_for_sale?></h3>
        </div>
    </div>
    <div class="col-6 col-md-2.4">
        <div class="card bg-info text-dark shadow-sm p-3 border-0 h-100">
            <h6>Total Kids</h6>
            <h3><?=$kids?></h3>
        </div>
    </div>
    <div class="col-6 col-md-2.4">
        <div class="card bg-warning text-dark shadow-sm p-3 border-0 h-100">
            <h6>Breeding Ready</h6>
            <h3><?=$breeding_ready?></h3>
        </div>
    </div>
    <div class="col-12 col-md-2.4">
        <div class="card bg-danger text-white shadow-sm p-3 border-0 h-100">
            <h6>Sick Animals</h6>
            <h3><?=$sick_animals?></h3>
        </div>
    </div>
</div>

<div class="row">
    <!-- Automated Vaccines Batch Queue -->
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