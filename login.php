<?php

require_once __DIR__ . '/includes/bootstrap.php';

$error = "";

if (isset($_SESSION['user_id'])) {
    // User is already logged in, redirect to ticket list
    header('Location: index.php');
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

      
           if ($email !== "" && $password !== "") {

            // Find the user by email
            $sql = "SELECT id, firstname, lastname, email, password, role
                    FROM users WHERE email = ?";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);

            $user = $stmt->fetch();

            // Verify the password
            if ($user && password_verify($password, $user["password"])) {

                // Regenerate session ID
                session_regenerate_id(true);

                // Store user information in session
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["firstname"] = $user["firstname"];
                $_SESSION["lastname"] = $user["lastname"];
                $_SESSION["email"] = $user["email"];
                $_SESSION["role"] = $user["role"];
                $_SESSION["user"] = [
                    "id" => (int) $user["id"],
                    "firstname" => $user["firstname"],
                    "lastname" => $user["lastname"],
                    "email" => $user["email"],
                    "role" => $user["role"],
                ];

                // Go to ticket list
                header("Location: index.php");
                exit;

            } else {
                $error = "That email and password don't match an account.";
            }

        } else {
            $error = "That email and password don't match an account.";
        }
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = "Something went wrong. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <link rel="stylesheet" href="styles.css">
    <title>Login</title>
</head>
<body>
    <h1>Fix-It Desk</h1>
    <h2>Log in to see and update help desk tickets</h2>
     <?php if ($error !== ""): ?>
            <div class="error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <div class="login-form">
        <form method="POST" action="login.php">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
            <br>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
            <br>
            <button type="submit">Log In</button>
        </form>
        </div>

    
</body>
</html>