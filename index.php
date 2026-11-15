<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    // User is not logged in, redirect to login page
    header('Location: login.php');
    exit;
}

$firstname = $_SESSION['firstname'];
$lastname = $_SESSION['lastname'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ticket list</title>
     <link rel="stylesheet" href="styles.css">
     <script src="app.js"></script>
</head>

<body>
    <div class="Header">
    <h1>Fix-It Desk</h1>
    <a href="index.php">Tickets</a>
    <a href="ticket-new.php">New Ticket</a>
    <a href="users.php">Users</a>
    <h2><?php echo htmlspecialchars($firstname . ' ' . $lastname); ?>!</h2>
    <a href="logout.php">Logout</a>
    </div>
    <div class="ticket-list">
    <h3>Tickets</h3>
    <div class="Newticket">
         <a href="ticket-new.php">New Ticket</a>
    </div>
    </div>
    <div class="filters">
        <button type="button" >All</button>
        <button type="button" >Open</button>
        <button type="button" >My Tickets</button>
    </div>
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
        </table>
   

</body>

</html>
