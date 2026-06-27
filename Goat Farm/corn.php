<?php
require_once 'config.php';
require_once 'functions.php';

// Safe authentication key protecting automated tasks from unauthorized invocation
$cron_key = 'farm_secret_cron_key_123';

if (($argv[1] ?? '') !== $cron_key && ($_GET['key'] ?? '') !== $cron_key) {
    http_response_code(403);
    die("Forbidden: Access key validation failed.");
}

try {
    runAutomation($pdo);
    echo "Task pipeline executions processed successfully.";
} catch (Exception $e) {
    error_log("Automation pipeline error: " . $e->getMessage());
    echo "Execution failed during task pipeline processing.";
}