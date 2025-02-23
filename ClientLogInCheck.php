<!-- the page that checks if the client's login credentials are valid, and if they are redirect them to the loggedon page, and if not, shows the login form again, along with the errors -->
<?php
    session_start();
    $dbHost = "localhost";
    $dbName = "from_to";
    $dbUser = "root";
    $dbPass = "";

    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $errors = [];
    $errorCount = 0;

    if (!empty($_POST['usernameCL'])) {
        $u = $_POST['usernameCL'];
    } else {
        $errors[] = "Please enter username!";
        $errorCount++;
    }

    if (!empty($_POST['passwordCL'])) {
        $ps = $_POST['passwordCL'];
    } else {
        $errors[] = "Please enter password!";
        $errorCount++;
    }

    if ($errorCount == 0) {
        $stmt = $conn->prepare("SELECT User_ID, User_Password, User_Role FROM User WHERE User_Username = ? AND User_Role = 'Client'");
        $stmt->bind_param("s", $u);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) 
        {
            $hashedPassword = $row['User_Password'];

            if (password_verify($ps, $hashedPassword)) 
            {
                $_SESSION['User_ID'] = $row['User_ID'];
                $_SESSION['User_Username'] = $u;
                $_SESSION['User_Role'] = 'Client';

                header('Location: http://localhost:8000/LoggedIn_Client.php');
                exit;
            } else 
            {
                $errors[] = "Invalid username or password!";
                $errorCount++;
            }
        } else {
            $errors[] = "Invalid username or password!";
            $errorCount++;
        }

        $stmt->close();
    }

    if ($errorCount != 0) {
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Client LogIn</title>

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
            From-To
        </div>

        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="About.php">About</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- the login form, along with the errors -->
        <div class="background-wrapper">
            <div class="container-fluid">
                <div class="main-content">
                    <div class="content-wrapper">
                        <div class="middle-content">
                            <h3 class="mb-4">LogIn Errors</h3>
                            <?php foreach ($errors as $value): ?>
                                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($value); ?></div>
                            <?php endforeach; ?>

                            <form action="ClientLoginCheck.php" method="POST">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" name="usernameCL" id="username" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" name="passwordCL" id="password" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">LogIn</button>
                            </form>
                        </div>
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
<?php
    }
    $conn->close();