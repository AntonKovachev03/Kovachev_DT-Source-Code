<!-- the page that checks the user's registration input as an admin and shows errors, if any, or registers them and logs them in -->
<?php
session_start();

        if (!isset($_SESSION['adminAttempt']) || $_SESSION['adminAttempt'] == false)
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

    $errorCount = 0;
    $errors = [];

    if (!empty($_POST['usernameAdR'])) {//check if username is already taken
        $username = $_POST['usernameAdR'];
        $stmt = $conn->prepare("SELECT * FROM admin WHERE Admin_Username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $errors[] = "Username is already taken!";
            $errorCount++;
        }
    } else {
        $errors[] = "Please enter a username!";
        $errorCount++;
    }

    if (!empty($_POST['passwordAdR'])) {
        $password = $_POST['passwordAdR'];
    } else {
        $errors[] = "Please enter a password!";
        $errorCount++;
    }

    if ($_POST['passwordAdR2'] != $_POST['passwordAdR'] || empty($_POST['passwordAdR2'])) {
        $errors[] = "Please make sure the second password is correct!";
        $errorCount++;
    }

    $phonePattern = '/^[0-9]*$/';
    if (!empty($_POST['phoneAdR']) && preg_match($phonePattern, $_POST['phoneAdR'])) {//like the username, it checks for uniqueness
        $phone = $_POST['phoneAdR'];
        $stmt = $conn->prepare("SELECT * FROM admin WHERE Admin_Phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $errors[] = "There is already an admin with this phone number!";
            $errorCount++;
        }
    } else {
        $errors[] = "Please enter a valid phone number!";
        $errorCount++;
    }

    $emailPattern = "/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/i";
    if (!empty($_POST['emailAdR']) && preg_match($emailPattern, $_POST['emailAdR'])) {
        $email = $_POST['emailAdR'];
        $stmt = $conn->prepare("SELECT * FROM admin WHERE Admin_Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $errors[] = "There is already an admin with this email!";
            $errorCount++;
        }
    } else {
        $errors[] = "Please enter a valid email!";
        $errorCount++;
    }

    if ($errorCount === 0) 
    {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);//the database stores the passwords as hashed

        $stmt = $conn->prepare("INSERT INTO admin (Admin_Username, Admin_Password, Admin_Email, Admin_Phone) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $hashedPassword, $email, $phone);

        $stmt->execute();

        if ($stmt->affected_rows > 0) 
        {
            $idStmt = $conn->prepare("
                SELECT Admin_ID
                FROM Admin a
                WHERE a.Admin_Username = ?
            ");
            $idStmt->bind_param("s", $username);
            $idStmt->execute();
            $idResult = $idStmt->get_result();
            $adminID = $idResult->fetch_assoc();
            $idStmt->close();
        }

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $adminID['Admin_ID'];
        $_SESSION['admin_username'] = $username;
        
        $stmt->close();

        header("Location: http://localhost:8000/LoggedIn_Admin.php");
        exit;
        } 
        else {
            $errors[] = "Registration failed due to a database error. Please try again.";
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
        .footer {
            background-color: #333;
            color: white;
            padding: 5px 0;
            text-align: center;
            position: fixed;
            bottom: 0;
            width: 100%;
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

                        <div id="registerErrorsContainer" style="display:none;">
                            <h3 class="mb-4">Registration Errors</h3>
                            <div id="registerErrors"></div>
                        </div>

                        <div class="field" id="formContainer">
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
    const registerErrorsContainer = document.getElementById("registerErrorsContainer");
    const registerErrors = document.getElementById("registerErrors");

    loginTab.addEventListener("click", showLogIn);
    registerTab.addEventListener("click", showRegister);

    // Show Login form
function showLogIn() {
        registerErrorsContainer.style.display = "none";
        registerTab.classList.remove("active");
        loginTab.classList.add("active");

        formContainer.innerHTML = `
            <form action="AdminLogInCheck.php" method="POST">
                <div class="mb-3">
                    <label for="usernameAdL" class="form-label">Username</label>
                    <input type="text" class="form-control" name="usernameAdL" required id="usernameAdL">
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
        registerErrorsContainer.style.display = "block";
        registerTab.classList.add("active");
        loginTab.classList.remove("active");

        <?php if (count($errors) > 0): ?>
            let errorMessages = <?php echo json_encode($errors); ?>;
            let errorHTML = errorMessages.map(msg => `<div class="alert alert-danger" role="alert">${msg}</div>`).join('');
            registerErrors.innerHTML = errorHTML;
        <?php endif; ?>

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

    showRegister();//since this is the register check, the default form that is shown in case of errors is again the register form
    </script>

</body>
</html>
