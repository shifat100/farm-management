<?php
require_once 'config.php';
require_once 'auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Dismiss specific alert item safely [1]
    if (isset($_POST['mark_read'])) {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE alerts SET is_read = 1 WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Dismiss all unread notifications globally [1]
    if (isset($_POST['mark_all'])) {
        $pdo->query("UPDATE alerts SET is_read = 1 WHERE is_read = 0");
        echo json_encode(['success' => true]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Unsupported action endpoint request.']);