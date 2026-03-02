-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Oct 27, 2025 at 08:52 AM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `inventory_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

DROP TABLE IF EXISTS `assets`;
CREATE TABLE IF NOT EXISTS `assets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `asset_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `asset_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_acquired` date DEFAULT NULL,
  `serial_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `accountable_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `authorized_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_issued` date DEFAULT NULL,
  `status` enum('Assigned','Available','Maintenance','Repair','Damaged','Returned') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Available',
  `date_added` datetime DEFAULT CURRENT_TIMESTAMP,
  `deleted` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_id` (`asset_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6932 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assets`
--

INSERT INTO `assets` (`id`, `asset_name`, `asset_id`, `category`, `date_acquired`, `serial_number`, `description`, `accountable_name`, `authorized_by`, `date_issued`, `status`, `date_added`, `deleted`) VALUES
(6931, 'Laptop', 'LT-01', 'Laptop', '2025-10-27', 'acerlt01111', 'unit assigned to Sean with signed accountability form', 'Sean', 'Rogino Mahinay', '2025-10-27', 'Assigned', '2025-10-27 15:22:38', 0);

-- --------------------------------------------------------

--
-- Table structure for table `asset_files`
--

DROP TABLE IF EXISTS `asset_files`;
CREATE TABLE IF NOT EXISTS `asset_files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `asset_id` int NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `upload_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `asset_id` (`asset_id`)
) ENGINE=MyISAM AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consumables`
--

DROP TABLE IF EXISTS `consumables`;
CREATE TABLE IF NOT EXISTS `consumables` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL DEFAULT 'Uncategorized',
  `current_stock` int NOT NULL DEFAULT '0',
  `min_stock_level` int NOT NULL DEFAULT '10',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_item` (`item_name`(150))
) ENGINE=MyISAM AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `consumables`
--

INSERT INTO `consumables` (`id`, `item_name`, `category`, `current_stock`, `min_stock_level`, `created_at`) VALUES
(1, 'Paper (A4)', 'Office Supplies', 0, 10, '2025-10-20 07:14:46'),
(2, 'Paper (Letter)', 'Office Supplies', 0, 5, '2025-10-20 07:14:46'),
(3, 'Paper (Legal)', 'Office Supplies', 0, 5, '2025-10-20 07:14:46'),
(4, 'Pens', 'Office Supplies', 0, 20, '2025-10-20 07:14:46'),
(5, 'Pencils', 'Office Supplies', 0, 20, '2025-10-20 07:14:46'),
(6, 'Markers', 'Office Supplies', 0, 15, '2025-10-20 07:14:46'),
(7, 'Notebooks', 'Office Supplies', 0, 10, '2025-10-20 07:14:46'),
(8, 'Sticky Notes', 'Office Supplies', 0, 15, '2025-10-20 07:14:46'),
(9, 'Staplers', 'Office Supplies', 0, 5, '2025-10-20 07:14:46'),
(10, 'Staples', 'Office Supplies', 0, 20, '2025-10-20 07:14:46'),
(11, 'Paper Clips', 'Office Supplies', 0, 20, '2025-10-20 07:14:46'),
(12, 'Binder Clips', 'Office Supplies', 0, 10, '2025-10-20 07:14:46'),
(13, 'Envelopes', 'Office Supplies', 0, 15, '2025-10-20 07:14:46'),
(14, 'Folders', 'Office Supplies', 0, 15, '2025-10-20 07:14:46'),
(15, 'Tape (Scotch)', 'Office Supplies', 0, 10, '2025-10-20 07:14:46'),
(16, 'Tape (Masking)', 'Office Supplies', 0, 5, '2025-10-20 07:14:46'),
(17, 'Highlighters', 'Office Supplies', 0, 10, '2025-10-20 07:14:46'),
(18, 'Correction Fluid/Tape', 'Office Supplies', 0, 10, '2025-10-20 07:14:46'),
(19, 'Whiteboard Markers', 'Office Supplies', 0, 10, '2025-10-20 07:14:46'),
(20, 'Whiteboard Erasers', 'Office Supplies', 0, 5, '2025-10-20 07:14:46'),
(21, 'Replacement Laptop Battery', 'Laptop Repair Items', 0, 3, '2025-10-20 07:14:46'),
(22, 'Laptop Charger/Adapter', 'Laptop Repair Items', 0, 5, '2025-10-20 07:14:46'),
(23, 'Screws and Screwdrivers (precision sets)', 'Laptop Repair Items', 0, 5, '2025-10-20 07:14:46'),
(24, 'Thermal Paste', 'Laptop Repair Items', 46, 5, '2025-10-20 07:14:46'),
(25, 'Replacement Keyboard', 'Laptop Repair Items', 0, 5, '2025-10-20 07:14:46'),
(26, 'Replacement Screen/Display Panel', 'Laptop Repair Items', 0, 3, '2025-10-20 07:14:46'),
(27, 'Laptop Hinges', 'Laptop Repair Items', 0, 5, '2025-10-20 07:14:46'),
(28, 'Cables and Connectors', 'Laptop Repair Items', 0, 10, '2025-10-20 07:14:46'),
(29, 'Cleaning Brushes and Cloths', 'Laptop Repair Items', 0, 10, '2025-10-20 07:14:46'),
(30, 'Anti-static Wrist Straps', 'Laptop Repair Items', 0, 5, '2025-10-20 07:14:46'),
(31, 'Spare RAM Modules', 'Laptop Repair Items', 0, 5, '2025-10-20 07:14:46'),
(32, 'SSD/HDD Drives', 'Laptop Repair Items', 0, 3, '2025-10-20 07:14:46'),
(33, 'Warranty Stickers', 'Warranty Control Items', 0, 50, '2025-10-20 07:14:46'),
(34, 'Tamper-evident Seals', 'Warranty Control Items', 0, 50, '2025-10-20 07:14:46'),
(35, 'Warranty Cards', 'Warranty Control Items', 0, 20, '2025-10-20 07:14:46'),
(36, 'Service Tags', 'Warranty Control Items', 0, 20, '2025-10-20 07:14:46'),
(37, 'Barcode Labels', 'Warranty Control Items', 0, 50, '2025-10-20 07:14:46'),
(38, 'Serial Number Stickers', 'Warranty Control Items', 0, 50, '2025-10-20 07:14:46'),
(39, 'Documentation Envelopes', 'Warranty Control Items', 0, 20, '2025-10-20 07:14:46'),
(40, 'Packing Boxes', 'Shipment Items', 0, 20, '2025-10-20 07:14:46'),
(41, 'Bubble Wrap', 'Shipment Items', 0, 5, '2025-10-20 07:14:46'),
(42, 'Packing Tape', 'Shipment Items', 0, 10, '2025-10-20 07:14:46'),
(43, 'Foam Peanuts or Padding', 'Shipment Items', 0, 10, '2025-10-20 07:14:46'),
(44, 'Shipping Labels', 'Shipment Items', 0, 50, '2025-10-20 07:14:46'),
(45, 'Fragile Stickers', 'Shipment Items', 0, 50, '2025-10-20 07:14:46'),
(46, 'Zip Ties', 'Shipment Items', 0, 20, '2025-10-20 07:14:46'),
(47, 'Pallet Wrap', 'Shipment Items', 0, 5, '2025-10-20 07:14:46'),
(48, 'Document Envelopes (Shipping)', 'Shipment Items', 0, 10, '2025-10-20 07:14:46'),
(49, 'Service Kits (screwdrivers, pliers, tweezers)', 'Onsite Service Engineer Items', 0, 2, '2025-10-20 07:14:46'),
(50, 'Portable Diagnostic Tools', 'Onsite Service Engineer Items', 0, 2, '2025-10-20 07:14:46'),
(51, 'Laptop or tablet for reporting', 'Onsite Service Engineer Items', 0, 2, '2025-10-20 07:14:46'),
(52, 'Spare parts (common replacements)', 'Onsite Service Engineer Items', 0, 5, '2025-10-20 07:14:46'),
(53, 'Safety gloves', 'Onsite Service Engineer Items', 0, 10, '2025-10-20 07:14:46'),
(54, 'Cable testers', 'Onsite Service Engineer Items', 0, 2, '2025-10-20 07:14:46'),
(55, 'Network cables', 'Onsite Service Engineer Items', 0, 10, '2025-10-20 07:14:46'),
(56, 'Power strips and extension cords', 'Onsite Service Engineer Items', 0, 5, '2025-10-20 07:14:46'),
(57, 'Personal protective equipment (PPE)', 'Onsite Service Engineer Items', 0, 5, '2025-10-20 07:14:46'),
(58, 'Documentation forms and checklists', 'Onsite Service Engineer Items', 0, 10, '2025-10-20 07:14:46'),
(59, 'Mobile phone and charger', 'Onsite Service Engineer Items', 0, 1, '2025-10-20 07:14:46'),
(60, 'Portable Tool Bag or Case', 'Onsite Service Engineer Items', 0, 1, '2025-10-20 07:14:46');

-- --------------------------------------------------------

--
-- Table structure for table `consumable_activity`
--

DROP TABLE IF EXISTS `consumable_activity`;
CREATE TABLE IF NOT EXISTS `consumable_activity` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_name` varchar(255) NOT NULL,
  `action_by` varchar(255) NOT NULL,
  `quantity_change` varchar(50) NOT NULL,
  `activity_date` datetime NOT NULL,
  `status` varchar(100) NOT NULL,
  `user_id_requester` varchar(255) NOT NULL,
  `user_id_dispenser` varchar(255) NOT NULL,
  `ReceivedBy` varchar(100) DEFAULT NULL,
  `ReleasedBy` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consumable_logs`
--

DROP TABLE IF EXISTS `consumable_logs`;
CREATE TABLE IF NOT EXISTS `consumable_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `item_name` varchar(255) NOT NULL,
  `transaction_type` enum('ADD','USE') NOT NULL,
  `quantity_change` int NOT NULL,
  `user_id_requester` varchar(255) NOT NULL,
  `user_id_dispenser` varchar(255) NOT NULL,
  `log_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `ReceivedBy` varchar(100) DEFAULT NULL,
  `ReleasedBy` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`log_id`),
  KEY `user_id_requester` (`user_id_requester`(250)),
  KEY `user_id_dispenser` (`user_id_dispenser`(250))
) ENGINE=MyISAM AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `consumable_logs`
--

INSERT INTO `consumable_logs` (`log_id`, `item_name`, `transaction_type`, `quantity_change`, `user_id_requester`, `user_id_dispenser`, `log_date`, `ReceivedBy`, `ReleasedBy`) VALUES
(18, 'Thermal Paste', 'ADD', 48, '', '1', '2025-10-17 00:00:00', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `history`
--

DROP TABLE IF EXISTS `history`;
CREATE TABLE IF NOT EXISTS `history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `asset_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `accountable_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_asset_id` (`asset_id`)
) ENGINE=InnoDB AUTO_INCREMENT=97 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

DROP TABLE IF EXISTS `inventory`;
CREATE TABLE IF NOT EXISTS `inventory` (
  `ItemID` int NOT NULL AUTO_INCREMENT,
  `CaseID` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `PartNumber` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `ItemDescription` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `SerialNumber` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `DateIn` date NOT NULL,
  `AddedBy` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `ItemStatus` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `DispatchStatus` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `ReleasedBy` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `ReceivedBy` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `DateofRelease` datetime DEFAULT NULL,
  `UpdatedStatus` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `NewSerialNumber` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `DateUpdated` datetime DEFAULT NULL,
  `UpdatedBy` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `ReturnedBy` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `DateofReturntoFacility` datetime DEFAULT NULL,
  `Region` varchar(100) DEFAULT NULL,
  `ConditionDetails` text,
  `OnsiteCondition` text,
  `rtfcss` enum('yes','no') DEFAULT 'no',
  `injection_status` varchar(50) DEFAULT 'Not Started' COMMENT 'Tracks the current state of the mainboard injection process',
  PRIMARY KEY (`ItemID`),
  UNIQUE KEY `Serial Number` (`SerialNumber`)
) ENGINE=MyISAM AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`ItemID`, `CaseID`, `PartNumber`, `ItemDescription`, `SerialNumber`, `DateIn`, `AddedBy`, `ItemStatus`, `DispatchStatus`, `ReleasedBy`, `ReceivedBy`, `DateofRelease`, `UpdatedStatus`, `NewSerialNumber`, `DateUpdated`, `UpdatedBy`, `ReturnedBy`, `DateofReturntoFacility`, `Region`, `ConditionDetails`, `OnsiteCondition`, `rtfcss`, `injection_status`) VALUES
(42, '12341234', 'WQER', 'Memory 16GB', 'ACER-0011', '2025-10-27', 'admin', 'DISPATCHED', 'OnSite', 'admin', 'TSE_Taradji', '2025-10-27 00:00:00', 'Consumed', 'aaa1', '2025-10-27 07:14:04', 'admin', 'admin', '2025-10-27 00:00:00', 'NCR/Metro Manila', 'Defects Found: None Identified | General Notes: N/A', 'a1', 'yes', ''),
(43, '123', 'QWE', 'Touchpad', 'QWE', '2025-10-27', 'admin', 'New', 'OnSite', 'admin', 'TSE_Taradji', '2025-10-27 00:00:00', NULL, NULL, '2025-10-27 16:06:15', NULL, NULL, NULL, 'NCR/Metro Manila', 'Defects Found: None Identified | General Notes: N/A', NULL, 'no', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
CREATE TABLE IF NOT EXISTS `logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `asset_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  `id_link` int DEFAULT NULL,
  `id_user_link` int DEFAULT NULL,
  `module` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `fk_logs_id_link` (`id_link`)
) ENGINE=InnoDB AUTO_INCREMENT=290 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `logs`
--

INSERT INTO `logs` (`id`, `user_id`, `action`, `asset_id`, `description`, `timestamp`, `id_link`, `id_user_link`, `module`) VALUES
(261, 0, 'Added jhkadsjhk as Manager', NULL, 'User \'jhkadsjhk\' (ashjkdhjkad) created with the role of Manager.', '2025-10-24 16:13:41', NULL, 25, NULL),
(262, 0, 'Added hjkqewhjk as Manager', NULL, 'User \'hjkqewhjk\' (asjkhdhahjksdhjk) created with the role of Manager.', '2025-10-24 16:13:54', NULL, 26, NULL),
(263, 0, 'Deleted jhkadsjhk', NULL, 'User \'jhkadsjhk\' was removed from the system.', '2025-10-24 16:13:59', NULL, 25, NULL),
(264, 0, 'Deleted hjkqewhjk', NULL, 'User \'hjkqewhjk\' was removed from the system.', '2025-10-24 16:14:04', NULL, 26, NULL),
(265, 0, 'Deleted itsoc_jai', NULL, 'User \'itsoc_jai\' was removed from the system.', '2025-10-24 16:14:15', NULL, 23, NULL),
(266, 0, 'Added itsoc_renvic as Authorized Personnel', NULL, 'User \'itsoc_renvic\' (Renvic) created with the role of Authorized Personnel.', '2025-10-24 16:15:35', NULL, 27, NULL),
(267, 0, 'Added New Inventory Item', NULL, 'Added \'HDD With OS\' (SN: \'2311123123\') to \'NCR/Metro Manila\' inventory under Case ID \'123456B\'.', '2025-10-24 16:24:25', 41, NULL, 'Inventory'),
(268, 0, 'Added Shipment Item', NULL, 'Added \'Mainboard\' (SN: qwer1234, Case: 123412341234) to shipment for Laren (PRONCE). Courier: LBC.', '2025-10-24 16:28:07', 15, NULL, 'Shipment'),
(269, 0, 'Added Shipment Item', NULL, 'Added \'Lowercase\' (SN: qwerqwer, Case: 123412341234) to shipment for Laren (PRONCE). Courier: LBC.', '2025-10-24 16:28:07', 16, NULL, 'Shipment'),
(270, 0, 'Updated User itsoc_renvic', NULL, 'Changed role from \'Authorized Personnel\' to \'Manager\'', '2025-10-24 16:54:52', NULL, 27, NULL),
(271, 0, 'Updated User itsoc_seanadam', NULL, 'Changed role from \'Authorized Personnel\' to \'Manager\'\n Updated Password', '2025-10-24 16:56:27', NULL, 21, NULL),
(272, 0, 'Added qq', 'qq', 'Added asset: qq', '2025-10-27 11:39:13', 6930, NULL, NULL),
(273, 0, 'Updated User itsoc_seanadam', NULL, 'Updated Password', '2025-10-27 15:03:23', NULL, 21, NULL),
(274, 0, 'Added New Inventory Item', NULL, 'Added \'Memory 16GB\' (SN: \'ACER-0011\') to \'NCR/Metro Manila\' inventory under Case ID \'12341234\'.', '2025-10-27 15:04:36', 42, NULL, 'Inventory'),
(275, 0, 'Added TSE_Taradji as Authorized Personnel', NULL, 'User \'TSE_Taradji\' (Alhamid) created with the role of Authorized Personnel.', '2025-10-27 15:09:50', NULL, 28, NULL),
(276, 0, 'Dispatched Item', NULL, 'Dispatched \'Memory 16GB\' (SN: ACER-0011, Case: 12341234) to TSE_Taradji.', '2025-10-27 15:10:16', 42, NULL, 'Inventory'),
(277, 0, 'Updated Onsite Item', NULL, 'Item (SN: ACER-0011, Case: 12341234) updated. New Status: \'Consumed\'. New SN: \'aaa1\'. Onsite Condition: \'a1\'.', '2025-10-27 15:14:04', 42, NULL, 'Inventory'),
(278, 0, 'Approved Item for Return', NULL, 'Item \'Memory 16GB\' (SN: ACER-0011, Case: 12341234) was approved for return.', '2025-10-27 15:14:43', 42, NULL, 'Inventory'),
(279, 0, 'Returned Item to Facility', NULL, 'Item \'Memory 16GB\' (SN: ACER-0011, Case: 12341234) was marked as \'Returned to Facility\' by admin.', '2025-10-27 15:15:04', 42, NULL, 'Inventory'),
(280, 0, 'Added Shipment Item', NULL, 'Added \'Lowercase\' (SN: acer12345, Case: acer 3) to shipment for jpoy (ace;pgic). Courier: LBC.', '2025-10-27 15:18:52', 17, NULL, 'Shipment'),
(281, 0, 'Received Inbound Item', NULL, 'Received item for Case \'acer 3\'. Original SN: \'acer12345\' -> New SN: \'wrwerwedfdf\'. Original PN: \'acerrrrr\' -> New PN: \'acerrrrr\'. New Status: \'Returned - Faulty\'. Tracking: \'dfgdfgdsgsdgfsdfgd\'.', '2025-10-27 15:19:55', 17, NULL, 'Shipment'),
(282, 0, 'Approved Item for Return', NULL, 'Item \'Lowercase\' (SN: acer12345, Case: acer 3) was approved for return.', '2025-10-27 15:20:14', 17, NULL, 'Inventory'),
(283, 0, 'Returned Item to Facility', NULL, 'Item \'Lowercase\' (SN: acer12345, Case: acer 3) was marked as \'Returned to Facility\' by Renvic.', '2025-10-27 15:20:28', 17, NULL, 'Inventory'),
(284, 0, 'Added LT-01', 'LT-01', 'Added asset: Laptop', '2025-10-27 15:22:38', 6931, NULL, NULL),
(285, 0, 'Added New Inventory Item', NULL, 'Added \'Touchpad\' (SN: \'QWE\') to \'NCR/Metro Manila\' inventory under Case ID \'123\'.', '2025-10-27 16:02:29', 43, NULL, 'Inventory'),
(286, 0, 'Dispatched Item', NULL, 'Dispatched \'Touchpad\' (SN: QWE, Case: 123) to TSE_Taradji.', '2025-10-27 16:06:15', 43, NULL, 'Inventory'),
(287, 0, 'Inbound Item Receipt', '1123', 'Item received. Case ID: 123, Model: 123, Status: Received Defective. Defects: None.', '2025-10-27 16:43:01', 123, NULL, 'Inbound Receipt'),
(288, 0, 'Inbound Item Receipt', '123', 'Item received. Case ID: 123, Model: 123, Status: Received Defective. Defects: None.', '2025-10-27 16:44:17', 123, NULL, 'Inbound Receipt'),
(289, 0, 'Inbound Item Receipt', '123', 'Item received. Case ID: 123, Model: 123, Status: Received Defective. Defects: None.', '2025-10-27 16:45:52', 123, NULL, 'Inbound Receipt');

-- --------------------------------------------------------

--
-- Table structure for table `monitor`
--

DROP TABLE IF EXISTS `monitor`;
CREATE TABLE IF NOT EXISTS `monitor` (
  `monitor_id` int NOT NULL AUTO_INCREMENT,
  `serialnumber` varchar(100) NOT NULL,
  `model` varchar(50) DEFAULT NULL,
  `datepurchased` date DEFAULT NULL,
  `caseid` varchar(100) DEFAULT NULL,
  `problem` varchar(100) DEFAULT NULL,
  `waybillnumber` varchar(100) DEFAULT NULL,
  `recievedby` varchar(100) DEFAULT NULL,
  `item_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `datein` date DEFAULT NULL,
  `item_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `last_updated` date DEFAULT NULL,
  `ReceivedBy` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `ReleasedBy` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `DateOfRTF` date DEFAULT NULL,
  `NewSerialNumber` varchar(100) DEFAULT NULL,
  `NewPartNumber` varchar(100) DEFAULT NULL,
  `NewItemDescription` varchar(255) DEFAULT NULL,
  `NewItemStatus` varchar(50) DEFAULT NULL,
  `DateAdded` date DEFAULT NULL,
  `AddedBy` varchar(100) DEFAULT NULL,
  `Region` varchar(100) DEFAULT NULL,
  `TrackingNumber` varchar(50) DEFAULT NULL,
  `ReceiverName` varchar(100) DEFAULT NULL,
  `ReceiverAddress` varchar(255) DEFAULT NULL,
  `Courier` varchar(255) DEFAULT NULL,
  `defect_description` text,
  `NewItemCondition` text,
  `DateOfShipment` date DEFAULT NULL,
  `rtfcss` enum('yes','no') DEFAULT 'no',
  `ReceiverCompanyName` varchar(100) DEFAULT NULL,
  `ReceiverContactNumber` varchar(15) DEFAULT NULL,
  `DeclaredValue` varchar(15) DEFAULT NULL,
  `ShipperName` varchar(100) DEFAULT NULL,
  `ShipperContactNumber` varchar(15) DEFAULT NULL,
  `ShipperRegion` varchar(15) DEFAULT NULL,
  `Remarks` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`monitor_id`),
  UNIQUE KEY `serialnumber` (`serialnumber`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `monitor`
--

INSERT INTO `monitor` (`monitor_id`, `serialnumber`, `model`, `datepurchased`, `caseid`, `problem`, `waybillnumber`, `recievedby`, `item_status`, `datein`, `item_type`, `last_updated`, `ReceivedBy`, `ReleasedBy`, `DateOfRTF`, `NewSerialNumber`, `NewPartNumber`, `NewItemDescription`, `NewItemStatus`, `DateAdded`, `AddedBy`, `Region`, `TrackingNumber`, `ReceiverName`, `ReceiverAddress`, `Courier`, `defect_description`, `NewItemCondition`, `DateOfShipment`, `rtfcss`, `ReceiverCompanyName`, `ReceiverContactNumber`, `DeclaredValue`, `ShipperName`, `ShipperContactNumber`, `ShipperRegion`, `Remarks`) VALUES
(9, '123', '123', '1999-12-10', '123', NULL, '123', 'itsoc_seanadam', 'Received Defective', '2025-10-27', 'Monitor', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `partslogs`
--

DROP TABLE IF EXISTS `partslogs`;
CREATE TABLE IF NOT EXISTS `partslogs` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `id_link` int DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `part_number` varchar(255) DEFAULT NULL,
  `description` text,
  `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id_user_link` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `release_log`
--

DROP TABLE IF EXISTS `release_log`;
CREATE TABLE IF NOT EXISTS `release_log` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `quantity_released` int NOT NULL,
  `released_by_id` int NOT NULL,
  `received_by_id` int NOT NULL,
  `release_date` date NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `item_id` (`item_id`),
  KEY `released_by_id` (`released_by_id`),
  KEY `received_by_id` (`received_by_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `release_log`
--

INSERT INTO `release_log` (`log_id`, `item_id`, `quantity_released`, `released_by_id`, `received_by_id`, `release_date`, `timestamp`) VALUES
(5, 24, 2, 0, 28, '2025-10-27', '2025-10-27 07:24:37');

-- --------------------------------------------------------

--
-- Table structure for table `shipment_details`
--

DROP TABLE IF EXISTS `shipment_details`;
CREATE TABLE IF NOT EXISTS `shipment_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `CaseID` varchar(100) NOT NULL,
  `PartNumber` varchar(100) DEFAULT NULL,
  `ItemDescription` varchar(255) DEFAULT NULL,
  `SerialNumber` varchar(100) DEFAULT NULL,
  `DateIn` date DEFAULT NULL,
  `AddedBy` varchar(100) DEFAULT NULL,
  `ItemStatus` varchar(50) DEFAULT 'Outbound',
  `ConditionReceived` text,
  `ReceiverName` varchar(150) DEFAULT NULL,
  `ReceiverAddress` varchar(255) DEFAULT NULL,
  `DateOfShipment` date DEFAULT NULL,
  `Courier` varchar(100) DEFAULT NULL,
  `TrackingNumber` varchar(100) DEFAULT NULL,
  `Remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `NewSerialNumber` varchar(100) DEFAULT NULL,
  `WaybillNumber` varchar(100) DEFAULT NULL,
  `NewPartNumber` varchar(100) DEFAULT NULL,
  `NewItemStatus` varchar(100) DEFAULT NULL,
  `NewDate` datetime DEFAULT NULL,
  `ModifiedBy` varchar(255) DEFAULT NULL,
  `ReleasedBy` varchar(100) DEFAULT NULL,
  `ReceivedBy` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `DateOfRTF` datetime DEFAULT NULL,
  `Region` varchar(100) DEFAULT NULL,
  `InboundCondition` text,
  `CompanyName` varchar(255) DEFAULT NULL,
  `DeclaredValue` varchar(100) DEFAULT NULL,
  `ContactNumber` varchar(50) DEFAULT NULL,
  `ShipperName` varchar(255) DEFAULT NULL,
  `ShipperContact` varchar(50) DEFAULT NULL,
  `rtfcss` enum('yes','no') DEFAULT 'no',
  `injection_status` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `shipment_details`
--

INSERT INTO `shipment_details` (`id`, `CaseID`, `PartNumber`, `ItemDescription`, `SerialNumber`, `DateIn`, `AddedBy`, `ItemStatus`, `ConditionReceived`, `ReceiverName`, `ReceiverAddress`, `DateOfShipment`, `Courier`, `TrackingNumber`, `Remarks`, `created_at`, `NewSerialNumber`, `WaybillNumber`, `NewPartNumber`, `NewItemStatus`, `NewDate`, `ModifiedBy`, `ReleasedBy`, `ReceivedBy`, `DateOfRTF`, `Region`, `InboundCondition`, `CompanyName`, `DeclaredValue`, `ContactNumber`, `ShipperName`, `ShipperContact`, `rtfcss`, `injection_status`) VALUES
(17, 'acer 3', 'acerrrrr', 'Lowercase', 'acer12345', '2025-10-27', 'admin', 'New', '{}', 'jpoy', 'dumaguete', '2025-10-27', 'LBC', NULL, 'for dhipment', '2025-10-27 07:18:52', 'wrwerwedfdf', 'dfgdfgdsgsdgfsdfgd', 'acerrrrr', 'Returned - Faulty', NULL, NULL, NULL, 'Renvic', '2025-10-27 00:00:00', 'ASP', NULL, 'ace;pgic', '50000', '233111123', 'sean', '231215645465', 'yes', '0');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

DROP TABLE IF EXISTS `system_logs`;
CREATE TABLE IF NOT EXISTS `system_logs` (
  `LogID` int NOT NULL AUTO_INCREMENT,
  `Timestamp` datetime NOT NULL,
  `Username` varchar(100) DEFAULT NULL,
  `ActionType` varchar(50) NOT NULL,
  `CaseID` varchar(100) DEFAULT NULL,
  `Details` text,
  PRIMARY KEY (`LogID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('Manager','Authorized Personnel') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `displayname` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`, `displayname`) VALUES
(21, 'itsoc_seanadam', '$2y$10$KqwCjE0udzMu9LqQ9Kuyv.IrijNjWSS5roHIFLpXHRQIbEGL/uaTm', 'Manager', '2025-10-24 08:08:49', 'Sean'),
(27, 'itsoc_renvic', '$2y$10$hPqM1ZWcrQKKw.iJcWs3Y.50dRlvXf17pbrhae53zUm7lwY4Xl6l6', 'Manager', '2025-10-24 08:15:35', 'Renvic'),
(28, 'TSE_Taradji', '$2y$10$d5kQk3k9I/4ehGdvkD.Q6en9gdeAGt5Pih6UJ0B6uYu5xm9Mbh7AC', 'Authorized Personnel', '2025-10-27 07:09:50', 'Alhamid');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `history`
--
ALTER TABLE `history`
  ADD CONSTRAINT `fk_asset_id` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
