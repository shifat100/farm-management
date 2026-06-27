<?php
require_once 'config.php';
require_once 'auth.php';

if ($_SESSION['user']['role'] !== 'admin') {
    die("Access denied.");
}

// Dynamic Backup Exporter
if (isset($_GET['download'])) {
    ob_clean();
    $tables = [];
    $result = $pdo->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    $sql_dump = "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $sql_dump .= "\n\n" . $row[1] . ";\n\n";
        
        $stmt_data = $pdo->query("SELECT * FROM `{$table}`");
        while ($data = $stmt_data->fetch(PDO::FETCH_ASSOC)) {
            $columns = array_map(function($col) { return "`{$col}`"; }, array_keys($data));
            $values = array_map(function($val) use ($pdo) {
                if ($val === null) return "NULL";
                return $pdo->quote($val);
            }, array_values($data));
            
            $sql_dump .= "INSERT INTO `{$table}` (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ");\n";
        }
    }
    
    $sql_dump .= "\n\nSET FOREIGN_KEY_CHECKS=1;";
    
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename=Database_Backup_' . date('Y-m-d_H-i-s') . '.sql');
    echo $sql_dump;
    exit;
}
?>

<div class="card p-4 shadow-sm bg-white text-center">
    <div class="py-4">
        <i class="bi bi-cloud-arrow-down-fill text-primary fs-1"></i>
        <h4 class="mt-3">One-Click Database Backup</h4>
        <p class="text-muted">Ensure farm continuity. Download a complete SQL archive of your management configuration and financial data to local offline storage.</p>
        <a href="?page=backup&download=1" class="btn btn-primary px-4 py-2"><i class="bi bi-download me-2"></i>Download SQL Database Dump</a>
    </div>
</div>