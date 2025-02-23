<!-- the my profile page of the client, shows them their account details and allows them to change them, as well as see all their cargo -->
<?php
    session_start();

    if (!isset($_SESSION['User_Username']) || !isset($_SESSION['User_Role']) || ($_SESSION['User_Role'] !== 'Client')) {
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

    $username = $_SESSION['User_Username'];
    $userID = $_SESSION['User_ID'];

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

    if(!$status || $status['User_Approved'] === "Not Approved")
    {
        header("Location: Unauthorized.php");
        exit();
    }

    $stmt = $conn->prepare("SELECT * FROM User WHERE User_Username = ? AND User_Role = 'Client'");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $userResult = $stmt->get_result()->fetch_assoc();

    if (!$userResult) {
        die("Client not found.");
    }

    $userUsername = $userResult['User_Username'];
    $userEmail = $userResult['User_Email'];
    $userPhone = $userResult['User_Phone'];

    $message = "";

    //get the client's cargo, along with its picture from the gallery
    $cargoQuery = "
        SELECT 
            c.Cargo_Description, 
            c.Cargo_Weight, 
            c.Cargo_Dimensions, 
            o.Order_Status,
            g.Image_Path
        FROM 
            Cargo c
        INNER JOIN 
            Order_Cargo oc ON c.Cargo_ID = oc.Cargo_ID
        INNER JOIN 
            `Order` o ON oc.Order_ID = o.Order_ID
        LEFT JOIN Gallery g ON c.Cargo_ID = g.Entity_ID 
        AND g.Entity_Type = 'Cargo'
        WHERE 
            c.User_ID = ?
    ";

    $stmt = $conn->prepare($cargoQuery);
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $cargoResult = $stmt->get_result();
    $cargoList = $cargoResult->fetch_all(MYSQLI_ASSOC);

    $stmt->close();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {//updating the username, email, phone, or password
        if (isset($_POST['update_username']) && !empty($_POST['new_username'])) {
            $newUsername = trim($_POST['new_username']);
            $stmt = $conn->prepare("UPDATE User SET User_Username = ? WHERE User_Username = ?");
            $stmt->bind_param("ss", $newUsername, $userUsername);
            if ($stmt->execute()) {
                $_SESSION['User_Username'] = $newUsername;
                $userUsername = $newUsername;
                $message = "Username updated successfully.";
            } else {
                $message = "Failed to update username.";
            }
            $stmt->close();
        }

        if (isset($_POST['update_email']) && !empty($_POST['new_email']) && filter_var($_POST['new_email'], FILTER_VALIDATE_EMAIL)) {
            $newEmail = trim($_POST['new_email']);
            $stmt = $conn->prepare("UPDATE User SET User_Email = ? WHERE User_Username = ?");
            $stmt->bind_param("ss", $newEmail, $userUsername);
            if ($stmt->execute()) {
                $userEmail = $newEmail;
                $message = "Email updated successfully.";
            } else {
                $message = "Failed to update email.";
            }
            $stmt->close();
        }

        if (isset($_POST['update_phone']) && !empty($_POST['new_phone'])) {
            $newPhone = trim($_POST['new_phone']);
            $stmt = $conn->prepare("UPDATE User SET User_Phone = ? WHERE User_Username = ?");
            $stmt->bind_param("ss", $newPhone, $userUsername);
            if ($stmt->execute()) {
                $userPhone = $newPhone;
                $message = "Phone updated successfully.";
            } else {
                $message = "Failed to update phone.";
            }
            $stmt->close();
        }

        if (isset($_POST['change_password'])) {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];

            if ($newPassword === $confirmPassword) {
                $stmt = $conn->prepare("SELECT User_Password FROM User WHERE User_Username = ?");
                $stmt->bind_param("s", $userUsername);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (password_verify($currentPassword, $result['User_Password'])) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE User SET User_Password = ? WHERE User_Username = ?");
                    $stmt->bind_param("ss", $hashedPassword, $userUsername);
                    if ($stmt->execute()) {
                        $message = "Password updated successfully.";
                    } else {
                        $message = "Failed to update password.";
                    }
                    $stmt->close();
                } else {
                    $message = "Current password is incorrect.";
                }
            } else {
                $message = "New passwords do not match.";
            }
        }
    }

    $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Profile</title>
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
        .main-content { min-height: 400px; display: flex; justify-content: center; align-items: center; }
        .content-wrapper { max-width: 600px; width: 100%; background: #f8f9fa; padding: 20px; border-radius: 10px; }
        .form-container { display: none; margin-top: 10px; }
        .btn { margin-top: 5px; }
        .message { color: green; font-weight: bold; margin-top: 15px; }

        .table-container table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-container th, .table-container td {
            padding: 12px;
            text-align: center;
        }

        .table-container th {
            background-color: #343a40;
            color: white;
        }

        .table-container td {
            background-color: #f8f9fa;
        }

        .table-container img {
            max-width: 100px;
            max-height: 100px; 
            object-fit: cover;
            border-radius: 5px;
        }

        .cargo-status {
            font-weight: bold;
        }

        .no-image {
            color: #999;
            font-style: italic;
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

    <div class="banner">From-To Client View</div>


    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="LoggedIn_Client.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="Client_MyCargo1.php">My Cargo</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="Client_MyProfile.php">My Profile</a>
                    </li>
                </ul>
            </div>
            <a class="btn btn-outline-light" href="LogOut.php">Log Out</a>
        </div>
    </nav>


    <div class="background-wrapper">

        <div class="main-content">
            <div class="content-wrapper">
                <h2>Account Details</h2>
                <?php if (!empty($message)) echo "<div class='message'>$message</div>"; ?>

                <div>
                    <p><strong>Username:</strong> <?= htmlspecialchars($userUsername) ?> <button class="btn btn-primary btn-sm" onclick="toggleForm('usernameForm')">Change</button></p>
                    <form method="POST" id="usernameForm" class="form-container">
                        <input type="text" name="new_username" class="form-control" placeholder="Enter new username">
                        <button type="submit" name="update_username" class="btn btn-success">Save</button>
                    </form>
                </div>

                <div>
                    <p><strong>Email:</strong> <?= htmlspecialchars($userEmail) ?> <button class="btn btn-primary btn-sm" onclick="toggleForm('emailForm')">Change</button></p>
                    <form method="POST" id="emailForm" class="form-container">
                        <input type="email" name="new_email" class="form-control" placeholder="Enter new email">
                        <button type="submit" name="update_email" class="btn btn-success">Save</button>
                    </form>
                </div>

                <div>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($userPhone) ?> <button class="btn btn-primary btn-sm" onclick="toggleForm('phoneForm')">Change</button></p>
                    <form method="POST" id="phoneForm" class="form-container">
                        <input type="text" name="new_phone" class="form-control" placeholder="Enter new phone">
                        <button type="submit" name="update_phone" class="btn btn-success">Save</button>
                    </form>
                </div>

                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#passwordModal">Change Password</button>

                <!-- the modal that appears whenever the client wants to change their password -->
                <div class="modal fade" id="passwordModal" tabindex="-1" aria-labelledby="passwordModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <form method="POST" class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="passwordModalLabel">Change Password</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <input type="password" name="current_password" class="form-control" placeholder="Current Password" required>
                                </br>
                                <input type="password" name="new_password" class="form-control" placeholder="New Password" required>
                                </br>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Confirm New Password" required>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="change_password" class="btn btn-danger">Change Password</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="table-container">
                    <h3>Your Cargo</h3>
                    <table class="table table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Cargo Image</th>
                                <th>Cargo Description</th>
                                <th>Cargo Weight</th>
                                <th>Cargo Dimensions</th>
                                <th>Cargo Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cargoList)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No cargo found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($cargoList as $cargo): ?><!-- goes thorugh the list of user's cargo and showcases it along with a picture if available, if not, shows a default no image picture -->
                                    <tr>
                                        <td>
                                                <img src="<?= htmlspecialchars($cargo['Image_Path'] ?? 'uploads\no-image.png') ?>" alt="Cargo Image" class="cargo-image">
                                        </td>
                                        <td><?= htmlspecialchars($cargo['Cargo_Description']) ?></td>
                                        <td><?= htmlspecialchars($cargo['Cargo_Weight']) ?> kg</td>
                                        <td><?= htmlspecialchars($cargo['Cargo_Dimensions']) ?></td>
                                        <td><?= htmlspecialchars($cargo['Order_Status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
            </div>
        </div>
    </div>

    <div class="footer">Anton Kovachev | © 2024</div>

    <script>
        function toggleForm(formId) {
            const form = document.getElementById(formId);
            form.style.display = (form.style.display === "block") ? "none" : "block";
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>