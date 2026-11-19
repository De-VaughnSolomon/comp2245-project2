<?php

require __DIR__ ."/../includes/bootstrap.php";
require __DIR__ ."/../includes/api.php";

api_require_method('GET');
$currentUser = requireLogin();
$filter = $_GET['filter'] ?? 'all';

$sql = "SELECT t.id, t.title, t.priority, t.status, t.created_At,
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
        $params = [':uid'] = (int) $currentUser['id'];
        break;
    default:
    json_response(['error'=> 'Unknown filter. Use all, open or mine.'], 400);
}

$sql .= ' ORDER BY t.created_at DESC, t.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$tickets = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $tickets[] = [
        'id'=> (int) $row['id'],
        'title'=> $row['title'],
        'priority'=> $row['priority'],
        'status'=> $row['status'],
        'created_by_name'=> $row['created_by_name'],
        'assigned_to_name'=> $row['assigned_to_name'],
        'created_at'=> format_dt($row['created_at']),
    ];
}

json_response([
    'filter'=> $filter,
    'count'=> count($tickets),
    'tickets'=> $tickets,
    ]);