<?php
require __DIR__ ."/../includes/bootstrap.php";
require __DIR__ ."/../includes/api.php";

const NOTE_MAX_LENGTH = 5000;

api_require_method('POST');
$currentUser = requireLogin();
api_require_csrf();

$data = read_json_body();
$ticketId = filter_var($data['ticket_id'] ?? null, FILTER_VALIDATE_INT);
$noteBody = $data['body'] ?? '';

if ($ticketId === false || trim($noteBody) === '') {
    json_response(['error'=> 'Write something before adding a note.'], 422);
}
$noteBody = trim($noteBody);
if (mb_strlen($noteBody) > NOTE_MAX_LENGTH) {
    json_response(['error'=> 'Notes can be at most ' . NOTE_MAX_LENGTH . ' characters.'], 422);
}

$stmt = $pdo->prepare('SELECT id FROM tickets WHERE id = :id');
$stmt->execute([':id'=> $ticketId]);
if (!$stmt->fetch()) {
    json_response(['error'=> 'Ticket not found.'], 404);
}

$stmt = $pdo->prepare('INSERT INTO notes (ticket_id, body, created_by) VALUES (:ticket, :body, :uid)');
$stmt->execute([
    ':ticket'=> $ticketId,
    ':body'=> $noteBody,
    ':uid'=> (int) $currentUser['id'],
]);$noteId = (int) $pdo->lastInsertId();

$stmt = $pdo->prepare(
    "SELECT n.id, n.body, n.created_at,
        CONCAT(u.firstname, ' ', u.lastname) AS author_name
    FROM notes n
    JOIN users u ON u.id = n.created_by
    WHERE n.id = :id"
);
$stmt->execute([':id'=> $noteId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

json_response([
    'ok'=> true,
    'note'=>[
        'id'=> (int) $row['id'],
        'body'=> $row['body'],
        'author_name'=> $row['author_name'],
        'created_at'=> format_dt($row['created_at']),
    ]
], 201);