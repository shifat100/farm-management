<?php
require_once 'config.php';

if (isset($_SESSION['user'])) {
    header('Location: index.php?page=dashboard');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username !== '' && $password !== '') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user;
            header('Location: index.php?page=dashboard');
            exit;
        } else {
            $error = "Incorrect credentials supplied.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Goat Farm Manager Pro - Secure Sign-On</title>
    <!-- FIX: Swap MDB UI CSS with standard Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
<div class="container" style="max-width:400px">
    <div class="card shadow-lg p-3">
        <div class="card-body text-center">
            <i class="bi bi-goat fs-1 text-warning"></i>
            <h4 class="mt-2 text-uppercase font-weight-bold">Farm Manager Pro</h4>
            <p class="small text-muted mb-4">Secure Sign-On Portal</p>
            
            <form method="post">
                <div class="mb-3">
                    <input name="username" class="form-control" placeholder="System Username" required autocomplete="off">
                </div>
                <div class="mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Secured Password Key" required>
                </div>
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger py-2 small"><?=$error?></div>
                <?php endif; ?>
                <button class="btn btn-primary w-100 shadow-0">Verify Identity</button>
            </form>
            <div class="mt-4 border-top pt-2">
                <small class="text-muted d-block">Developer default access: admin / password</small>
            </div>
        </div>
    </div>
</div>
</body>
</html>