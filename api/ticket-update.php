<?php
require __DIR__ ."/../includes/bootstrap.php";
require __DIR__ ."/../includes/api.php";

api_require_method('POST');
$currentUser = requireLogin();
api_require_csrf();

$body = read_json_body();
$ticketId = filter_var($body['ticket_id'] ?? null, FILTER_VALIDATE_INT);
$action = $body['action'] ?? '';

if ($ticketId == false || $ticketId < 1) {
    json_response(['error' => 'Invalid ticket.'], 400);
}

$stmt = $pdo->prepare('SELECT id FROM tickets WHERE id = :id');
$stmt->execute([':id'=> $ticketId]);
if (!$stmt->fetch()) {
    json_response(['error'=> 'Ticket not found.'], 404);
}

if ($action == 'status') {
    $status = $body['status'] ??'';
    $allowed = ['Open', 'In Progress', 'Closed'];
    if (!is_string($status) || !in_array($status, $allowed, true)) {
        json_response(['error'=> 'Status must be Open, In progess or Closed.'], 422);
    }
    $stmt = $pdo->prepare('UPDATE tickets SET status = :status WHERE id = :id');
    $stmt->execute([':status'=> $status, ':id' => $ticketId]);
} else if ($action == 'assign') {
    $stmt = $pdo->prepare('UPDATE tickets SET assigned_to = :uid WHERE id = :id');
    $stmt->execute([':uid' => (int) $currentUser['id'],'id'=> $ticketId]);
} else {
    json_response(['error'=> 'Unknown action. Use status or assign.'],400);
}

$stmt = $pdo->prepare(
    "SELECT t.status, t.assigned_to, t.updated_at,
        CONCAT(a.firstname, ' ', a.lastname) AS assigned_to_name
    FROM tickets t
    LEFT JOIN users a ON a.id = t.assigned_to
    WHERE t.id = :id"
);
$stmt->execute([":id"=> $ticketId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

json_response([
    'ok'=> true,
    'ticket'=>[
        'id'=> $ticketId,
        'status'=> $row['status'],
        'assigned_to'=> $row['assigned_to'] === null ? null : (int) $row['assigned_to'],
        'assigned_to_name'=> $row['assigned_to_name'],
        'updated_at'=> format_dt($row['updated_at']),
    ],
]);