<?php
session_start();

// Check login
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=localhost;port=3306;dbname=helpdesk;charset=utf8mb4",
        "comp2245",
        "comp2245-demo"
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get users for the assignment dropdown
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

// Form values
$title = "";
$description = "";
$priority = "Medium";
$assigned_to = "";

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

    // Save ticket if there are no errors
    if (!in_array(true, array_map(
        function ($error) {
            return $error !== "";
        },
        $errors
    ), true)) {

        $stmt = $pdo->prepare("
            INSERT INTO tickets
            (title, description, priority, created_by, assigned_to)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $title,
            $description,
            $priority,
            $_SESSION["user_id"],
            $assigned_id
        ]);

        $ticket_id = $pdo->lastInsertId();

        header("Location: ticket.php?id=" . $ticket_id);
        exit;
    }
}

// Escape output
function h($value) {
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <main>

    <div class="form-container">

        <h2>Create a Ticket</h2>

        <form method="POST" action="ticket-new.php">

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

                <button type="submit">Create Ticket</button>

                <a href="index.php" class="cancel">Cancel</a>

            </div>

        </form>

    </div>

</main>
</body>
</html>