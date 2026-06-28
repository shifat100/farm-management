<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost'; 
$db   = 'goat_farm'; 
$user = 'root'; 
$pass = '';

// cron.php-এর জন্য গোপন কী (অত্যন্ত শক্তিশালী র‍্যান্ডম স্ট্রিং ব্যবহার করো)
define('CRON_SECRET_KEY', 'your_very_strong_random_key_here_12345'); // এটা পরিবর্তন করো

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) { 
    error_log("Database connection failure: " . $e->getMessage());
    die("Database connection failed. Please contact your system administrator."); 
}