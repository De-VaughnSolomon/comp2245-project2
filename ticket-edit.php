<?php
session_start();

// Check login
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
// Get ticket ID
$id = $_GET["id"] ?? "";

if (!ctype_digit((string) $id) || (int) $id < 1) {
    http_response_code(404);
    die("Ticket not found.");
}

$id = (int) $id;

try {
    $pdo = new PDO(
        "mysql:host=localhost;port=3306;dbname=helpdesk;charset=utf8mb4",
        "comp2245",
        "comp2245-demo"
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get existing ticket
    $stmt = $pdo->prepare("
        SELECT * FROM tickets WHERE id = ?
    ");

    $stmt->execute([$id]);

    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        http_response_code(404);
        die("Ticket not found.");
    }

    // Get users for assignment dropdown
    $stmt = $pdo->query("
        SELECT id, firstname, lastname
        FROM users
        ORDER BY firstname, lastname
    ");

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Something went wrong. Please try again later.");
}

// Starting form values
$title = $ticket["title"];
$description = $ticket["description"];
$priority = $ticket["priority"];
$assigned_to = $ticket["assigned_to"] === null
    ? ""
    : (string) $ticket["assigned_to"];

// Error messages
$errors = [
    "title" => "",
    "description" => "",
    "priority" => "",
    "assigned_to" => ""
];

// Process form
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $priority = $_POST["priority"] ?? "";
    $assigned_to = $_POST["assigned_to"] ?? "";

    // Validate title
    if ($title === "") {
        $errors["title"] = "Please enter a title.";
    } elseif (mb_strlen($title) > 150) {
        $errors["title"] = "Title cannot be longer than 150 characters.";
    }

    // Validate description
    if ($description === "") {
        $errors["description"] = "Please enter a description.";
    }

    // Validate priority
    $allowed_priorities = ["Low", "Medium", "High"];

    if (!in_array($priority, $allowed_priorities, true)) {
        $errors["priority"] = "Please select a valid priority.";
    }

    // Validate assigned user
    $assigned_id = null;

    if ($assigned_to !== "") {

        if (!ctype_digit($assigned_to)) {
            $errors["assigned_to"] = "Please select a valid user.";
        } else {

            $stmt = $pdo->prepare("
                SELECT id FROM users WHERE id = ?
            ");

            $stmt->execute([$assigned_to]);

            if (!$stmt->fetch()) {
                $errors["assigned_to"] = "The selected user does not exist.";
            } else {
                $assigned_id = (int) $assigned_to;
            }
        }
    }

    // Update ticket if there are no errors
    if (!in_array(true, array_map(
        function ($error) {
            return $error !== "";
        },
        $errors
    ), true)) {

        $stmt = $pdo->prepare("
            UPDATE tickets
            SET title = ?,
                description = ?,
                priority = ?,
                assigned_to = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $title,
            $description,
            $priority,
            $assigned_id,
            $id
        ]);

        header("Location: ticket.php?id=" . $id);
        exit;
    }
    function  h($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}
require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">

<main>

    <div class="form-container">

        <h2>Edit Ticket #<?php echo $id; ?></h2>

        <form method="POST" action="ticket-edit.php?id=<?php echo $id; ?>">

            <div class="field">
                <label for="title">Title</label>

                <input
                    type="text"
                    id="title"
                    name="title"
                    maxlength="150"
                    value="<?php echo h($title); ?>"
                >

                <?php if ($errors["title"] !== ""): ?>
                    <p class="error"><?php echo h($errors["title"]); ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="description">Description</label>

                <textarea
                    id="description"
                    name="description"
                ><?php echo h($description); ?></textarea>

                <?php if ($errors["description"] !== ""): ?>
                    <p class="error"><?php echo h($errors["description"]); ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="priority">Priority</label>

                <select id="priority" name="priority">

                    <option value="Low" <?php if ($priority === "Low") echo "selected"; ?>>
                        Low
                    </option>

                    <option value="Medium" <?php if ($priority === "Medium") echo "selected"; ?>>
                        Medium
                    </option>

                    <option value="High" <?php if ($priority === "High") echo "selected"; ?>>
                        High
                    </option>

                </select>

                <?php if ($errors["priority"] !== ""): ?>
                    <p class="error"><?php echo h($errors["priority"]); ?></p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="assigned_to">Assign To (Optional)</label>

                <select id="assigned_to" name="assigned_to">

                    <option value="">Nobody</option>

                    <?php foreach ($users as $user): ?>

                        <option
                            value="<?php echo $user["id"]; ?>"
                            <?php if ($assigned_to === (string) $user["id"]) echo "selected"; ?>
                        >
                            <?php echo h($user["firstname"] . " " . $user["lastname"]); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <?php if ($errors["assigned_to"] !== ""): ?>
                    <p class="error"><?php echo h($errors["assigned_to"]); ?></p>
                <?php endif; ?>
            </div>

            <div class="buttons">

                <button type="submit">Save Changes</button>

                <a href="ticket.php?id=<?php echo $id; ?>" class="cancel">
                    Cancel
                </a>

            </div>

        </form>

    </div>

</main>

</body>
<?php include 'includes/footer.php'; ?>
</html>