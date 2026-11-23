
<?php

require __DIR__ . '/../includes/bootstrap.php';

ini_set('display_errors', '0');

function json_response(array $data, int $status = 200): void
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
$userId = (int) $currentUser['id'];

$sentToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (
    !is_string($sentToken) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $sentToken)
) {
    json_response(['error' => 'Invalid or missing security token. Reload the page and try again.'], 403);
}

$body = json_decode(file_get_contents('php://input'), true);

if (!is_array($body)) {
    json_response(['error' => 'Request body must be valid JSON.'], 400);
}

$ticketId = filter_var($body['ticket_id'] ?? null, FILTER_VALIDATE_INT);
$action = $body['action'] ?? '';

if ($ticketId === false || $ticketId === null || $ticketId < 1) {
    json_response(['error' => 'Invalid ticket.'], 400);
}

// Get ticket information
$stmt = $pdo->prepare(
    'SELECT id, created_by FROM tickets WHERE id = :id'
);
$stmt->execute([':id' => $ticketId]);

$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    json_response(['error' => 'Ticket not found.'], 404);
}

if ($action === 'status') {

    $status = $body['status'] ?? '';
    $allowed = ['Open', 'In progress', 'Closed'];

    if (!is_string($status) || !in_array($status, $allowed, true)) {
        json_response(['error' => 'Status must be Open, In progress or Closed.'], 422);
    }

    $stmt = $pdo->prepare(
        'UPDATE tickets SET status = :status, updated_at = NOW() WHERE id = :id'
    );

    $stmt->execute([
        ':status' => $status,
        ':id' => $ticketId
    ]);

} elseif ($action === 'assign') {

    $stmt = $pdo->prepare(
        'UPDATE tickets SET assigned_to = :uid, updated_at = NOW() WHERE id = :id'
    );

    $stmt->execute([
        ':uid' => $userId,
        ':id' => $ticketId
    ]);

} elseif ($action === 'delete') {

    // Only admins or the ticket creator can delete tickets
    if (
        ($currentUser['role'] ?? '') !== 'admin' &&
        $userId !== (int) $ticket['created_by']
    ) {
        json_response(['error' => 'You cannot delete this ticket.'], 403);
    }

    $stmt = $pdo->prepare(
        'DELETE FROM tickets WHERE id = :id'
    );

    $stmt->execute([':id' => $ticketId]);

    json_response([
        'ok' => true,
        'message' => 'Ticket deleted successfully.'
    ]);

} else {

    json_response(['error' => 'Unknown action. Use status, assign or delete.'], 400);
}

// Get updated ticket information
$stmt = $pdo->prepare(
    "SELECT t.status, t.assigned_to, t.updated_at,
            CONCAT(a.firstname, ' ', a.lastname) AS assigned_to_name
     FROM tickets t
     LEFT JOIN users a ON a.id = t.assigned_to
     WHERE t.id = :id"
);

$stmt->execute([':id' => $ticketId]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);

json_response([
    'ok' => true,
    'ticket' => [
        'id' => $ticketId,
        'status' => $row['status'],
        'assigned_to' => $row['assigned_to'] === null ? null : (int) $row['assigned_to'],
        'assigned_to_name' => $row['assigned_to_name'],
        'updated_at' => date('j M Y, g:i a', strtotime($row['updated_at'])),
    ],
]);
