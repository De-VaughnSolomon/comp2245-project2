
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Invalid request method.");
}

// Validate CSRF token
$submitted_token = $_POST['csrf_token'] ?? '';
$session_token = $_SESSION['csrf_token'] ?? '';

if (
    $session_token === '' ||
    $submitted_token === '' ||
    !hash_equals($session_token, $submitted_token)
) {
    http_response_code(403);
    exit("Invalid CSRF token.");
}

// Clear session variables
$_SESSION = [];

// Delete session cookie
if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );

}

// Destroy session
session_destroy();

// Redirect to login
header("Location: login.php");
exit;

?>
