<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireAdmin();

$error = "";
$success = "";

$firstname = "";
$lastname = "";
$email = "";
$role = "member";

try {
    // Add a new user
    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        // Check CSRF token
        $csrf_token = $_POST["csrf_token"] ?? "";

        if (!verifyCsrfToken($csrf_token)) {
            $error = "Invalid security token. Please try again.";
        } else {

            $firstname = trim($_POST["firstname"] ?? "");
            $lastname = trim($_POST["lastname"] ?? "");
            $email = trim($_POST["email"] ?? "");
            $password = $_POST["password"] ?? "";
            $role = $_POST["role"] ?? "";

            // All fields are required
            if (
                $firstname === "" ||
                $lastname === "" ||
                $email === "" ||
                $password === "" ||
                $role === ""
            ) {
                $error = "Please complete all fields.";

            // Check email format
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please enter a valid email address.";

            // Check password length, letter and number
            } elseif (
                strlen($password) < 8 ||
                !preg_match("/[A-Za-z]/", $password) ||
                !preg_match("/[0-9]/", $password)
            ) {
                $error = "Password must be at least 8 characters and contain at least one letter and one number.";

            // Check role
            } elseif (!in_array($role, ["admin", "member"], true)) {
                $error = "Please select a valid role.";

            } else {

                // Check if email already exists
                $stmt = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE email = ?
                ");

                $stmt->execute([$email]);

                if ($stmt->fetch()) {
                    $error = "That email address is already being used.";

                } else {

                    // Hash the password
                    $password_hash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    // Insert the new user
                    $stmt = $pdo->prepare("
                        INSERT INTO users
                            (firstname, lastname, email, password, role)
                        VALUES (?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $firstname,
                        $lastname,
                        $email,
                        $password_hash,
                        $role
                    ]);

                    // Clear form values
                    $firstname = "";
                    $lastname = "";
                    $email = "";
                    $role = "member";

                    $success = "User added successfully.";
                }
            }
        }
    }

    // Get all users without their password hashes
    $stmt = $pdo->prepare("
        SELECT id, firstname, lastname, email, role, created_at
        FROM users
        ORDER BY created_at DESC, id DESC
    ");

    $stmt->execute();

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log($e->getMessage());

    $error = "Something went wrong. Please try again later.";
}

// Escape output
$pageTitle = 'Manage Users';
require __DIR__ . '/includes/header.php';
?>

<div class="card">

        <h2>Add a User</h2>

        <?php if ($error !== ""): ?>
            <p class="error"><?php echo e($error); ?></p>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
            <p class="success"><?php echo e($success); ?></p>
        <?php endif; ?>

        <form method="POST" action="users.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e($_SESSION["csrf_token"]); ?>"
            >

            <label for="firstname">First Name</label>
            <input
                type="text"
                id="firstname"
                name="firstname"
                value="<?php echo e($firstname); ?>"
                required
            >

            <label for="lastname">Last Name</label>
            <input
                type="text"
                id="lastname"
                name="lastname"
                value="<?php echo e($lastname); ?>"
                required
            >

            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                value="<?php echo e($email); ?>"
                required
            >

            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                minlength="8"
                required
            >

            <label for="role">Role</label>
            <select id="role" name="role" required>
                <option value="member" <?php if ($role === "member") echo "selected"; ?>>
                    Member
                </option>

                <option value="admin" <?php if ($role === "admin") echo "selected"; ?>>
                    Admin
                </option>
            </select>

            <button type="submit">Add User</button>

        </form>

    </div>

    <div class="card">

        <h2>All Users</h2>

        <div class="table-container">

            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Date Added</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($users as $user): ?>

                        <tr>
                            <td>
                                <?php echo e($user["firstname"] . " " . $user["lastname"]); ?>
                            </td>

                            <td><?php echo e($user["email"]); ?></td>

                            <td><?php echo e($user["role"]); ?></td>

                            <td><?php echo e($user["created_at"]); ?></td>
                        </tr>

                    <?php endforeach; ?>

                    <?php if (count($users) === 0): ?>
                        <tr>
                            <td colspan="4">No users found.</td>
                        </tr>
                    <?php endif; ?>

                </tbody>
            </table>

        </div>

    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>