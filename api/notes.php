<?php

require __DIR__ . '/../includes/bootstrap.php';

const NOTE_MAX_LENGTH = 5000;

ini_set('display_errors', '0');

function json_response(array $data, int $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

set_exception_handler(function (Throwable $e) {
    error_log($e);
    json_response(['error' => 'Something went wrong on the server.'], 500);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_response(['error' => 'Method not allowed.'], 405);
}

if (empty($_SESSION['user']['id'])) {
    json_response(['error' => 'You are not logged in.'], 401);
}
$currentUser = $_SESSION['user'];

$sentToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_string($sentToken) || !hash_equals($_SESSION['csrf_token'], $sentToken)) {
    json_response(['error' => 'Invalid or missing security token. Reload the page and try again.'], 403);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    json_response(['error' => 'Request body must be valid JSON.'], 400);
}

$ticketId = filter_var($data['ticket_id'] ?? null, FILTER_VALIDATE_INT);
$noteBody = $data['body'] ?? '';

if ($ticketId === false || $ticketId < 1) {
    json_response(['error' => 'Invalid ticket.'], 400);
}
if (!is_string($noteBody) || trim($noteBody) === '') {
    json_response(['error' => 'Write something before adding a note.'], 422);
}
$noteBody = trim($noteBody);
if (mb_strlen($noteBody) > NOTE_MAX_LENGTH) {
    json_response(['error' => 'Notes can be at most ' . NOTE_MAX_LENGTH . ' characters.'], 422);
}

$stmt = $pdo->prepare('SELECT id FROM tickets WHERE id = :id');
$stmt->execute([':id' => $ticketId]);
if (!$stmt->fetch()) {
    json_response(['error' => 'Ticket not found.'], 404);
}

$stmt = $pdo->prepare('INSERT INTO notes (ticket_id, body, created_by) VALUES (:ticket, :body, :uid)');
$stmt->execute([
    ':ticket' => $ticketId,
    ':body'   => $noteBody,
    ':uid'    => (int) $currentUser['id'],
]);
$noteId = (int) $pdo->lastInsertId();

$stmt = $pdo->prepare(
    "SELECT n.id, n.body, n.created_at,
            CONCAT(u.firstname, ' ', u.lastname) AS author_name
     FROM notes n
     JOIN users u ON u.id = n.created_by
     WHERE n.id = :id"
);
$stmt->execute([':id' => $noteId]);
$row = $stmt->fetch();

json_response([
    'ok'   => true,
    'note' => [
        'id'          => (int) $row['id'],
        'body'        => $row['body'],
        'author_name' => $row['author_name'],
        'created_at'  => date('j M Y, g:i a', strtotime($row['created_at'])),
    ],
], 201);