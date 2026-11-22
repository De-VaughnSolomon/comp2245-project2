<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();
$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'member';
$ticketId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$ticketId || $ticketId < 1) {
    http_response_code(400);
    exit('Invalid ticket ID.');
}
$stmt = $pdo->prepare(
    'SELECT t.*, creator.firstname AS creator_firstname, creator.lastname AS creator_lastname,
            assignee.firstname AS assignee_firstname, assignee.lastname AS assignee_lastname
     FROM tickets t
     JOIN users creator ON creator.id = t.created_by
     LEFT JOIN users assignee ON assignee.id = t.assigned_to
     WHERE t.id = :id'
);
$stmt->execute([':id' => $ticketId]);
$ticket = $stmt->fetch();
if (!$ticket) {
    http_response_code(404);
    exit('Ticket not found.');
}
$stmt = $pdo->prepare(
    'SELECT n.id, n.body, n.created_at, u.firstname, u.lastname
     FROM notes n JOIN users u ON u.id = n.created_by
     WHERE n.ticket_id = :ticket_id ORDER BY n.created_at ASC, n.id ASC'
);
$stmt->execute([':ticket_id' => $ticketId]);
$notes = $stmt->fetchAll();
$canDelete = $role === 'admin' || $userId === (int) $ticket['created_by'];
$assignedToMe = (int) ($ticket['assigned_to'] ?? 0) === $userId;
$pageTitle = 'Ticket Details';
require __DIR__ . '/includes/header.php';
?>
<div class="ticket-actions">
    <a href="index.php" class="ticket-nav back-button">Back to Tickets</a>
    <a href="ticket-edit.php?id=<?= e($ticketId) ?>" class="ticket-nav edit-button">Edit Ticket</a>
</div>
<div class="ticket-details">
    <h2><?= e($ticket['title']) ?></h2>
    <p><strong>Description:</strong></p>
    <p><?= nl2br(e($ticket['description'])) ?></p>
    <p><strong>Priority:</strong> <?= e($ticket['priority']) ?></p>
    <p><strong>Status:</strong>
        <select id="status">
            <?php foreach (['Open', 'In progress', 'Closed'] as $statusOption): ?>
                <option value="<?= e($statusOption) ?>" <?= $ticket['status'] === $statusOption ? 'selected' : '' ?>><?= e($statusOption) ?></option>
            <?php endforeach; ?>
        </select>
        <span id="status-message"></span>
    </p>
    <p><strong>Created By:</strong> <?= e($ticket['creator_firstname'] . ' ' . $ticket['creator_lastname']) ?></p>
    <p><strong>Assigned To:</strong> <span id="assigned-to"><?= $ticket['assignee_firstname'] ? e($ticket['assignee_firstname'] . ' ' . $ticket['assignee_lastname']) : 'Nobody' ?></span></p>
    <?php if (!$assignedToMe): ?><button type="button" id="assign-button">Assign to me</button><?php endif; ?>
    <p><strong>Created:</strong> <?= e($ticket['created_at']) ?></p>
    <p><strong>Last updated:</strong> <span id="updated-at"><?= e($ticket['updated_at']) ?></span></p>
    <hr>
    <h3>Notes</h3>
    <div id="notes-list">
        <?php if ($notes): ?>
            <?php foreach ($notes as $note): ?>
                <div class="note">
                    <p><strong><?= e($note['firstname'] . ' ' . $note['lastname']) ?></strong></p>
                    <p><?= nl2br(e($note['body'])) ?></p>
                    <small><?= e($note['created_at']) ?></small>
                </div>
            <?php endforeach; ?>
        <?php else: ?><p id="no-notes">No notes yet.</p><?php endif; ?>
    </div>
    <h3>Add a Note</h3>
    <form id="note-form">
        <textarea id="note-body" placeholder="Write your note..." required></textarea>
        <button type="submit">Add Note</button>
        <p id="note-message"></p>
    </form>
    <?php if ($canDelete): ?>
        <hr>
        <button type="button" id="delete-button">Delete Ticket</button>
        <div id="delete-confirmation" hidden>
            <p>Are you sure you want to delete this ticket?</p>
            <button type="button" id="confirm-delete">Yes, Delete</button>
            <button type="button" id="cancel-delete">Cancel</button>
        </div>
    <?php endif; ?>
</div>
<script>
const ticketId = <?= json_encode((int) $ticketId) ?>;
const csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
