
<?php

// Start the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load database configuration
$config = require __DIR__ . '/../config.php';

// Connect to the database
try {

    $pdo = new PDO(
        "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

} catch (PDOException $e) {

    error_log($e->getMessage());
    exit("Database connection failed.");

}

// Escape output safely
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Create CSRF token if one does not exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Keep the session user shape consistent for pages and API endpoints.
if (isset($_SESSION['user_id']) && empty($_SESSION['user'])) {
    $_SESSION['user'] = [
        'id' => (int) $_SESSION['user_id'],
        'firstname' => $_SESSION['firstname'] ?? '',
        'lastname' => $_SESSION['lastname'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? 'member',
    ];
}

// Check whether user is logged in
function requireLogin() {

    if (empty($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }

}

// Check whether user is an administrator
function requireAdmin() {

    requireLogin();

    if (
        !isset($_SESSION['role']) ||
        $_SESSION['role'] !== 'admin'
    ) {

        http_response_code(403);
        exit("Access denied. Admins only.");

    }

}

?>
