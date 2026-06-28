<?php
if ($_SESSION['user']['role'] !== 'admin') { 
    die("<div class='alert alert-danger'>Administrative credentials required for resource pathway.</div>"); 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die("<div class='alert alert-danger'>Invalid security token. Please refresh the page and try again.</div>");
    }

    if (isset($_POST['add'])) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'worker';
        
        if ($username !== '' && $password !== '') {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            
            $stmt_check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt_check->execute([$username]);
            if ($stmt_check->fetch()) {
                echo "<div class='alert alert-danger'>User already exists.</div>";
            } else {
                $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)")->execute([$username, $hash, $role]);
                echo "<div class='alert alert-success'>Credentials deployed successfully.</div>";
            }
        }
    } elseif (isset($_POST['delete'])) {
        $targetId = (int)$_POST['id'];
        if ($targetId !== (int)$_SESSION['user']['id']) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$targetId]);
            echo "<div class='alert alert-success'>Target cleared from logs.</div>";
        }
    }
}

$users = $pdo->query("SELECT id, username, role, created_at FROM users")->fetchAll();
?>

<h4 class="mb-3">System Identity and Authentication Log</h4>

<div class="card p-3 shadow-sm bg-white mb-4">
    <h6>Deploy Access Key Credentials</h6>
    <form method="post" class="row g-3 align-items-end">
        <input type="hidden" name="add" value="1">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        <div class="col-md-3">
            <label class="form-label small">Username</label>
            <input name="username" class="form-control" placeholder="Target Alias" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Password Key</label>
            <input type="password" name="password" class="form-control" placeholder="Secured Password Wrapper" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small">RBAC Allocation Role</label>
            <select name="role" class="form-select">
                <option>worker</option>
                <option>manager</option>
                <option>admin</option>
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn btn-primary w-100"><i class="bi bi-person-plus"></i> Deploy</button>
        </div>
    </form>
</div>

<div class="table-responsive bg-white rounded shadow-sm p-3">
    <table class="table table-hover align-middle">
        <thead class="table-dark">
            <tr><th>Identifier Profile</th><th>Allocated Role</th><th>Created On</th><th>Operations Action</th></tr>
        </thead>
        <tbody>
            <?php foreach($users as $u): ?>
            <tr>
                <td><?=e($u['username'])?></td>
                <td><span class="badge bg-secondary"><?=e($u['role'])?></span></td>
                <td><?=e($u['created_at'])?></td>
                <td>
                    <?php if($u['id'] !== $_SESSION['user']['id']): ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="delete" value="1">
                        <input type="hidden" name="id" value="<?=$u['id']?>">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <button class="btn btn-sm btn-danger" onclick="return confirm('Revoke credential accesses permanently?')"><i class="bi bi-trash"></i> Revoke</button>
                    </form>
                    <?php else: ?>
                    <span class="small text-muted text-italic">Active credential</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>