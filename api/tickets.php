<?php

require __DIR__ . '/../includes/bootstrap.php';

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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    json_response(['error' => 'Method not allowed.'], 405);
}

if (empty($_SESSION['user']['id'])) {
    json_response(['error' => 'You are not logged in.'], 401);
}
$currentUser = $_SESSION['user'];

$filter = $_GET['filter'] ?? 'all';

$sql = "SELECT t.id, t.title, t.priority, t.status, t.created_at,
               CONCAT(c.firstname, ' ', c.lastname) AS created_by_name,
               CONCAT(a.firstname, ' ', a.lastname) AS assigned_to_name
        FROM tickets t
        JOIN users c ON c.id = t.created_by
        LEFT JOIN users a ON a.id = t.assigned_to";
$params = [];

switch ($filter) {
    case 'all':
        break;
    case 'open':
        $sql .= " WHERE t.status <> 'Closed'";
        break;
    case 'mine':
        $sql .= " WHERE t.assigned_to = :uid";
        $params[':uid'] = (int) $currentUser['id'];
        break;
    default:
        json_response(['error' => 'Unknown filter. Use all, open or mine.'], 400);
}

$sql .= " ORDER BY t.created_at DESC, t.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$tickets = [];
foreach ($stmt->fetchAll() as $row) {
    $tickets[] = [
        'id'=> (int) $row['id'],
        'title'=> $row['title'],
        'priority'=> $row['priority'],
        'status'=> $row['status'],
        'created_by_name'=> $row['created_by_name'],
        'assigned_to_name'=> $row['assigned_to_name'], 
        'created_at'=> date('j M Y, g:i a', strtotime($row['created_at'])),
    ];
}

json_response([
    'filter'=> $filter,
    'count'=> count($tickets),
    'tickets'=> $tickets,
]);