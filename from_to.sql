CREATE DATABASE IF NOT EXISTS `from_to`;
USE `from_to`;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 29, 2024 at 03:34 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `from_to`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `Admin_ID` int(11) NOT NULL,
  `Admin_Username` varchar(255) NOT NULL,
  `Admin_Password` varchar(255) NOT NULL,
  `Admin_Email` varchar(255) NOT NULL,
  `Admin_Phone` varchar(255) NOT NULL,
  `Admin_Approved` enum('Approved','Not Approved') NOT NULL DEFAULT 'Not Approved'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`Admin_ID`, `Admin_Username`, `Admin_Password`, `Admin_Email`, `Admin_Phone`, `Admin_Approved`) VALUES
(1, 'admin', '$2y$10$CWFzTvuVJidWlWHqyH.Sg..OK.eJDBsxZgxh0lYVK2YWL7NVfIh22', 'admin@fromto.com', '8986458780', 'Approved'),
(12, 'admin2', '$2y$10$FGCSC5Ob3Hq9h91oWpbhfed3/dV86EbisvMEkENf6P8q.yxOQdiCi', 'admin@from2.com', '0876985324', 'Approved');

-- --------------------------------------------------------

--
-- Table structure for table `cargo`
--

CREATE TABLE `cargo` (
  `Cargo_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Cargo_Description` text NOT NULL,
  `Cargo_Weight` float NOT NULL,
  `Cargo_Dimensions` varchar(50) NOT NULL,
  `Cargo_Status` enum('Pending','In Process','Delivered') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cargo`
--

INSERT INTO `cargo` (`Cargo_ID`, `User_ID`, `Cargo_Description`, `Cargo_Weight`, `Cargo_Dimensions`, `Cargo_Status`) VALUES
(48, 13, 'Candy', 100, '5', 'Pending'),
(49, 14, 'Wood', 150, '10', 'Pending'),
(50, 14, 'Alcohol', 10, '1', 'Pending'),
(51, 11, 'Candy', 1000, '50', 'Pending'),
(52, 19, 'Christmas Toy', 5, '1', 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `contract`
--

CREATE TABLE `contract` (
  `Contract_ID` int(11) NOT NULL,
  `Order_ID` int(11) NOT NULL,
  `Deliverer_ID` int(11) NOT NULL,
  `Proposed_Cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `Contract_Status` enum('Deliverer Confirmed','Both Confirmed','Rejected','Client Confirmed','Non Confirmed','Completed') DEFAULT 'Non Confirmed',
  `Contract_Approval` enum('Not Approved','Approved') DEFAULT 'Not Approved',
  `Created_Date` datetime DEFAULT current_timestamp(),
  `Updated_Date` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Vehicle_IDs` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contract`
--

INSERT INTO `contract` (`Contract_ID`, `Order_ID`, `Deliverer_ID`, `Proposed_Cost`, `Contract_Status`, `Contract_Approval`, `Created_Date`, `Updated_Date`, `Vehicle_IDs`) VALUES
(107, 44, 12, 1700.00, 'Completed', 'Approved', '2024-11-29 16:06:06', '2024-11-29 16:08:02', '18'),
(108, 44, 15, 2000.00, 'Client Confirmed', 'Not Approved', '2024-11-29 16:06:14', '2024-11-29 16:06:14', NULL),
(109, 42, 18, 111.00, 'Deliverer Confirmed', 'Not Approved', '2024-11-29 16:27:53', '2024-11-29 16:27:53', '24'),
(110, 45, 18, 222.00, 'Completed', 'Approved', '2024-11-29 16:30:01', '2024-11-29 16:33:34', '24');

-- --------------------------------------------------------

--
-- Table structure for table `delivery`
--

CREATE TABLE `delivery` (
  `Delivery_ID` int(11) NOT NULL,
  `Contract_ID` int(11) NOT NULL,
  `Delivery_StartDate` datetime DEFAULT NULL,
  `Delivery_EndDate` datetime DEFAULT NULL,
  `Delivery_Status` enum('Cargo Not Taken','Cargo Taken','Cargo Is Being Transported','Cargo Delivered') DEFAULT 'Cargo Not Taken',
  `Delivery_CurrentLocation` varchar(255) DEFAULT NULL,
  `Delivery_Confirmed` enum('Not Confirmed','Confirmed') NOT NULL DEFAULT 'Not Confirmed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery`
--

INSERT INTO `delivery` (`Delivery_ID`, `Contract_ID`, `Delivery_StartDate`, `Delivery_EndDate`, `Delivery_Status`, `Delivery_CurrentLocation`, `Delivery_Confirmed`) VALUES
(12, 107, NULL, '2024-11-29 16:07:51', 'Cargo Delivered', 'There', 'Confirmed'),
(13, 110, NULL, '2024-11-29 16:33:22', 'Cargo Delivered', 'There', 'Confirmed');

-- --------------------------------------------------------

--
-- Table structure for table `gallery`
--

CREATE TABLE `gallery` (
  `Gallery_ID` int(11) NOT NULL,
  `Entity_Type` enum('Vehicle','Cargo') NOT NULL,
  `Entity_ID` int(11) NOT NULL,
  `Image_Path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gallery`
--

INSERT INTO `gallery` (`Gallery_ID`, `Entity_Type`, `Entity_ID`, `Image_Path`) VALUES
(16, 'Cargo', 48, 'uploads/cargo_images/cargo_48_6749c5ba2a148_candy.jpg'),
(17, 'Cargo', 49, 'uploads/cargo_images/cargo_49_6749c614ac5ab_What-is-timber-wood-and-which-are-the-best-types-f.jpg'),
(18, 'Cargo', 50, 'uploads/cargo_images/cargo_50_6749c614ad8b4_Booze.jpg'),
(21, 'Vehicle', 22, 'uploads/vehicles/vehicle_22_Volvo.jpg'),
(22, 'Vehicle', 23, 'uploads/vehicles/vehicle_23_brichka_by_blackblackbird13_de5knm3-fullview.jpg'),
(23, 'Vehicle', 18, 'uploads/vehicles/vehicle_18_139571996.jpg'),
(24, 'Cargo', 51, 'uploads/cargo_images/cargo_51_6749ca3309f2d_candy.jpg'),
(25, 'Vehicle', 24, 'uploads/vehicles/vehicle_24_kola.jpg'),
(26, 'Cargo', 52, 'uploads/cargo_images/cargo_52_6749cfc3de457_319575759_470364565171064_4602256776435852646_n.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `order`
--

CREATE TABLE `order` (
  `Order_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Order_Date` datetime NOT NULL,
  `Order_Status` enum('Pending','Active','Completed') DEFAULT 'Pending',
  `Order_Approved` enum('Approved','Not Approved') NOT NULL DEFAULT 'Not Approved',
  `Order_FromLocation` text DEFAULT NULL,
  `Order_ToLocation` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order`
--

INSERT INTO `order` (`Order_ID`, `User_ID`, `Order_Date`, `Order_Status`, `Order_Approved`, `Order_FromLocation`, `Order_ToLocation`) VALUES
(42, 13, '2024-11-29 15:46:34', 'Pending', 'Approved', 'Here', 'There'),
(43, 14, '2024-11-29 15:48:04', 'Pending', 'Approved', 'Tuk', 'Tam'),
(44, 11, '2024-11-29 16:05:39', 'Completed', 'Approved', 'Herer', 'There'),
(45, 19, '2024-11-29 16:29:23', 'Completed', 'Approved', 'Blagoevgrad', 'Plovdiv');

-- --------------------------------------------------------

--
-- Table structure for table `order_cargo`
--

CREATE TABLE `order_cargo` (
  `OrderCargo_ID` int(11) NOT NULL,
  `Order_ID` int(11) NOT NULL,
  `Cargo_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_cargo`
--

INSERT INTO `order_cargo` (`OrderCargo_ID`, `Order_ID`, `Cargo_ID`) VALUES
(48, 42, 48),
(49, 43, 49),
(50, 43, 50),
(51, 44, 51),
(52, 45, 52);

-- --------------------------------------------------------

--
-- Table structure for table `review`
--

CREATE TABLE `review` (
  `Review_ID` int(11) NOT NULL,
  `Contract_ID` int(11) DEFAULT NULL,
  `User_ID` int(11) DEFAULT NULL,
  `Review_Rating` int(11) DEFAULT NULL CHECK (`Review_Rating` >= 1 and `Review_Rating` <= 5),
  `Review_Comment` text DEFAULT NULL,
  `Review_Date` datetime DEFAULT current_timestamp(),
  `Review_Approved` enum('Approved','Pending') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `review`
--

INSERT INTO `review` (`Review_ID`, `Contract_ID`, `User_ID`, `Review_Rating`, `Review_Comment`, `Review_Date`, `Review_Approved`) VALUES
(14, 107, 11, 5, 'AMAZING SERVICE!', '2024-11-29 16:08:13', 'Approved'),
(15, 110, 19, 5, 'Super!', '2024-11-29 16:33:42', 'Approved');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `User_ID` int(11) NOT NULL,
  `User_Username` varchar(255) NOT NULL,
  `User_Password` varchar(255) NOT NULL,
  `User_Phone` varchar(10) NOT NULL,
  `User_Email` varchar(255) NOT NULL,
  `User_Role` enum('Client','Deliverer') NOT NULL,
  `User_Approved` enum('Approved','Not Approved') NOT NULL DEFAULT 'Approved',
  `Review_IDs` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`User_ID`, `User_Username`, `User_Password`, `User_Phone`, `User_Email`, `User_Role`, `User_Approved`, `Review_IDs`) VALUES
(11, 'CLIENT', '$2y$10$d4MJH4Hox1KTJ1XjpvgygOwx1IOxCizagze88tB6uAtTVO5BC94.2', '0852345345', 'sdf@dff.com', 'Client', 'Approved', NULL),
(12, 'DELIVERER', '$2y$10$MpzLnj1eWh8oroYSWXriB.qpNicuZMn2dW7cPdD4vR6Il17NwjW6m', '0887345987', 'del@del.com', 'Deliverer', 'Approved', '14'),
(13, 'Anton', '$2y$10$PDat83zi/8iT9RbHE60jO.SDoM7aci2qOsZmklbwiEn0/9LVdVrQC', '0878547896', 'anton@toni.com', 'Client', 'Approved', NULL),
(14, 'cliento', '$2y$10$aZiPkpo1o4lDWMpDONhqdersW6NqUECg1r1BipW7HTXcjU7hC1KtC', '0234298374', 'lcies@gmail.com', 'Client', 'Approved', NULL),
(15, 'ToniBoni', '$2y$10$/qKQRKE1ABiaIb3TQQohN.uQ.bPOoncsHOsASX90ysKu2XTvzbtYK', '0876958746', 'toni@boni.com', 'Deliverer', 'Approved', NULL),
(16, 'Ganya', '$2y$10$gWjG5UwicuuuKZAg3l7K..F467abzmn66BUR1WMjjf9FdmgHwV3JO', '0876698547', 'baiganya@aleko.com', 'Deliverer', 'Approved', NULL),
(18, 'test', '$2y$10$L7ZvgjzegYKsRoCc7Qy4JeKAvZcvJH0hVSl/hFOYqA03dh8Yv4Hoi', '0879684598', 'test@test.com', 'Deliverer', 'Approved', '15'),
(19, 'TEST1', '$2y$10$rl/.yd6VDU29AOH4v3M6dOntJGpkVFgM.VUmi.9q11XTooCHa54Tq', '0874698532', 'TEST1@TEST1.com', 'Client', 'Approved', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vehicle`
--

CREATE TABLE `vehicle` (
  `Vehicle_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Vehicle_Make` varchar(100) NOT NULL,
  `Vehicle_Model` varchar(100) NOT NULL,
  `Vehicle_Type` varchar(100) NOT NULL,
  `Vehicle_CapacityM` decimal(10,2) NOT NULL,
  `Vehicle_CapacityKG` decimal(10,2) NOT NULL,
  `Vehicle_Status` enum('Approved','Not Approved') NOT NULL DEFAULT 'Not Approved',
  `Vehicle_UseStatus` enum('In Use','Available') NOT NULL DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle`
--

INSERT INTO `vehicle` (`Vehicle_ID`, `User_ID`, `Vehicle_Make`, `Vehicle_Model`, `Vehicle_Type`, `Vehicle_CapacityM`, `Vehicle_CapacityKG`, `Vehicle_Status`, `Vehicle_UseStatus`) VALUES
(18, 12, 'MAN', 'TGX', 'Truck', 90.00, 29000.00, 'Approved', 'Available'),
(22, 15, 'Volvo', 'Electric', 'Truck', 99.00, 30000.00, 'Approved', 'Available'),
(23, 16, 'Karuca', 'Hubava', 'Other', 5.00, 80.00, 'Approved', 'Available'),
(24, 18, 'Fast', 'Car', 'Car', 10.00, 300.00, 'Approved', 'Available');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`Admin_ID`);

--
-- Indexes for table `cargo`
--
ALTER TABLE `cargo`
  ADD PRIMARY KEY (`Cargo_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `contract`
--
ALTER TABLE `contract`
  ADD PRIMARY KEY (`Contract_ID`),
  ADD KEY `Order_ID` (`Order_ID`),
  ADD KEY `Deliverer_ID` (`Deliverer_ID`);

--
-- Indexes for table `delivery`
--
ALTER TABLE `delivery`
  ADD PRIMARY KEY (`Delivery_ID`),
  ADD KEY `Contract_ID` (`Contract_ID`);

--
-- Indexes for table `gallery`
--
ALTER TABLE `gallery`
  ADD PRIMARY KEY (`Gallery_ID`);

--
-- Indexes for table `order`
--
ALTER TABLE `order`
  ADD PRIMARY KEY (`Order_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `order_cargo`
--
ALTER TABLE `order_cargo`
  ADD PRIMARY KEY (`OrderCargo_ID`),
  ADD KEY `Order_ID` (`Order_ID`),
  ADD KEY `Cargo_ID` (`Cargo_ID`);

--
-- Indexes for table `review`
--
ALTER TABLE `review`
  ADD PRIMARY KEY (`Review_ID`),
  ADD KEY `Contract_ID` (`Contract_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`User_ID`),
  ADD UNIQUE KEY `User_Email` (`User_Email`);

--
-- Indexes for table `vehicle`
--
ALTER TABLE `vehicle`
  ADD PRIMARY KEY (`Vehicle_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `Admin_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `cargo`
--
ALTER TABLE `cargo`
  MODIFY `Cargo_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `contract`
--
ALTER TABLE `contract`
  MODIFY `Contract_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `delivery`
--
ALTER TABLE `delivery`
  MODIFY `Delivery_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `gallery`
--
ALTER TABLE `gallery`
  MODIFY `Gallery_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `order`
--
ALTER TABLE `order`
  MODIFY `Order_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `order_cargo`
--
ALTER TABLE `order_cargo`
  MODIFY `OrderCargo_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `review`
--
ALTER TABLE `review`
  MODIFY `Review_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `User_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `vehicle`
--
ALTER TABLE `vehicle`
  MODIFY `Vehicle_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cargo`
--
ALTER TABLE `cargo`
  ADD CONSTRAINT `cargo_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`) ON DELETE CASCADE;

--
-- Constraints for table `contract`
--
ALTER TABLE `contract`
  ADD CONSTRAINT `contract_ibfk_1` FOREIGN KEY (`Order_ID`) REFERENCES `order` (`Order_ID`),
  ADD CONSTRAINT `contract_ibfk_2` FOREIGN KEY (`Deliverer_ID`) REFERENCES `user` (`User_ID`);

--
-- Constraints for table `delivery`
--
ALTER TABLE `delivery`
  ADD CONSTRAINT `delivery_ibfk_1` FOREIGN KEY (`Contract_ID`) REFERENCES `contract` (`Contract_ID`) ON DELETE CASCADE;

--
-- Constraints for table `order`
--
ALTER TABLE `order`
  ADD CONSTRAINT `order_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`);

--
-- Constraints for table `order_cargo`
--
ALTER TABLE `order_cargo`
  ADD CONSTRAINT `order_cargo_ibfk_1` FOREIGN KEY (`Order_ID`) REFERENCES `order` (`Order_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_cargo_ibfk_2` FOREIGN KEY (`Cargo_ID`) REFERENCES `cargo` (`Cargo_ID`) ON DELETE CASCADE;

--
-- Constraints for table `review`
--
ALTER TABLE `review`
  ADD CONSTRAINT `review_ibfk_1` FOREIGN KEY (`Contract_ID`) REFERENCES `contract` (`Contract_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `review_ibfk_2` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`) ON DELETE CASCADE;

--
-- Constraints for table `vehicle`
--
ALTER TABLE `vehicle`
  ADD CONSTRAINT `vehicle_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
