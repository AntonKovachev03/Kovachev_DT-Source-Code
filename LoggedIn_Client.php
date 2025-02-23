<!-- the main page for the cient, and the page they see when logging in -->
<?php 
    session_start();

    if (!isset($_SESSION['User_Username']) || !isset($_SESSION['User_Role']) || !isset($_SESSION['User_ID']) || ($_SESSION['User_Role']=='Deliverer')) 
    {
        header("Location: Unauthorized.php");
        exit();
    }

    $dbHost = "localhost";
    $dbName = "from_to";
    $dbUser = "root";
    $dbPass = "";
    
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $statusStmt = $conn->prepare("
        SELECT User_Approved
        FROM User u
        WHERE u.User_ID = ?
    ");
    $statusStmt->bind_param("i", $_SESSION['User_ID']);
    $statusStmt->execute();
    $statusResult = $statusStmt->get_result();
    $status = $statusResult->fetch_assoc();
    $statusStmt->close();

    $isApproved = isset($status['User_Approved']) && $status['User_Approved'] === "Approved";

    $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LoggedIn Client Home</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        .banner {
            background-color: #661e0f;
            color: white;
            padding: 15px;
            font-size: 24px;
            font-weight: bold;
        }

        .footer {
            background-color: #333;
            color: white;
            padding: 5px 0;
            text-align: center;
            position: fixed;
            bottom: 0;
            width: 100%;
        }

        html, body {
            height: 100%;
            margin: 0;
        }

        .navbar-nav {
            flex: 1;
            justify-content: flex-start;
        }

        .main-content {
            flex-grow: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .content-wrapper {
            display: flex;
            justify-content: center;
            width: 100%;
        }

        .middle-content {
            max-width: 600px;
            width: 100%;
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .alert { margin-bottom: 15px; }

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
    </style>
</head>

<body>
    <div class="banner">
        From-To Client View
    </div>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="LoggedIn_Client.php">Home</a>
                    </li>
                    <?php if ($isApproved): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="Client_MyCargo1.php">My Cargo</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="Client_MyProfile.php">My Profile</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
            <a class="btn btn-outline-light" href="LogOut.php">Log Out</a>
        </div>
    </nav>

    <div class="background-wrapper">
        <div class="container-fluid">
            <div class="main-content">
                <div class="middle-content">
                    <?php if ($isApproved): ?>
                        <h3 class="mb-4">Welcome to the Client Panel!</h3>
                        <p>Use the navigation menu to view your cargo, profile, and more.</p>
                    <?php else: ?>
                        <h3 class="mb-4">Awaiting Approval</h3>
                        <p>Your account is awaiting approval. You currently do not have access to all functionalities. Please contact support or wait for approval.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        <p style="font-size: 14px; margin-bottom: 0;">Anton Kovachev | © 2024</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

</body>
</html>