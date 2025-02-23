<!-- the page that shows to the admin the reviews that need to be approved -->
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
        if (isset($_POST['approveReview'])) {
            $reviewId = $_POST['reviewId'];

            $approveStmt = $conn->prepare("UPDATE Review SET Review_Approved = 'Approved' WHERE Review_ID = ?");
            $approveStmt->bind_param("i", $reviewId);
            $approveStmt->execute();
            $approveStmt->close();

            $contractStmt = $conn->prepare("
                SELECT c.Contract_ID, c.Deliverer_ID
                FROM Review r
                JOIN Contract c ON r.Contract_ID = c.Contract_ID
                WHERE r.Review_ID = ?
            ");
            $contractStmt->bind_param("i", $reviewId);
            $contractStmt->execute();
            $contractResult = $contractStmt->get_result();
            $contract = $contractResult->fetch_assoc();
            $contractStmt->close();

            if ($contract) {//Once the admin approves a review, it will show up in the deliverer's column in the user table, so that it can be used later when needed
                $delivererId = $contract['Deliverer_ID'];
                $reviewIdToAdd = $reviewId;

                $userStmt = $conn->prepare("SELECT Review_IDs FROM User WHERE User_ID = ?");
                $userStmt->bind_param("i", $delivererId);
                $userStmt->execute();
                $userResult = $userStmt->get_result();
                $user = $userResult->fetch_assoc();
                $userStmt->close();

                $currentReviewIds = $user['Review_IDs'] ?? '';

                if ($currentReviewIds) {
                    $newReviewIds = $currentReviewIds . "," . $reviewIdToAdd;
                } else {
                    $newReviewIds = $reviewIdToAdd;
                }

                $updateStmt = $conn->prepare("UPDATE User SET Review_IDs = ? WHERE User_ID = ?");
                $updateStmt->bind_param("si", $newReviewIds, $delivererId);
                $updateStmt->execute();
                $updateStmt->close();
            }

            header("Location: Admin_Reviews.php");
            exit;
        } elseif (isset($_POST['deleteReview'])) {
            $reviewId = $_POST['reviewId'];
            $deleteStmt = $conn->prepare("DELETE FROM Review WHERE Review_ID = ?");
            $deleteStmt->bind_param("i", $reviewId);
            $deleteStmt->execute();
            $deleteStmt->close();
        }
        header("Location: Admin_Reviews.php");
        exit;
    }

    //get the reviews that need approval
    $reviewsStmt = $conn->prepare("
        SELECT r.Review_ID, r.Review_Rating, r.Review_Comment, r.Review_Date, 
            r.Review_Approved, c.Contract_ID, u.User_Username 
        FROM Review r
        JOIN Contract c ON r.Contract_ID = c.Contract_ID
        JOIN User u ON r.User_ID = u.User_ID
    ");
    $reviewsStmt->execute();
    $reviewsResult = $reviewsStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Reviews</title>
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

        .delete-btn {
            background-color: #e74c3c;
            color: white;
        }

        .delete-btn:hover {
            background-color: #c0392b;
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
        From-To Admin Panel - Manage Reviews
    </div>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="LoggedIn_Admin.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="Admin_Orders.php">Orders</a></li>
                    <li class="nav-item"><a class="nav-link" href="Admin_Vehicles.php">Vehicles</a></li>
                    <li class="nav-item"><a class="nav-link active" href="Admin_Reviews.php">Reviews</a></li>
                    <li class="nav-item"><a class="nav-link" href="Admin_Accounts.php">Accounts</a></li>
                </ul>
                <a class="btn btn-outline-light" href="LogOut.php">Log Out</a>
            </div>
        </div>
    </nav>

    <div class="background-wrapper">
        <div class="container table-container">
            <h2>Manage Reviews</h2>
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Contract ID</th>
                        <th>Rating</th>
                        <th>Comment</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($review = $reviewsResult->fetch_assoc()): ?><!-- the code goes through the reviews that were fetched earlier by the query, adn loops through them, whosing them in the table -->
                        <tr>
                            <td><?= htmlspecialchars($review['User_Username']) ?></td>
                            <td><?= htmlspecialchars($review['Contract_ID']) ?></td>
                            <td><?= htmlspecialchars($review['Review_Rating']) ?></td>
                            <td><?= htmlspecialchars($review['Review_Comment']) ?></td>
                            <td><?= htmlspecialchars($review['Review_Date']) ?></td>
                            <td><?= $review['Review_Approved'] == "Pending" ? "Pending" : "Approved" ?></td>
                            <td>
                                <?php if ($review['Review_Approved'] == 'Pending'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="reviewId" value="<?= $review['Review_ID'] ?>">
                                        <button type="submit" name="approveReview" class="btn approve-btn">Approve</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="reviewId" value="<?= $review['Review_ID'] ?>">
                                    <button type="submit" name="deleteReview" class="btn delete-btn">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="footer">
        <p style="font-size: 14px; margin-bottom: 0;">Anton Kovachev | © 2024</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$reviewsStmt->close();
$conn->close();
?>