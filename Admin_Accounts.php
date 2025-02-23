<!-- the page where admins can approve, disapprove or delete the accounts of users and admins -->
<?php
    session_start();

    //if the user is not logged in as a client, they are sent to the page for unauthorized users
    if (!isset($_SESSION['admin_id'])) 
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


    $adminApprovedStatus = $conn->prepare("
            SELECT Admin_Approved
            FROM Admin a
            WHERE a.Admin_ID = ?
        ");
    $adminApprovedStatus->bind_param("i", $_SESSION['admin_id']);
    $adminApprovedStatus->execute();
    $adminApproved = $adminApprovedStatus->get_result();
    $isAdminApproved = $adminApproved->fetch_assoc();
    $adminApprovedStatus->close();

    //if the user is an unapproved admin and somehow tries to access this page, they are sent to the page for unauthorized users
    if(!$isAdminApproved || $isAdminApproved['Admin_Approved'] === "Not Approved")
    {
        header("Location: Unauthorized.php");
        exit();
    }

    if (isset($_POST['approveUserButton'])) {
        $userId = $_POST['userId'];
        $approveUser = $conn->prepare("UPDATE User SET User_Approved = 'Approved' WHERE User_ID = ?");
        $approveUser->bind_param("i", $userId);
        $approveUser->execute();
        $approveUser->close();
    }

    if (isset($_POST['disapproveUserButton'])) {
        $userId = $_POST['userId'];
        $disapproveUser = $conn->prepare("UPDATE User SET User_Approved = 'Not Approved' WHERE User_ID = ?");
        $disapproveUser->bind_param("i", $userId);
        $disapproveUser->execute();
        $disapproveUser->close();
    }

    if (isset($_POST['deleteUserButton'])) {
        $userId = $_POST['userId'];
        $deleteUser = $conn->prepare("DELETE FROM User WHERE User_ID = ?");
        $deleteUser->bind_param("i", $userId);
        $deleteUser->execute();
        $deleteUser->close();
    }

    if (isset($_POST['approveAdminButton'])) {
        $adminId = $_POST['adminId'];
        $approveAdmin = $conn->prepare("UPDATE Admin SET Admin_Approved = 'Approved' WHERE Admin_ID = ?");
        $approveAdmin->bind_param("i", $adminId);
        $approveAdmin->execute();
        $approveAdmin->close();
    }
    
    if (isset($_POST['disapproveAdminButton'])) {
        $adminId = $_POST['adminId'];
        $disapproveAdmin = $conn->prepare("UPDATE Admin SET Admin_Approved = 'Not Approved' WHERE Admin_ID = ?");
        $disapproveAdmin->bind_param("i", $adminId);
        $disapproveAdmin->execute();
        $disapproveAdmin->close();
    }

    if (isset($_POST['deleteAdminButton'])) {
        $adminId = $_POST['adminId'];
        $deleteAdmin = $conn->prepare("DELETE FROM Admin WHERE Admin_ID = ?");
        $deleteAdmin->bind_param("i", $adminId);
        $deleteAdmin->execute();
        $deleteAdmin->close();
    }

    //I want all the users to be visible in the table, but the main admin, with id of 1, will not appear in the table, since I do not want other admins to mess up his approval

    $allUsers = $conn->prepare("SELECT * FROM User");
    $allUsers->execute();
    $usersResult = $allUsers->get_result();

    $pendingAdmins = $conn->prepare("
        SELECT Admin_ID, Admin_Username, Admin_Email, Admin_Phone, Admin_Approved
        FROM Admin
        WHERE Admin_ID != 1
    ");
    $pendingAdmins->execute();
    $pendingAdminsResult = $pendingAdmins->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Accounts</title>

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

        .background-wrapper {
            background-image: url('uploads/bg1.jpg'); 
            background-size: cover; 
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
            overflow: hidden;
            color:black;
        }

        .table-container {
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .approve-btn {
            background-color: #04AA6D;
            color: white;
        }

        .approve-btn:hover {
            background-color: #028a52;
        }

        .disapprove-btn {
            background-color: #f0ad4e;
            color: white;
        }

        .disapprove-btn:hover {
            background-color: #ec971f;
        }

        .delete-btn {
            background-color: #d11a2a;
            color: white;
        }

        .delete-btn:hover {
            background-color: #a10d1c;
        }

        .navbar-brand {
            font-weight: bold;
        }

        h2 {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

    <div class="banner">
        From-To Admin Panel - Manage Accounts
    </div>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="LoggedIn_Admin.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="Admin_Orders.php">Orders</a></li>
                    <li class="nav-item"><a class="nav-link" href="Admin_Vehicles.php">Vehicles</a></li>
                    <li class="nav-item"><a class="nav-link" href="Admin_Reviews.php">Reviews</a></li>
                    <li class="nav-item"><a class="nav-link active" href="Admin_Accounts.php">Accounts</a></li>
                </ul>
                <a class="btn btn-outline-light" href="LogOut.php">Log Out</a>
            </div>
        </div>
    </nav>

    <!-- The tatble where the admin will be able to manage users -->
    <div class="background-wrapper">
        <div class="container">
            <div class="table-container">
                <h2>Manage User Accounts</h2>
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $usersResult->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['User_ID']) ?></td>
                                <td><?= htmlspecialchars($user['User_Username']) ?></td>
                                <td><?= htmlspecialchars($user['User_Email']) ?></td>
                                <td><?= htmlspecialchars($user['User_Role']) ?></td>
                                <td><?= htmlspecialchars($user['User_Approved']) ?></td>
                                <td>
                                    <?php if ($user['User_Approved'] == "Not Approved"): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="userId" value="<?= $user['User_ID'] ?>">
                                            <button type="submit" name="approveUserButton" class="btn approve-btn">Approve</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;">
                                        <input type="hidden" name="userId" value="<?= $user['User_ID'] ?>">
                                            <button type="submit" name="disapproveUserButton" class="btn approve-btn">Disapprove</button>
                                        </form>
                                    <?php endif?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="userId" value="<?= $user['User_ID'] ?>">
                                        <button type="submit" name="deleteUserButton" class="btn delete-btn">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <!-- The table where the admins will be able to manage the approval status of other admins -->
                <h2>Admins Awaiting Approval</h2>
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                        <thead>
                            <tr>
                                <th>Admin ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Phone</th> 
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($admin = $pendingAdminsResult->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($admin['Admin_ID']) ?></td>
                                <td><?= htmlspecialchars($admin['Admin_Username']) ?></td>
                                <td><?= htmlspecialchars($admin['Admin_Email']) ?></td>
                                <td><?= htmlspecialchars($admin['Admin_Phone']) ?></td>
                                <td><?= htmlspecialchars($admin['Admin_Approved']) ?></td>
                                <td>
                                    <?php if ($admin['Admin_Approved'] == "Not Approved"): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="adminId" value="<?= $admin['Admin_ID'] ?>">
                                            <button type="submit" name="approveAdminButton" class="btn approve-btn">Approve</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="adminId" value="<?= $admin['Admin_ID'] ?>">
                                            <button type="submit" name="disapproveAdminButton" class="btn disapprove-btn">Disapprove</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="adminId" value="<?= $admin['Admin_ID'] ?>">
                                        <button type="submit" name="deleteAdminButton" class="btn delete-btn">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="footer">
        <p style="font-size: 14px; margin-bottom: 0;">Anton Kovachev | © 2024</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
    $allUsers->close();
    $conn->close();
?>