-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Aug 14, 2025 at 11:04 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mulagopharmacy`
--

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `department_name`, `location`, `contact_person`, `contact_phone`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Main Pharmacy', 'Main Building', 'Head Pharmacist', '+256700000000', 1, '2025-08-12 20:31:46', '2025-08-12 20:31:46'),
(2, 'Laboratory', 'Main building level 5', 'Kato Haruna', '+256 701 234 567', 1, '2025-08-13 14:21:07', '2025-08-13 14:21:07');

-- --------------------------------------------------------

--
-- Table structure for table `drugs`
--

CREATE TABLE `drugs` (
  `drug_id` int NOT NULL,
  `drug_name` varchar(100) NOT NULL,
  `generic_name` varchar(100) DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `unit_of_measure` varchar(20) NOT NULL,
  `reorder_level` int NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `drugs`
--

INSERT INTO `drugs` (`drug_id`, `drug_name`, `generic_name`, `category_id`, `unit_of_measure`, `reorder_level`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Amoxicillin 500mg', 'Amoxicillin', 1, 'Box of 10 tablets', 20, 'Used to treat bacterial infections such as respiratory tract infections and urinary tract infections.', 1, '2025-08-13 14:15:12', '2025-08-13 14:15:12'),
(2, 'Amlodipine 5mg', 'Amlodipine', 3, 'Box of 30 tablets', 15, 'Lowers high blood pressure to reduce the risk of heart disease and stroke.', 1, '2025-08-13 14:16:58', '2025-08-14 22:11:38'),
(3, 'Metformin 500mg', 'Metformin', 4, 'Box of 30 tablets', 10, 'Helps control blood sugar levels in type 2 diabetes', 1, '2025-08-14 19:35:53', '2025-08-14 19:35:53');

-- --------------------------------------------------------

--
-- Table structure for table `drug_categories`
--

CREATE TABLE `drug_categories` (
  `category_id` int NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `drug_categories`
--

INSERT INTO `drug_categories` (`category_id`, `category_name`, `description`, `created_at`) VALUES
(1, 'Antibiotics', 'Medications that fight bacterial infections', '2025-08-13 14:05:12'),
(2, 'Analgesics', 'Pain relief medications', '2025-08-13 14:05:51'),
(3, 'Antihypertensives', 'Drugs for high blood pressure', '2025-08-13 14:06:20'),
(4, 'Antidiabetics', 'Medications for diabetes management', '2025-08-13 14:07:02'),
(5, 'Vitamins', 'Nutritional supplements', '2025-08-13 14:07:30');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int NOT NULL,
  `drug_id` int NOT NULL,
  `department_id` int NOT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity_in_stock` int NOT NULL DEFAULT '0',
  `last_restocked` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`inventory_id`, `drug_id`, `department_id`, `batch_number`, `expiry_date`, `quantity_in_stock`, `last_restocked`, `created_at`, `updated_at`) VALUES
(2, 2, 2, '122334', '2025-08-18', 2, '2025-08-13', '2025-08-13 20:50:16', '2025-08-14 21:14:31'),
(3, 1, 1, '23345667', '2027-08-23', 16, '2025-08-13', '2025-08-13 20:51:36', '2025-08-14 22:07:48'),
(5, 3, 2, 'BATCH-3877-25', '2027-08-14', 15, '2025-08-14', '2025-08-14 20:38:10', '2025-08-14 20:38:10');

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `purchase_id` int NOT NULL,
  `drug_id` int NOT NULL,
  `supplier_id` int NOT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `purchase_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `received_by` int DEFAULT NULL,
  `invoice_number` varchar(50) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `purchases`
--

INSERT INTO `purchases` (`purchase_id`, `drug_id`, `supplier_id`, `batch_number`, `quantity`, `unit_price`, `total_price`, `purchase_date`, `expiry_date`, `received_by`, `invoice_number`, `notes`, `created_at`) VALUES
(1, 2, 1, '122334', 20, 5500.00, 110000.00, '2025-08-13', '2025-08-16', 1, NULL, NULL, '2025-08-13 20:50:16'),
(2, 1, 1, '23345667', 20, 1500.00, 30000.00, '2025-08-13', '2026-12-19', 1, NULL, NULL, '2025-08-13 20:51:36'),
(4, 3, 1, 'BATCH-3877-25', 15, 5700.00, 85500.00, '2025-08-14', '2027-08-14', 1, NULL, NULL, '2025-08-14 20:38:10');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int NOT NULL,
  `supplier_name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `contact_person`, `contact_phone`, `email`, `address`, `is_active`, `created_at`) VALUES
(1, 'BF SUMA', 'Dr Frank', '0760317540', 'b.frank@bfsuma.org', 'Kampala central business centre \r\nKampala road boulevoured building level 4', 1, '2025-08-13 14:03:54'),
(2, 'BIVA ORGANIC', 'Dr Ismael', '0775141003', 'a.ismael@biva.org', 'Jinja road', 1, '2025-08-14 22:40:12');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int NOT NULL,
  `drug_id` int NOT NULL,
  `department_id` int NOT NULL,
  `transaction_type` enum('return','sale','stock_adjust_in','stock_adjust_out','stock_transfer','stock_receive') NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,0) DEFAULT NULL,
  `total_amount` decimal(10,0) DEFAULT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `notes` text,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `transfer_to_department` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `drug_id`, `department_id`, `transaction_type`, `quantity`, `unit_price`, `total_amount`, `reference_number`, `notes`, `created_by`, `created_at`, `transfer_to_department`) VALUES
(6, 1, 2, 'sale', 10, 1700, 17000, '000777222', 'Lorem Ipsum is simply dummy text of the printing and typesetting industry.', 1, '2025-08-14 16:36:12', NULL),
(8, 1, 2, 'return', 5, 1700, -8500, '6790224', 'Lorem Ipsum is simply dummy text of the printing and typesetting industry.', 1, '2025-08-14 17:13:23', NULL),
(10, 1, 1, 'sale', 20, 1500, 30000, '346709', 'Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry&#039;s standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book.', 1, '2025-08-14 18:22:01', NULL),
(11, 1, 1, 'stock_adjust_in', 10, NULL, NULL, NULL, 'more stock received', 1, '2025-08-14 19:32:26', NULL),
(12, 3, 2, 'stock_receive', 15, NULL, NULL, NULL, 'Initial restock', 1, '2025-08-14 20:38:11', NULL),
(13, 2, 2, 'stock_adjust_in', 9, NULL, NULL, NULL, 'taken to lab', 1, '2025-08-14 21:07:15', NULL),
(14, 1, 1, 'stock_adjust_out', 25, NULL, NULL, NULL, 'Removing expiring stock', 1, '2025-08-14 21:13:21', NULL),
(15, 2, 2, 'stock_adjust_out', 36, NULL, NULL, NULL, 'Removing expiring stock', 1, '2025-08-14 21:14:31', NULL),
(16, 1, 1, 'stock_adjust_in', 15, NULL, NULL, NULL, 'Restocked', 1, '2025-08-14 21:35:39', NULL),
(17, 1, 1, 'stock_adjust_in', 16, NULL, NULL, NULL, 'Re-stocked', 1, '2025-08-14 22:07:48', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `role` enum('admin','pharmacist','department_staff') NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password_hash`, `full_name`, `email`, `department_id`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'pharmacy_admin', '$2y$12$dRJmnfm/ppCbHOOznJAIUeLeBB6eiegxk0hPg8zg3pBGZTI6MME7W', 'System Administrator', 'admin@mulagopharmacy.com', 1, 'admin', 1, '2025-08-14 08:40:43', '2025-08-12 20:31:46', '2025-08-14 05:40:43'),
(2, 'Akram', '$2y$12$ZYoGY4YrlruyxsLZQMk/xubHAa/pNofeJKdbSJEO914HCiYLGS5aa', 'Walugembe Akram', 'akramwalugembe66@gmail.com', 2, 'pharmacist', 1, NULL, '2025-08-13 11:33:51', '2025-08-14 22:11:13');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`);

--
-- Indexes for table `drugs`
--
ALTER TABLE `drugs`
  ADD PRIMARY KEY (`drug_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_drug_name` (`drug_name`);

--
-- Indexes for table `drug_categories`
--
ALTER TABLE `drug_categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD UNIQUE KEY `drug_id` (`drug_id`,`department_id`,`batch_number`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `idx_inventory_drug` (`drug_id`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`purchase_id`),
  ADD KEY `drug_id` (`drug_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `drug_id` (`drug_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `transfer_to_department` (`transfer_to_department`),
  ADD KEY `idx_transactions_date` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `department_id` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `drugs`
--
ALTER TABLE `drugs`
  MODIFY `drug_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `drug_categories`
--
ALTER TABLE `drug_categories`
  MODIFY `category_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `purchase_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `drugs`
--
ALTER TABLE `drugs`
  ADD CONSTRAINT `drugs_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `drug_categories` (`category_id`);

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`drug_id`) REFERENCES `drugs` (`drug_id`),
  ADD CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`);

--
-- Constraints for table `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`drug_id`) REFERENCES `drugs` (`drug_id`),
  ADD CONSTRAINT `purchases_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`),
  ADD CONSTRAINT `purchases_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`drug_id`) REFERENCES `drugs` (`drug_id`),
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`),
  ADD CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `transactions_ibfk_4` FOREIGN KEY (`transfer_to_department`) REFERENCES `departments` (`department_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
