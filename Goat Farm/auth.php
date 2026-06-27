<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect unauthenticated clients directly back to secure sign-on
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}