<!-- the page that shows to the admin the vehicles that need approval -->
<?php
    session_start();

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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['approveVehicle'])) {
            $vehicleId = $_POST['vehicleId'];
            $approveStmt = $conn->prepare("UPDATE Vehicle SET Vehicle_Status = 'Approved' WHERE Vehicle_ID = ?");
            $approveStmt->bind_param("i", $vehicleId);
            $approveStmt->execute();
            $approveStmt->close();
        } elseif (isset($_POST['deleteVehicle'])) {
            $vehicleId = $_POST['vehicleId'];
            $deleteStmt = $conn->prepare("DELETE FROM Vehicle WHERE Vehicle_ID = ?");
            $deleteStmt->bind_param("i", $vehicleId);
            $deleteStmt->execute();
            $deleteStmt->close();
        }
        header("Location: Admin_Vehicles.php");
        exit;
    }

    $vehiclesStmt = $conn->prepare("
        SELECT v.Vehicle_ID, v.Vehicle_Make, v.Vehicle_Model, v.Vehicle_Type, 
            v.Vehicle_CapacityM, v.Vehicle_CapacityKG, v.Vehicle_Status, 
            u.User_Username 
        FROM Vehicle v
        JOIN User u ON v.User_ID = u.User_ID
    ");
    $vehiclesStmt->execute();
    $vehiclesResult = $vehiclesStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Vehicles</title>

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
            max-width: 900px;
            width: 100%;
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .navbar-nav {
            flex: 1;
            justify-content: flex-start;
        }

        .navbar-nav .nav-link {
            font-size: 16px;
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

        .table-container {
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .btn {
            font-size: 14px;
            padding: 5px 10px;
            cursor: pointer;
        }

        .approve-btn {
            background-color: #04AA6D;
            color: white;
        }

        .approve-btn:hover {
            background-color: #028a52;
        }

        .delete-btn {
            background-color: #d11a2a;
            color: white;
        }

        .delete-btn:hover {
            background-color: #a10d1c;
        }

        table {
            width: 100%;
            table-layout: fixed;
        }

        th, td {
            word-wrap: break-word;
        }

        .btn-group {
            display: flex;
            gap: 10px;
        }

        .btn-group button {
            flex: 1;
        }

        #logout {
            border-radius: 5px; 
            padding: 8px 15px; 
            font-size: 16px;
            border-color: white;     
        }

        #logout:hover {
            background-color: white;
            border-color: #04AA6D;
            color: black;
        }
    </style>
</head>

<body>

    <div class="banner">
        From-To Admin Panel - Manage Vehicles
    </div>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="LoggedIn_Admin.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="Admin_Orders.php">Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="Admin_Vehicles.php">Vehicles</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="Admin_Reviews.php">Reviews</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="Admin_Accounts.php">Accounts</a>
                    </li>
                    </ul>
                    </div>
                        <a id="logout" class="btn btn-outline-light" href="LogOut.php">Log Out</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="background-wrapper">
        <div class="container-fluid">
            <div class="main-content">
                <div class="content-wrapper">
                    <div class="middle-content">
                        <h3 class="mb-4">Manage Vehicles</h3>
                        <div class="table-container">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Make</th>
                                        <th>Model</th>
                                        <th>Type</th>
                                        <th>Capacity (m³)</th>
                                        <th>Carrying Capacity (kg)</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($vehicle = $vehiclesResult->fetch_assoc()): ?><!-- again, looping through the results of thr qeury and showcasing them here, this is prettly much what i've used everywhere that needs a table -->
                                        <tr>
                                            <td><?= htmlspecialchars($vehicle['User_Username']) ?></td>
                                            <td><?= htmlspecialchars($vehicle['Vehicle_Make']) ?></td>
                                            <td><?= htmlspecialchars($vehicle['Vehicle_Model']) ?></td>
                                            <td><?= htmlspecialchars($vehicle['Vehicle_Type']) ?></td>
                                            <td><?= htmlspecialchars($vehicle['Vehicle_CapacityM']) ?></td>
                                            <td><?= htmlspecialchars($vehicle['Vehicle_CapacityKG']) ?></td>
                                            <td><?= $vehicle['Vehicle_Status'] == "Approved" ? "Approved" : "Not Approved" ?></td>
                                            <td>
                                                <?php if ($vehicle['Vehicle_Status'] == "Not Approved"): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="vehicleId" value="<?= $vehicle['Vehicle_ID'] ?>">
                                                        <button type="submit" name="approveVehicle" class="btn approve-btn">Approve</button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="vehicleId" value="<?= $vehicle['Vehicle_ID'] ?>">
                                                    <button type="submit" name="deleteVehicle" class="btn delete-btn">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
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
$vehiclesStmt->close();
$conn->close();
?>