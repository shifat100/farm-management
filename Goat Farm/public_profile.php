<?php
require_once 'config.php';
require_once 'functions.php';

$id = (int)($_GET['id'] ?? 0);

// Fetch profile safely [1]
$stmt = $pdo->prepare("SELECT a.*, s.name as shed, m.name as mother, f.name as father 
                       FROM animals a 
                       LEFT JOIN sheds s ON a.shed_id = s.id 
                       LEFT JOIN animals m ON a.mother_id = m.id 
                       LEFT JOIN animals f ON a.father_id = f.id 
                       WHERE a.id = ?");
$stmt->execute([$id]);
$a = $stmt->fetch();

if (!$a) {
    die("<div class='container mt-5 text-center'><div class='alert alert-danger'>Profile record not identified.</div></div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Profile - ID: <?=e($a['auto_id'])?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 600px;">
    <div class="card shadow border-0">
        <div class="text-center bg-primary text-white py-4 rounded-top">
            <i class="bi bi-goat fs-1 text-warning"></i>
            <h4 class="mt-2 text-uppercase mb-0">Animal Verification Registry</h4>
            <p class="small text-white-50 mb-0">Dynamic QR Status Report</p>
        </div>
        <div class="card-body text-center">
            <img src="assets/images/<?=e($a['image'])?>" class="img-fluid rounded mb-3 shadow" style="max-height:220px; width: 100%; object-fit: cover;" onerror="this.src='assets/images/default.png'">
            <h2 class="mb-1"><?=e($a['name'] ? $a['name'] : 'Unnamed Stock')?></h2>
            <h5 class="text-muted mb-4">Tag ID: <?=e($a['auto_id'])?></h5>
            
            <div class="text-start">
                <table class="table table-bordered align-middle table-striped">
                    <tbody>
                        <tr><th>Classification</th><td><?=e($a['type'])?></td></tr>
                        <tr><th>Breed</th><td><?=e($a['breed'] ? $a['breed'] : 'Not Recorded')?></td></tr>
                        <tr><th>DOB (Age)</th><td><?=e($a['dob'])?> (<?=getAge($a['dob'])?>)</td></tr>
                        <tr><th>Current Weight</th><td><?=e($a['weight'])?> kg</td></tr>
                        <tr><th>Color</th><td><?=e($a['color'] ? $a['color'] : 'Not Recorded')?></td></tr>
                        <tr><th>Health Status</th><td>
                            <span class="badge bg-<?=$a['health_status'] === 'Healthy' ? 'success' : 'danger'?>">
                                <?=e($a['health_status'])?>
                            </span>
                        </td></tr>
                        <tr><th>Immunizations</th><td><?=e($a['vaccination_status'])?></td></tr>
                        <tr><th>Active Location</th><td><?=e($a['shed'] ? $a['shed'] : 'Shed Unassigned')?></td></tr>
                        <tr><th>Dam (Mother)</th><td><?=e($a['mother'] ? $a['mother'] : 'Unrecorded')?></td></tr>
                        <tr><th>Sire (Father)</th><td><?=e($a['father'] ? $a['father'] : 'Unrecorded')?></td></tr>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4 border-top pt-3">
                <p class="small text-muted mb-0"><i class="bi bi-shield-lock-fill me-1"></i>Secure cryptographic scan. Private data parameters are isolated from public clients.</p>
            </div>
        </div>
    </div>
</div>
</body>
</html>