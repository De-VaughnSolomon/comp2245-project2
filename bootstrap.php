<?php

// Load the database configuration
$config = require __DIR__ . '/../config.php';

// Start the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Connect to the database
try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['username'],
        $config['password']
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    exit("Database connection failed.");
}

// Safely display text on pages
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Create a CSRF token for forms
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check whether a user is logged in
function requireLogin() {
    if (empty($_SESSION['user'])) {
        header("Location: login.php");
        exit;
    }
}

// Check whether the logged-in user is an administrator
function requireAdmin() {
    requireLogin();

    if ($_SESSION['user']['role'] !== 'admin') {
        http_response_code(403);
        exit("Access denied.");
    }
}
