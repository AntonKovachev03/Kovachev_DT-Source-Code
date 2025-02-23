<!-- the page where most of the deliverer's functionalities are, they can update deliveries, accept/reject/haggle for contracts, and propose contracts -->
<?php
    session_start();

    if (!isset($_SESSION['User_Username']) || !isset($_SESSION['User_Role']) || !isset($_SESSION['User_ID']) || ($_SESSION['User_Role']=='Client')) {
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

    $username = $_SESSION['User_Username'];
    $stmt = $conn->prepare("SELECT User_ID FROM User WHERE User_Username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $userResult = $stmt->get_result()->fetch_assoc();
    $userId = $userResult['User_ID'];
    $stmt->close();

    $availableCapacityKG = 0;
    $availableCapacityM = 0;
    $inUseCapacityKG = 0;
    $inUseCapacityM = 0;
    $combinedCapacityKG = 0;
    $combinedCapacityM = 0;

    //get the carrying capabilities of the available vehicles
    $availableVehicleCapQuery = $conn->prepare("
        SELECT SUM(v.Vehicle_CapacityKG) AS TotalCapacityKG, SUM(v.Vehicle_CapacityM) AS TotalCapacityM
        FROM Vehicle v
        WHERE v.User_ID = ? AND v.Vehicle_Status = 'Approved' AND v.Vehicle_UseStatus = 'Available'
    ");
    $availableVehicleCapQuery->bind_param("i", $userId);
    $availableVehicleCapQuery->execute();
    $availableCapacities = $availableVehicleCapQuery->get_result()->fetch_assoc();
    $availableCapacityKG = $availableCapacities['TotalCapacityKG'] ?? 0;
    $availableCapacityM = $availableCapacities['TotalCapacityM'] ?? 0;
    $availableVehicleCapQuery->close();

    //get the carrying capabilities of the in use vehicles; i'm pretty sure this is not needed, since I removed a feature, but I've left it just in case
    $inUseVehicleCapQuery = $conn->prepare("
        SELECT SUM(v.Vehicle_CapacityKG) AS TotalCapacityKG, SUM(v.Vehicle_CapacityM) AS TotalCapacityM
        FROM Vehicle v
        WHERE v.User_ID = ? AND v.Vehicle_UseStatus = 'In Use'
    ");
    $inUseVehicleCapQuery->bind_param("i", $userId);
    $inUseVehicleCapQuery->execute();
    $inUseCapacities = $inUseVehicleCapQuery->get_result()->fetch_assoc();
    $inUseCapacityKG = $inUseCapacities['TotalCapacityKG'] ?? 0;
    $inUseCapacityM = $inUseCapacities['TotalCapacityM'] ?? 0;
    $inUseVehicleCapQuery->close();

    $combinedCapacityKG = $availableCapacityKG + $inUseCapacityKG;
    $combinedCapacityM = $availableCapacityM + $inUseCapacityM;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['getCargoDetails'])) {
    $orderID = isset($_POST['orderID']) ? (int)$_POST['orderID'] : 0;

    $cargoQuery = "
        SELECT c.Cargo_Description, c.Cargo_Weight, c.Cargo_Dimensions, g.Image_Path
        FROM Cargo c
        JOIN Order_Cargo oc ON c.Cargo_ID = oc.Cargo_ID
        LEFT JOIN Gallery g ON c.Cargo_ID = g.Entity_ID AND g.Entity_Type = 'Cargo'
        WHERE oc.Order_ID = ?
    ";

    $stmt = $conn->prepare($cargoQuery);
    $stmt->bind_param("i", $orderID);
    $stmt->execute();
    $result = $stmt->get_result();

    $cargoData = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $cargoData[] = $row;
        }
    }

    echo json_encode(['cargo' => $cargoData]);
    $stmt->close();
    exit;
    }

    $potentialOffersStmt = $conn->prepare("
        SELECT o.Order_ID, o.Order_Date, SUM(c.Cargo_Weight) AS TotalWeight, SUM(c.Cargo_Dimensions) AS TotalDimensions
        FROM `Order` o
        JOIN Order_Cargo oc ON o.Order_ID = oc.Order_ID
        JOIN Cargo c ON oc.Cargo_ID = c.Cargo_ID
        WHERE o.Order_Status = 'Pending' AND o.Order_Approved = 'Approved'
        GROUP BY o.Order_ID
        HAVING (TotalWeight > ? OR TotalDimensions > ?) 
            AND (TotalWeight <= ? AND TotalDimensions <= ?)
    ");
    $potentialOffersStmt->bind_param("dddd", $availableCapacityKG, $availableCapacityM, $combinedCapacityKG, $combinedCapacityM);
    $potentialOffersStmt->execute();
    $potentialOffers = $potentialOffersStmt->get_result();

    $potentialOfferIds = [];
    while ($potentialOffer = $potentialOffers->fetch_assoc()) {
    $potentialOfferIds[] = $potentialOffer['Order_ID'];
    }
    $potentialOffersStmt->close();


    $offersStmt = $conn->prepare("
        SELECT o.Order_ID, o.Order_Date, SUM(c.Cargo_Weight) AS TotalWeight, SUM(c.Cargo_Dimensions) AS TotalDimensions
        FROM `Order` o
        JOIN Order_Cargo oc ON o.Order_ID = oc.Order_ID
        JOIN Cargo c ON oc.Cargo_ID = c.Cargo_ID
        WHERE o.Order_Status = 'Pending' 
        AND o.Order_Approved = 'Approved'
        AND NOT EXISTS (
            SELECT 1
            FROM Contract con
            WHERE con.Order_ID = o.Order_ID
            AND con.Deliverer_ID = ?
            AND con.Contract_Status = 'Deliverer Confirmed'
        )
        GROUP BY o.Order_ID
        HAVING TotalWeight <= ? AND TotalDimensions <= ?
    ");

    $offersStmt->bind_param("sdd", $userId, $availableCapacityKG, $availableCapacityM);
    $offersStmt->execute();
    $offers = $offersStmt->get_result();

    
    $vehiclesStmt = $conn->prepare("
    SELECT Vehicle_ID, Vehicle_Make, Vehicle_Model, Vehicle_CapacityKG, Vehicle_CapacityM
    FROM Vehicle 
    WHERE User_ID = ? AND Vehicle_UseStatus = 'Available' and Vehicle_Status = 'Approved'
    ");
    $vehiclesStmt->bind_param("i", $userId);  // Assuming $userId is already set from the session
    $vehiclesStmt->execute();
    $vehicles = $vehiclesStmt->get_result();
    $vehiclesStmt->close(); 

    //contracts with deliveries
    $contractsWithDeliveryStmt = $conn->prepare("
        SELECT c.Contract_ID, c.Order_ID, c.Proposed_Cost, c.Contract_Status, 
            d.Delivery_StartDate, d.Delivery_Status, d.Delivery_CurrentLocation, d.Delivery_Confirmed,
            o.Order_ID, 
            GROUP_CONCAT(
                CONCAT(
                    cr.Cargo_ID, '|', cr.Cargo_Description, '|', cr.Cargo_Weight, '|', cr.Cargo_Dimensions
                ) SEPARATOR ';'
            ) AS cargos
        FROM Contract c
        JOIN Delivery d ON c.Contract_ID = d.Contract_ID
        JOIN `Order` o ON c.Order_ID = o.Order_ID
        JOIN Order_Cargo oc ON o.Order_ID = oc.Order_ID
        JOIN Cargo cr ON oc.Cargo_ID = cr.Cargo_ID
        WHERE c.Deliverer_ID = ? AND c.Contract_Approval = 'Approved' AND d.Delivery_Status != 'Cargo Delivered'
        GROUP BY c.Contract_ID
    ");
    $contractsWithDeliveryStmt->bind_param("i", $userId);
    $contractsWithDeliveryStmt->execute();
    $contractsWithDelivery = $contractsWithDeliveryStmt->get_result();
    $contractsWithDeliveryStmt->close();

    //contracts without deliveries
    $currentContractsStmt = $conn->prepare("
        SELECT c.Contract_ID, c.Order_ID, c.Proposed_Cost, c.Contract_Status 
        FROM Contract c
        LEFT JOIN Delivery d ON c.Contract_ID = d.Contract_ID
        WHERE c.Deliverer_ID = ? 
        AND c.Contract_Status IN ('Deliverer Confirmed', 'Both Confirmed')
        AND d.Contract_ID IS NULL
    ");
    $currentContractsStmt->bind_param("i", $userId);
    $currentContractsStmt->execute();
    $currentContracts = $currentContractsStmt->get_result();
    $currentContractsStmt->close();

    //contracts proposed by a client
    $offeredContractsStmt = $conn->prepare("
        SELECT c.Contract_ID, c.Order_ID, c.Proposed_Cost, c.Contract_Status 
        FROM Contract c
        WHERE c.Deliverer_ID = ? AND c.Contract_Status = 'Client Confirmed'
    ");
    $offeredContractsStmt->bind_param("i", $userId);
    $offeredContractsStmt->execute();
    $offeredContracts = $offeredContractsStmt->get_result();
    $offeredContractsStmt->close();

    $completedDeliveriesStmt = $conn->prepare("
    SELECT 
        c.Contract_ID, 
        c.Order_ID, 
        c.Proposed_Cost, 
        d.Delivery_Status, 
        d.Delivery_CurrentLocation,
        d.Delivery_Confirmed
    FROM 
        Contract c
    JOIN 
        Delivery d ON c.Contract_ID = d.Contract_ID
    WHERE 
        c.Deliverer_ID = ? 
        AND d.Delivery_Status = 'Cargo Delivered' 
    ");
    $completedDeliveriesStmt->bind_param("i", $userId);
    $completedDeliveriesStmt->execute();
    $completedDeliveriesResult = $completedDeliveriesStmt->get_result();
    $completedDeliveriesStmt->close();

    $hasVehiclesAccept = false;
    $hasVehiclesHaggle = false;
    $hasVehiclesPropose = false;

    if (!isset($_SESSION['pressedAccept'])) {
        $_SESSION['pressedAccept'] = "";
    }

    if (!isset($_SESSION['pressedHaggle'])) {
        $_SESSION['pressedHaggle'] = "";
    }
    
    if (!isset($_SESSION['pressedPropose'])) 
    {
        $_SESSION['pressedPropose'] = "";
    }

    if (isset($_POST['rejectContract'])) {
        $contractID = $_POST['contractID'];
    
        $deleteContractStmt = $conn->prepare("DELETE FROM Contract WHERE Contract_ID = ?");
        $deleteContractStmt->bind_param("i", $contractID);
        $deleteContractStmt->execute();
        $deleteContractStmt->close();
    
        header("Location: Deliverer_Offers1.php");
        exit;
    }

    if (isset($_POST['showAcceptModal'])) {
        $selectedContractID = $_POST['contractID'];
        $selectedOrderID = $_POST['orderID'];
        
        //get the order's details
        $orderStmt = $conn->prepare("
            SELECT o.Order_ID, o.Order_Date, 
                SUM(c.Cargo_Weight) AS TotalWeight, 
                SUM(c.Cargo_Dimensions) AS TotalDimensions, 
                con.Proposed_Cost
            FROM `Order` o
            JOIN Order_Cargo oc ON o.Order_ID = oc.Order_ID
            JOIN Cargo c ON oc.Cargo_ID = c.Cargo_ID
            JOIN Contract con ON o.Order_ID = con.Order_ID  -- assuming Order_ID links to Contract
            WHERE o.Order_ID = ? AND con.Contract_ID = ?
            GROUP BY o.Order_ID, con.Proposed_Cost
        ");
        $orderStmt->bind_param("ii", $selectedOrderID, $selectedContractID);
        $orderStmt->execute();
        $selectedOrder = $orderStmt->get_result()->fetch_assoc();
        $orderStmt->close();
        
        // Fetch available vehicles for Accept modal
        $vehiclesStmt = $conn->prepare("
            SELECT Vehicle_ID, Vehicle_Make, Vehicle_Model, Vehicle_CapacityKG, Vehicle_CapacityM 
            FROM Vehicle 
            WHERE User_ID = ? AND Vehicle_UseStatus = 'Available' and Vehicle_Status = 'Approved'
        ");
        $vehiclesStmt->bind_param("i", $userId);
        $vehiclesStmt->execute();
        $vehicles = $vehiclesStmt->get_result();
        $hasVehiclesAccept = $vehicles->num_rows > 0;
        $_SESSION['pressedAccept'] = true;
        $_SESSION['pressedHaggle'] = "";
        $_SESSION['pressedPropose'] = "";
    }
    
    $alertMessage = ''; 
    $showModal = false; 

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acceptContract'])) {
        $contractID = $_POST['contractID'];
        $orderID = $_POST['orderID'];
        $selectedVehicles = $_POST['vehicleIds'] ?? [];

        if (empty($selectedVehicles)) {
            $alertMessage = "<div class='alert alert-danger'>Please select at least one vehicle before accepting the contract.</div>";
            $showModal = true;
        } else {
            try {
                $conn->begin_transaction();

                //get the order's weight and dimensions
                $orderStmt = $conn->prepare("
                    SELECT 
                        SUM(c.Cargo_Weight) AS TotalWeight, 
                        SUM(c.Cargo_Dimensions) AS TotalDimensions
                    FROM `Order` o
                    JOIN Order_Cargo oc ON o.Order_ID = oc.Order_ID
                    JOIN Cargo c ON oc.Cargo_ID = c.Cargo_ID
                    WHERE o.Order_ID = ?
                ");
                $orderStmt->bind_param("i", $orderID);
                $orderStmt->execute();
                $selectedOrder = $orderStmt->get_result()->fetch_assoc();
                $orderStmt->close();

                if (!$selectedOrder) {
                    $alertMessage = "<div class='alert alert-danger'>Order details could not be retrieved.</div>";
                    $showModal = true;
                    $conn->rollback();
                } else {
                    $totalWeight = 0;
                    $totalDimensions = 0;

                    foreach ($selectedVehicles as $vehicleID) {
                        $vehicleStmt = $conn->prepare("
                            SELECT Vehicle_CapacityKG, Vehicle_CapacityM 
                            FROM Vehicle 
                            WHERE Vehicle_ID = ?
                        ");
                        $vehicleStmt->bind_param("i", $vehicleID);
                        $vehicleStmt->execute();
                        $vehicle = $vehicleStmt->get_result()->fetch_assoc();
                        $vehicleStmt->close();

                        $totalWeight += $vehicle['Vehicle_CapacityKG'];
                        $totalDimensions += $vehicle['Vehicle_CapacityM'];
                    }

                    if ($totalWeight < $selectedOrder['TotalWeight'] || $totalDimensions < $selectedOrder['TotalDimensions']) {
                        $alertMessage = "
                            <div class='alert alert-danger'>
                                The selected vehicles do not meet the order's requirements.<br>
                                Required Weight: <strong>" . htmlspecialchars($selectedOrder['TotalWeight']) . " kg</strong>, Selected: <strong>" . htmlspecialchars($totalWeight) . " kg</strong><br>
                                Required Dimensions: <strong>" . htmlspecialchars($selectedOrder['TotalDimensions']) . " m³</strong>, Selected: <strong>" . htmlspecialchars($totalDimensions) . " m³</strong>
                            </div>";
                        $showModal = true;
                        $conn->rollback();
                    } else {//if the deliverer's capabilities exceed the requirements, a contract is confirmed, the vehicles become in use, the order is active, other contracts about the same order are removed
                        $vehicleIdsString = implode(',', $selectedVehicles);
                        $updateContractStmt = $conn->prepare("
                            UPDATE Contract 
                            SET Contract_Status = 'Both Confirmed', Vehicle_IDs = ? 
                            WHERE Contract_ID = ?
                        ");
                        $updateContractStmt->bind_param("si", $vehicleIdsString, $contractID);
                        $updateContractStmt->execute();
                        $updateContractStmt->close();

                        $removeContractsSameOrderStmt = $conn->prepare("
                            DELETE FROM Contract 
                            WHERE Order_ID = ? 
                            AND Contract_ID != ?
                        ");
                        $removeContractsSameOrderStmt->bind_param("ii", $orderID, $contractID);
                        $removeContractsSameOrderStmt->execute();
                        $removeContractsSameOrderStmt->close();

                        $updateVehicleStmt = $conn->prepare("UPDATE Vehicle SET Vehicle_UseStatus = 'In Use' WHERE Vehicle_ID = ?");
                        foreach ($selectedVehicles as $vehicleID) {
                            $updateVehicleStmt->bind_param("i", $vehicleID);
                            $updateVehicleStmt->execute();
                        }
                        $updateVehicleStmt->close();

                        $updateOrderStmt = $conn->prepare("UPDATE `Order` SET Order_Status = 'Active' WHERE Order_ID = ?");
                        $updateOrderStmt->bind_param("i", $orderID);
                        $updateOrderStmt->execute();
                        $updateOrderStmt->close();

                        $conn->commit();
                        $alertMessage = "<div class='alert alert-success'>Contract accepted successfully!</div>";
                        $showModal = true;
                        header("Location: Deliverer_Offers1.php");
                        exit;

                    }
                }
            } catch (Exception $e) {
                $conn->rollback();
                $alertMessage = "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
                $showModal = true;
            }
        }
    }

        
    if (isset($_POST['showHaggleModal'])) {
        $selectedContractID = $_POST['contractID'];
        $selectedOrderID = $_POST['orderID'];
        
        $orderStmt = $conn->prepare("
            SELECT o.Order_ID, o.Order_Date, 
                   SUM(c.Cargo_Weight) AS TotalWeight, 
                   SUM(c.Cargo_Dimensions) AS TotalDimensions, 
                   con.Proposed_Cost
            FROM `Order` o
            JOIN Order_Cargo oc ON o.Order_ID = oc.Order_ID
            JOIN Cargo c ON oc.Cargo_ID = c.Cargo_ID
            JOIN Contract con ON o.Order_ID = con.Order_ID
            WHERE o.Order_ID = ? AND con.Contract_ID = ?
            GROUP BY o.Order_ID, con.Proposed_Cost
        ");
        $orderStmt->bind_param("ii", $selectedOrderID, $selectedContractID);
        $orderStmt->execute();
        $selectedOrder = $orderStmt->get_result()->fetch_assoc();
        $orderStmt->close();
        
        $vehiclesStmt = $conn->prepare("
            SELECT Vehicle_ID, Vehicle_Make, Vehicle_Model, Vehicle_CapacityKG, Vehicle_CapacityM 
            FROM Vehicle 
            WHERE User_ID = ? AND Vehicle_UseStatus = 'Available' AND Vehicle_Status = 'Approved'
        ");
        $vehiclesStmt->bind_param("i", $userId);
        $vehiclesStmt->execute();
        $vehicles = $vehiclesStmt->get_result();
        $hasVehiclesHaggle = $vehicles->num_rows > 0;
        $_SESSION['pressedHaggle'] = true;
        $_SESSION['pressedAccept'] = "";
        $_SESSION['pressedPropose'] = "";
    }

    $alertMessageHaggle = '';
    $showHaggleModal = false;

    if (isset($_POST['submitHaggleProposal'])) {
        $contractID = $_POST['contractID'];
        $newProposedCost = $_POST['newProposedCost'];
        $orderID = $_POST['orderID'];
        $selectedVehicles = $_POST['vehicleIds'] ?? [];

        if (empty($selectedVehicles)) {
            $alertMessageHaggle = "<div class='alert alert-danger'>Please select at least one vehicle.</div>";
            $showHaggleModal = true;
        } elseif (empty($newProposedCost) || $newProposedCost <= 0) {
            $alertMessageHaggle = "<div class='alert alert-danger'>Please propose a valid cost greater than 0.</div>";
            $showHaggleModal = true;
        } else {
            try {
                $conn->begin_transaction();

                //get the total capacities of selected vehicles, so that it can be compared to the order later
                $totalWeight = 0;
                $totalDimensions = 0;

                foreach ($selectedVehicles as $vehicleID) {
                    $vehicleStmt = $conn->prepare("
                        SELECT Vehicle_CapacityKG, Vehicle_CapacityM 
                        FROM Vehicle 
                        WHERE Vehicle_ID = ?
                    ");
                    $vehicleStmt->bind_param("i", $vehicleID);
                    $vehicleStmt->execute();
                    $vehicle = $vehicleStmt->get_result()->fetch_assoc();
                    $vehicleStmt->close();

                    $totalWeight += $vehicle['Vehicle_CapacityKG'];
                    $totalDimensions += $vehicle['Vehicle_CapacityM'];
                }

                //order details
                $orderStmt = $conn->prepare("
                    SELECT o.Order_ID, o.Order_Date, 
                        SUM(c.Cargo_Weight) AS TotalWeight, 
                        SUM(c.Cargo_Dimensions) AS TotalDimensions 
                    FROM `Order` o
                    JOIN Order_Cargo oc ON o.Order_ID = oc.Order_ID
                    JOIN Cargo c ON oc.Cargo_ID = c.Cargo_ID
                    WHERE o.Order_ID = ?
                    GROUP BY o.Order_ID
                ");
                $orderStmt->bind_param("i", $orderID);
                $orderStmt->execute();
                $selectedOrder = $orderStmt->get_result()->fetch_assoc();
                $orderStmt->close();

                //check if the selected vehicles meet the order's requirements
                if ($totalWeight < $selectedOrder['TotalWeight'] || $totalDimensions < $selectedOrder['TotalDimensions']) {
                    $alertMessageHaggle = "<div class='alert alert-danger'>
                        Selected vehicles do not meet the Order's requirements.<br>
                        Required Weight: " . htmlspecialchars($selectedOrder['TotalWeight']) . " kg, Selected: " . htmlspecialchars($totalWeight) . " kg<br>
                        Required Dimensions: " . htmlspecialchars($selectedOrder['TotalDimensions']) . " m³, Selected: " . htmlspecialchars($totalDimensions) . " m³
                    </div>";
                    $showHaggleModal = true;
                    $conn->rollback();
                } else {//update the contract with the new cost, and include the IDs of the deliverer's vehicles to the contract
                    $updateContractStmt = $conn->prepare("
                        UPDATE Contract 
                        SET Contract_Status = 'Deliverer Confirmed', Proposed_Cost = ?
                        WHERE Contract_ID = ?
                    ");
                    $updateContractStmt->bind_param("di", $newProposedCost, $contractID);
                    $updateContractStmt->execute();
                    $updateContractStmt->close();

                    $vehicleIdsString = implode(',', $selectedVehicles);
                    $updateContractVehiclesStmt = $conn->prepare("
                        UPDATE Contract 
                        SET Vehicle_IDs = ? 
                        WHERE Contract_ID = ?
                    ");
                    $updateContractVehiclesStmt->bind_param("si", $vehicleIdsString, $contractID);
                    $updateContractVehiclesStmt->execute();
                    $updateContractVehiclesStmt->close();

                    $conn->commit();
                    $alertMessageHaggle = "<div class='alert alert-success'>New proposal submitted successfully!</div>";
                    $showHaggleModal = true;

                    header("Location: Deliverer_Offers1.php");
                    exit;
                }
            } catch (Exception $e) {
                $conn->rollback();
                $alertMessageHaggle = "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
                $showHaggleModal = true;
            }
        }
    }

    $alertMessagePropose = '';
    $showProposeModal = false;

    if (isset($_POST['showProposeModal'])) {
        $selectedOrderID = $_POST['orderID'];
    
        $orderStmt = $conn->prepare("
            SELECT o.Order_ID, o.Order_Date, 
                SUM(c.Cargo_Weight) AS TotalWeight, 
                SUM(c.Cargo_Dimensions) AS TotalDimensions
            FROM `Order` o
            JOIN Order_Cargo oc ON o.Order_ID = oc.Order_ID
            JOIN Cargo c ON oc.Cargo_ID = c.Cargo_ID
            WHERE o.Order_ID = ?
            GROUP BY o.Order_ID
        ");
        $orderStmt->bind_param("i", $selectedOrderID);
        $orderStmt->execute();
        $selectedOrder = $orderStmt->get_result()->fetch_assoc();
        $orderStmt->close();

        $vehiclesStmt = $conn->prepare("
            SELECT Vehicle_ID, Vehicle_Make, Vehicle_Model, Vehicle_CapacityKG, Vehicle_CapacityM 
            FROM Vehicle 
            WHERE User_ID = ? AND Vehicle_UseStatus = 'Available' AND Vehicle_Status = 'Approved'
        ");
        $vehiclesStmt->bind_param("i", $userId);
        $vehiclesStmt->execute();
        $vehicles = $vehiclesStmt->get_result();
        $hasVehiclesPropose = $vehicles->num_rows > 0;

        $_SESSION['pressedHaggle'] = "";
        $_SESSION['pressedAccept'] = "";
        $_SESSION['pressedPropose'] = true;
    }

    $userId = $_SESSION['User_ID'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proposeContract'])) {
        $orderID = $_POST['orderID'];
        $contractCost = $_POST['proposedCost'];
        $vehicleIds = $_POST['vehicleIds'] ?? [];

        if (empty($vehicleIds)) {
            $alertMessagePropose = "<div class='alert alert-danger'>Please select at least one vehicle.</div>";
            $showProposeModal = true;
        } elseif (empty($contractCost) || $contractCost <= 0) {
            $alertMessagePropose = "<div class='alert alert-danger'>Please propose a valid cost greater than 0.</div>";
            $showProposeModal = true;
        } else {
            try {
                $conn->begin_transaction();

                $orderStmt = $conn->prepare("
                    SELECT 
                        SUM(c.Cargo_Weight) AS TotalWeight, 
                        SUM(c.Cargo_Dimensions) AS TotalDimensions
                    FROM `Order` o
                    JOIN Order_Cargo oc ON o.Order_ID = oc.Order_ID
                    JOIN Cargo c ON oc.Cargo_ID = c.Cargo_ID
                    WHERE o.Order_ID = ?
                ");
                $orderStmt->bind_param("i", $orderID);
                $orderStmt->execute();
                $selectedOrder = $orderStmt->get_result()->fetch_assoc();
                $orderStmt->close();
    
                if (!$selectedOrder) {
                    $alertMessagePropose = "<div class='alert alert-danger'>Order details could not be retrieved.</div>";
                    $showProposeModal = true;
                    $conn->rollback();
                } else {
                    //get the total capacities of selected vehicles for comparison with the order
                    $totalWeight = 0;
                    $totalDimensions = 0;
    
                    foreach ($vehicleIds as $vehicleID) {
                        $vehicleStmt = $conn->prepare("
                            SELECT Vehicle_CapacityKG, Vehicle_CapacityM 
                            FROM Vehicle 
                            WHERE Vehicle_ID = ?
                        ");
                        $vehicleStmt->bind_param("i", $vehicleID);
                        $vehicleStmt->execute();
                        $vehicle = $vehicleStmt->get_result()->fetch_assoc();
                        $vehicleStmt->close();
    
                        $totalWeight += $vehicle['Vehicle_CapacityKG'];
                        $totalDimensions += $vehicle['Vehicle_CapacityM'];
                    }
    
                    if ($totalWeight < $selectedOrder['TotalWeight'] || $totalDimensions < $selectedOrder['TotalDimensions']) {
                        $alertMessagePropose = "
                            <div class='alert alert-danger'>
                                The selected vehicles do not meet the order's requirements.<br>
                                Required Weight: <strong>" . htmlspecialchars($selectedOrder['TotalWeight']) . " kg</strong>, Selected: <strong>" . htmlspecialchars($totalWeight) . " kg</strong><br>
                                Required Dimensions: <strong>" . htmlspecialchars($selectedOrder['TotalDimensions']) . " m³</strong>, Selected: <strong>" . htmlspecialchars($totalDimensions) . " m³</strong>
                            </div>";
                        $conn->rollback();
                        $showProposeModal = true;
                    } else {//create a new contract
                        $vehicleIdsString = implode(',', $vehicleIds);
                        $insertContractStmt = $conn->prepare("
                            INSERT INTO contract (Order_ID, Deliverer_ID, Proposed_Cost, Contract_Status, Vehicle_IDs, Created_Date)
                            VALUES (?, ?, ?, 'Deliverer Confirmed', ?, NOW())"
                        );
                        $insertContractStmt->bind_param("iiss", $orderID, $userId, $contractCost, $vehicleIdsString);
                        $insertContractStmt->execute();
                        $contractID = $insertContractStmt->insert_id;
                        $insertContractStmt->close();
    
                        $conn->commit();
                        $alertMessagePropose = "<div class='alert alert-success'>Contract proposed successfully!</div>";
                        $showProposeModal = true;
                        $_SESSION['pressedPropose'] = false;

                        header("Location: Deliverer_Offers1.php");
                        exit;
                    }
                }
            } catch (Exception $e) {
                $conn->rollback();
                $alertMessagePropose = "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
                $showProposeModal = true;
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['getCargoDetails']) && isset($_POST['orderID'])) {
        $orderID = intval($_POST['orderID']);

        $cargoStmt = $conn->prepare("
            SELECT Cargo_Description, Cargo_Weight, Cargo_Dimensions, Image_Path 
            FROM Cargo 
            WHERE Cargo_ID IN (
                SELECT Cargo_ID 
                FROM Order_Cargo 
                WHERE Order_ID = ?
            )
        ");
        $cargoStmt->bind_param("i", $orderID);
        $cargoStmt->execute();
        $result = $cargoStmt->get_result();
        $cargoDetails = [];

        while ($row = $result->fetch_assoc()) {
            $cargoDetails[] = $row;
        }

        $cargoStmt->close();

        echo json_encode(['cargo' => $cargoDetails]);
        exit;
    }

    if (isset($_POST['startDelivery'])) {
        $contractId = $_POST['contractId'];
        $deliveryStatus = $_POST['deliveryStatus'];
        $deliveryLocation = $_POST['deliveryLocation'];
    
        $startDeliveryStmt = $conn->prepare("
            UPDATE Delivery 
            SET Delivery_StartDate = NOW(), Delivery_Status = ?, Delivery_CurrentLocation = ? 
            WHERE Contract_ID = ?
        ");
        $startDeliveryStmt->bind_param("ssi", $deliveryStatus, $deliveryLocation, $contractId);
        $startDeliveryStmt->execute();
        $startDeliveryStmt->close();
    
        header("Location: Deliverer_Offers1.php");
        exit;
    }
    
    if (isset($_POST['updateDelivery'])) {
        $contractId = $_POST['contractId'];
        $deliveryStatus = $_POST['deliveryStatus'];
        $deliveryLocation = $_POST['deliveryLocation'];
    
        $conn->begin_transaction();
    
        try {
            if ($deliveryStatus === "Cargo Delivered") {
                $updateDeliveryStmt = $conn->prepare("
                    UPDATE Delivery 
                    SET Delivery_Status = ?, Delivery_CurrentLocation = ?, Delivery_EndDate = NOW() 
                    WHERE Contract_ID = ?
                ");
            } else {
                $updateDeliveryStmt = $conn->prepare("
                    UPDATE Delivery 
                    SET Delivery_Status = ?, Delivery_CurrentLocation = ? 
                    WHERE Contract_ID = ?
                ");
            }
            $updateDeliveryStmt->bind_param("ssi", $deliveryStatus, $deliveryLocation, $contractId);
            $updateDeliveryStmt->execute();
            $updateDeliveryStmt->close();
    
            //if the status is Cargo Delivered, update Vehicle_UseStatus for the vehicles used in the delivery
            if ($deliveryStatus === "Cargo Delivered") {
                // Get the list of Vehicle IDs from the Contract table
                $getVehicleIdsStmt = $conn->prepare("
                    SELECT Vehicle_IDs FROM Contract WHERE Contract_ID = ?
                ");
                $getVehicleIdsStmt->bind_param("i", $contractId);
                $getVehicleIdsStmt->execute();
                $getVehicleIdsStmt->bind_result($vehicleIds);
                $getVehicleIdsStmt->fetch();
                $getVehicleIdsStmt->close();
    
                if (!empty($vehicleIds)) {
                    $vehicleIdsArray = explode(',', $vehicleIds);
    
                    foreach ($vehicleIdsArray as $vehicleId) {
                        $updateVehicleStmt = $conn->prepare("
                            UPDATE Vehicle 
                            SET Vehicle_UseStatus = 'Available' 
                            WHERE Vehicle_ID = ?
                        ");
                        $updateVehicleStmt->bind_param("i", $vehicleId);
                        $updateVehicleStmt->execute();
                        $updateVehicleStmt->close();
                    }
                }
            }   
            $conn->commit();
            header("Location: Deliverer_Offers1.php");
            exit;
    
        } catch (Exception $e) {
            $conn->rollback();
            echo "Error: " . $e->getMessage();
        }
    }

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deliverer Offers</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

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

        .main-content {
            padding: 20px;
            min-height: 400px;
        }

        .content-wrapper {
            max-width: 1000px;
            margin: 0 auto;
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .btn-custom {
            margin: 10px 0;
            font-size: 16px;
            width: 100%;
        }

        .table-container {
            margin-top: 20px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th, .table td {
            padding: 12px;
            text-align: center;
        }

        .table th {
            background-color: #343a40;
            color: white;
        }

        .table td {
            background-color: #f8f9fa;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 14px;
        }

        .status-active {
            background-color: #04AA6D;
            color: white;
        }

        .status-inactive {
            background-color: #dc3545;
            color: white;
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
        From-To Deliverer Offers
    </div>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="LoggedIn_Deliverer.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="Deliverer_Offers1.php">Offers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="Deliverer_MyProfile1.php">My Profile</a>
                    </li>
                </ul>
            </div>
            <a class="btn btn-outline-light" href="LogOut.php">Log Out</a>
        </div>
    </nav>

    <div class="background-wrapper">
        <div class="main-content">
            <div class="content-wrapper">
                <h2 class="text-center">Deliveries, Contracts, Offers</h2>
                <div class="container mt-4">

                    <div class="table-responsive">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3>Current Contracts (With Deliveries)</h3><!-- toggle button to hide the table, also present on the other tables -->
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
                        <table class="table table-bordered table-striped text-center">
                            <thead class="table-dark">
                                <tr>
                                    <th>Contract ID</th>
                                    <th>Cost</th>
                                    <th>Current Location</th>
                                    <th>Status</th>
                                    <th>Update</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($contractsWithDelivery->num_rows === 0): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No deliveries found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php while ($contract = $contractsWithDelivery->fetch_assoc()): 
                                        $cargos = [];
                                        if ($contract['cargos']) {
                                            foreach (explode(';', $contract['cargos']) as $cargoData) {
                                                list($cargoId, $description, $weight, $dimensions) = explode('|', $cargoData);
                                                $cargos[] = [
                                                    'Cargo_ID' => $cargoId,
                                                    'Description' => $description,
                                                    'Weight' => $weight,
                                                    'Dimensions' => $dimensions,
                                                ];
                                            }
                                        }
                                    ?>
                                        <tr>
                                            <td>
                                                <a href="#" data-bs-toggle="modal" data-bs-target="#modalContract<?= $contract['Contract_ID'] ?>">
                                                    <?= htmlspecialchars($contract['Contract_ID']) ?>
                                                </a>
                                            </td>
                                            <td>$<?= htmlspecialchars($contract['Proposed_Cost']) ?></td>
                                            <td><?= htmlspecialchars($contract['Delivery_CurrentLocation'] ?? 'N/A') ?></td>
                                            <td>
                                                <?= htmlspecialchars($contract['Delivery_Status'] ?? 'Not Started') ?>
                                                <?php if ($contract['Delivery_Status'] === 'Cargo Delivered'): ?>
                                                    <br><strong>Confirmed:</strong> 
                                                    <?= $contract['Delivery_Confirmed'] === 'Confirmed' ? 'Yes' : 'No' ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($contract['Delivery_Status'] === 'Cargo Delivered'): ?>
                                                    <span class="badge bg-success">Completed</span>
                                                <?php else: ?>
                                                    <button 
                                                        class="btn btn-primary btn-sm open-update-modal" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#updateModal"
                                                        data-contract-id="<?= htmlspecialchars($contract['Contract_ID']) ?>"
                                                        data-current-location="<?= htmlspecialchars($contract['Delivery_CurrentLocation'] ?? '') ?>"
                                                        data-status="<?= htmlspecialchars($contract['Delivery_Status'] ?? 'Not Started') ?>">
                                                        Update
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>

                                        <!-- modal that shows the contract's details -->
                                        <div class="modal fade" id="modalContract<?= $contract['Contract_ID'] ?>" tabindex="-1" aria-labelledby="modalContractLabel<?= $contract['Contract_ID'] ?>" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="modalContractLabel<?= $contract['Contract_ID'] ?>">Contract Details</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p><strong>Order ID:</strong> <?= htmlspecialchars($contract['Order_ID']) ?></p>
                                                        <h6>Cargos</h6>
                                                        <ul>
                                                            <?php foreach ($cargos as $cargo): ?>
                                                                <li>
                                                                    <strong>Cargo ID:</strong> <?= htmlspecialchars($cargo['Cargo_ID']) ?>,
                                                                    <strong>Description:</strong> <?= htmlspecialchars($cargo['Description']) ?>,
                                                                    <strong>Weight:</strong> <?= htmlspecialchars($cargo['Weight']) ?>kg,
                                                                    <strong>Dimensions:</strong> <?= htmlspecialchars($cargo['Dimensions']) ?>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    </div>

                    <!-- modal for updating the delivery -->
                    <div class="modal fade" id="updateModal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="updateModalLabel">Update Delivery</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="contractId" id="modalContractId">
                                        <div class="mb-3">
                                            <label for="modalDeliveryStatus" class="form-label">Delivery Status</label>
                                            <select name="deliveryStatus" id="modalDeliveryStatus" class="form-select" required>
                                                <option value="Cargo Not Taken">Cargo Not Taken</option>
                                                <option value="Cargo Taken">Cargo Taken</option>
                                                <option value="Cargo Is Being Transported">Cargo Is Being Transported</option>
                                                <option value="Cargo Delivered">Cargo Delivered</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="modalDeliveryLocation" class="form-label">Current Location</label>
                                            <input type="text" name="deliveryLocation" id="modalDeliveryLocation" class="form-control" placeholder="Enter Current Location" required>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" name="updateDelivery" class="btn btn-primary">Update Delivery</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="mt-4 mb-3">Current Contracts</h4>
                        <button 
                            class="btn btn-primary collapse-btn" 
                            type="button" 
                            data-bs-toggle="collapse" 
                            data-bs-target="#currentContractsTable" 
                            aria-expanded="true" 
                            aria-controls="currentContractsTable">
                            Toggle Table
                        </button>
                    </div>

                    <div class="collapse show" id="currentContractsTable">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped text-center">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Contract ID</th>
                                        <th>Order ID</th>
                                        <th>Cost</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($currentContracts->num_rows === 0): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No current contracts found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php while ($contract = $currentContracts->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($contract['Contract_ID']) ?></td>
                                                <td><?= htmlspecialchars($contract['Order_ID']) ?></td>
                                                <td>$<?= htmlspecialchars($contract['Proposed_Cost']) ?></td>
                                                <td><?= htmlspecialchars($contract['Contract_Status']) ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    

                    <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mt-4 mb-3">Offered Contracts</h4>
                        <button 
                            class="btn btn-primary collapse-btn" 
                            type="button" 
                            data-bs-toggle="collapse" 
                            data-bs-target="#offeredContractsTable" 
                            aria-expanded="true" 
                            aria-controls="offeredContractsTable">
                            Toggle Table
                        </button>
                    </div>

                    <div class="collapse show" id="offeredContractsTable">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped text-center">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Contract ID</th>
                                        <th>Order ID</th>
                                        <th>Cost</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($offeredContracts->num_rows === 0): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No offered contracts found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php while ($contract = $offeredContracts->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($contract['Contract_ID']) ?></td>
                                                <td><?= htmlspecialchars($contract['Order_ID']) ?></td>
                                                <td>$<?= htmlspecialchars($contract['Proposed_Cost']) ?></td>
                                                <td><?= htmlspecialchars($contract['Contract_Status']) ?></td>
                                                <td class="d-flex justify-content-center gap-2">
                                                    <form method="POST">
                                                        <input type="hidden" name="contractID" value="<?= htmlspecialchars($contract['Contract_ID']) ?>">
                                                        <input type="hidden" name="orderID" value="<?= htmlspecialchars($contract['Order_ID']) ?>">
                                                        <button type="submit" name="showAcceptModal" class="btn btn-success btn-sm">Accept</button>
                                                    </form>

                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="contractID" value="<?= $contract['Contract_ID'] ?>">
                                                        <button type="submit" name="rejectContract" class="btn btn-danger btn-sm">
                                                            Reject
                                                        </button>
                                                    </form>

                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="contractID" value="<?= $contract['Contract_ID'] ?>">
                                                        <input type="hidden" name="orderID" value="<?= $contract['Order_ID'] ?>">
                                                        <button type="submit" name="showHaggleModal" class="btn btn-warning btn-sm">Haggle</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php if ($_SESSION['pressedAccept']): ?>
                        <!-- modal for accepting a contract, the deliverer will have to select vehicles -->
                        <div class="modal fade" id="acceptOrderModal" tabindex="-1" aria-labelledby="acceptOrderModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="acceptOrderModalLabel">Accept Contract</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <?= $alertMessage ?>

                                        <?php if (!$hasVehiclesAccept): ?>
                                            <p class="text-danger">You have no vehicles fit for the job, or your selected vehicles do not fit the requirements.</p>
                                        <?php elseif (isset($selectedOrder)): ?>
                                            <form method="POST">
                                                <input type="hidden" name="orderID" value="<?= htmlspecialchars($selectedOrder['Order_ID']) ?>">
                                                <input type="hidden" name="contractID" value="<?= htmlspecialchars($selectedContractID) ?>">

                                                <p><strong>Order ID:</strong> <?= htmlspecialchars($selectedOrder['Order_ID']) ?></p>
                                                <p><strong>Order Date:</strong> <?= htmlspecialchars($selectedOrder['Order_Date']) ?></p>
                                                <p><strong>Total Weight Required:</strong> <?= htmlspecialchars($selectedOrder['TotalWeight']) ?> kg</p>
                                                <p><strong>Total Dimensions Required:</strong> <?= htmlspecialchars($selectedOrder['TotalDimensions']) ?> m³</p>
                                                <p><strong>Current Cost:</strong> $ <?= htmlspecialchars($selectedOrder['Proposed_Cost']) ?> </p>

                                                <h5>Select Vehicles:</h5>
                                                <?php while ($vehicle = $vehicles->fetch_assoc()): ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="vehicleIds[]" value="<?= htmlspecialchars($vehicle['Vehicle_ID']) ?>">
                                                        <label class="form-check-label">
                                                            <?= htmlspecialchars($vehicle['Vehicle_Make'] . ' ' . $vehicle['Vehicle_Model']) ?> 
                                                            (<?= htmlspecialchars($vehicle['Vehicle_CapacityKG']) ?> kg, <?= htmlspecialchars($vehicle['Vehicle_CapacityM']) ?> m³)
                                                        </label>
                                                    </div>
                                                <?php endwhile; ?>

                                                <button type="submit" name="acceptContract" class="btn btn-primary mt-3">Confirm Selection</button>
                                            </form>
                                        <?php else: ?>
                                            <p class="text-danger">No data available to display.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($_SESSION['pressedHaggle']): ?>
                    <!-- modal that appears when the deliverer wants to haggle for a better price, also needs to select vehicles for the contract -->
                    <div class="modal fade" id="haggleOrderModal" tabindex="-1" aria-labelledby="haggleOrderModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="haggleOrderModalLabel">Haggle Contract</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <?= $alertMessageHaggle ?>

                                    <?php if (!$hasVehiclesHaggle): ?>
                                        <p class="text-danger">You have no vehicles fit for the job, or your selected vehicles do not fit the requirements.</p>
                                    <?php elseif (isset($selectedOrder)): ?>
                                        <form method="POST">
                                            <input type="hidden" name="contractID" value="<?= htmlspecialchars($selectedContractID) ?>">
                                            <input type="hidden" name="orderID" value="<?= htmlspecialchars($selectedOrderID) ?>">

                                            <p><strong>Order ID:</strong> <?= htmlspecialchars($selectedOrder['Order_ID']) ?></p>
                                            <p><strong>Order Date:</strong> <?= htmlspecialchars($selectedOrder['Order_Date']) ?></p>
                                            <p><strong>Total Weight Required:</strong> <?= htmlspecialchars($selectedOrder['TotalWeight']) ?> kg</p>
                                            <p><strong>Total Dimensions Required:</strong> <?= htmlspecialchars($selectedOrder['TotalDimensions']) ?> m³</p>
                                            <p><strong>Current Cost:</strong> $ <?= htmlspecialchars($selectedOrder['Proposed_Cost']) ?></p>

                                            <div class="mb-3">
                                                <label for="newProposedCost" class="form-label">Propose a New Cost</label>
                                                <input type="number" class="form-control" id="newProposedCost" name="newProposedCost" required>
                                            </div>

                                            <h5>Select Vehicles:</h5>
                                            <?php while ($vehicle = $vehicles->fetch_assoc()): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="vehicleIds[]" value="<?= htmlspecialchars($vehicle['Vehicle_ID']) ?>">
                                                    <label class="form-check-label">
                                                        <?= htmlspecialchars($vehicle['Vehicle_Make'] . ' ' . $vehicle['Vehicle_Model']) ?> 
                                                        (<?= htmlspecialchars($vehicle['Vehicle_CapacityKG']) ?> kg, <?= htmlspecialchars($vehicle['Vehicle_CapacityM']) ?> m³)
                                                    </label>
                                                </div>
                                            <?php endwhile; ?>

                                            <button type="submit" name="submitHaggleProposal" class="btn btn-primary mt-3">Submit New Proposal</button>
                                        </form>
                                    <?php else: ?>
                                        <p class="text-danger">No data available to display.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mt-4 mb-3">Offers</h4>
                    <button 
                        class="btn btn-primary collapse-btn" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#offersTable" 
                        aria-expanded="true" 
                        aria-controls="offersTable">
                        Toggle Table
                    </button>
                </div>

                <div class="collapse show" id="offersTable">
                    <div class="table-responsive">
                        <?php if ($offers->num_rows > 0): ?>
                            <table class="table table-bordered table-striped text-center">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Order Date</th>
                                        <th>Total Weight (kg)</th>
                                        <th>Total Dimensions (m³)</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($order = $offers->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($order['Order_ID']) ?></td>
                                            <td><?= htmlspecialchars($order['Order_Date']) ?></td>
                                            <td><?= htmlspecialchars($order['TotalWeight']) ?></td>
                                            <td><?= htmlspecialchars($order['TotalDimensions']) ?></td>
                                            <td>
                                                <form method="POST">
                                                    <input type="hidden" name="contractID" value="<?= htmlspecialchars($order['Order_ID']) ?>">
                                                    <input type="hidden" name="orderID" value="<?= htmlspecialchars($order['Order_ID']) ?>">
                                                    <button type="submit" name="showProposeModal" class="btn btn-primary btn-sm">
                                                        Propose Contract
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No available offers at the moment.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($_SESSION['pressedPropose']): ?>
                    <!-- modal that appears when a delverer wants to propose a contract; similar to the haggle modal -->
                    <div class="modal fade" id="proposeContractModal" tabindex="-1" aria-labelledby="proposeContractModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="proposeContractModalLabel">Propose Contract</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                    <div class="modal-body">
                                        <?= $alertMessagePropose ?>

                                        <?php if (!$hasVehiclesPropose): ?>
                                            <p class="text-danger">You have no vehicles fit for the job, or your selected vehicles do not fit the requirements.</p>
                                        <?php elseif (isset($selectedOrder)): ?>
                                            <form method="POST">
                                                <input type="hidden" name="orderID" value="<?= htmlspecialchars($selectedOrder['Order_ID']) ?>">
                                                <input type="hidden" name="contractID" value="<?= htmlspecialchars($selectedContractID) ?>">

                                                <p><strong>Order ID:</strong> <?= htmlspecialchars($selectedOrder['Order_ID']) ?></p>
                                                <p><strong>Order Date:</strong> <?= htmlspecialchars($selectedOrder['Order_Date']) ?></p>
                                                <p><strong>Total Weight Required:</strong> <?= htmlspecialchars($selectedOrder['TotalWeight']) ?> kg</p>
                                                <p><strong>Total Dimensions Required:</strong> <?= htmlspecialchars($selectedOrder['TotalDimensions']) ?> m³</p>

                                                <div class="mb-3">
                                                    <label for="proposedCost" class="form-label">Proposed Cost</label>
                                                    <input type="number" class="form-control" id="proposedCost" name="proposedCost" required>
                                                </div>

                                                <h5>Select Vehicles:</h5>
                                                <?php while ($vehicle = $vehicles->fetch_assoc()): ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="vehicleIds[]" value="<?= htmlspecialchars($vehicle['Vehicle_ID']) ?>">
                                                        <label class="form-check-label">
                                                            <?= htmlspecialchars($vehicle['Vehicle_Make'] . ' ' . $vehicle['Vehicle_Model']) ?> 
                                                            (<?= htmlspecialchars($vehicle['Vehicle_CapacityKG']) ?> kg, <?= htmlspecialchars($vehicle['Vehicle_CapacityM']) ?> m³)
                                                        </label>
                                                    </div>
                                                <?php endwhile; ?>

                                                <button type="submit" name="proposeContract" class="btn btn-primary mt-3">Propose Contract</button>
                                            </form>
                                        <?php else: ?>
                                            <p class="text-danger">No data available to display.</p>
                                        <?php endif; ?>
                                    </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>         

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mt-4 mb-3">Completed Deliveries</h4>
                    <button 
                        class="btn btn-primary collapse-btn" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#completedDeliveriesTable" 
                        aria-expanded="true" 
                        aria-controls="completedDeliveriesTable">
                        Toggle Table
                    </button>
                </div>

                <div class="collapse show" id="completedDeliveriesTable">
                    <div class="table-responsive">
                        <?php if ($completedDeliveriesResult->num_rows > 0): ?>
                            <table class="table table-bordered table-striped text-center">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Contract ID</th>
                                        <th>Cost</th>
                                        <th>Current Location</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($contract = $completedDeliveriesResult->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($contract['Contract_ID']) ?></td>
                                            <td><?= htmlspecialchars($contract['Proposed_Cost']) ?></td>
                                            <td><?= htmlspecialchars($contract['Delivery_CurrentLocation'] ?? 'N/A') ?></td>
                                            <td>
                                                <?= htmlspecialchars($contract['Delivery_Status']) ?>
                                                <br><strong>Delivery Confirmed: </strong>
                                                <?= htmlspecialchars($contract['Delivery_Confirmed']) ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No completed deliveries found.</p>
                        <?php endif; ?>
                    </div>
                </div>


                </div>
            </div>
        </div>
    </div>

    <script>
        //checks for which modal to show, whether the deliverer has selected vehicles, and populating modals
        <?php if ($showModal): ?>
            var myModal = new bootstrap.Modal(document.getElementById('acceptOrderModal'), {
                keyboard: false
            });
            myModal.show();
        <?php endif; ?>

        <?php if ($showHaggleModal): ?>
            var haggleModal = new bootstrap.Modal(document.getElementById('haggleOrderModal'), {
                keyboard: false
            });
            haggleModal.show();
        <?php endif; ?>

            <?php if ($_SESSION['pressedPropose']): ?>
                document.addEventListener('DOMContentLoaded', () => {
                    var proposeModal = new bootstrap.Modal(document.getElementById('proposeContractModal'));
                    proposeModal.show();
                });
            <?php endif; ?>


            document.addEventListener('DOMContentLoaded', () => 
            {    
                <?php if ($showProposeModal): ?>
                    const modal = new bootstrap.Modal(proposeContractModal);
                    modal.show();
                <?php endif; ?>

                var proposeContractModalElement = document.getElementById('proposeContractModal');
                proposeContractModalElement.addEventListener('hidden.bs.modal', function () {
                    var backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) {
                        backdrop.remove();
                    }

                    document.body.style.overflow = 'auto';
                    document.body.classList.remove('modal-open'); 
                    document.body.style.paddingRight = '';
                });

                proposeContractModalElement.addEventListener('shown.bs.modal', function () {
                    document.body.style.paddingRight = window.innerWidth - document.body.clientWidth + 'px';
                });
            });

        document.addEventListener('DOMContentLoaded', () => {
            const updateButtons = document.querySelectorAll('.open-update-modal');

            updateButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const contractId = button.getAttribute('data-contract-id');
                    const currentLocation = button.getAttribute('data-current-location');
                    const status = button.getAttribute('data-status');

                    document.getElementById('modalContractId').value = contractId;
                    document.getElementById('modalDeliveryStatus').value = status;
                    document.getElementById('modalDeliveryLocation').value = currentLocation;
                });
            });
        });

        document.addEventListener("DOMContentLoaded", function () {
            <?php if (isset($selectedContractID) && isset($selectedOrderID)): ?>
                const modal = new bootstrap.Modal(document.getElementById('acceptOrderModal'));
                modal.show();
            <?php endif; ?>
        });

        function populateHaggleModal(contractID, orderID, proposedCost) {
            document.getElementById('haggleContractID').value = contractID;
            document.getElementById('haggleOrderID').value = orderID;
            document.getElementById('haggleProposedCost').textContent = proposedCost;
        }

        document.addEventListener('DOMContentLoaded', () => {
            const submitButton = document.querySelector('button[type="submit"][name="submitHaggleProposal"]');
            const vehicleCheckboxes = document.querySelectorAll('input[name="vehicleIds[]"]');

            const updateButtonState = () => {
                const isVehicleSelected = Array.from(vehicleCheckboxes).some(checkbox => checkbox.checked);
                submitButton.disabled = !isVehicleSelected;
            };

            vehicleCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateButtonState);
            });

            updateButtonState();
        });

        document.addEventListener('DOMContentLoaded', () => {
            const haggleForm = document.querySelector('form');
            const vehicleCheckboxes = document.querySelectorAll('input[name="vehicleIds[]"]');
            const submitButton = haggleForm.querySelector('button[type="submit"]');

            haggleForm.addEventListener('submit', (event) => {
                // Only run the vehicle checkbox validation if the checkboxes are present
                if (vehicleCheckboxes.length > 0) {
                    let isVehicleSelected = false;
                    vehicleCheckboxes.forEach((checkbox) => {
                        if (checkbox.checked) {
                            isVehicleSelected = true;
                        }
                    });

                    if (!isVehicleSelected) {
                        event.preventDefault();
                        alert('Please select at least one vehicle.');
                    }
                }
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            const vehicleCheckboxes = document.querySelectorAll('input[name="vehicleIds[]"]');
            const submitButton = document.querySelector('button[name="proposeContract"]');

            function updateSubmitButtonState() {
                const isSelected = Array.from(vehicleCheckboxes).some(checkbox => checkbox.checked);
                submitButton.disabled = !isSelected;
            }

            vehicleCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSubmitButtonState);
            });
            updateSubmitButtonState();
        });

        function validateAcceptForm() {
            const vehicleCheckboxes = document.querySelectorAll('#acceptOrderModal input[name="vehicleIds[]"]');
            const isSelected = Array.from(vehicleCheckboxes).some(checkbox => checkbox.checked);

            if (!isSelected) {
                alert("Please select at least one vehicle before accepting the contract.");
                return false;
            }
            return true; 
        }

        function validateHaggleForm() {
            const vehicleCheckboxes = document.querySelectorAll('#haggleModal input[name="vehicleIds[]"]');
            const isSelected = Array.from(vehicleCheckboxes).some(checkbox => checkbox.checked);

            if (!isSelected) {
                alert("Please select at least one vehicle before haggling.");
                return false;
            }
            return true;
        }

        document.addEventListener('DOMContentLoaded', () => {
            const vehicleCheckboxes = document.querySelectorAll('#acceptOrderModal input[name="vehicleIds[]"]');
            const acceptButton = document.querySelector('#acceptOrderModal button[name="acceptContract"]');

            function toggleAcceptButton() {
                const isSelected = Array.from(vehicleCheckboxes).some(checkbox => checkbox.checked);
                acceptButton.disabled = !isSelected;
            }

            vehicleCheckboxes.forEach(checkbox => checkbox.addEventListener('change', toggleAcceptButton));
            toggleAcceptButton();
        });

        function validateForm() {
            const vehicleCheckboxes = document.querySelectorAll('input[name="vehicleIds[]"]');
            const isSelected = Array.from(vehicleCheckboxes).some(checkbox => checkbox.checked);

            if (!isSelected) {
                alert("Please select at least one vehicle.");
                return false; 
            }
            return true;

            if (!isChecked) {
                alert("Please select at least one vehicle.");
                return false;
            }
            return true;
        }

        document.addEventListener("DOMContentLoaded", function () {
            <?php if (isset($selectedContractID) && isset($selectedOrderID)): ?>
                const modal = new bootstrap.Modal(document.getElementById('haggleOrderModal'));
                modal.show();
            <?php endif; ?>

            $('#cargoModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var orderID = button.data('order-id');

                fetch('Deliverer_Offers1.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        getCargoDetails: true,
                        orderID: orderID
                    })
                })
                .then(response => response.json())
                .then(data => {
                    let content = `<h3>Cargo Details for Order ${orderID}</h3>`;
                    
                    if (data.cargo && data.cargo.length > 0) {
                        data.cargo.forEach(item => {
                            content += `<div>
                                            <p><strong>Description:</strong> ${item.Cargo_Description}</p>
                                            <p><strong>Weight (kg):</strong> ${item.Cargo_Weight}</p>
                                            <p><strong>Dimensions (m³):</strong> ${item.Cargo_Dimensions}</p>`;
                            
                            if (item.Image_Path) {
                                content += `<p><strong>Image:</strong><br>
                                                <img src="${item.Image_Path}" alt="Cargo Image" style="max-width: 300px; max-height: 200px;">
                                            </p>`;
                            }
                            content += `</div>`;
                        });
                    } else {
                        content += `<p>No cargo information found for this order.</p>`;
                    }

                    document.getElementById('modalContent').innerHTML = content;
                })
                .catch(error => console.error('Error:', error));
            });
        });
    </script>

    <div class="footer">
        <p style="font-size: 14px; margin-bottom: 0;">Anton Kovachev | © 2024</p>
    </div>

    
</body>
</html>