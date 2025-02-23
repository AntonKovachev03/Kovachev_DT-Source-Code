<!-- the page that shows to the admin the orders and contracts that need approving -->
<?php
    session_start();

    //if the user is not logged in as a client, they are sent to the page for unauthorized users, this is the same for all admin pages, except for the main one
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


    if (isset($_POST['approveContractButton'])) {
        $contractId = $_POST['contractId'];

        //A delivery can only start with an admin's approval of a contract, which has been accepted by both a client and a deliverre
        $approveContractStmt = $conn->prepare("UPDATE Contract SET Contract_Approval = 'Approved' WHERE Contract_ID = ?");
        $approveContractStmt->bind_param("i", $contractId);
        $approveContractStmt->execute();
        $approveContractStmt->close();

        $createDeliveryStmt = $conn->prepare("
            INSERT INTO Delivery (Contract_ID) 
            VALUES (?)
        ");
        $createDeliveryStmt->bind_param("i", $contractId);
        $createDeliveryStmt->execute();
        $createDeliveryStmt->close();
    }

    if (isset($_POST['deleteContract'])) {
        $contractId = $_POST['contractId'];
        $deleteContractStmt = $conn->prepare("DELETE FROM Contract WHERE Contract_ID = ?");
        $deleteContractStmt->bind_param("i", $contractId);
        $deleteContractStmt->execute();
        $deleteContractStmt->close();
    }

    //Only vontracts with status both confirmed appear, since the others are irrelevant
    $contractsStmt = $conn->prepare("
        SELECT * 
        FROM Contract 
        WHERE Contract_Status = 'Both Confirmed'
    ");
    $contractsStmt->execute();
    $contractsResult = $contractsStmt->get_result();

    if (isset($_POST['approveOrder'])) {
        $orderId = $_POST['orderId'];
        $approveStmt = $conn->prepare("UPDATE `Order` SET Order_Approved = 'Approved' WHERE Order_ID = ?");
        $approveStmt->bind_param("i", $orderId);
        $approveStmt->execute();
        $approveStmt->close();
    }

    if (isset($_POST['disapproveOrder'])) {
        $orderId = $_POST['orderId'];
        $disapproveStmt = $conn->prepare("UPDATE Order SET Order_Approved = 'Not Approved' WHERE Order_ID = ?");
        $disapproveStmt->bind_param("i", $orderId);
        $disapproveStmt->execute();
        $disapproveStmt->close();
    }

    if (isset($_POST['deleteOrder'])) {
        $orderId = $_POST['orderId'];
        $deleteStmt = $conn->prepare("DELETE FROM Order WHERE Order_ID = ?");
        $deleteStmt->bind_param("i", $orderId);
        $deleteStmt->execute();
        $deleteStmt->close();
    }

    $ordersStmt = $conn->prepare("SELECT * FROM `Order`");
    $ordersStmt->execute();
    $ordersResult = $ordersStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Orders</title>

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
            overflow: hidden; 
            color:black;
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
        From-To Admin Panel - Manage Orders
    </div>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="LoggedIn_Admin.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="Admin_Orders.php">Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="Admin_Vehicles.php">Vehicles</a>
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
    </nav>

    <!-- The two tables, one for the contracts, and one for the orders, since both need approval at certain stages -->
    <div class="background-wrapper">
        <div class="container-fluid">
            <div class="main-content">
                <div class="content-wrapper">
                    <div class="middle-content">
                        <h3 class="mb-4">Manage Contracts</h3>
                        <div class="table-container">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Contract ID</th>
                                        <th>Order ID</th>
                                        <th>Client ID</th>
                                        <th>Deliverer ID</th>
                                        <th>Contract Status</th>
                                        <th>Approval</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($contract = $contractsResult->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($contract['Contract_ID']) ?></td>
                                            <td><?= htmlspecialchars($contract['Order_ID']) ?></td>
                                            <td><?= htmlspecialchars($contract['Deliverer_ID']) ?></td>
                                            <td><?= htmlspecialchars($contract['Contract_Status']) ?></td>
                                            <td><?= htmlspecialchars($contract['Contract_Approval']) ?></td>
                                            <td>
                                                <?php if ($contract['Contract_Approval'] == "Not Approved"): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="contractId" value="<?= $contract['Contract_ID'] ?>">
                                                        <button type="submit" name="approveContractButton" class="btn approve-btn">Approve</button>
                                                    </form>
                                                <?php endif; ?>

                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="contractId" value="<?= $contract['Contract_ID'] ?>">
                                                    <button type="submit" name="deleteContract" class="btn delete-btn">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <h3 class="mt-4 mb-4">Manage Orders</h3>
                        <div class="table-container">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Order Date</th>
                                        <th>Client ID</th>
                                        <th>Order Approved</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($order = $ordersResult->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($order['Order_ID']) ?></td>
                                            <td><?= htmlspecialchars($order['Order_Date']) ?></td>
                                            <td><?= htmlspecialchars($order['User_ID']) ?></td>
                                            <td><?= htmlspecialchars($order['Order_Approved']) ?></td>
                                            <td>
                                                <?php if ($order['Order_Approved'] == "Not Approved"): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="orderId" value="<?= $order['Order_ID'] ?>">
                                                        <button type="submit" name="approveOrder" class="btn approve-btn">Approve</button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if ($order['Order_Approved'] == "Approved"): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="orderId" value="<?= $order['Order_ID'] ?>">
                                                        <button type="submit" name="disapproveOrder" class="btn disapprove-btn">Disapprove</button>
                                                    </form>
                                                <?php endif; ?>

                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="orderId" value="<?= $order['Order_ID'] ?>">
                                                    <button type="submit" name="deleteOrder" class="btn delete-btn">Delete</button>
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
    $contractsStmt->close();
    $ordersStmt->close();
    $conn->close();
?>