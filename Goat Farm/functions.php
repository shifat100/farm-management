<?php
// Global Secure Escaper against XSS Vulnerabilities
if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// Auto ID Generation matching (Goat->c, Buck->p, Castrated->k) [1]
function generateAutoID($pdo, $type) {
    $prefix = ['Goat' => 'c', 'Buck' => 'p', 'Castrated' => 'k'][$type] ?? 'x';
    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(auto_id, 2) AS UNSIGNED)) FROM animals WHERE type = ?");
    $stmt->execute([$type]);
    $max = $stmt->fetchColumn();
    return $prefix . (($max ?? 0) + 1);
}

// Live Dynamic Age Calculation
function getAge($dob) {
    if (!$dob) return 'N/A';
    try {
        $d = new DateTime($dob); 
        $diff = $d->diff(new DateTime());
        if ($diff->y > 0) {
            return $diff->y . 'y ' . $diff->m . 'm';
        }
        return $diff->m . 'm';
    } catch (Exception $e) {
        return 'N/A';
    }
}

// Calculated Total Investment Cost
function calcTotalCost($pdo, $id) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0.00) FROM animal_costs WHERE animal_id = ?");
    $stmt->execute([$id]);
    return (float)$stmt->fetchColumn();
}

// Upcoming Group Vaccinations Finder (Next 15 Days)
function getUpcomingVaccinations($pdo) {
    $stmt = $pdo->query("
        SELECT a.id as animal_id, a.name as animal_name, a.auto_id, v.id as vaccine_id, v.name as vaccine_name,
               DATE_ADD(a.dob, INTERVAL v.recommended_age_months MONTH) as estimated_due_date
        FROM animals a
        CROSS JOIN vaccines v
        LEFT JOIN vaccination_records vr ON a.id = vr.animal_id AND v.id = vr.vaccine_id
        WHERE a.status = 'Active'
          AND vr.id IS NULL
          AND DATE_ADD(a.dob, INTERVAL v.recommended_age_months MONTH) <= DATE_ADD(CURDATE(), INTERVAL 15 DAY)
        ORDER BY estimated_due_date ASC
    ");
    return $stmt->fetchAll();
}

// Lineage Breeding Suggester
function getBreedingSuggestions($pdo) {
    $stmt = $pdo->query("
        SELECT id, name, auto_id, mother_id, father_id 
        FROM animals 
        WHERE type='Goat' AND status='Active' AND health_status='Healthy' AND pregnancy_status='Not Pregnant' 
          AND TIMESTAMPDIFF(MONTH, dob, CURDATE()) >= 8
    ");
    $females = $stmt->fetchAll();
    $suggestions = [];
    
    foreach ($females as $f) {
        $sql = "SELECT id, name, auto_id FROM animals 
                WHERE type='Buck' AND status='Active' AND health_status='Healthy' 
                  AND id != :father_id";
        $params = [':father_id' => $f['father_id'] ?? 0];
        
        if ($f['mother_id']) {
            $sql .= " AND (mother_id IS NULL OR mother_id != :mother_id)";
            $params[':mother_id'] = $f['mother_id'];
        }
        
        $stmt_buck = $pdo->prepare($sql);
        $stmt_buck->execute($params);
        $bucks = $stmt_buck->fetchAll();
        
        if ($bucks) {
            $suggestions[] = [
                'female' => $f,
                'bucks' => $bucks
            ];
        }
    }
    return $suggestions;
}

// Standard Parameterized Filters Helper [1]
function buildFilterWhere($filters, $prefix = 'a') {
    $where = []; 
    $params = [];
    
    if (!empty($filters['type'])) { 
        $where[] = "{$prefix}.type = :type"; 
        $params[':type'] = $filters['type']; 
    }
    if (!empty($filters['breed'])) { 
        $where[] = "{$prefix}.breed LIKE :breed"; 
        $params[':breed'] = '%' . $filters['breed'] . '%'; 
    }
    if (!empty($filters['health_status'])) { 
        $where[] = "{$prefix}.health_status = :hs"; 
        $params[':hs'] = $filters['health_status']; 
    }
    if (!empty($filters['vaccination_status'])) { 
        $where[] = "{$prefix}.vaccination_status = :vs"; 
        $params[':vs'] = $filters['vaccination_status']; 
    }
    if (!empty($filters['pregnancy_status'])) { 
        $where[] = "{$prefix}.pregnancy_status = :ps"; 
        $params[':ps'] = $filters['pregnancy_status']; 
    }
    if (!empty($filters['weight_min'])) { 
        $where[] = "{$prefix}.weight >= :wmin"; 
        $params[':wmin'] = $filters['weight_min']; 
    }
    if (!empty($filters['weight_max'])) { 
        $where[] = "{$prefix}.weight <= :wmax"; 
        $params[':wmax'] = $filters['weight_max']; 
    }
    if (!empty($filters['sale_readiness'])) { 
        $where[] = "{$prefix}.sale_readiness = :sr"; 
        $params[':sr'] = $filters['sale_readiness']; 
    }
    if (!empty($filters['status'])) { 
        $where[] = "{$prefix}.status = :st"; 
        $params[':st'] = $filters['status']; 
    }
    if (!empty($filters['date_from'])) { 
        $where[] = "{$prefix}.dob >= :df"; 
        $params[':df'] = $filters['date_from']; 
    }
    if (!empty($filters['date_to'])) { 
        $where[] = "{$prefix}.dob <= :dt"; 
        $params[':dt'] = $filters['date_to']; 
    }
    if (!empty($filters['shed_id'])) { 
        $where[] = "{$prefix}.shed_id = :sh"; 
        $params[':sh'] = $filters['shed_id']; 
    }
    if (!empty($filters['age_min'])) { 
        $where[] = "TIMESTAMPDIFF(MONTH, {$prefix}.dob, CURDATE()) >= :amin"; 
        $params[':amin'] = $filters['age_min']; 
    }
    if (!empty($filters['age_max'])) { 
        $where[] = "TIMESTAMPDIFF(MONTH, {$prefix}.dob, CURDATE()) <= :amax"; 
        $params[':amax'] = $filters['age_max']; 
    }
    if (!empty($filters['profit_loss'])) {
        $cost_subquery = "(SELECT COALESCE(SUM(amount), 0.00) FROM animal_costs WHERE animal_id = {$prefix}.id)";
        if ($filters['profit_loss'] === 'profit') {
            $where[] = "({$cost_subquery} * 1.10) <= {$prefix}.selling_price";
        } else {
            $where[] = "({$cost_subquery} * 1.10) > {$prefix}.selling_price OR {$prefix}.selling_price IS NULL";
        }
    }
    
    return [
        'clause' => $where ? ' WHERE ' . implode(' AND ', $where) : '', 
        'params' => $params
    ];
}

// Complete DLS Automation Engine (With recurring schedule generators)
function runAutomation($pdo) {
    // 1. Mark past-due immunization schedules as Overdue
    $pdo->exec("UPDATE vaccination_records SET status = 'Overdue' WHERE status = 'Pending' AND due_date < CURDATE()");
    $pdo->exec("UPDATE animals SET vaccination_status = 'Overdue' WHERE id IN (SELECT DISTINCT animal_id FROM vaccination_records WHERE status = 'Overdue')");
    
    $animals = $pdo->query("SELECT id, dob, name, auto_id FROM animals WHERE status = 'Active'")->fetchAll();
    $vaccines = $pdo->query("SELECT * FROM vaccines")->fetchAll();
    
    foreach ($animals as $animal) {
        if (!$animal['dob']) continue;
        
        $dob = new DateTime($animal['dob']);
        $now = new DateTime();
        $age_days = $now->diff($dob)->days;
        $age_months = ($now->diff($dob)->y * 12) + $now->diff($dob)->m;
        
        foreach ($vaccines as $v) {
            $due_date = null;
            if (stripos($v['name'], 'CDT') !== false) {
                // Ecthyma guidelines: Dose 1 (Day 3), Dose 2 (Day 17), Dose 3 (Day 90)
                if ($age_days >= 3 && $age_days <= 16) {
                    $due_date = date('Y-m-d', strtotime($animal['dob'] . ' + 3 days'));
                } elseif ($age_days >= 17 && $age_days <= 89) {
                    $due_date = date('Y-m-d', strtotime($animal['dob'] . ' + 17 days'));
                } elseif ($age_days >= 90) {
                    $due_date = date('Y-m-d', strtotime($animal['dob'] . ' + 90 days'));
                }
            } else {
                // Custom or standard month-based schedules
                if ($age_months >= $v['recommended_age_months']) {
                    $due_date = date('Y-m-d', strtotime($animal['dob'] . ' + ' . $v['recommended_age_months'] . ' months'));
                }
            }
            
            if ($due_date) {
                $check = $pdo->prepare("SELECT id FROM vaccination_records WHERE animal_id = ? AND vaccine_id = ? AND due_date = ?");
                $check->execute([$animal['id'], $v['id'], $due_date]);
                if (!$check->fetch()) {
                    $pdo->prepare("INSERT INTO vaccination_records (animal_id, vaccine_id, due_date, status) VALUES (?, ?, ?, 'Pending')")
                        ->execute([$animal['id'], $v['id'], $due_date]);
                }
            }
        }
    }

    // 2. Schedule Subsequent Doses for Recurring Vaccines
    $completed_repeats = $pdo->query("
        SELECT vr.*, v.repeat_months 
        FROM vaccination_records vr 
        JOIN vaccines v ON vr.vaccine_id = v.id 
        WHERE vr.status = 'Completed' AND v.repeat_months > 0
    ")->fetchAll();

    foreach ($completed_repeats as $cr) {
        if (!$cr['given_date']) continue;
        
        $next_due_date = date('Y-m-d', strtotime($cr['given_date'] . ' + ' . $cr['repeat_months'] . ' months'));
        
        $check = $pdo->prepare("SELECT id FROM vaccination_records WHERE animal_id = ? AND vaccine_id = ? AND due_date = ?");
        $check->execute([$cr['animal_id'], $cr['vaccine_id'], $next_due_date]);
        if (!$check->fetch()) {
            $pdo->prepare("INSERT INTO vaccination_records (animal_id, vaccine_id, due_date, status) VALUES (?, ?, ?, 'Pending')")
                ->execute([$cr['animal_id'], $cr['vaccine_id'], $next_due_date]);
        }
    }

    // 3. Dynamic Seasonal Herd Deworming alerts (Onset of rainy season May/June & Winter transition Oct/Nov)
    $month = (int)date('m');
    if (in_array($month, [5, 6, 10, 11])) {
        $msg = "Seasonal Herd Deworming Alert: DLS rules recommend concurrent herd-wide deworming administration during this month's seasonal change.";
        $check = $pdo->prepare("SELECT id FROM alerts WHERE message = ? AND is_read = 0");
        $check->execute([$msg]);
        if (!$check->fetch()) {
            $pdo->prepare("INSERT INTO alerts (animal_id, message, type) VALUES (0, ?, 'Deworming Alert')")->execute([$msg]);
        }
    }
}