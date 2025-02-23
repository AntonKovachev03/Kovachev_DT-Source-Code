<!-- the my profile page of the deliverer, shows them their account details and allows them to change them, as well as see all their vehicles, make changes to them, and add new ones -->
<?php
    session_start();

    if (!isset($_SESSION['User_Username']) || !isset($_SESSION['User_Role']) || ($_SESSION['User_Role'] !== 'Deliverer')) {
        header("Location: Unauthorized.php");
        exit();
    }

    $_SESSION['pressedPropose'] = "";

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

    $stmt = $conn->prepare("SELECT * FROM User WHERE User_Username = ? AND User_Role = 'Deliverer'");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $userResult = $stmt->get_result()->fetch_assoc();

    if (!$userResult) {
        die("Deliverer not found.");
    }

    $userUsername = $userResult['User_Username'];
    $userEmail = $userResult['User_Email'];
    $userPhone = $userResult['User_Phone'];

    $message = "";

    $vehicleQuery = "
        SELECT 
            v.Vehicle_Make, 
            v.Vehicle_Model, 
            v.Vehicle_Type, 
            v.Vehicle_CapacityM, 
            v.Vehicle_CapacityKG,
            g.Image_Path
        FROM 
            Vehicle v
        LEFT JOIN 
            Gallery g ON v.Vehicle_ID = g.Entity_ID
        WHERE 
            v.User_ID = ? AND g.Entity_Type = 'Vehicle'
    ";

    $stmt = $conn->prepare($vehicleQuery);
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $vehicleResult = $stmt->get_result();
    $vehicleList = $vehicleResult->fetch_all(MYSQLI_ASSOC);

    $stmt->close();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {// the updates for username, email, phone and password
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

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addVehicle'])) {
        $vehicleMake = $_POST['vehicleMake'];
        $vehicleModel = $_POST['vehicleModel'];
        $vehicleType = $_POST['vehicleType'];
        $capacityM = $_POST['capacityM'];
        $capacityKG = $_POST['capacityKG'];
    
        //insert the new vehicle into the database
        $stmt = $conn->prepare("
            INSERT INTO Vehicle (User_ID, Vehicle_Make, Vehicle_Model, Vehicle_Type, Vehicle_CapacityM, Vehicle_CapacityKG, Vehicle_UseStatus, Vehicle_Status)
            VALUES (?, ?, ?, ?, ?, ?, 'Available', 'Not Approved')
        ");
        $stmt->bind_param("isssdd", $userID, $vehicleMake, $vehicleModel, $vehicleType, $capacityM, $capacityKG);
        $stmt->execute();
        $vehicleId = $stmt->insert_id;
        $stmt->close();
    
        if (isset($_FILES['vehicleImage']) && $_FILES['vehicleImage']['error'] == 0) {
            $imagePath = "uploads/vehicles/vehicle_" . $vehicleId . "_" . basename($_FILES['vehicleImage']['name']);
            move_uploaded_file($_FILES['vehicleImage']['tmp_name'], $imagePath);
    
            $stmt = $conn->prepare("INSERT INTO Gallery (Entity_Type, Entity_ID, Image_Path) VALUES ('Vehicle', ?, ?)");
            $stmt->bind_param("is", $vehicleId, $imagePath);
            $stmt->execute();
            $stmt->close();
        }
    
        header("Location: Deliverer_MyProfile1.php");
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updateVehicle'])) {
        $vehicleId = $_POST['vehicleId'];
        $vehicleMake = $_POST['vehicleMake'];
        $vehicleModel = $_POST['vehicleModel'];
        $vehicleType = $_POST['vehicleType'];
        $capacityM = $_POST['capacityM'];
        $capacityKG = $_POST['capacityKG'];
    
        //update the vehicle in the database, but set its status to not approved
        $stmt = $conn->prepare("
            UPDATE Vehicle
            SET Vehicle_Make = ?, Vehicle_Model = ?, Vehicle_Type = ?, Vehicle_CapacityM = ?, Vehicle_CapacityKG = ?, Vehicle_Status = 'Not Approved'
            WHERE Vehicle_ID = ?
        ");
        $stmt->bind_param("sssddi", $vehicleMake, $vehicleModel, $vehicleType, $capacityM, $capacityKG, $vehicleId);
        $stmt->execute();
        $stmt->close();
    
        if (isset($_FILES['vehicleImage']) && $_FILES['vehicleImage']['error'] == 0) {
            $imagePath = "uploads/vehicles/vehicle_" . $vehicleId . "_" . basename($_FILES['vehicleImage']['name']);
            move_uploaded_file($_FILES['vehicleImage']['tmp_name'], $imagePath);
    
            $stmt = $conn->prepare("INSERT INTO Gallery (Entity_Type, Entity_ID, Image_Path) VALUES ('Vehicle', ?, ?)");
            $stmt->bind_param("is", $vehicleId, $imagePath);
            $stmt->execute();
            $stmt->close();
        }
    
        header("Location: Deliverer_MyProfile1.php");
        exit;
    }

    $vehiclesStmt = $conn->prepare("SELECT * FROM Vehicle WHERE User_ID = ?");
    $vehiclesStmt->bind_param("i", $userID);
    $vehiclesStmt->execute();
    $vehiclesResult = $vehiclesStmt->get_result();

    $delivererId = $_SESSION['User_ID'];

    //approved reviews for the deliverer
    $sql = "
        SELECT 
            R.Review_Rating, 
            R.Review_Comment, 
            R.Review_Date, 
            R.Review_Approved, 
            U.User_Username AS Client_Username
        FROM Review R
        JOIN Contract C ON R.Contract_ID = C.Contract_ID
        JOIN User U ON R.User_ID = U.User_ID
        WHERE C.Deliverer_ID = ? AND R.Review_Approved = 'Approved'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $delivererId);
    $stmt->execute();
    $reviewsResult = $stmt->get_result();

    $reviews = [];
    $totalRating = 0;
    $reviewCount = 0;

    while ($review = $reviewsResult->fetch_assoc()) {
        $reviews[] = $review;
        $totalRating += $review['Review_Rating'];
        $reviewCount++;
    }

    $averageRating = $reviewCount > 0 ? round($totalRating / $reviewCount, 1) : 0;

    $stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deliverer Profile</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

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
        .content-wrapper { max-width: 1200px; width: 100%; background: #f8f9fa; padding: 20px; border-radius: 10px; }
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

        .section {
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.1);
        }

        .table th, .table td {
            vertical-align: middle;
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
    <div class="banner">From-To Deliverer View</div>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="LoggedIn_Deliverer.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="Deliverer_Offers1.php">Offers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="Deliverer_MyProfile1.php">My Profile</a>
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
                        <p><strong>Username:</strong> <?= htmlspecialchars($userUsername) ?> 
                            <button class="btn btn-primary btn-sm" onclick="toggleForm('usernameForm')">Change</button>
                        </p>
                        <form method="POST" id="usernameForm" class="form-container">
                            <input type="text" name="new_username" class="form-control" placeholder="Enter new username">
                            <button type="submit" name="update_username" class="btn btn-success">Save</button>
                        </form>
                    </div>

                    <div>
                        <p><strong>Email:</strong> <?= htmlspecialchars($userEmail) ?> 
                            <button class="btn btn-primary btn-sm" onclick="toggleForm('emailForm')">Change</button>
                        </p>
                        <form method="POST" id="emailForm" class="form-container">
                            <input type="email" name="new_email" class="form-control" placeholder="Enter new email">
                            <button type="submit" name="update_email" class="btn btn-success">Save</button>
                        </form>
                    </div>

                    <div>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($userPhone) ?> 
                            <button class="btn btn-primary btn-sm" onclick="toggleForm('phoneForm')">Change</button>
                        </p>
                        <form method="POST" id="phoneForm" class="form-container">
                            <input type="text" name="new_phone" class="form-control" placeholder="Enter new phone">
                            <button type="submit" name="update_phone" class="btn btn-success">Save</button>
                        </form>
                    </div>

                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#passwordModal">Change Password</button>

                    <!-- the modal that shows when the user wants to update their password -->
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


                    <div class="section">
                    <h2>My Vehicles</h2>
                    <table class="table table-bordered table-striped table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th>Image</th>
                                <th>Make</th>
                                <th>Model</th>
                                <th>Type</th>
                                <th>Capacity (m³)</th>
                                <th>Carrying Capacity (kg)</th>
                                <th>In Use?</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($vehicle = $vehiclesResult->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php 
                                        //get the image from the gallery
                                        $stmt = $conn->prepare("SELECT Image_Path FROM Gallery WHERE Entity_Type = 'Vehicle' AND Entity_ID = ?");
                                        $stmt->bind_param("i", $vehicle['Vehicle_ID']);
                                        $stmt->execute();
                                        $imageResult = $stmt->get_result();
                                        $image = $imageResult->fetch_assoc();
                                        ?>
                                        <img src="<?= htmlspecialchars($image['Image_Path'] ?? 'uploads\default_vehicle.jpg') ?>" alt="No Vehicle Image" class="img-fluid" style="width: 100px; height: auto; object-fit: cover;">
                                    </td>
                                    <td><?= htmlspecialchars($vehicle['Vehicle_Make']) ?></td>
                                    <td><?= htmlspecialchars($vehicle['Vehicle_Model']) ?></td>
                                    <td><?= htmlspecialchars($vehicle['Vehicle_Type']) ?></td>
                                    <td><?= htmlspecialchars($vehicle['Vehicle_CapacityM']) ?></td>
                                    <td><?= htmlspecialchars($vehicle['Vehicle_CapacityKG']) ?></td>
                                    <td><?= htmlspecialchars($vehicle['Vehicle_UseStatus']) ?></td>
                                    <td><?= htmlspecialchars($vehicle['Vehicle_Status']) ?></td>
                                    <td>
                                        <?php if ($vehicle['Vehicle_UseStatus'] !== 'In Use'): ?>
                                            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#vehicleModal" 
                                                    data-bs-vehicle-id="<?= $vehicle['Vehicle_ID'] ?>"
                                                    data-bs-vehicle-make="<?= htmlspecialchars($vehicle['Vehicle_Make']) ?>"
                                                    data-bs-vehicle-model="<?= htmlspecialchars($vehicle['Vehicle_Model']) ?>"
                                                    data-bs-vehicle-type="<?= htmlspecialchars($vehicle['Vehicle_Type']) ?>"
                                                    data-bs-vehicle-capacitym="<?= htmlspecialchars($vehicle['Vehicle_CapacityM']) ?>"
                                                    data-bs-vehicle-capacitykg="<?= htmlspecialchars($vehicle['Vehicle_CapacityKG']) ?>">
                                                Change
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">Cannot Edit Vehicle in Use</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                    <!-- the modal that appears when the user wants to update their vehicles -->
                    <div class="modal fade" id="vehicleModal" tabindex="-1" aria-labelledby="vehicleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="vehicleModalLabel">Update Vehicle Information</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form method="POST" enctype="multipart/form-data" id="vehicleUpdateForm">
                                        <input type="hidden" name="vehicleId" id="vehicleId">

                                        <div class="form-group">
                                            <label for="vehicleMake">Vehicle Make:</label>
                                            <input type="text" id="vehicleMake" name="vehicleMake" class="form-control" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="vehicleModel">Vehicle Model:</label>
                                            <input type="text" id="vehicleModel" name="vehicleModel" class="form-control" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="vehicleType">Vehicle Type:</label>
                                            <input type="text" id="vehicleType" name="vehicleType" class="form-control" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="capacityM">Capacity (m³):</label>
                                            <input type="number" id="capacityM" name="capacityM" step="0.1" class="form-control" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="capacityKG">Carrying Capacity (kg):</label>
                                            <input type="number" id="capacityKG" name="capacityKG" class="form-control" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="vehicleImage">Upload Vehicle Image:</label>
                                            <input type="file" id="vehicleImage" name="vehicleImage" class="form-control-file" accept="image/*">
                                        </div>

                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <button type="submit" name="updateVehicle" class="btn btn-success">Update Vehicle</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                    Add Vehicle
                    </button>

                    <!-- the modal that appears when the user wants to add a vehicle -->
                    <div class="modal fade" id="addVehicleModal" tabindex="-1" aria-labelledby="addVehicleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="addVehicleModalLabel">Add a New Vehicle</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="addVehicle" value="1">

                                        <div class="form-group">
                                            <label for="vehicleMake">Vehicle Make:</label>
                                            <input type="text" id="vehicleMake" name="vehicleMake" class="form-control" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="vehicleModel">Vehicle Model:</label>
                                            <input type="text" id="vehicleModel" name="vehicleModel" class="form-control" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="vehicleType">Vehicle Type:</label>
                                            <input type="text" id="vehicleType" name="vehicleType" class="form-control" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="capacityM">Capacity (m³):</label>
                                            <input type="number" id="capacityM" name="capacityM" step="0.1" class="form-control" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="capacityKG">Carrying Capacity (kg):</label>
                                            <input type="number" id="capacityKG" name="capacityKG" class="form-control" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="vehicleImage">Upload Vehicle Image:</label>
                                            <input type="file" id="vehicleImage" name="vehicleImage" class="form-control-file" accept="image/*">
                                        </div>

                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <button type="submit" class="btn btn-primary">Add Vehicle</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="section mt-5">
                    <h2>Reviews Section</h2>

                    <div class="mb-4">
                        <h4 class="text-primary">Average Rating: 
                            <?php if ($reviewCount > 0): ?>
                                <span class="badge bg-success">
                                    <?= htmlspecialchars($averageRating) ?> / 5
                                </span>
                            <?php else: ?>
                                <span class="text-muted">No Reviews</span>
                            <?php endif; ?>
                        </h4>
                    </div>

                    <table class="table table-bordered table-striped table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th>Client Username</th>
                                <th>Rating</th>
                                <th>Comment</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($reviews) > 0): ?>
                                <?php foreach ($reviews as $review): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($review['Client_Username']) ?></td>
                                        <td>
                                            <span class="badge bg-warning text-dark">
                                                <?= htmlspecialchars($review['Review_Rating']) ?> / 5
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($review['Review_Comment'] ?: 'No comment provided') ?></td>
                                        <td><?= htmlspecialchars(date("F j, Y", strtotime($review['Review_Date']))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No reviews available.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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

        document.addEventListener('DOMContentLoaded', function () {
            const vehicleModal = document.getElementById('vehicleModal');

            vehicleModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget; 

                const vehicleId = button.getAttribute('data-bs-vehicle-id');
                const vehicleMake = button.getAttribute('data-bs-vehicle-make');
                const vehicleModel = button.getAttribute('data-bs-vehicle-model');
                const vehicleType = button.getAttribute('data-bs-vehicle-type');
                const vehicleCapacityM = button.getAttribute('data-bs-vehicle-capacitym');
                const vehicleCapacityKG = button.getAttribute('data-bs-vehicle-capacitykg');

                document.getElementById('vehicleId').value = vehicleId;
                document.getElementById('vehicleMake').value = vehicleMake;
                document.getElementById('vehicleModel').value = vehicleModel;
                document.getElementById('vehicleType').value = vehicleType;
                document.getElementById('capacityM').value = vehicleCapacityM;
                document.getElementById('capacityKG').value = vehicleCapacityKG;
            });
        });
    </script>
</body>
</html>