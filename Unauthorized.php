<!-- the page that users see after trying to access a page that they shouldn't -->
<?php
session_start();

$isLoggedIn = isset($_SESSION['User_Username']) && isset($_SESSION['User_Role']) && isset($_SESSION['User_ID']);
$userRole = $isLoggedIn ? $_SESSION['User_Role'] : null;

$adminLoggedIn = isset($_SESSION['admin_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        .banner {
            background-color: #661e0f;
            color: white;
            padding: 20px;
            font-size: 24px;
            font-weight: bold;
            width: 100%;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 1000;
        }

        .footer {
            background-color: #333;
            color: white;
            padding: 10px 0;
            text-align: center;
            width: 100%;
            position: fixed;
            bottom: 0;
            left: 0;
        }

        .background-wrapper {
            background-image: url('uploads/bg1.jpg'); 
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
            color: black;
            overflow: hidden;
        }

        html, body {
            height: 100%;
            margin: 0;
            display: flex;
            flex-direction: column;
            padding-top: 37px;
        }

        .message {
            background-color: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 0 auto;
            font-size: 18px; 
            color: black;
            text-align: center; 
        }

        .button {
            padding: 10px 20px;
            font-size: 16px;
            margin-top: 10px;
            cursor: pointer;
            border: none;
            border-radius: 5px;
            background-color: #4CAF50;
            color: white;
        }

        .button:hover {
            background-color: #45a049;
        }

        body {
            font-family: Arial, sans-serif;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="banner">
        From-To
    </div>

    <!-- depending on the session data for the user, they will be shown a different version of the page -->
    <div class="background-wrapper">
        <div class="message">
            <?php if ($isLoggedIn): ?>
                <p>You are trying to access a page you are not authorized to see. Please go to your appropriate dashboard.</p>
                
                <?php if ($userRole == 'Client'): ?>
                    <form action="LoggedIn_Client.php">
                        <button type="submit" class="button">Go to Client Dashboard</button>
                    </form>
                <?php elseif ($userRole == 'Deliverer'): ?>
                    <form action="LoggedIn_Deliverer.php">
                        <button type="submit" class="button">Go to Deliverer Dashboard</button>
                    </form>
                <?php elseif ($adminLoggedIn): ?>
                    <form action="LoggedIn_Admin.php">
                        <button type="submit" class="button">Go to Admin Dashboard</button>
                    </form>
                <?php endif; ?>

            <?php else: ?>
                <p>You are trying to access a page you are not authorized to see. You will be sent to the main page.</p>
                <form action="index.php">
                    <button type="submit" class="button">Main Page</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer">
        <p style="font-size: 14px; margin-bottom: 0;">Anton Kovachev | © 2024</p>
    </div>

</body>
</html>