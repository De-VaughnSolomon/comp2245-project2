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
    <a href="logout.php">Logout</a>
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


    </div>
    <p id="ticketcount"></p>Loading tickets...</p>
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
