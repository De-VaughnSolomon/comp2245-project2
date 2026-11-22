
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
$user_id = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'member';

// Create CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {

    $pdo = new PDO(
        "mysql:host=localhost;port=3306;dbname=helpdesk;charset=utf8mb4",
        "comp2245",
        "comp2245-demo"
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {

    error_log($e->getMessage());
    http_response_code(500);
    exit('Database connection failed.');

}

// AJAX REQUESTS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json');

    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid request.']);
        exit;
    }

    if (
        !isset($data['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $data['csrf_token'])
    ) {
        http_response_code(403);
        echo json_encode(['message' => 'Invalid security token.']);
        exit;
    }

    $ticket_id = filter_var(
        $data['ticket_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$ticket_id || $ticket_id < 1) {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid ticket ID.']);
        exit;
    }

    $action = $data['action'] ?? '';

    try {

        // Find ticket
        $stmt = $pdo->prepare("
            SELECT id, created_by, assigned_to
            FROM tickets
            WHERE id = :id
        ");

        $stmt->execute(['id' => $ticket_id]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            http_response_code(404);
            echo json_encode(['message' => 'Ticket not found.']);
            exit;
        }

        // ADD NOTE
        if ($action === 'note') {

            $body = trim($data['body'] ?? '');

            if ($body === '') {
                http_response_code(400);
                echo json_encode([
                    'message' => 'Please enter a note.'
                ]);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO notes
                    (ticket_id, body, created_by, created_at)
                VALUES
                    (:ticket_id, :body, :user_id, NOW())
            ");

            $stmt->execute([
                'ticket_id' => $ticket_id,
                'body' => $body,
                'user_id' => $user_id
            ]);

            $note_id = $pdo->lastInsertId();

            // Get note and author
            $stmt = $pdo->prepare("
                SELECT
                    n.id,
                    n.body,
                    n.created_at,
                    u.firstname,
                    u.lastname
                FROM notes n
                INNER JOIN users u ON n.created_by = u.id
                WHERE n.id = :id
            ");

            $stmt->execute(['id' => $note_id]);

            echo json_encode([
                'message' => 'Note added.',
                'note' => $stmt->fetch(PDO::FETCH_ASSOC)
            ]);

            exit;
        }

        // CHANGE STATUS
        if ($action === 'status') {

            $status = $data['status'] ?? '';

            $allowed_statuses = [
                'Open',
                'In progress',
                'Closed'
            ];

            if (!in_array($status, $allowed_statuses, true)) {
                http_response_code(400);
                echo json_encode([
                    'message' => 'Invalid status.'
                ]);
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE tickets
                SET status = :status, updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                'status' => $status,
                'id' => $ticket_id
            ]);

            $stmt = $pdo->prepare("
                SELECT updated_at
                FROM tickets
                WHERE id = :id
            ");

            $stmt->execute(['id' => $ticket_id]);

            echo json_encode([
                'message' => 'Status updated.',
                'status' => $status,
                'updated_at' => $stmt->fetchColumn()
            ]);

            exit;
        }

        // ASSIGN TO ME
        if ($action === 'assign') {

            $stmt = $pdo->prepare("
                UPDATE tickets
                SET assigned_to = :user_id, updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                'user_id' => $user_id,
                'id' => $ticket_id
            ]);

            $stmt = $pdo->prepare("
                SELECT
                    u.firstname,
                    u.lastname,
                    t.updated_at
                FROM tickets t
                INNER JOIN users u ON t.assigned_to = u.id
                WHERE t.id = :id
            ");

            $stmt->execute(['id' => $ticket_id]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'message' => 'Ticket assigned.',
                'assigned_to' => $result['firstname'] . ' ' .
                    $result['lastname'],
                'updated_at' => $result['updated_at']
            ]);

            exit;
        }

        // DELETE TICKET
        if ($action === 'delete') {

            // Only admin or creator can delete
            if (
                $role !== 'admin' &&
                $user_id !== (int) $ticket['created_by']
            ) {
                http_response_code(403);
                echo json_encode([
                    'message' => 'You cannot delete this ticket.'
                ]);
                exit;
            }

            $pdo->beginTransaction();

            // Delete ticket notes first
            $stmt = $pdo->prepare("
                DELETE FROM notes
                WHERE ticket_id = :ticket_id
            ");

            $stmt->execute(['ticket_id' => $ticket_id]);

            // Delete ticket
            $stmt = $pdo->prepare("
                DELETE FROM tickets
                WHERE id = :id
            ");

            $stmt->execute(['id' => $ticket_id]);

            $pdo->commit();

            echo json_encode([
                'message' => 'Ticket deleted.'
            ]);

            exit;
        }

        http_response_code(400);

        echo json_encode([
            'message' => 'Invalid action.'
        ]);

    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log($e->getMessage());

        http_response_code(500);

        echo json_encode([
            'message' => 'Something went wrong.'
        ]);
    }

    exit;
}

// GET TICKET DETAILS
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(400);
    exit('Invalid ticket ID.');
}

$ticket_id = (int) $_GET['id'];

try {

    // Get ticket and user names
    $stmt = $pdo->prepare("
        SELECT
            t.*,
            creator.firstname AS creator_firstname,
            creator.lastname AS creator_lastname,
            assignee.firstname AS assignee_firstname,
            assignee.lastname AS assignee_lastname
        FROM tickets t
        INNER JOIN users creator
            ON t.created_by = creator.id
        LEFT JOIN users assignee
            ON t.assigned_to = assignee.id
        WHERE t.id = :id
    ");

    $stmt->execute(['id' => $ticket_id]);

    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        http_response_code(404);
        exit('Ticket not found.');
    }

    // Get notes and their authors
    $stmt = $pdo->prepare("
        SELECT
            n.id,
            n.body,
            n.created_at,
            u.firstname,
            u.lastname
        FROM notes n
        INNER JOIN users u
            ON n.created_by = u.id
        WHERE n.ticket_id = :ticket_id
        ORDER BY n.created_at ASC, n.id ASC
    ");

    $stmt->execute(['ticket_id' => $ticket_id]);

    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check permissions
    $can_delete =
        $role === 'admin' ||
        $user_id === (int) $ticket['created_by'];

    $assigned_to_me =
        (int) $ticket['assigned_to'] === $user_id;

} catch (PDOException $e) {

    error_log($e->getMessage());
    http_response_code(500);
    exit('Could not load ticket.');

}

function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ticket Details</title>

    <link rel="stylesheet" href="styles.css">

    <script src="app.js" defer></script>
</head>

<body>

    <a href="index.php">Back to Tickets</a>

    <main class="ticket-details">

        <h2><?php echo escape($ticket['title']); ?></h2>

        <p>
            <strong>Description:</strong><br>
            <?php echo nl2br(escape($ticket['description'])); ?>
        </p>

        <p>
            <strong>Priority:</strong>
            <?php echo escape($ticket['priority']); ?>
        </p>

        <p>
            <strong>Status:</strong>

            <select id="status">

                <option value="Open"
                    <?php echo $ticket['status'] === 'Open' ? 'selected' : ''; ?>>
                    Open
                </option>

                <option value="In progress"
                    <?php echo $ticket['status'] === 'In progress' ? 'selected' : ''; ?>>
                    In progress
                </option>

                <option value="Closed"
                    <?php echo $ticket['status'] === 'Closed' ? 'selected' : ''; ?>>
                    Closed
                </option>

            </select>

            <span id="status-message"></span>
        </p>

        <p>
            <strong>Created By:</strong>

            <?php echo escape(
                $ticket['creator_firstname'] . ' ' .
                $ticket['creator_lastname']
            ); ?>
        </p>

        <p>
            <strong>Assigned To:</strong>

            <span id="assigned-to">

                <?php
                echo $ticket['assignee_firstname']
                    ? escape(
                        $ticket['assignee_firstname'] . ' ' .
                        $ticket['assignee_lastname']
                    )
                    : 'Nobody';
                ?>

            </span>
        </p>

        <?php if (!$assigned_to_me): ?>

            <button type="button" id="assign-button">
                Assign to me
            </button>

        <?php endif; ?>

        <p>
            <strong>Created:</strong>

            <?php echo escape($ticket['created_at']); ?>
        </p>

        <p>
            <strong>Last updated:</strong>

            <span id="updated-at">
                <?php echo escape($ticket['updated_at']); ?>
            </span>
        </p>

        <hr>

        <h3>Notes</h3>

        <div id="notes-list">

            <?php if (count($notes) > 0): ?>

                <?php foreach ($notes as $note): ?>

                    <div class="note">

                        <p>
                            <strong>
                                <?php echo escape(
                                    $note['firstname'] . ' ' .
                                    $note['lastname']
                                ); ?>
                            </strong>
                        </p>

                        <p><?php echo nl2br(escape($note['body'])); ?></p>

                        <small>
                            <?php echo escape($note['created_at']); ?>
                        </small>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p id="no-notes">No notes yet.</p>

            <?php endif; ?>

        </div>

        <h3>Add a Note</h3>

        <form id="note-form">

            <textarea
                id="note-body"
                placeholder="Write your note..."
                required
            ></textarea>

            <button type="submit">Add Note</button>

            <p id="note-message"></p>

        </form>

        <?php if ($can_delete): ?>

            <hr>

            <button type="button" id="delete-button">
                Delete Ticket
            </button>

            <div id="delete-confirmation" hidden>

                <p>Are you sure you want to delete this ticket?</p>

                <button type="button" id="confirm-delete">
                    Yes, Delete
                </button>

                <button type="button" id="cancel-delete">
                    Cancel
                </button>

            </div>

        <?php endif; ?>

    </main>

    <script>
        const ticketId = <?php echo json_encode($ticket_id); ?>;
        const csrfToken = <?php echo json_encode($_SESSION['csrf_token']); ?>;
    </script>

</body>
<?php include 'includes/footer.php'; ?>
</html>
