<!-- this page is not actually shown to the user, but for some reason it is required for the code to work, i don't know why-->
<?php
       session_start();
   
   // Check if the user is logged in
    if (!isset($_SESSION['User_Username']) || !isset($_SESSION['User_Role']) || !isset($_SESSION['User_ID']) || ($_SESSION['User_Role']=='Deliverer')) {
        // Redirect to the login page if not logged in
        header("Location: Unauthorized.php");
        exit();
}

    // Get user role from session for access control
    $userRole = $_SESSION['User_Role'];
    $userID = $_SESSION['User_ID'];

    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);


    $dbHost = "localhost";
    $dbName = "from_to";
    $dbUser = "root";
    $dbPass = "";

    // Establishing connection
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $clientID = $_SESSION['User_ID'];

    $userId = $_SESSION['User_ID']; // Assuming the user ID is stored in the session
    $currentContractsStmt = $conn->prepare("
        SELECT 
            c.Contract_ID, 
            c.Order_ID, 
            o.Order_Date, 
            c.Proposed_Cost, 
            c.Contract_Status,
            SUM(cg.Cargo_Weight) AS Total_Weight, 
            SUM(cg.Cargo_Dimensions) AS Total_Volume
        FROM 
            Contract c
        JOIN 
            `Order` o ON c.Order_ID = o.Order_ID
        JOIN 
            Order_Cargo oc ON o.Order_ID = oc.Order_ID
        JOIN 
            Cargo cg ON oc.Cargo_ID = cg.Cargo_ID
        LEFT JOIN 
            Delivery d ON c.Contract_ID = d.Contract_ID
        WHERE 
            o.User_ID = ? 
            AND c.Contract_Status IN ('Client Confirmed', 'Both Confirmed')
            AND d.Delivery_ID IS NULL  -- Exclude contracts with a delivery
        GROUP BY 
            c.Contract_ID
    ");
    $currentContractsStmt->bind_param("i", $userId);
    $currentContractsStmt->execute();
    $currentContractsResult = $currentContractsStmt->get_result();
    $currentContractsStmt->close();

    $contractsWithDeliveryStmt = $conn->prepare("
        SELECT 
            c.Contract_ID, 
            c.Order_ID, 
            o.Order_Date, 
            c.Proposed_Cost, 
            c.Contract_Status,
            d.Delivery_Status, 
            d.Delivery_Confirmed,
            d.Delivery_CurrentLocation
        FROM 
            Contract c
        JOIN 
            `Order` o ON c.Order_ID = o.Order_ID
        LEFT JOIN 
            Delivery d ON c.Contract_ID = d.Contract_ID
        WHERE 
            o.User_ID = ? 
            AND c.Contract_Status = 'Both Confirmed' 
            AND c.Contract_Approval = 'Approved'
    ");
    $contractsWithDeliveryStmt->bind_param("i", $userId);
    $contractsWithDeliveryStmt->execute();
    $contractsWithDeliveryResult = $contractsWithDeliveryStmt->get_result();
    $contractsWithDeliveryStmt->close();

    // Query to fetch pending orders with approval status
    $pendingOrdersQuery = "SELECT * FROM `Order` WHERE User_ID = ? AND Order_Status = 'Pending'";
    $stmt = $conn->prepare($pendingOrdersQuery);
    $stmt->bind_param("i", $clientID);
    $stmt->execute();
    $pendingOrdersResult = $stmt->get_result();

    // Query to fetch delivered orders with approval status
    $deliveredOrdersQuery = "SELECT * FROM `Order` WHERE User_ID = ? AND Order_Status = 'Completed'";
    $stmt = $conn->prepare($deliveredOrdersQuery);
    $stmt->bind_param("i", $clientID);
    $stmt->execute();
    $deliveredOrdersResult = $stmt->get_result();

// Query to fetch delivered orders
$deliveredOrdersQuery = "SELECT * FROM `Order` WHERE User_ID = ? AND Order_Status = 'Completed'";
$stmt = $conn->prepare($deliveredOrdersQuery);
$stmt->bind_param("i", $clientID);
$stmt->execute();
$deliveredOrdersResult = $stmt->get_result();

// Query to get pending contracts where the associated Order is still "Pending"
$pendingContractsQuery = "
    SELECT c.Contract_ID, c.Order_ID, c.Contract_Status, c.Proposed_Cost, 
           u.User_Username AS Deliverer_Name, o.Order_Status
    FROM Contract c
    JOIN `Order` o ON c.Order_ID = o.Order_ID
    JOIN User u ON c.Deliverer_ID = u.User_ID
    WHERE o.User_ID = ? AND o.Order_Status = 'Pending'AND c.Contract_Status = 'Deliverer Confirmed'"; // Using o.User_ID for client

$stmt = $conn->prepare($pendingContractsQuery);
$stmt->bind_param("i", $clientID);  // Bind the client's User_ID to the query
$stmt->execute();
$pendingContractsResult = $stmt->get_result();
$stmt->close();

    // Handle Confirm Delivery
    if (isset($_POST['confirmDelivery'])) {
        $contractId = $_POST['contractId'];

        // Start a transaction to ensure all updates are made atomically
        $conn->begin_transaction();

        try {
            // 1. Update the Delivery_Confirmed status
            $confirmDeliveryStmt = $conn->prepare("
                UPDATE Delivery
                SET Delivery_Confirmed = 'Confirmed'
                WHERE Contract_ID = ?
            ");
            $confirmDeliveryStmt->bind_param("i", $contractId);
            $confirmDeliveryStmt->execute();
            $confirmDeliveryStmt->close();

            // 2. Update the Order_Status to 'Completed' (associated with the Contract)
            $updateOrderStmt = $conn->prepare("
                UPDATE `Order`
                SET Order_Status = 'Completed'
                WHERE Order_ID = (SELECT Order_ID FROM Contract WHERE Contract_ID = ?)
            ");
            $updateOrderStmt->bind_param("i", $contractId);
            $updateOrderStmt->execute();
            $updateOrderStmt->close();

            // 3. Optionally, update the Contract_Status to 'Completed' (if desired)
            $updateContractStmt = $conn->prepare("
                UPDATE Contract
                SET Contract_Status = 'Completed'
                WHERE Contract_ID = ?
            ");
            $updateContractStmt->bind_param("i", $contractId);
            $updateContractStmt->execute();
            $updateContractStmt->close();

            // Commit the transaction
            $conn->commit();

            // Optionally, you could redirect the user after confirming the delivery
            header("Location: Client_MyCargo.php");
            exit;
        } catch (Exception $e) {
            // If any of the queries fail, roll back the transaction
            $conn->rollback();
            // Handle error (e.g., show an error message)
            echo "Error: " . $e->getMessage();
        }
    }

if (isset($_POST['proposeDeliverer'])) {
    $orderID = $_POST['orderID'];
    $delivererID = $_POST['delivererID'];
    $proposedCost = $_POST['proposedCost'];

    // Prepare and execute the SQL statement to insert the contract
    $sql = "INSERT INTO Contract (Order_ID, Deliverer_ID, Proposed_Cost, Contract_Status, Created_Date)
            VALUES (?, ?, ?, 'Client Confirmed', NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iid", $orderID, $delivererID, $proposedCost);

    // Check if the query was executed successfully
    if ($stmt->execute()) {
        echo "Contract created successfully.";
    } else {
        echo "Error: " . $conn->error;
    }

    $stmt->close();
}

if (isset($_POST['getDelivererDetails'])) {
    $delivererID = $_POST['delivererID'];

    // Fetch Deliverer details
    $delivererQuery = "
        SELECT u.User_Username, u.User_Email, u.User_Phone
        FROM User u
        WHERE u.User_ID = ? AND u.User_Role = 'Deliverer'
    ";

    $stmt = $conn->prepare($delivererQuery);
    $stmt->bind_param("i", $delivererID);
    $stmt->execute();
    $delivererDetails = $stmt->get_result()->fetch_assoc();

    // Fetch Deliverer's vehicles
    $vehiclesQuery = "
        SELECT v.Vehicle_Make, v.Vehicle_Model, v.Vehicle_Type, g.Image_Path
        FROM Vehicle v
        LEFT JOIN Gallery g ON g.Entity_Type = 'Vehicle' AND g.Entity_ID = v.Vehicle_ID
        WHERE v.User_ID = ? AND v.Vehicle_Status = 'Approved'
    ";

    $stmtVehicles = $conn->prepare($vehiclesQuery);
    $stmtVehicles->bind_param("i", $delivererID);
    $stmtVehicles->execute();
    $vehiclesResult = $stmtVehicles->get_result();

    $vehicles = [];
    while ($vehicle = $vehiclesResult->fetch_assoc()) {
        $vehicles[] = [
            'make' => $vehicle['Vehicle_Make'],
            'model' => $vehicle['Vehicle_Model'],
            'type' => $vehicle['Vehicle_Type'],
            'image' => $vehicle['Image_Path'] // Path to the image
        ];
    }

    if ($delivererDetails) {
        echo json_encode([
            'username' => $delivererDetails['User_Username'],
            'email' => $delivererDetails['User_Email'],
            'phone' => $delivererDetails['User_Phone'],
            'vehicles' => $vehicles
        ]);
    } else {
        echo json_encode(['error' => 'Deliverer details not found']);
    }
    exit;
}

// Handle Find Deliverers Request (AJAX)
if (isset($_POST['findDeliverers'])) {
    $orderID = $_POST['orderID'];

    // Fetch Order details (total weight and volume)
    $orderDetailsQuery = "
        SELECT SUM(c.Cargo_Weight) AS TotalWeight, SUM(c.Cargo_Dimensions) AS TotalDimensions 
        FROM Order_Cargo oc
        JOIN Cargo c ON oc.Cargo_ID = c.Cargo_ID
        WHERE oc.Order_ID = ?";
    $stmt = $conn->prepare($orderDetailsQuery);
    $stmt->bind_param("i", $orderID);
    $stmt->execute();
    $orderDetails = $stmt->get_result()->fetch_assoc();

    $totalWeight = $orderDetails['TotalWeight'];
    $totalDimensions = $orderDetails['TotalDimensions'];

    // Find Deliverers who match the order and are not already in contracts
    $delivererQuery = "
        SELECT DISTINCT u.User_ID AS Deliverer_ID, u.User_Username, u.Review_IDs
        FROM User u
        JOIN Vehicle v ON u.User_ID = v.User_ID
        WHERE u.User_Role = 'Deliverer'
        AND v.Vehicle_UseStatus = 'Available'
        AND v.Vehicle_Status = 'Approved'
        AND u.User_ID NOT IN (
            SELECT Deliverer_ID
            FROM Contract
            WHERE Order_ID = ?
        )
    ";

    $stmt = $conn->prepare($delivererQuery);
    $stmt->bind_param("i", $orderID);
    $stmt->execute();
    $matchingDeliverers = $stmt->get_result();

    // Check if any deliverers are found
    if ($matchingDeliverers->num_rows > 0) {
        ob_start();

        // For each deliverer who matches the criteria
        while ($deliverer = $matchingDeliverers->fetch_assoc()) {
            $delivererID = $deliverer['Deliverer_ID'];
            $reviewIDs = $deliverer['Review_IDs'];

            // Calculate average rating for the deliverer
            $averageRating = "N/A";
            if ($reviewIDs) {
                $reviewIdsArray = explode(',', $reviewIDs);  // Convert the comma-separated list into an array
                $placeholders = implode(',', array_fill(0, count($reviewIdsArray), '?'));  // Create placeholders for the SQL query

                $reviewStmt = $conn->prepare("SELECT Review_Rating FROM Review WHERE Review_ID IN ($placeholders)");
                $types = str_repeat('i', count($reviewIdsArray));  // 'i' for integer
                $reviewStmt->bind_param($types, ...$reviewIdsArray);
                $reviewStmt->execute();
                $reviewResult = $reviewStmt->get_result();

                $totalScore = 0;
                $reviewCount = 0;

                while ($review = $reviewResult->fetch_assoc()) {
                    $totalScore += $review['Review_Rating'];
                    $reviewCount++;
                }

                if ($reviewCount > 0) {
                    $averageRating = number_format($totalScore / $reviewCount, 2);  // Format to 2 decimal places
                }

                $reviewStmt->close();
            }

            // Get vehicle capacities
            $vehicleCapacityQuery = "
                SELECT SUM(v.Vehicle_CapacityKG) AS TotalVehicleCapacityKG, 
                    SUM(v.Vehicle_CapacityM) AS TotalVehicleCapacityM
                FROM Vehicle v
                WHERE v.User_ID = ? 
                AND v.Vehicle_UseStatus = 'Available' 
                AND v.Vehicle_Status = 'Approved'";

            $stmtVehicle = $conn->prepare($vehicleCapacityQuery);
            $stmtVehicle->bind_param("i", $delivererID);
            $stmtVehicle->execute();
            $vehicleCapacities = $stmtVehicle->get_result()->fetch_assoc();

            $totalVehicleCapacityKG = $vehicleCapacities['TotalVehicleCapacityKG'];
            $totalVehicleCapacityM = $vehicleCapacities['TotalVehicleCapacityM'];

            // Check if the vehicle capacity meets or exceeds the order's weight and volume
            if ($totalVehicleCapacityKG >= $totalWeight && $totalVehicleCapacityM >= $totalDimensions) {
                // Wrap each deliverer's form in a container with a unique ID
                echo '<div id="delivererFormContainer_' . htmlspecialchars($delivererID, ENT_QUOTES, 'UTF-8') . '">';
                echo '<h2>Matching Deliverer Found</h2>';
                echo '<a href="javascript:void(0);" onclick="fetchDelivererDetails(' . htmlspecialchars($delivererID, ENT_QUOTES, 'UTF-8') . ')">';
                echo '<p>Deliverer Name: ' . htmlspecialchars($deliverer['User_Username'], ENT_QUOTES, 'UTF-8') . '</p>';
                echo '</a>';
                echo '<p>Average Rating: ' . htmlspecialchars($averageRating, ENT_QUOTES, 'UTF-8') . '</p>';
                echo '<p>Total Vehicle Capacity: ' . htmlspecialchars($totalVehicleCapacityKG, ENT_QUOTES, 'UTF-8') . "kg, " . htmlspecialchars($totalVehicleCapacityM, ENT_QUOTES, 'UTF-8') . "m³</p>";
                
                // Input and button for proposing a contract
                echo '<label for="proposedCost_' . htmlspecialchars($delivererID, ENT_QUOTES, 'UTF-8') . '">Proposed Cost:</label>';
                echo '<input type="number" id="proposedCost_' . htmlspecialchars($delivererID, ENT_QUOTES, 'UTF-8') . '" name="proposedCost" step="0.01" required>';
                echo '<button type="button" onclick="proposeContract(' . htmlspecialchars($orderID, ENT_QUOTES, 'UTF-8') . ', ' . htmlspecialchars($delivererID, ENT_QUOTES, 'UTF-8') . ', document.getElementById(\'proposedCost_' . htmlspecialchars($delivererID, ENT_QUOTES, 'UTF-8') . '\').value)">Propose Contract</button>';
                echo '</div>';
            }
        }

        $output = ob_get_clean();
        echo $output;
    } else {
        echo '<p>No matching Deliverers found for this Order.</p>';
    }

    exit;
}

// Handle form submission for adding cargo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addCargo'])) {
    // First, create the Order record for this Client
    $insertOrderQuery = "INSERT INTO `Order` (User_ID, Order_Date, Order_Status) VALUES (?, NOW(), 'Pending')";
    $stmt = $conn->prepare($insertOrderQuery);
    $stmt->bind_param("i", $clientID);
    $stmt->execute();
    $orderID = $stmt->insert_id; // Get the Order_ID of the newly created order
    $stmt->close();

    // Check if cargo data is provided
    if (isset($_POST['cargoDescription']) && isset($_FILES['cargoImage'])) {
        foreach ($_POST['cargoDescription'] as $index => $description) {
            $cargoDescription = $description;
            $cargoWeight = $_POST['cargoWeight'][$index];
            $cargoDimensions = $_POST['cargoDimensions'][$index];

            // Insert Cargo into the Cargo table
            $insertCargoQuery = "
                INSERT INTO Cargo (User_ID, Cargo_Description, Cargo_Weight, Cargo_Dimensions) 
                VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($insertCargoQuery);
            $stmt->bind_param("isss", $clientID, $cargoDescription, $cargoWeight, $cargoDimensions);
            $stmt->execute();
            $cargoID = $stmt->insert_id; // Get the inserted Cargo_ID
            $stmt->close();

            // Insert into Order_Cargo to link Cargo with the Order
            $insertOrderCargoQuery = "INSERT INTO Order_Cargo (Order_ID, Cargo_ID) VALUES (?, ?)";
            $stmt = $conn->prepare($insertOrderCargoQuery);
            $stmt->bind_param("ii", $orderID, $cargoID);
            $stmt->execute();
            $stmt->close();

            // Handle image upload for each cargo item
            if (isset($_FILES['cargoImage']['name'][$index]) && $_FILES['cargoImage']['error'][$index] === 0) {
                $imageTmpName = $_FILES['cargoImage']['tmp_name'][$index];
                $imageName = basename($_FILES['cargoImage']['name'][$index]);
                $imageFolder = "uploads/cargo_images/";
                $imagePath = $imageFolder . "cargo_" . $cargoID . "_" . uniqid() . "_" . $imageName;

                // Ensure the upload directory exists
                if (!is_dir($imageFolder)) {
                    mkdir($imageFolder, 0777, true);
                }

                // Move the uploaded file to the designated directory
                if (move_uploaded_file($imageTmpName, $imagePath)) {
                    // Insert the image into the Gallery table
                    $insertGalleryQuery = "INSERT INTO Gallery (Entity_Type, Entity_ID, Image_Path) VALUES ('Cargo', ?, ?)";
                    $stmtGallery = $conn->prepare($insertGalleryQuery);
                    $stmtGallery->bind_param("is", $cargoID, $imagePath);
                    $stmtGallery->execute();
                    $stmtGallery->close();
                } else {
                    // Handle image upload failure
                    echo "Failed to upload image for cargo item: " . $imageName;
                }
            }
        }
    }

    // Redirect to another page or show a success message after submitting
    header('Location: Client_MyCargo.php');
    exit;
}

// Handle form submission for reviews
if (isset($_POST['submitReview'])) {
    // Get the form data
    $contractId = $_POST['contractId'];
    $userId = $_SESSION['user_id']; // Assuming the user is logged in and user ID is stored in session
    $reviewRating = $_POST['reviewRating'];
    $reviewComment = $_POST['reviewComment'];

    // Insert the review into the database
    $insertReviewStmt = $conn->prepare("
        INSERT INTO Review (Contract_ID, User_ID, Review_Rating, Review_Comment, Review_Approved)
        VALUES (?, ?, ?, ?, 'Pending')  -- Default Review_Approved to 'Pending'
    ");
    
    // Bind parameters
    $insertReviewStmt->bind_param("iiis", $contractId, $clientID, $reviewRating, $reviewComment);

    // Execute the statement
    if ($insertReviewStmt->execute()) {
        // Review successfully inserted, show success message or reload the page
        echo "Review submitted successfully!";
    } else {
        // Handle error
        echo "Error submitting review: " . $insertReviewStmt->error;
    }

    // Close the statement
    $insertReviewStmt->close();

    // Optionally redirect after review submission
    header("Location: Client_MyCargo.php");
    exit;
}

// Handle Accept/Reject/Haggle Contract
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contractID = $_POST['contractId'];

    if (isset($_POST['acceptContract'])) {
        // Accept the contract
        $updateContractStmt = $conn->prepare("UPDATE Contract SET Contract_Status = 'Both Confirmed' WHERE Contract_ID = ?");
        $updateContractStmt->bind_param("i", $contractID);
        $updateContractStmt->execute();

        $removeOtherContractsStmt = $conn->prepare("
            DELETE FROM Contract 
            WHERE Order_ID = ? AND Contract_ID != ? 
        ");
        $removeOtherContractsStmt->bind_param("ii", $orderID, $contractID);
        $removeOtherContractsStmt->execute();
        $removeOtherContractsStmt->close();

        // Update associated Order to Active
        $orderID = $_POST['orderId']; // Assuming order ID is passed in the form
        $updateOrderStmt = $conn->prepare("UPDATE `Order` SET Order_Status = 'Active' WHERE Order_ID = ?");
        $updateOrderStmt->bind_param("i", $orderID);
        $updateOrderStmt->execute();

    } elseif (isset($_POST['rejectContract'])) {
        // Reject the contract
        $vehicleIDsQuery = "SELECT Vehicle_IDs FROM Contract WHERE Contract_ID = ?";
        $stmt = $conn->prepare($vehicleIDsQuery);
        $stmt->bind_param("i", $contractID);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row) {
            $vehicleIDs = $row['Vehicle_IDs'];
            if (!empty($vehicleIDs)) {
                $vehicleIDsArray = explode(',', $vehicleIDs);
                $placeholders = implode(',', array_fill(0, count($vehicleIDsArray), '?'));
                $updateVehicleQuery = "UPDATE Vehicle SET Vehicle_UseStatus = 'Available' WHERE Vehicle_ID IN ($placeholders)";
                $stmt = $conn->prepare($updateVehicleQuery);
                $stmt->bind_param(str_repeat('i', count($vehicleIDsArray)), ...$vehicleIDsArray);
                $stmt->execute();
                $stmt->close();
            }
        }

        $deleteContractStmt = $conn->prepare("DELETE FROM Contract WHERE Contract_ID = ?");
        $deleteContractStmt->bind_param("i", $contractID);
        $deleteContractStmt->execute();
        $deleteContractStmt->close();

        $orderID = $_POST['orderId'];
        $updateOrderStmt = $conn->prepare("UPDATE `Order` SET Order_Status = 'Pending' WHERE Order_ID = ?");
        $updateOrderStmt->bind_param("i", $orderID);
        $updateOrderStmt->execute();
        $updateOrderStmt->close();

        header("Location: Client_MyCargo.php");
        exit;

    } elseif (isset($_POST['haggleContract'])) {
        $contractID = $_POST['contractId'];
        $newProposedCost = $_POST['newProposedCost'];  // This is the new proposed cost input by the client
    
        // Check if the new proposed cost is a valid positive number
        if (!is_numeric($newProposedCost) || $newProposedCost <= 0) {
            echo "<p>Error: Please enter a valid positive cost.</p>";
            exit;
        }
    
        // Retrieve the current vehicles associated with the contract
        $vehicleQuery = "SELECT Vehicle_IDs FROM Contract WHERE Contract_ID = ?";
        $stmt = $conn->prepare($vehicleQuery);
        $stmt->bind_param("i", $contractID);
        $stmt->execute();
        $vehicleResult = $stmt->get_result();
        $vehicleData = $vehicleResult->fetch_assoc();
        $stmt->close();
    
        if (!empty($vehicleData['Vehicle_IDs'])) {
            $vehicleIDs = explode(',', $vehicleData['Vehicle_IDs']); // Convert CSV to array
    
            // Update the UseStatus of the vehicles back to 'Available'
            $vehiclePlaceholders = implode(',', array_fill(0, count($vehicleIDs), '?'));
            $updateVehiclesQuery = "UPDATE Vehicle SET Vehicle_UseStatus = 'Available' WHERE Vehicle_ID IN ($vehiclePlaceholders)";
            $stmt = $conn->prepare($updateVehiclesQuery);
            $stmt->bind_param(str_repeat('i', count($vehicleIDs)), ...$vehicleIDs);
            $stmt->execute();
            $stmt->close();
        }
    
        // Update the Proposed Cost and clear the Vehicle_IDs in the Contract
        $updateContractQuery = "UPDATE Contract SET Proposed_Cost = ?, Contract_Status = 'Client Confirmed', Vehicle_IDs = NULL WHERE Contract_ID = ?";
        $stmt = $conn->prepare($updateContractQuery);
        $stmt->bind_param("di", $newProposedCost, $contractID);
        $stmt->execute();
        $stmt->close();
    
        echo "<p>Your new cost proposal has been sent. Awaiting deliverer confirmation.</p>";
    }

    header("Location: Client_MyCargo.php");
    exit;
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Client My Cargo</title>

    <style>
        ul.navigation {
            list-style-type: none;
            margin: 0;
            padding: 0;
            overflow: hidden;
            background-color: #333;
        }

        ul.navigation li {
            float: left;
        }

        ul.navigation li a {
            display: inline-block;
            color: white;
            text-align: center;
            padding: 14px 16px;
            text-decoration: none;
        }

        ul.navigation li a:hover:not(.active) {
            background-color: #000;
        }

        ul.navigation li a.active {
            background-color: #04AA6D;
        }

        .cargo-section {
            margin-top: 20px;
        }

        .cargo-item {
            margin-bottom: 10px;
        }

        .cargo-form {
            margin-top: 20px;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            background-color: #f9f9f9;
        }

        .cargo-form input,
        .cargo-form textarea {
            width: 100%;
            margin: 5px 0;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }

        .cargo-form button {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 4px;
        }

        .cargo-form button:hover {
            background-color: #45a049;
        }

        .cargo-form .remove-cargo {
            color: #ff0000;
            cursor: pointer;
            margin-top: 10px;
        }

        .cargo-form .remove-cargo:hover {
            text-decoration: underline;
        }

        .cargo-section h2 {
            font-size: 1.6em;
            margin-bottom: 10px;
        }

        .cargo-form button.add-cargo-btn {
            background-color: #008CBA;
        }

        .cargo-form button.add-cargo-btn:hover {
            background-color: #007B9A;
        }

        .submit-cargo-btn {
            display: none;
            margin-top: 10px;
        }

        .cargo-form .submit-visible {
            display: inline-block;
        }
    </style>

    <!-- CSS for Modal -->
<style>
/* Modal Styles */
.review-modal {
    display: none; /* Hidden by default */
    position: fixed; /* Stay in place */
    z-index: 1; /* Sit on top */
    left: 0;
    top: 0;
    width: 100%; /* Full width */
    height: 100%; /* Full height */
    background-color: rgba(0, 0, 0, 0.5); /* Black with opacity */
    padding-top: 60px;
}

.review-modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%; /* Width of the modal */
    max-width: 500px;
    position: relative;
}

.close-btn {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    position: absolute;
    top: 10px;
    right: 25px;
    padding: 0;
    cursor: pointer;
}

.close-btn:hover,
.close-btn:focus {
    color: black;
    text-decoration: none;
    cursor: pointer;
}

form {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

form label {
    font-weight: bold;
}

form input, form textarea {
    padding: 10px;
    margin-top: 5px;
}

form button {
    padding: 10px;
    background-color: #4CAF50;
    color: white;
    border: none;
    cursor: pointer;
}

form button:hover {
    background-color: #45a049;
}

#delivererModal img {
        max-width: 100%;
        height: auto;
        margin: 10px 0;
    }

    #delivererModal {
        display: none; 
        position: fixed; 
        top: 0; 
        left: 0; 
        width: 100%; 
        height: 100%; 
        background: rgba(0, 0, 0, 0.5);
        overflow: auto; /* Enable scrolling for the modal */
        z-index: 1000; /* Ensure it is above other content */
    }

    #delivererModal > div {
        background: white; 
        margin: 5% auto; 
        padding: 20px; 
        width: 90%; /* Adjust width for smaller screens */
        max-width: 600px; /* Limit the width on larger screens */
        border-radius: 10px; 
        position: relative;
        overflow: hidden; /* Prevent content overflow inside the modal */
    }

    #modalContent {
        max-height: 70vh; /* Limit height of modal content */
        overflow-y: auto; /* Enable vertical scrolling for content */
    }

    #closeModal {
        position: absolute; 
        top: 10px; 
        right: 10px; 
        cursor: pointer; 
        font-size: 20px;
    }

    #delivererModal img {
        max-width: 100%; 
        height: auto; 
        margin: 10px 0;
    }
</style>
</head>
<body>
    <div id="delivererModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div style="background:white; margin:5% auto; padding:20px; width:50%; border-radius:10px; position:relative;">
            <span id="closeModal" style="position:absolute; top:10px; right:10px; cursor:pointer; font-size:20px;">&times;</span>
            <h2>Deliverer Details</h2>
            <div id="modalContent"></div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <ul class="navigation">
        <li><a href="LoggedIn_Client.php">Home</a></li>
        <li><a href="Client_MyCargo.php" class="active">My Cargo</a></li>
        <li><a href="Client_MyProfile.php">My Profile</a></li>
        <li><a href="About.php">About</a></li>
        <li style="float:right"><a href="LogOut.php">Log Out</a></li>
    </ul>

    <h1>Your Cargo Dashboard</h1>

    <div class="cargo-section">
        <h2>Current Contracts (With Deliveries)</h2>
        <?php if ($contractsWithDeliveryResult->num_rows > 0): ?>
            <ul>
                <?php while ($row = $contractsWithDeliveryResult->fetch_assoc()): ?>
                    <li>
                        <strong>Contract ID:</strong> <?php echo $row['Contract_ID']; ?> |
                        <strong>Order ID:</strong> <?php echo $row['Order_ID']; ?> |
                        <strong>Cost:</strong> $<?php echo $row['Proposed_Cost']; ?> |
                        <strong>Status:</strong> <?php echo $row['Contract_Status']; ?>

                        <!-- Show Delivery Details -->
                        <?php if ($row['Delivery_Status']): ?>
                            <div class="delivery-details">
                                <p><strong>Delivery Status:</strong> <?= $row['Delivery_Status']; ?></p>
                                <p><strong>Current Location:</strong> <?= $row['Delivery_CurrentLocation'] ?: 'Not Available'; ?></p>

                                <?php if ($row['Delivery_Status'] == "Cargo Delivered" && $row['Delivery_Confirmed'] != "Confirmed"): ?>
                                    <!-- Confirm Completion Button -->
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="contractId" value="<?= $row['Contract_ID']; ?>">
                                        <button type="submit" name="confirmDelivery" class="btn confirm-btn">Confirm Completion</button>
                                    </form>
                                <?php elseif ($row['Delivery_Confirmed'] == "Confirmed"): ?>
                                    <p><strong>Delivery Confirmation:</strong> Confirmed</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p>No contracts with delivery found.</p>
        <?php endif; ?>
    </div>

    <!-- Current Contracts Section -->
    <div class="cargo-section">
        <h2>Current Contracts (Without Deliveries)</h2>
        <?php if ($currentContractsResult->num_rows > 0): ?>
            <ul>
                <?php while ($row = $currentContractsResult->fetch_assoc()): ?>
                    <li>
                        <strong>Contract ID:</strong> <?php echo $row['Contract_ID']; ?> |
                        <strong>Order ID:</strong> <?php echo $row['Order_ID']; ?> |
                        <strong>Total Weight:</strong> <?php echo $row['Total_Weight']; ?> kg |
                        <strong>Total Volume:</strong> <?php echo $row['Total_Volume']; ?> m³ |
                        <strong>Order Date:</strong> <?php echo $row['Order_Date']; ?> |
                        <strong>Cost:</strong> $<?php echo $row['Proposed_Cost']; ?> |
                        <strong>Status:</strong> <?php echo $row['Contract_Status']; ?>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p>No current contracts found.</p>
        <?php endif; ?>
    </div>

    <!-- Pending Contracts Section -->
        <div class="cargo-section">
        <h2>Pending Contracts</h2>
        <?php if ($pendingContractsResult->num_rows > 0): ?>
            <ul>
                <?php while ($row = $pendingContractsResult->fetch_assoc()): ?>
                    <li>
                        <strong>Contract ID:</strong> <?php echo $row['Contract_ID']; ?><br>
                        <strong>Order ID:</strong> <?php echo $row['Order_ID']; ?><br>
                        <strong>Deliverer:</strong> <?php echo $row['Deliverer_Name']; ?><br>
                        <strong>Status:</strong> <?php echo $row['Contract_Status']; ?><br>
                        <strong>Proposed Cost:</strong> $<?php echo number_format($row['Proposed_Cost'], 2); ?><br>
                        
                        <form method="POST" action="Client_MyCargo.php">
                            <input type="hidden" name="contractId" value="<?php echo $row['Contract_ID']; ?>">
                            <input type="hidden" name="orderId" value="<?php echo $row['Order_ID']; ?>">

                            <button type="submit" name="acceptContract">Accept</button>
                            <button type="submit" name="rejectContract">Reject</button>
                            <!-- Haggle Button -->
                            <button type="button" class="show-haggle-btn" data-contract-id="<?php echo $row['Contract_ID']; ?>">Haggle</button>
                            
                            <!-- Haggle Section (Hidden by Default) -->
                            <div class="haggle-section" id="haggle-<?php echo $row['Contract_ID']; ?>" style="display: none;">
                                <label for="newProposedCost-<?php echo $row['Contract_ID']; ?>">New Proposed Cost:</label>
                                <input type="number" name="newProposedCost" id="newProposedCost-<?php echo $row['Contract_ID']; ?>" step="0.01" min="0" placeholder="Enter new cost">
                                <button type="submit" name="haggleContract">Submit</button>
                            </div>
                        </form>
                    </li>
                    <hr>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p>You have no pending contracts.</p>
        <?php endif; ?>
    </div>

<script>
    // JavaScript to handle show/hide of the haggle section
    document.querySelectorAll('.show-haggle-btn').forEach(button => {
        button.addEventListener('click', () => {
            const contractId = button.dataset.contractId;
            const haggleSection = document.getElementById(`haggle-${contractId}`);
            
            // Toggle the visibility of the haggle section
            haggleSection.style.display = haggleSection.style.display === 'none' ? 'block' : 'none';
        });
    });
</script>

    <!-- Current Orders Section -->
    <div class="cargo-section">
        <h2>Current Orders (Pending Approval)</h2>
        <?php if ($pendingOrdersResult->num_rows > 0): ?>
            <ul>
                <?php while ($row = $pendingOrdersResult->fetch_assoc()): ?>
                    <li>
                        Order ID: <?php echo $row['Order_ID']; ?> | Date: <?php echo $row['Order_Date']; ?> |
                        Status: <?php echo $row['Order_Status']; ?>
                        <?php if ($row['Order_Approved'] == 'Approved'): ?>
                            <form method="POST" class="findDeliverersForm" onsubmit="return false;">
                                <input type="hidden" name="orderID" value="<?php echo $row['Order_ID']; ?>">
                                <button type="button" onclick="toggleDeliverers(<?php echo $row['Order_ID']; ?>, this)">Find Deliverers</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p>You have no current orders.</p>
        <?php endif; ?>
    </div>
    <!-- JavaScript Section for Dynamic Content -->
    <script>
        function toggleDeliverers(orderID, button) 
        {
        // Check if the container for matching deliverers exists
        var container = document.getElementById('matchingDeliverersContainer_' + orderID);

        // If it doesn't exist, create it
        if (!container) {
            // Create a new container for this order
            container = document.createElement('div');
            container.id = 'matchingDeliverersContainer_' + orderID;
            container.setAttribute('data-order-id', orderID);
            button.parentElement.appendChild(container);
        }

        // Toggle visibility of the container
        if (container.style.display === 'block') {
            container.style.display = 'none';
            button.textContent = 'Find Deliverers';
        } else {
            container.style.display = 'block';
            button.textContent = 'Hide Deliverers';

            // Clear any previous content
            container.innerHTML = "<p>Loading matching deliverers...</p>";

            // Fetch the deliverers data
            findDeliverers(orderID, container);
        }
    }

    function findDeliverers(orderID, container) {
        var formData = new FormData();
        formData.append('findDeliverers', true);
        formData.append('orderID', orderID);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'Client_MyCargo.php', true);

        xhr.onload = function() {
            if (xhr.status === 200) {
                container.innerHTML = xhr.responseText;  // Add the deliverer list with forms

                // Optionally, handle form submission here if using AJAX
            } else {
                container.innerHTML = '<p>Request failed with status: ' + xhr.status + '</p>';
            }
        };

        xhr.send(formData);
    }

    function fetchDelivererDetails(delivererID) {
    fetch('Client_MyCargo.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            getDelivererDetails: true,
            delivererID: delivererID
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
            } else {
                // Prepare content for the modal
                let content = `<p><strong>Username:</strong> ${data.username}</p>
                               <p><strong>Email:</strong> ${data.email}</p>
                               <p><strong>Phone:</strong> ${data.phone}</p>
                               <h3>Vehicles:</h3>`;
                if (data.vehicles && data.vehicles.length > 0) {
                    data.vehicles.forEach(vehicle => {
                        content += `<div>
                                        <p>Make: ${vehicle.make}, Model: ${vehicle.model}, Type: ${vehicle.type}</p>`;
                        if (vehicle.image) {
                            content += `<img src="${vehicle.image}" alt="Vehicle Image">`;
                        }
                        content += `</div>`;
                    });
                } else {
                    content += `<p>No vehicles found for this deliverer.</p>`;
                }

                // Show the modal
                document.getElementById('modalContent').innerHTML = content;
                document.getElementById('delivererModal').style.display = 'block';

                // Add close functionality
                document.getElementById('closeModal').onclick = () => {
                    document.getElementById('delivererModal').style.display = 'none';
                };

                // Close when clicking outside the modal
                window.onclick = (event) => {
                    if (event.target.id === 'delivererModal') {
                        document.getElementById('delivererModal').style.display = 'none';
                    }
                };
            }
        })
        .catch(error => console.error('Error:', error));
}
    // Example AJAX function to propose a contract
    function proposeContract(orderID, delivererID, proposedCost) {
    if (!proposedCost || proposedCost <= 0) {
        alert('Please enter a valid proposed cost.');
        return;
    }

    var formData = new FormData();
    formData.append('orderID', orderID);
    formData.append('delivererID', delivererID);
    formData.append('proposedCost', proposedCost);
    formData.append('proposeDeliverer', true); // This triggers the PHP code for contract proposal

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'Client_MyCargo.php', true);

    xhr.onload = function() {
        if (xhr.status === 200) {
            // No alert, just refresh the page to show the updated contracts
            var formContainer = document.getElementById('delivererFormContainer_' + delivererID);
            if (formContainer) {
                formContainer.innerHTML = '<p>Contract proposed successfully!</p>';
            }

            // Refresh the page to reflect the updated contracts
            window.location.reload(); // This will refresh the entire page
        } else {
            alert('Failed to propose contract');
        }
    };

    xhr.send(formData);
}
    </script>

    <!-- Add New Order Section -->
    <div class="cargo-section">
        <h2>Add New Order</h2>
        <form id="cargoForm" method="POST" action="Client_MyCargo.php" enctype="multipart/form-data">
            <div class="cargo-item">
                <button type="button" class="add-cargo-btn" onclick="addCargoFields()">Add Cargo Item</button>
            </div>
            <div id="cargoItems">
                <!-- Dynamic Cargo Fields will be inserted here -->
            </div>
            <div class="cargo-item">
                <button type="submit" name="addCargo" id="submitButton" class="submit-cargo-btn">Submit Cargo and Create Order</button>
            </div>
        </form>
    </div>

    <!-- Display Past Order with Leave Review button -->
<div class="cargo-section">
    <h2>Past Orders</h2>
    <?php if ($deliveredOrdersResult->num_rows > 0): ?>
        <ul>
            <?php while ($row = $deliveredOrdersResult->fetch_assoc()): 
                // Fetch Contract_ID for this order
                $orderId = $row['Order_ID'];
                $contractQuery = "
                    SELECT c.Contract_ID
                    FROM Contract c
                    JOIN `Order` o ON c.Order_ID = o.Order_ID
                    WHERE o.Order_ID = ?
                ";
                $stmt = $conn->prepare($contractQuery);
                $stmt->bind_param("i", $orderId);
                $stmt->execute();
                $stmt->bind_result($contractId);
                $stmt->fetch();
                $stmt->close();

                // Check if review already exists for this Contract
                $reviewCheckQuery = "
                    SELECT COUNT(*) 
                    FROM Review 
                    WHERE Contract_ID = ? AND User_ID = ?
                ";
                $reviewCheckStmt = $conn->prepare($reviewCheckQuery);
                $reviewCheckStmt->bind_param("ii", $contractId, $clientID);
                $reviewCheckStmt->execute();
                $reviewCheckStmt->bind_result($reviewCount);
                $reviewCheckStmt->fetch();
                $reviewCheckStmt->close();
            ?>
            <li>
                Order ID: <?php echo $row['Order_ID']; ?> | Date: <?php echo $row['Order_Date']; ?> | Status: Delivered

                <?php if ($reviewCount == 0): ?>
                    <!-- Show the Leave a Review button if no review exists -->
                    <button class="leave-review-btn" data-contract-id="<?= $contractId ?>">Leave a Review</button>
                <?php else: ?>
                    <!-- Indicate that the review has already been left -->
                    <span>Review Submitted</span>
                <?php endif; ?>

                <!-- Review Form (Hidden Initially) -->
                <div class="review-form" id="review-form-<?= $contractId ?>" style="display: none;">
                    <form method="POST">
                        <input type="hidden" name="contractId" value="<?= $contractId ?>">
                        <label for="reviewRating">Rating (1-5):</label>
                        <input type="number" name="reviewRating" min="1" max="5" required>
                        
                        <label for="reviewComment">Comment:</label>
                        <textarea name="reviewComment" required></textarea>
                        
                        <button type="submit" name="submitReview" class="btn">Submit Review</button>
                    </form>
                </div>
            </li>
            <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <p>You have no past orders.</p>
    <?php endif; ?>
</div>

<!-- JavaScript to handle modal display -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Get all the "Leave a Review" buttons
    const leaveReviewBtns = document.querySelectorAll('.leave-review-btn');
    
    leaveReviewBtns.forEach(button => {
        // When the button is clicked, show the modal
        button.addEventListener('click', function() {
            const orderId = this.getAttribute('data-order-id');
            const modal = document.getElementById('review-modal-' + orderId);
            modal.style.display = 'block';
        });
    });

    // Get all close buttons and close the modal when clicked
    const closeBtns = document.querySelectorAll('.close-btn');
    
    closeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.getAttribute('id').split('-')[3]; // Extract orderId from close-btn ID
            const modal = document.getElementById('review-modal-' + orderId);
            modal.style.display = 'none';
        });
    });

        // Close the modal if the user clicks outside of the modal content
        window.addEventListener('click', function(event) {
            const modals = document.querySelectorAll('.review-modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
    });

    document.querySelectorAll('.leave-review-btn').forEach(button => {
        button.addEventListener('click', function () {
            const contractId = this.getAttribute('data-contract-id');
            const reviewForm = document.getElementById('review-form-' + contractId);
            reviewForm.style.display = (reviewForm.style.display === 'none' || reviewForm.style.display === '') ? 'block' : 'none';
        });
    });
</script>

    
    <script>
        function addCargoFields() {
        const cargoDiv = document.createElement('div');
        cargoDiv.classList.add('cargo-item');
        cargoDiv.innerHTML = `
            <label for="cargoDescription[]">Cargo Description</label>
            <textarea name="cargoDescription[]" required></textarea>
            
            <label for="cargoWeight[]">Cargo Weight (kg)</label>
            <input type="number" name="cargoWeight[]" required>
            
            <label for="cargoDimensions[]">Cargo Dimensions (m³)</label>
            <input type="text" name="cargoDimensions[]" required>
            
            <label for="cargoImage[]">Cargo Image</label>
            <input type="file" name="cargoImage[]" accept="image/*">
            
            <span class="remove-cargo" onclick="removeCargoFields(this)">Remove</span>
        `;

        document.getElementById('cargoItems').appendChild(cargoDiv);
        document.getElementById('submitButton').style.display = 'inline-block';
    }

    function removeCargoFields(button) {
        button.parentElement.remove();
        if (document.getElementById('cargoItems').children.length === 0) {
            document.getElementById('submitButton').style.display = 'none';
        }
    }

        function removeCargoFields(button) {
            button.parentElement.remove();
            if (document.getElementById('cargoItems').children.length === 0) {
                document.getElementById('submitButton').style.display = 'none';
            }
        }
    </script>
</body>
</html>