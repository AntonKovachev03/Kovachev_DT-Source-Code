<!-- the page which includes most of the client's functionalities -->
<?php
    session_start();

    if (!isset($_SESSION['User_Username']) || !isset($_SESSION['User_Role']) || !isset($_SESSION['User_ID']) || ($_SESSION['User_Role']=='Deliverer')) {
        header("Location: Unauthorized.php");
        exit();
    }

    $userRole = $_SESSION['User_Role'];
    $userID = $_SESSION['User_ID'];

    $dbHost = "localhost";
    $dbName = "from_to";
    $dbUser = "root";
    $dbPass = "";

    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

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

    $clientID = $_SESSION['User_ID'];

    //contracts witouth a delivery
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
            AND d.Delivery_ID IS NULL
        GROUP BY 
            c.Contract_ID
    ");
    $currentContractsStmt->bind_param("i", $userID);
    $currentContractsStmt->execute();
    $currentContractsResult = $currentContractsStmt->get_result();
    $currentContractsStmt->close();

    //deliveries
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
    $contractsWithDeliveryStmt->bind_param("i", $userID);
    $contractsWithDeliveryStmt->execute();
    $contractsWithDeliveryResult = $contractsWithDeliveryStmt->get_result();
    $contractsWithDeliveryStmt->close();

    // contracts proposed by deliverers
    $pendingContractsQuery = "
        SELECT c.Contract_ID, c.Order_ID, c.Contract_Status, c.Proposed_Cost, 
            u.User_Username AS Deliverer_Name, o.Order_Status
        FROM Contract c
        JOIN `Order` o ON c.Order_ID = o.Order_ID
        JOIN User u ON c.Deliverer_ID = u.User_ID
        WHERE o.User_ID = ? AND o.Order_Status = 'Pending'AND c.Contract_Status = 'Deliverer Confirmed'"; // Using o.User_ID for client

    $stmt = $conn->prepare($pendingContractsQuery);
    $stmt->bind_param("i", $clientID);
    $stmt->execute();
    $pendingContractsResult = $stmt->get_result();
    $stmt->close();

    //orders that do not have an active contract yet
    $pendingOrdersQuery = "
    SELECT o.Order_ID, o.Order_Date, o.Order_Status, o.Order_Approved, GROUP_CONCAT(c.Cargo_Description SEPARATOR ', ') AS Cargo_Descriptions
    FROM `Order` o
    JOIN Order_Cargo oc ON o.Order_ID = oc.Order_ID
    JOIN Cargo c ON oc.Cargo_ID = c.Cargo_ID
    WHERE o.User_ID = ? AND o.Order_Status = 'Pending'
    GROUP BY o.Order_ID, o.Order_Date, o.Order_Status
    ";

    $stmt = $conn->prepare($pendingOrdersQuery);
    $stmt->bind_param("i", $clientID);
    $stmt->execute();
    $pendingOrdersResult = $stmt->get_result();

    //completed orders
    $pastOrdersQuery = "
        SELECT 
            o.Order_ID,
            o.Order_Date,
            GROUP_CONCAT(c.Cargo_Description SEPARATOR ', ') AS Order_Description,
            u.User_Username AS Deliverer_Username
        FROM `Order` o
        LEFT JOIN Order_Cargo oc ON o.Order_ID = oc.Order_ID
        LEFT JOIN Cargo c ON oc.Cargo_ID = c.Cargo_ID
        LEFT JOIN Contract co ON o.Order_ID = co.Order_ID
        LEFT JOIN User u ON co.Deliverer_ID = u.User_ID
        WHERE o.User_ID = ? AND o.Order_Status = 'Completed'
        GROUP BY o.Order_ID
    ";

    $stmt = $conn->prepare($pastOrdersQuery);
    $stmt->bind_param("i", $clientID);
    $stmt->execute();
    $deliveredOrdersResult = $stmt->get_result();

    if (isset($_POST['confirmDelivery'])) {
        $contractId = $_POST['contractId'];
        $conn->begin_transaction();

        try {//when a client confims the delivery, it updates the connected statuses of the order, delivery, and contract
            $confirmDeliveryStmt = $conn->prepare("
                UPDATE Delivery
                SET Delivery_Confirmed = 'Confirmed'
                WHERE Contract_ID = ?
            ");
            $confirmDeliveryStmt->bind_param("i", $contractId);
            $confirmDeliveryStmt->execute();
            $confirmDeliveryStmt->close();

            $updateOrderStmt = $conn->prepare("
                UPDATE `Order`
                SET Order_Status = 'Completed'
                WHERE Order_ID = (SELECT Order_ID FROM Contract WHERE Contract_ID = ?)
            ");
            $updateOrderStmt->bind_param("i", $contractId);
            $updateOrderStmt->execute();
            $updateOrderStmt->close();

            $updateContractStmt = $conn->prepare("
                UPDATE Contract
                SET Contract_Status = 'Completed'
                WHERE Contract_ID = ?
            ");
            $updateContractStmt->bind_param("i", $contractId);
            $updateContractStmt->execute();
            $updateContractStmt->close();

            $conn->commit();
            header("Location: Client_MyCargo1.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            echo "Error: " . $e->getMessage();
        }
    }

    /*if (isset($_POST['getDelivererDetails']) && isset($_POST['delivererID'])) {
        $delivererID = intval($_POST['delivererID']);

        $query = "SELECT User_Username AS username, User_Email AS email, User_Phone AS phone FROM User WHERE User_ID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $delivererID);
        $stmt->execute();
        $deliverer = $stmt->get_result()->fetch_assoc();
    
        if ($deliverer) {
            $vehicleQuery = "SELECT Vehicle_Make AS make, Vehicle_Model AS model, Vehicle_Type AS type, Vehicle_CapacityKG AS capacity_kg, Vehicle_CapacityM AS volume_m3, Vehicle_Image AS image FROM Vehicle WHERE User_ID = ? AND Vehicle_Status = 'Approved'";
            $stmt = $conn->prepare($vehicleQuery);
            $stmt->bind_param("i", $delivererID);
            $stmt->execute();
            $vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
            $deliverer['vehicles'] = $vehicles;
    
            echo json_encode($deliverer);
        } else {
            echo json_encode(['error' => 'Deliverer not found.']);
        }
        exit;
    } */

    if (isset($_POST['findDeliverers']) && isset($_POST['orderID'])) {
        $orderID = intval($_POST['orderID']);
    
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
        
        //get the deliverers and their vehicles and reviews
        $delivererQuery = "
            SELECT DISTINCT u.User_ID AS Deliverer_ID, u.User_Username
            FROM User u
            LEFT JOIN Contract c ON u.User_ID = c.Deliverer_ID
            LEFT JOIN Review r ON c.Contract_ID = r.Contract_ID AND r.Review_Approved = 'Approved'
            JOIN Vehicle v ON u.User_ID = v.User_ID
            WHERE u.User_Role = 'Deliverer'
            AND v.Vehicle_UseStatus = 'Available'
            AND v.Vehicle_Status = 'Approved'
            AND u.User_ID NOT IN (
                SELECT Deliverer_ID
                FROM Contract
                WHERE Order_ID = ?
            )
            GROUP BY u.User_ID
        ";
        
        $stmt = $conn->prepare($delivererQuery);
        $stmt->bind_param("i", $orderID);
        $stmt->execute();
        $matchingDeliverers = $stmt->get_result();
    
        if ($matchingDeliverers->num_rows > 0) {
            echo '<table class="table table-striped">';
            echo '<thead class="table-dark">';
            echo '<tr>';
            echo '<th>Deliverer Name</th>';
            echo '<th>Average Rating</th>';
            echo '<th>Vehicle Capacity</th>';
            echo '<th>Action</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
        
            while ($deliverer = $matchingDeliverers->fetch_assoc()) {
                $delivererID = $deliverer['Deliverer_ID'];
                $averageRating = 'No Reviews'; 
    
                //get the review for the specific deliverer and calculate the average rating
                $reviewQuery = "
                    SELECT AVG(Review_Rating) AS AverageRating 
                    FROM Review r
                    JOIN Contract c ON r.Contract_ID = c.Contract_ID
                    WHERE r.Review_Approved = 'Approved'
                    AND c.Deliverer_ID = ?";
                $stmt = $conn->prepare($reviewQuery);
                $stmt->bind_param("i", $delivererID);
                $stmt->execute();
                $reviewResult = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                if ($reviewResult['AverageRating'] !== null) {
                    $averageRating = number_format($reviewResult['AverageRating'], 2);
                }

                //check if the deliverer's vehicles can carry out the order
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
            
                if ($totalVehicleCapacityKG >= $totalWeight && $totalVehicleCapacityM >= $totalDimensions) {
                    echo '<tr>';
                    echo '<td><a href="javascript:void(0);" class="deliverer-name" data-deliverer-id="' . $delivererID . '">' 
                         . htmlspecialchars($deliverer['User_Username'], ENT_QUOTES, 'UTF-8') 
                         . '</a></td>';
                         echo '<td>' . htmlspecialchars($averageRating, ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars($totalVehicleCapacityKG, ENT_QUOTES, 'UTF-8') . ' kg</td>';
                    echo '<td>';
                    echo '<input type="number" id="proposedCost_' . $delivererID . '" class="form-control mb-2" placeholder="Propose cost">';
                    echo '<button class="btn btn-success" onclick="proposeContract(' . $orderID . ', ' . $delivererID . ', document.getElementById(\'proposedCost_' . $delivererID . '\').value)">Propose Contract</button>';
                    echo '</td>';
                    echo '</tr>';
                
                    echo '<tr id="delivererDetails_' . $delivererID . '" class="collapse-row" style="display: none;">';
                    echo '<td colspan="4" class="expanded-details">
                            <div class="details-container">
                                <p>Loading details...</p>
                            </div>
                          </td>';
                    echo '</tr>';
                }
            }
    
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p class="text-muted">No matching deliverers found for this order.</p>';
        }
    
        exit;
    }

    if (isset($_POST['proposeDeliverer'])) {//propose a contract to a deliverer
        $orderID = intval($_POST['orderID']);
        $delivererID = intval($_POST['delivererID']);
        $proposedCost = floatval($_POST['proposedCost']);
    
        if ($proposedCost <= 0) {
            echo "Invalid cost.";
            exit;
        }
    
        $query = "INSERT INTO Contract (Order_ID, Deliverer_ID, Proposed_Cost, Contract_Status)
                  VALUES (?, ?, ?, 'Client Confirmed')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iid', $orderID, $delivererID, $proposedCost);
    
        if ($stmt->execute()) {
            echo "Contract proposed successfully!";
        } else {
            echo "Failed to propose contract.";
        }
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addCargo'])) {
        if (empty($_POST['cargoDescription']) || empty($_POST['cargoWeight']) || empty($_POST['cargoDimensions'])) {
            die("Error: At least one cargo item must be provided.");
        }

        $fromLocation = $_POST['fromLocation'];
        $toLocation = $_POST['toLocation'];
    
        //creates an order first, and then connects the order to the cargo
        $insertOrderQuery = "INSERT INTO `Order` (User_ID, Order_FromLocation, Order_ToLocation, Order_Date, Order_Status) VALUES (?, ?, ?, NOW(), 'Pending')";
        $stmt = $conn->prepare($insertOrderQuery);
        $stmt->bind_param("iss", $clientID, $fromLocation, $toLocation);
        $stmt->execute();
        $orderID = $stmt->insert_id;
        $stmt->close();
        
        if (isset($_POST['cargoDescription']) && isset($_FILES['cargoImage'])) {
            foreach ($_POST['cargoDescription'] as $index => $description) {
                $cargoDescription = $description;
                $cargoWeight = $_POST['cargoWeight'][$index];
                $cargoDimensions = $_POST['cargoDimensions'][$index];

                //add to the database the instances of cargo that the user added
                $insertCargoQuery = "
                    INSERT INTO Cargo (User_ID, Cargo_Description, Cargo_Weight, Cargo_Dimensions) 
                    VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($insertCargoQuery);
                $stmt->bind_param("isss", $clientID, $cargoDescription, $cargoWeight, $cargoDimensions);
                $stmt->execute();
                $cargoID = $stmt->insert_id; 
                $stmt->close();

                $insertOrderCargoQuery = "INSERT INTO Order_Cargo (Order_ID, Cargo_ID) VALUES (?, ?)";
                $stmt = $conn->prepare($insertOrderCargoQuery);
                $stmt->bind_param("ii", $orderID, $cargoID);
                $stmt->execute();
                $stmt->close();

                if (isset($_FILES['cargoImage']['name'][$index]) && $_FILES['cargoImage']['error'][$index] === 0) {
                    $imageTmpName = $_FILES['cargoImage']['tmp_name'][$index];
                    $imageName = basename($_FILES['cargoImage']['name'][$index]);
                    $imageFolder = "uploads/cargo_images/";
                    $imagePath = $imageFolder . "cargo_" . $cargoID . "_" . uniqid() . "_" . $imageName;

                    if (!is_dir($imageFolder)) {
                        mkdir($imageFolder, 0777, true);
                    }

                    if (move_uploaded_file($imageTmpName, $imagePath)) {
                        $insertGalleryQuery = "INSERT INTO Gallery (Entity_Type, Entity_ID, Image_Path) VALUES ('Cargo', ?, ?)";
                        $stmtGallery = $conn->prepare($insertGalleryQuery);
                        $stmtGallery->bind_param("is", $cargoID, $imagePath);
                        $stmtGallery->execute();
                        $stmtGallery->close();
                    } else {
                        echo "Failed to upload image for cargo item: " . $imageName;
                    }
                }
            }
        }

        header('Location: Client_MyCargo1.php');
        exit;
    }

    if (isset($_POST['submitReview'])) {
        $contractId = $_POST['contractId'];
        $reviewRating = $_POST['reviewRating'];
        $reviewComment = $_POST['reviewComment'];
    
        $insertReviewStmt = $conn->prepare("
            INSERT INTO Review (Contract_ID, User_ID, Review_Rating, Review_Comment, Review_Approved)
            VALUES (?, ?, ?, ?, 'Pending')
        ");
        $insertReviewStmt->bind_param("iiis", $contractId, $clientID, $reviewRating, $reviewComment);
    
        if ($insertReviewStmt->execute()) {
            echo "<script>alert('Review submitted successfully!');</script>";
        } else {
            echo "<script>alert('Error submitting review: " . $insertReviewStmt->error . "');</script>";
        }
    
        $insertReviewStmt->close();
        header("Location: Client_MyCargo1.php");
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $contractID = $_POST['contractId'];
        $orderID = $_POST['orderId'];

        if (isset($_POST['acceptContract'])) {
            // sets the corresponding contract to both confirmed
            $updateContractStmt = $conn->prepare("UPDATE Contract SET Contract_Status = 'Both Confirmed' WHERE Contract_ID = ?");
            $updateContractStmt->bind_param("i", $contractID);
            $updateContractStmt->execute();
            $updateContractStmt->close();
        
            //in order to not have duplicate contracts for an order after acceptance, the other contracts for the order are deleted
            $removeOtherContractsStmt = $conn->prepare("
                DELETE FROM Contract 
                WHERE Order_ID = ? AND Contract_ID != ?
            ");
            $removeOtherContractsStmt->bind_param("ii", $orderID, $contractID);
            $removeOtherContractsStmt->execute();
            $removeOtherContractsStmt->close();
        
            $vehicleIDsQuery = "SELECT Vehicle_IDs FROM Contract WHERE Contract_ID = ?";
            $stmt = $conn->prepare($vehicleIDsQuery);
            $stmt->bind_param("i", $contractID);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            //set the status of the vehicles used in the contract to In Use
            if ($row) {
                $vehicleIDs = $row['Vehicle_IDs'];
                if (!empty($vehicleIDs)) {
                    $vehicleIDsArray = explode(',', $vehicleIDs);
                    $placeholders = implode(',', array_fill(0, count($vehicleIDsArray), '?'));
                    $updateVehicleQuery = "UPDATE Vehicle SET Vehicle_UseStatus = 'In Use' WHERE Vehicle_ID IN ($placeholders)";
                    $stmt = $conn->prepare($updateVehicleQuery);
                    $stmt->bind_param(str_repeat('i', count($vehicleIDsArray)), ...$vehicleIDsArray);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        
            $removeConflictingContractsStmt = $conn->prepare("
                DELETE FROM Contract 
                WHERE Contract_ID != ? 
                AND Contract_ID IN (
                    SELECT c.Contract_ID
                    FROM Contract c
                    JOIN Vehicle v ON FIND_IN_SET(v.Vehicle_ID, c.Vehicle_IDs) > 0
                    WHERE c.Contract_Status != 'Both Confirmed' 
                    AND FIND_IN_SET(v.Vehicle_ID, c.Vehicle_IDs) > 0
                )
            ");
            $removeConflictingContractsStmt->bind_param("i", $contractID);
            $removeConflictingContractsStmt->execute();
            $removeConflictingContractsStmt->close();

    
            $orderID = $_POST['orderId'];
            $updateOrderStmt = $conn->prepare("UPDATE `Order` SET Order_Status = 'Active' WHERE Order_ID = ?");
            $updateOrderStmt->bind_param("i", $orderID);
            $updateOrderStmt->execute();
            $updateOrderStmt->close();
        
            echo "<div class='alert alert-success'>Contract accepted successfully!</div>";
        
            header('Location: Client_MyCargo1.php');
            exit;
        } elseif (isset($_POST['rejectContract'])) {
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

            header("Location: Client_MyCargo1.php");
            exit;

        } elseif (isset($_POST['haggleContract'])) {
            $contractID = $_POST['contractId'];
            $newProposedCost = $_POST['newProposedCost'];
        
            if (!is_numeric($newProposedCost) || $newProposedCost <= 0) {
                echo "<p>Error: Please enter a valid positive cost.</p>";
                exit;
            }

            $vehicleQuery = "SELECT Vehicle_IDs FROM Contract WHERE Contract_ID = ?";
            $stmt = $conn->prepare($vehicleQuery);
            $stmt->bind_param("i", $contractID);
            $stmt->execute();
            $vehicleResult = $stmt->get_result();
            $vehicleData = $vehicleResult->fetch_assoc();
            $stmt->close();
        
            if (!empty($vehicleData['Vehicle_IDs'])) {
                $vehicleIDs = explode(',', $vehicleData['Vehicle_IDs']); 
        
                $vehiclePlaceholders = implode(',', array_fill(0, count($vehicleIDs), '?'));
                $updateVehiclesQuery = "UPDATE Vehicle SET Vehicle_UseStatus = 'Available' WHERE Vehicle_ID IN ($vehiclePlaceholders)";
                $stmt = $conn->prepare($updateVehiclesQuery);
                $stmt->bind_param(str_repeat('i', count($vehicleIDs)), ...$vehicleIDs);
                $stmt->execute();
                $stmt->close();
            }
        
            $updateContractQuery = "UPDATE Contract SET Proposed_Cost = ?, Contract_Status = 'Client Confirmed', Vehicle_IDs = NULL WHERE Contract_ID = ?";
            $stmt = $conn->prepare($updateContractQuery);
            $stmt->bind_param("di", $newProposedCost, $contractID);
            $stmt->execute();
            $stmt->close();
        
            echo "<p>Your new cost proposal has been sent. Awaiting deliverer confirmation.</p>";
        }

        header("Location: Client_MyCargo1.php");
        exit;
    }
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client My Cargo</title>
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

        .main-content {
            min-height: 400px;
            display: flex;
            justify-content: center;
            align-items: center;
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

        .table th, .table td {
            text-align: center;
            vertical-align: middle;
        }

        .btn-custom {
            margin: 10px;
            font-size: 16px;
            width: 100%;
        }

        .btn-small {
            font-size: 14px;
        }

        .collapse-btn {
            font-size: 16px;
            cursor: pointer;
            background: none;
            border: none;
            color: #007bff;
            text-decoration: underline;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }

        .orders-table th, .orders-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .orders-table th {
            background-color: #f4f4f4;
        }

        .toggle-deliverers-btn {
            background-color: #007BFF;
            color: white;
            padding: 8px 16px;
            border: none;
            cursor: pointer;
        }

        .toggle-deliverers-btn:hover {
            background-color: #0056b3;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        .details-container {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
        }

        .details-table th, .details-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .details-table th {
            background-color: #f1f1f1;
            font-weight: bold;
        }

        .details-table img {
            max-width: 100%;
            height: auto;
            display: block;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="banner">
        From-To Client View
    </div>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="LoggedIn_Client.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="Client_MyCargo1.php">My Cargo</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="Client_MyProfile.php">My Profile</a>
                    </li>
                </ul>
            </div>
            <a class="btn btn-outline-light" href="LogOut.php">Log Out</a>
        </div>
    </nav>

    <div class="background-wrapper">
        <div class="main-content">
            <div class="content-wrapper">
                <div class="middle-content">
                    <h2 class="text-center">Your Cargo Dashboard</h2>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3>Deliveries</h3>
                            <!-- toggle button that is also present for the other tables, it just hides the table when pressed -->
                            <button 
                                class="btn btn-primary collapse-btn" 
                                type="button" 
                                data-bs-toggle="collapse" 
                                data-bs-target="#contractsTable" 
                                aria-expanded="true" 
                                aria-controls="contractsTable">
                                Toggle Table
                            </button>
                        </div>

                        <div class="collapse show" id="contractsTable">
                            <?php if ($contractsWithDeliveryResult->num_rows > 0): ?>
                                <table class="table table-bordered table-striped">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th>Contract ID</th>
                                            <th>Order ID</th>
                                            <th>Proposed Cost</th>
                                            <th>Status</th>
                                            <th>Delivery Status</th>
                                            <th>Current Location</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $contractsWithDeliveryResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $row['Contract_ID']; ?></td>
                                                <td><?php echo $row['Order_ID']; ?></td>
                                                <td>$<?php echo number_format($row['Proposed_Cost'], 2); ?></td>
                                                <td><?php echo $row['Contract_Status']; ?></td>
                                                <td><?php echo $row['Delivery_Status'] ?: 'Not Available'; ?></td>
                                                <td><?php echo $row['Delivery_CurrentLocation'] ?: 'Not Available'; ?></td>
                                                <td>
                                                    <?php if ($row['Delivery_Status'] == "Cargo Delivered" && $row['Delivery_Confirmed'] != "Confirmed"): ?>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="contractId" value="<?= $row['Contract_ID']; ?>">
                                                            <button type="submit" name="confirmDelivery" class="btn btn-success btn-small">Confirm Completion</button>
                                                        </form>
                                                    <?php elseif ($row['Delivery_Confirmed'] == "Confirmed"): ?>
                                                        <span class="text-success"><strong>Confirmed</strong></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p>No contracts with delivery found.</p>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                            <h3>Current Contracts</h3>
                            <button 
                                class="btn btn-primary collapse-btn" 
                                type="button" 
                                data-bs-toggle="collapse" 
                                data-bs-target="#noDeliveryContractsTable" 
                                aria-expanded="true" 
                                aria-controls="noDeliveryContractsTable">
                                Toggle Table
                            </button>
                        </div>

                        <div class="collapse show" id="noDeliveryContractsTable">
                            <?php if ($currentContractsResult->num_rows > 0): ?>
                                <table class="table table-bordered table-striped">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th>Contract ID</th>
                                            <th>Order ID</th>
                                            <th>Total Weight (kg)</th>
                                            <th>Total Volume (m³)</th>
                                            <th>Order Date</th>
                                            <th>Cost</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $currentContractsResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $row['Contract_ID']; ?></td>
                                                <td><?php echo $row['Order_ID']; ?></td>
                                                <td><?php echo $row['Total_Weight']; ?> kg</td>
                                                <td><?php echo $row['Total_Volume']; ?> m³</td>
                                                <td><?php echo $row['Order_Date']; ?></td>
                                                <td>$<?php echo number_format($row['Proposed_Cost'], 2); ?></td>
                                                <td><?php echo $row['Contract_Status']; ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p>No current contracts found.</p>
                            <?php endif; ?>
                        </div>
                    <div class="cargo-section mt-4">
                        <h3>Pending Contracts</h3>
                        <?php if ($pendingContractsResult->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle text-center">
                                    <thead>
                                        <tr>
                                            <th>Contract ID</th>
                                            <th>Order ID</th>
                                            <th>Deliverer</th>
                                            <th>Status</th>
                                            <th>Proposed Cost</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $pendingContractsResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $row['Contract_ID']; ?></td>
                                                <td><?php echo $row['Order_ID']; ?></td>
                                                <td><?php echo $row['Deliverer_Name']; ?></td>
                                                <td><?php echo $row['Contract_Status']; ?></td>
                                                <td>$<?php echo number_format($row['Proposed_Cost'], 2); ?></td>
                                                <td>
                                                    <form method="POST" action="Client_MyCargo1.php" class="d-inline">
                                                        <input type="hidden" name="contractId" value="<?php echo $row['Contract_ID']; ?>">
                                                        <input type="hidden" name="orderId" value="<?php echo $row['Order_ID']; ?>">
                                                        <button type="submit" name="acceptContract" class="btn btn-success btn-sm me-1">Accept</button>
                                                        <button type="submit" name="rejectContract" class="btn btn-danger btn-sm me-1">Reject</button>
                                                    </form>
                                                    <button class="btn btn-warning btn-sm me-1" data-bs-toggle="modal" data-bs-target="#haggleModal-<?php echo $row['Contract_ID']; ?>">
                                                        Haggle
                                                    </button>
                                                </td>
                                            </tr>

                                            <!-- the modal that will show the haggle fields to the user, where they can set a new price to the contract -->
                                            <div class="modal fade" id="haggleModal-<?php echo $row['Contract_ID']; ?>" tabindex="-1" aria-labelledby="haggleModalLabel-<?php echo $row['Contract_ID']; ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="haggleModalLabel-<?php echo $row['Contract_ID']; ?>">Haggle Contract</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <form method="POST" action="Client_MyCargo1.php">
                                                                <input type="hidden" name="contractId" value="<?php echo $row['Contract_ID']; ?>">
                                                                <label for="newProposedCost-<?php echo $row['Contract_ID']; ?>" class="form-label">New Proposed Cost</label>
                                                                <input type="number" name="newProposedCost" id="newProposedCost-<?php echo $row['Contract_ID']; ?>" class="form-control mb-3" step="0.01" min="0" placeholder="Enter new cost" required>
                                                                <div class="d-flex justify-content-end">
                                                                    <button type="submit" name="haggleContract" class="btn btn-primary">Submit</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center">You have no pending contracts.</p>
                        <?php endif; ?>
                    </div>

                    <div class="cargo-section">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3>Current Orders</h3>
                            <button 
                                class="btn btn-primary collapse-btn" 
                                type="button" 
                                data-bs-toggle="collapse" 
                                data-bs-target="#ordersTable" 
                                aria-expanded="true" 
                                aria-controls="ordersTable">
                                Toggle Table
                            </button>
                        </div>

                        <div class="collapse show" id="ordersTable">
                            <?php if ($pendingOrdersResult->num_rows > 0): ?>
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Description</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $pendingOrdersResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['Order_ID']); ?></td>
                                                <td><?php echo htmlspecialchars($row['Order_Date']); ?></td>
                                                <td><?php echo htmlspecialchars($row['Order_Status']); ?></td>
                                                <td><?php echo htmlspecialchars($row['Cargo_Descriptions']); ?></td>
                                                <td>
                                                    <?php if ($row['Order_Status'] === 'Pending' && $row['Order_Approved'] == 'Approved'): ?>
                                                        <button 
                                                            class="btn btn-primary btn-sm" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#delivererModal" 
                                                            onclick="fetchDeliverers(<?php echo $row['Order_ID']; ?>)">
                                                            Find Deliverers
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted">Awaiting Approval</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-muted">You have no current orders.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="modal fade" id="delivererModal" tabindex="-1" aria-labelledby="delivererModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="delivererModalLabel">Matching Deliverers</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Loading deliverers...</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="cargo-section">
                        <h3>Add Order</h3>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addOrderModal">
                            Add New Order
                        </button>

                        <div class="modal fade" id="addOrderModal" tabindex="-1" aria-labelledby="addOrderModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="addOrderModalLabel">Add New Order</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="cargoForm" method="POST" action="Client_MyCargo1.php" enctype="multipart/form-data">
                                            <div class="mb-3">
                                                <label for="fromLocation" class="form-label">From Location</label>
                                                <input type="text" class="form-control" id="fromLocation" name="fromLocation" placeholder="City, Country" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="toLocation" class="form-label">To Location</label>
                                                <input type="text" class="form-control" id="toLocation" name="toLocation" placeholder="City, Country" required>
                                            </div>
                                            
                                            <h6>Cargo Details</h6>
                                            <div id="cargoItems">
                                            </div>
                                            <button type="button" class="btn btn-success btn-sm mt-2" onclick="addCargoFields()">
                                                Add Cargo Item
                                            </button>
                                        </form>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" name="addCargo" class="btn btn-primary" form="cargoForm" id="submitOrderButton" disabled>
                                            Submit Order
                                        </button>
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="cargo-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3>Past Orders</h3>    
                        <button 
                            class="btn btn-primary collapse-btn" 
                            type="button" 
                            data-bs-toggle="collapse" 
                            data-bs-target="#pastOrdersTable" 
                            aria-expanded="true" 
                            aria-controls="pastOrdersTable">
                            Toggle Table
                        </button>
                    </div>

                    <div class="collapse show" id="pastOrdersTable">
                        <?php if ($deliveredOrdersResult->num_rows > 0): ?>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Order Date</th>
                                        <th>Order Description</th>
                                        <th>Deliverer</th>
                                        <th>Status</th>
                                        <th>Review</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $deliveredOrdersResult->fetch_assoc()): 
                                        $orderId = $row['Order_ID'];
                                        $contractId = null;

                                        $stmt = $conn->prepare("
                                            SELECT Contract_ID
                                            FROM Contract 
                                            WHERE Order_ID = ?
                                        ");
                                        $stmt->bind_param("i", $orderId);
                                        $stmt->execute();
                                        $stmt->bind_result($contractId);
                                        $stmt->fetch();
                                        $stmt->close();

                                        //checks whether ot not the client has already submitted a review for that contract
                                        $reviewCheckStmt = $conn->prepare("
                                            SELECT COUNT(*) 
                                            FROM Review 
                                            WHERE Contract_ID = ? AND User_ID = ?
                                        ");
                                        $reviewCheckStmt->bind_param("ii", $contractId, $clientID);
                                        $reviewCheckStmt->execute();
                                        $reviewCheckStmt->bind_result($reviewCount);
                                        $reviewCheckStmt->fetch();
                                        $reviewCheckStmt->close();
                                    ?>
                                    <tr>
                                        <td><?= $orderId ?></td>
                                        <td><?= $row['Order_Date'] ?></td>
                                        <td><?= htmlspecialchars($row['Order_Description']) ?></td>
                                        <td><?= htmlspecialchars($row['Deliverer_Username']) ?></td>
                                        <td>Delivered</td>
                                        <td>
                                            <?php if ($reviewCount == 0): ?>
                                                <button 
                                                    class="btn btn-outline-primary btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#reviewModal<?= $contractId ?>">
                                                    Leave a Review
                                                </button>
                                            <?php else: ?>
                                                <span class="badge bg-success">Review Submitted</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <!-- the modal that will show up whenever the client decides to leave a review for the delivery -->
                                    <div class="modal fade" id="reviewModal<?= $contractId ?>" tabindex="-1" aria-labelledby="reviewModalLabel<?= $contractId ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="reviewModalLabel<?= $contractId ?>">Leave a Review</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <form method="POST">
                                                        <input type="hidden" name="contractId" value="<?= $contractId ?>">
                                                        <div class="mb-3">
                                                            <label for="reviewRating<?= $contractId ?>" class="form-label">Rating (1-5)</label>
                                                            <input type="number" class="form-control" id="reviewRating<?= $contractId ?>" name="reviewRating" min="1" max="5" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="reviewComment<?= $contractId ?>" class="form-label">Comment</label>
                                                            <textarea class="form-control" id="reviewComment<?= $contractId ?>" name="reviewComment" rows="3" required></textarea>
                                                        </div>
                                                        <button type="submit" name="submitReview" class="btn btn-primary">Submit Review</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No past orders to display.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function fetchDeliverers(orderID) {//getting the matching deliverers for an order, depending on whether or not they meet the requirements
            const modalBody = document.querySelector('#delivererModal .modal-body');
            modalBody.innerHTML = '<p>Loading matching deliverers...</p>';

            const formData = new FormData();
            formData.append('findDeliverers', true);
            formData.append('orderID', orderID);

            fetch('Client_MyCargo1.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to fetch deliverers.');
                }
                return response.text();
            })
            .then(data => {
                modalBody.innerHTML = data;
                
            })
            .catch(error => {
                modalBody.innerHTML = `<p class="text-danger">${error.message}</p>`;
            });
        }

        /* function toggleDelivererDetails(delivererID, linkElement) {//the client is able to expand the details of the deliverer
            const detailsRow = document.getElementById(`delivererDetails_${delivererID}`);

            if (detailsRow.style.display === 'none') {
                detailsRow.style.display = '';
                const detailsContainer = detailsRow.querySelector('.details-container');
                detailsContainer.innerHTML = '<p>Loading deliverer details...</p>';

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
                        detailsContainer.innerHTML = `<p class="text-danger">${data.error}</p>`;
                        return;
                    }

                    let content = `
                        <table class="details-table">
                            <tr>
                                <th>Username</th>
                                <td>${data.username}</td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td>${data.email}</td>
                            </tr>
                            <tr>
                                <th>Phone</th>
                                <td>${data.phone}</td>
                            </tr>
                        </table>
                        <h4 class="mt-4">Vehicles</h4>
                        <table class="details-table">
                            <thead>
                                <tr>
                                    <th>Make</th>
                                    <th>Model</th>
                                    <th>Type</th>
                                    <th>Image</th>
                                </tr>
                            </thead>
                            <tbody>`;

                    if (data.vehicles && data.vehicles.length > 0) {
                        data.vehicles.forEach(vehicle => {
                            content += `
                                <tr>
                                    <td>${vehicle.make}</td>
                                    <td>${vehicle.model}</td>
                                    <td>${vehicle.type}</td>
                                    <td>`;
                            if (vehicle.image) {
                                content += `<img src="${vehicle.image}" alt="Vehicle Image" style="max-width: 100px;">`;
                            } else {
                                content += `No image available`;
                            }
                            content += `</td></tr>`;
                        });
                    } else {
                        content += `
                            <tr>
                                <td colspan="6">No vehicles found for this deliverer.</td>
                            </tr>`;
                    }

                    content += `</tbody></table>`;
                    detailsContainer.innerHTML = content;
                })
                .catch(error => {
                    detailsContainer.innerHTML = '<p class="text-danger">Failed to load details. Please try again later.</p>';
                });
            } else {
                detailsRow.style.display = 'none';
            }
        }*/

        function proposeContract(orderID, delivererID, proposedCost) {//client proposing to the deliverer, if the client has entered a valid cost
            if (!proposedCost || proposedCost <= 0) {
                alert('Please enter a valid proposed cost.');
                return;
            }

            const formData = new FormData();
            formData.append('orderID', orderID);
            formData.append('delivererID', delivererID);
            formData.append('proposedCost', proposedCost);
            formData.append('proposeDeliverer', true);

            fetch('Client_MyCargo1.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                alert('Contract proposed successfully!');
                window.location.reload();
            })
            .catch(error => {
                alert('Failed to propose contract. Please try again.');
            });
        }

        document.getElementById('addOrderModal').addEventListener('shown.bs.modal', () => {
            const cargoItems = document.getElementById('cargoItems');
            if (cargoItems.childElementCount === 0) {
                addCargoFields(); 
            }
            toggleSubmitButton(); 
        });

        function addCargoFields() {//the fields where the client adds the cargo details for the new order
            const cargoDiv = document.createElement('div');
            cargoDiv.classList.add('card', 'mt-3');
            cargoDiv.innerHTML = `
                <div class="card-body">
                    <div class="mb-3">
                        <label for="cargoDescription[]" class="form-label">Cargo Description</label>
                        <textarea class="form-control" name="cargoDescription[]" rows="2" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="cargoWeight[]" class="form-label">Cargo Weight (kg)</label>
                        <input type="number" class="form-control" name="cargoWeight[]" required>
                    </div>
                    <div class="mb-3">
                        <label for="cargoDimensions[]" class="form-label">Cargo Dimensions (m³)</label>
                        <input type="text" class="form-control" name="cargoDimensions[]" required>
                    </div>
                    <div class="mb-3">
                        <label for="cargoImage[]" class="form-label">Cargo Image</label>
                        <input type="file" class="form-control" name="cargoImage[]" accept="image/*">
                    </div>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeCargoFields(this)">
                        Remove Cargo
                    </button>
                </div>
            `;
            document.getElementById('cargoItems').appendChild(cargoDiv);
            toggleSubmitButton();
        }

        function removeCargoFields(button) {
            button.closest('.card').remove();
            toggleSubmitButton();
        }

        function toggleSubmitButton() {
            const cargoItems = document.getElementById('cargoItems');
            const submitButton = document.getElementById('submitOrderButton');
            submitButton.disabled = cargoItems.childElementCount === 0;
        }
    </script>
    
    <div class="footer">
        <p style="font-size: 14px; margin-bottom: 0;">Anton Kovachev | © 2024</p>
    </div>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>