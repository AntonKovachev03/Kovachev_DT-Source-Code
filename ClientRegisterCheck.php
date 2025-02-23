<!-- the page that checks if the client's registration credentials are valid, and if they are redirect them to the loggedon page, and if not, shows the registration form again, along with the errors -->
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

if (!empty($_POST['usernameCR'])) {
    $u = $_POST['usernameCR'];
    $stmt = $conn->prepare("SELECT * FROM User WHERE User_Username = ?");
    $stmt->bind_param("s", $u);
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

if (!empty($_POST['passwordCR'])) {
    $ps = $_POST['passwordCR'];
} else {
    $errors[] = "Please enter a password!";
    $errorCount++;
}

if ($_POST['passwordC2'] != $_POST['passwordCR'] || empty($_POST['passwordC2'])) {
    $errors[] = "Please make sure the second password is correct!";
    $errorCount++;
}

$numberPattern = '/^[0-9]*$/';
if (!empty($_POST['phoneC']) && preg_match($numberPattern, $_POST['phoneC'])) {
    $ph = $_POST['phoneC'];
    $stmt = $conn->prepare("SELECT * FROM User WHERE User_Phone = ?");
    $stmt->bind_param("s", $ph);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $errors[] = "There is already a user with this phone number!";
        $errorCount++;
    }
} else {
    $errors[] = "Please enter a correct phone number!";
    $errorCount++;
}

$emailPattern = "/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/i";
if (!empty($_POST['emailC']) && preg_match($emailPattern, $_POST['emailC'])) {
    $em = $_POST['emailC'];
    $stmt = $conn->prepare("SELECT * FROM User WHERE User_Email = ?");
    $stmt->bind_param("s", $em);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $errors[] = "There is already a user with this email!";
        $errorCount++;
    }
} else {
    $errors[] = "Please enter a correct email!";
    $errorCount++;
}

if ($errorCount == 0) {
    $_SESSION['user'] = $u;
    $_SESSION['pass'] = password_hash($ps, PASSWORD_DEFAULT);
    $_SESSION['phoneN'] = $ph;
    $_SESSION['email'] = $em;

    $_SESSION['User_Username'] = $u;
    $_SESSION['User_Role'] = 'Client';

    $stmt = $conn->prepare("INSERT INTO User (User_Username, User_Password, User_Phone, User_Email, User_Role, User_Approved) VALUES (?, ?, ?, ?, 'Client', 'Approved')");
    $stmt->bind_param("ssss", $_SESSION['user'], $_SESSION['pass'], $_SESSION['phoneN'], $_SESSION['email']);
    $stmt->execute();

    $stmt = $conn->prepare("SELECT User_ID FROM User WHERE User_Username = ?");
    $stmt->bind_param("s", $u);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $_SESSION['User_ID'] = $row['User_ID']; 
    } else {
        $_SESSION['User_ID'] = null;
    }

    header('Location: http://localhost:8000/LoggedIn_Client.php');
    exit;
} else {
    //shows the form again, along with the errors
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LogIn or Registration</title>

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

    <!-- the registration form along with the errors -->
    <div class="background-wrapper">
        <div class="container-fluid">
            <div class="main-content">
                <div class="middle-content">
                    <h3 class="mb-4">Registration Errors</h3>
                    <?php foreach ($errors as $value): ?>
                        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($value); ?></div>
                    <?php endforeach; ?>

                    <form action="ClientRegisterCheck.php" method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" name="usernameCR" value="<?php echo isset($_POST['usernameCR']) ? htmlspecialchars($_POST['usernameCR']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" name="passwordCR" required>
                        </div>
                        <div class="mb-3">
                            <label for="password2" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" name="passwordC2" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" name="phoneC" value="<?php echo isset($_POST['phoneC']) ? htmlspecialchars($_POST['phoneC']) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="text" class="form-control" name="emailC" value="<?php echo isset($_POST['emailC']) ? htmlspecialchars($_POST['emailC']) : ''; ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Register</button>
                    </form>
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
?>