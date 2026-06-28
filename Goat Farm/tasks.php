<?php
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("<div class='alert alert-danger'>Invalid security token. Please refresh the page and try again.</div>");
    }

    if (isset($_POST['create_task']) && $_SESSION['user']['role'] !== 'worker') {
        $assigned_to = (int)$_POST['assigned_to_id'];
        $title = trim($_POST['task_title'] ?? '');
        $description = trim($_POST['task_description'] ?? '');
        $due_date = $_POST['due_date'] ?? date('Y-m-d');
        
        if ($title !== '') {
            $stmt = $pdo->prepare("INSERT INTO employee_tasks (assigned_to_id, task_title, task_description, due_date, status) VALUES (?,?,?,?,'Pending')");
            $stmt->execute([$assigned_to, $title, $description, $due_date]);
            echo "<div class='alert alert-success'>Chore deployed successfully.</div>";
        }
    }
    
    if (isset($_POST['complete_task'])) {
        $task_id = (int)$_POST['task_id'];
        
        if ($_SESSION['user']['role'] === 'worker') {
            $stmt_chk = $pdo->prepare("SELECT assigned_to_id FROM employee_tasks WHERE id = ?");
            $stmt_chk->execute([$task_id]);
            $assigned = (int)$stmt_chk->fetchColumn();
            if ($assigned !== (int)$_SESSION['user']['id']) {
                die("Unauthorized action.");
            }
        }
        $pdo->prepare("UPDATE employee_tasks SET status='Completed', completed_at=CURRENT_TIMESTAMP WHERE id = ?")->execute([$task_id]);
        echo "<div class='alert alert-success'>Task updated successfully.</div>";
    }
}

if ($_SESSION['user']['role'] === 'worker') {
    $stmt = $pdo->prepare("SELECT t.*, u.username as assigned_to 
                           FROM employee_tasks t 
                           JOIN users u ON t.assigned_to_id = u.id 
                           WHERE t.assigned_to_id = ? 
                           ORDER BY t.status ASC, t.due_date ASC");
    $stmt->execute([$_SESSION['user']['id']]);
} else {
    $stmt = $pdo->query("SELECT t.*, u.username as assigned_to 
                         FROM employee_tasks t 
                         JOIN users u ON t.assigned_to_id = u.id 
                         ORDER BY t.status ASC, t.due_date ASC");
}
$tasks = $stmt->fetchAll();

$workers = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC")->fetchAll();
?>

<div class="row">
    <?php if ($_SESSION['user']['role'] !== 'worker'): ?>
    <div class="col-md-4 mb-4">
        <div class="card p-4 shadow-sm bg-white border">
            <h5 class="mb-3 border-bottom pb-2"><i class="bi bi-clipboard-plus me-2"></i>Assign Farm Chore</h5>
            <form method="post">
                <input type="hidden" name="create_task" value="1">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <div class="mb-2">
                    <label class="form-label small">Task Summary</label>
                    <input name="task_title" class="form-control form-control-sm" required placeholder="e.g. Sanitize Pen C">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Assign Worker</label>
                    <select name="assigned_to_id" class="form-select form-select-sm" required>
                        <?php foreach($workers as $w) echo "<option value='{$w['id']}'>".e($w['username'])." (".e($w['role']).")</option>"; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Due Target Date</label>
                    <input type="date" name="due_date" class="form-control form-control-sm" value="<?=date('Y-m-d')?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Additional Instructions</label>
                    <textarea name="task_description" class="form-control form-control-sm" rows="2"></textarea>
                </div>
                <button class="btn btn-primary btn-sm w-100">Deploy Assignment</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="col-md-<?=$_SESSION['user']['role'] === 'worker' ? '12' : '8'?>">
        <div class="card p-4 shadow-sm bg-white border h-100">
            <h5 class="mb-3 border-bottom pb-2"><i class="bi bi-kanban me-2"></i>Active Daily Chores Tracker</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr><th>Task</th><th>Assigned To</th><th>Due Date</th><th>Status</th><th>Operation</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($tasks as $t): ?>
                        <tr>
                            <td>
                                <strong><?=e($t['task_title'])?></strong>
                                <?php if($t['task_description']): ?>
                                <br><small class="text-muted"><?=e($t['task_description'])?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-secondary"><?=e($t['assigned_to'])?></span></td>
                            <td><?=e($t['due_date'])?></td>
                            <td>
                                <span class="badge bg-<?=$t['status']==='Completed'?'success':'warning'?>"><?=e($t['status'])?></span>
                            </td>
                            <td>
                                <?php if($t['status'] === 'Pending'): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="complete_task" value="1">
                                    <input type="hidden" name="task_id" value="<?=(int)$t['id']?>">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <button class="btn btn-sm btn-success py-1 px-2"><i class="bi bi-check-lg"></i> Complete</button>
                                </form>
                                <?php else: ?>
                                <small class="text-muted">Done at <?=e($t['completed_at'])?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; if(empty($tasks)) echo "<tr><td colspan='5' class='text-center text-muted'>No chores allocated for current filters.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>