
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
// Escape output safely
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
$user_id = (int) $_SESSION['user_id'];
$firstname = $_SESSION['firstname'];
$lastname = $_SESSION['lastname'];

// AJAX request for tickets
if (isset($_GET['ajax']) && $_GET['ajax'] === 'tickets') {

    header('Content-Type: application/json');

    try {
        $pdo = new PDO(
            "mysql:host=localhost;port=3306;dbname=helpdesk;charset=utf8mb4",
            "comp2245",
            "comp2245-demo"
        );

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $filter = $_GET['filter'] ?? 'all';

        $sql = "
            SELECT
                t.id,
                t.title,
                t.priority,
                t.status,
                t.created_at,
                creator.firstname AS creator_firstname,
                creator.lastname AS creator_lastname,
                assignee.firstname AS assignee_firstname,
                assignee.lastname AS assignee_lastname
            FROM tickets t
            INNER JOIN users creator
                ON t.created_by = creator.id
            LEFT JOIN users assignee
                ON t.assigned_to = assignee.id
        ";

        if ($filter === 'open') {
            $sql .= " WHERE t.status != 'Closed'";
        } elseif ($filter === 'mine') {
            $sql .= " WHERE t.assigned_to = :user_id";
        } elseif ($filter !== 'all') {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid filter.']);
            exit;
        }

        $sql .= " ORDER BY t.created_at DESC";

        $stmt = $pdo->prepare($sql);

        if ($filter === 'mine') {
            $stmt->execute(['user_id' => $user_id]);
        } else {
            $stmt->execute();
        }

        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

    } catch (PDOException $e) {
        error_log($e->getMessage());
        http_response_code(500);
        echo json_encode(['message' => 'Could not load tickets.']);
    }

    exit;

}

require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket List</title>
    <link rel="stylesheet" href="styles.css">
    <script src="app.js" defer></script>
</head>

<body>

    <a href="logout.php">Logout</a>
    <a href="users.php">Users</a>

    <div class="ticket-list">
        <h3>Tickets</h3>

        <div class="Newticket">
            <a href="ticket-new.php">New Ticket</a>
        </div>
    </div>

    <div class="filters">

        <button type="button" class="active" data-filter="all">
            All
        </button>

        <button type="button" data-filter="open">
            Open
        </button>

        <button type="button" data-filter="mine">
            My Tickets
        </button>
    <?php if (isset($_GET['deleted']) && $_GET['deleted'] === '1'): ?>
        <p class="success-message">
            Ticket was successfully deleted.
        </p>
    <?php endif; ?>
    </div>

    <p id="ticketcount">Loading tickets...</p>

    <div class="table content">

        <table>
            <thead>
                <tr>
                    <th>Ticket #</th>
                    <th>Title</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Assigned To</th>
                    <th>Created</th>
                </tr>
            </thead>

            <tbody id="ticket-list">
                <tr>
                    <td colspan="7" class="message">
                        Loading tickets...
                    </td>
                </tr>
            </tbody>

        </table>

    </div>

</body>
<?php include 'includes/footer.php'; ?>
</html>
