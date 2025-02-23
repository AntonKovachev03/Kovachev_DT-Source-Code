<!-- the page that checks the user's login input as an admin and shows errors, if any, or logs them in -->
<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    session_start();

        //this checks whether the user has reached this page the correct way by attempting to login through the form, or if he has typed the address in the searchbar
        if (!isset($_SESSION['adminAttempt']))
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

    $errors = [];
    $errorCount = 0;
    $adminUsername = "";
    $adminPassword = "";

    if (!empty($_POST['usernameAdL'])) { 
        $adminUsername = $_POST['usernameAdL'];
    } else {
        $errors[] = "Please enter username!";
        $errorCount++;
    }

    if (!empty($_POST['passwordAdL'])) { 
        $adminPassword = $_POST['passwordAdL'];
    } else {
        $errors[] = "Please enter password!";
        $errorCount++;
    }

    if ($errorCount === 0) {
        $stmt = $conn->prepare("SELECT Admin_ID, Admin_Password FROM admin WHERE Admin_Username = ?");
        $stmt->bind_param("s", $adminUsername);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $hashedPassword = $row['Admin_Password'];
            $adminID = $row['Admin_ID'];

            if (password_verify($adminPassword, $hashedPassword)) {//passwords in the database are hashed, so to check if they are correct, i need tp use this to verify
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $adminID;
                $_SESSION['admin_username'] = $adminUsername;
                
                header('Location: LoggedIn_Admin.php'); //if correct, the user logs in as an admin
                exit; 
            } else {
                $errors[] = "Invalid username or password!";
                $errorCount++;
            }
        } else {
            $errors[] = "Invalid username or password!";
            $errorCount++;
        }
        
        $stmt->close();
    }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        .banner {
            background-color: #661e0f;
            color: white;
            padding: 15px;
            font-size: 24px;
            font-weight: bold;
            text-align: center;
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

        .options ul {
            display: flex;
            justify-content: center;
            background-color: #f0f0f0;
            padding: 10px 0;
            margin-bottom: 20px;
        }

        .options li {
            list-style-type: none;
            padding: 10px 20px;
            cursor: pointer;
        }

        .options li.active {
            background-color: #04AA6D;
            color: white;
            font-weight: bold;
        }

        .form-control {
            margin-bottom: 10px;
        }

        .btn-primary {
            width: 100%;
        }

        .alert {
            display: none;
        }
    </style>
</head>

<body>
    <div class="banner">
        Admin Login / Registration
    </div>

    <div class="background-wrapper">
        <div class="container-fluid">
            <div class="main-content">
                <div class="content-wrapper">
                    <div class="middle-content">
                        <div class="options">
                            <ul>
                                <li class="active" id="loginTab">LogIn</li>
                                <li id="registerTab">Register</li>
                            </ul>
                        </div>

                        <!-- this is where the user's errors are shown, after an unsuccessful login attempt -->
                        <h3 class="mb-4">LogIn Errors</h3>
                        <div class="alert alert-danger" id="loginErrors" style="display: <?php echo ($errorCount > 0 && isset($_POST['usernameAdL'])) ? 'block' : 'none'; ?>;">
                            <?php 
                            if ($errorCount > 0) {
                                foreach ($errors as $value) {
                                    echo "<p>$value</p>";
                                }
                            }
                            ?>
                        </div>

                        <div class="field" id="formContainer">
                            <form action="AdminLogInCheck.php" method="POST">
                                <div class="mb-3">
                                    <label for="usernameAdL" class="form-label">Username</label>
                                    <input type="text" class="form-control" name="usernameAdL" value="<?php echo htmlspecialchars($adminUsername); ?>" required id="usernameAdL">
                                </div>
                                <div class="mb-3">
                                    <label for="passwordAdL" class="form-label">Password</label>
                                    <input type="password" class="form-control" name="passwordAdL" required id="passwordAdL">
                                </div>
                                <button type="submit" class="btn btn-primary">LogIn</button>
                            </form>
                        </div>
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

    <script>
        const loginTab = document.getElementById("loginTab");
        const registerTab = document.getElementById("registerTab");
        const formContainer = document.getElementById("formContainer");
        const loginErrors = document.getElementById("loginErrors");

        loginTab.addEventListener("click", showLogIn);
        registerTab.addEventListener("click", showRegister);

        //similar to the admin-login-lb1tk2.php, the user can swap between the login and registration forms
        function showLogIn() {
            loginTab.classList.add("active");
            registerTab.classList.remove("active");

            loginErrors.style.display = "block"; 

            formContainer.innerHTML = `
                <form action="AdminLogInCheck.php" method="POST">
                    <div class="mb-3">
                        <label for="usernameAdL" class="form-label">Username</label>
                        <input type="text" class="form-control" name="usernameAdL" value="<?php echo htmlspecialchars($adminUsername); ?>" required id="usernameAdL">
                    </div>
                    <div class="mb-3">
                        <label for="passwordAdL" class="form-label">Password</label>
                        <input type="password" class="form-control" name="passwordAdL" required id="passwordAdL">
                    </div>
                    <button type="submit" class="btn btn-primary">LogIn</button>
                </form>
            `;
        }

        function showRegister() {
            loginErrors.style.display = "none"; 

            registerTab.classList.add("active");
            loginTab.classList.remove("active");

            formContainer.innerHTML = `
                <form action="AdminRegisterCheck.php" method="POST">
                    <div class="mb-3">
                        <label for="usernameAdR" class="form-label">Username</label>
                        <input type="text" class="form-control" name="usernameAdR" required id="usernameAdR">
                    </div>
                    <div class="mb-3">
                        <label for="passwordAdR" class="form-label">Password</label>
                        <input type="password" class="form-control" name="passwordAdR" required id="passwordAdR">
                    </div>
                    <div class="mb-3">
                        <label for="passwordAdR2" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" name="passwordAdR2" required id="passwordAdR2">
                    </div>
                    <div class="mb-3">
                        <label for="emailAdR" class="form-label">Email</label>
                        <input type="email" class="form-control" name="emailAdR" required id="emailAdR">
                    </div>
                    <div class="mb-3">
                        <label for="phoneAdR" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" name="phoneAdR" required id="phoneAdR">
                    </div>
                    <button type="submit" class="btn btn-primary">Register</button>
                </form>
            `;
        }

        showLogIn();
    </script>

</body>
</html>