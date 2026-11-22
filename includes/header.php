
<?php

$pageTitle = $pageTitle ?? 'Fix-It Desk';
$user = $_SESSION['user'] ?? null;

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?> | Fix-It Desk</title>

    <link rel="stylesheet" href="styles.css">

    <meta name="csrf-token"
          content="<?= e($_SESSION['csrf_token'] ?? '') ?>">

</head>

<body>

<header>

    <h1>Fix-It Desk</h1>

    <nav>

        <a href="index.php">Tickets</a>

        <a href="ticket-new.php">New Ticket</a>

        <?php if ($user && $user['role'] === 'admin'): ?>

            <a href="users.php">Users</a>

        <?php endif; ?>

    </nav>

    <?php if ($user): ?>

        <div>

            Welcome,
            <?= e($user['firstname'] . ' ' . $user['lastname']) ?>

            <form action="logout.php" method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token'] ?? '') ?>"
                >

                <button type="submit">Log out</button>

            </form>

        </div>

    <?php endif; ?>

</header>

<main>
