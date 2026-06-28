<?php
require_once 'config.php';
require_once 'auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation (AJAX-এর জন্যও)
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid security token.']);
        exit;
    }

    if (isset($_POST['mark_read'])) {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE alerts SET is_read = 1 WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    if (isset($_POST['mark_all'])) {
        $pdo->query("UPDATE alerts SET is_read = 1 WHERE is_read = 0");
        echo json_encode(['success' => true]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Unsupported action endpoint request.']);