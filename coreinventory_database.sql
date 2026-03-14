-- ============================================================
-- CoreInventory — FIXED Database Schema + Sample Data
-- Version: Fixed (error-free)
-- ============================================================
-- FIXES APPLIED:
--  1. stock table: Added UNIQUE KEY (product_id, warehouse_id)
--     → Required for ON DUPLICATE KEY UPDATE to work correctly
--  2. adjustments table: Removed phantom 'notes' column reference
--     (code was referencing it but it didn't exist)
--  3. deliveries table: Removed phantom 'notes' column reference
--  4. receipts table: Removed phantom 'notes' column reference
--  5. receipt_items: 'quantity_expected' column did not exist —
--     code references it, schema only has quantity_received
--  6. stock data: Cleaned up duplicate rows (product+warehouse)
--     into single aggregated rows so UNIQUE constraint works
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Database: `coreinventory`

-- --------------------------------------------------------
-- Table: adjustments
-- FIX: No 'notes' column (code bug removed from pages/adjustments.php)
-- --------------------------------------------------------

CREATE TABLE `adjustments` (
  `id` int(11) NOT NULL,
  `reference` varchar(50) DEFAULT NULL,
  `warehouse_id` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `validated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `adjustments` (`id`, `reference`, `warehouse_id`, `status`, `created_by`, `created_at`, `validated_at`) VALUES
(1, 'ADJ001', 1, 'done', 1, '2026-03-14 07:22:56', NULL),
(2, 'ADJ002', 2, 'done', 2, '2026-03-14 07:22:56', '2026-03-14 13:12:25');

-- --------------------------------------------------------
-- Table: adjustment_items
-- --------------------------------------------------------

CREATE TABLE `adjustment_items` (
  `id` int(11) NOT NULL,
  `adjustment_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `system_quantity` decimal(10,2) DEFAULT NULL,
  `counted_quantity` decimal(10,2) DEFAULT NULL,
  `difference` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `adjustment_items` (`id`, `adjustment_id`, `product_id`, `system_quantity`, `counted_quantity`, `difference`) VALUES
(1, 1, 1, 50.00, 48.00, -2.00),
(2, 2, 3, 30.00, 35.00, 5.00);

-- --------------------------------------------------------
-- Table: categories
-- --------------------------------------------------------

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'Electronics', 'Electronic Items'),
(2, 'Furniture', 'Office Furniture'),
(3, 'Packaging', 'Packaging Material'),
(4, 'Spare Parts', 'Machine Spare Parts');

-- --------------------------------------------------------
-- Table: deliveries
-- FIX: No 'notes' column (code bug removed from pages/deliveries.php)
-- --------------------------------------------------------

CREATE TABLE `deliveries` (
  `id` int(11) NOT NULL,
  `reference` varchar(50) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `warehouse_id` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `validated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `deliveries` (`id`, `reference`, `customer_name`, `warehouse_id`, `status`, `created_by`, `created_at`, `validated_at`) VALUES
(1, 'DEL001', 'ABC Company', 1, 'done', 2, '2026-03-14 07:22:56', NULL),
(2, 'DEL002', 'XYZ Store', 2, 'done', 3, '2026-03-14 07:22:56', '2026-03-14 16:46:13');

-- --------------------------------------------------------
-- Table: delivery_items
-- --------------------------------------------------------

CREATE TABLE `delivery_items` (
  `id` int(11) NOT NULL,
  `delivery_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `delivery_items` (`id`, `delivery_id`, `product_id`, `quantity`) VALUES
(1, 1, 1, 5.00),
(2, 1, 2, 10.00),
(3, 2, 3, 2.00);

-- --------------------------------------------------------
-- Table: password_resets
-- --------------------------------------------------------

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: products
-- --------------------------------------------------------

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `unit_of_measure` varchar(50) DEFAULT NULL,
  `reorder_level` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `products` (`id`, `name`, `sku`, `category_id`, `unit_of_measure`, `reorder_level`, `created_at`, `is_active`, `description`) VALUES
(1, 'Laptop', 'ELE-001', 1, 'pcs', 5, '2026-03-14 07:22:56', 1, NULL),
(2, 'Mouse', 'ELE-002', 1, 'pcs', 20, '2026-03-14 07:22:56', 1, NULL),
(3, 'Office Chair', 'FUR-001', 2, 'pcs', 10, '2026-03-14 07:22:56', 1, NULL),
(4, 'Cardboard Box', 'PKG-001', 3, 'pcs', 100, '2026-03-14 07:22:56', 1, NULL),
(5, 'Bolt Set', 'SPR-001', 4, 'pcs', 115, '2026-03-14 07:22:56', 1, ''),
(6, 'Table Fan', 'ELE-003', 1, 'pcs', 20, '2026-03-14 10:13:49', 0, ''),
(7, 'Desk Lamp', 'ELE-004', 1, 'pcs', 15, '2026-03-14 10:17:17', 1, '');

-- --------------------------------------------------------
-- Table: receipts
-- FIX: No 'notes' column (code bug removed from pages/receipts.php)
-- --------------------------------------------------------

CREATE TABLE `receipts` (
  `id` int(11) NOT NULL,
  `reference` varchar(50) DEFAULT NULL,
  `supplier_name` varchar(100) DEFAULT NULL,
  `warehouse_id` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `validated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `receipts` (`id`, `reference`, `supplier_name`, `warehouse_id`, `status`, `created_by`, `created_at`, `validated_at`) VALUES
(1, 'REC001', 'Tech Supplier', 1, 'done', 1, '2026-03-14 07:22:56', NULL),
(2, 'REC002', 'Furniture Supplier', 2, 'done', 2, '2026-03-14 07:22:56', NULL);

-- --------------------------------------------------------
-- Table: receipt_items
-- FIX: Only 'quantity_received' column exists.
--      Old code wrongly referenced 'quantity_expected' — fixed in PHP.
-- --------------------------------------------------------

CREATE TABLE `receipt_items` (
  `id` int(11) NOT NULL,
  `receipt_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity_received` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `receipt_items` (`id`, `receipt_id`, `product_id`, `quantity_received`) VALUES
(1, 1, 1, 20.00),
(2, 1, 2, 50.00),
(3, 2, 3, 10.00);

-- --------------------------------------------------------
-- Table: stock
-- FIX #1: Added UNIQUE KEY (product_id, warehouse_id)
--         This is REQUIRED for ON DUPLICATE KEY UPDATE to work.
--         Without it, INSERT ... ON DUPLICATE KEY UPDATE creates
--         infinite duplicate rows instead of updating.
-- FIX #2: Removed duplicate (product_id, warehouse_id) rows from data.
--         Original data had many duplicates causing wrong totals.
-- --------------------------------------------------------

CREATE TABLE `stock` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `warehouse_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  UNIQUE KEY `unique_product_warehouse` (`product_id`, `warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `stock` (`id`, `product_id`, `warehouse_id`, `quantity`) VALUES
(1,  1, 1, 50.00),
(2,  2, 1, 200.00),
(3,  3, 2, 26.00),
(4,  4, 1, 520.00),
(5,  5, 3, 120.00),
(6,  6, 3, 2000.00),
(7,  7, 1, 20.00),
(8,  3, 3, 5.00);

-- --------------------------------------------------------
-- Table: stock_ledger
-- --------------------------------------------------------

CREATE TABLE `stock_ledger` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `operation_type` enum('receipt','delivery','transfer_in','transfer_out','adjustment') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `quantity_change` decimal(10,2) NOT NULL,
  `quantity_after` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `stock_ledger` (`id`, `product_id`, `warehouse_id`, `operation_type`, `reference_id`, `reference_type`, `quantity_change`, `quantity_after`, `notes`, `created_by`, `created_at`) VALUES
(1,  1, 1, 'receipt',      NULL, NULL,         50.00,    50.00,   'Initial stock',    1, '2026-03-14 07:34:54'),
(2,  1, 1, 'delivery',     NULL, NULL,         -5.00,    45.00,   'Order delivery',   1, '2026-03-14 07:34:54'),
(3,  2, 1, 'receipt',      NULL, NULL,        100.00,   100.00,   'Stock received',   1, '2026-03-14 07:34:54'),
(4,  3, 2, 'adjustment',   2,    'adjustment',  5.00,    35.00,   'Stock adjustment', 3, '2026-03-14 07:41:41'),
(5,  6, 3, 'adjustment',   NULL, NULL,       2000.00,  2000.00,   'Initial stock',    1, '2026-03-14 10:13:49'),
(6,  7, 1, 'adjustment',   NULL, NULL,         20.00,    20.00,   'Initial stock',    1, '2026-03-14 10:17:17'),
(7,  4, 3, 'transfer_out', 3,    'transfer',  -10.00,     0.00,   NULL,               1, '2026-03-14 11:13:09'),
(8,  4, 1, 'transfer_in',  3,    'transfer',   10.00,   510.00,   NULL,               1, '2026-03-14 11:13:09'),
(9,  3, 2, 'transfer_out', 2,    'transfer',   -5.00,    25.00,   NULL,               1, '2026-03-14 11:14:55'),
(10, 3, 3, 'transfer_in',  2,    'transfer',    5.00,     5.00,   NULL,               1, '2026-03-14 11:14:55'),
(11, 3, 2, 'delivery',     2,    'delivery',   -2.00,    23.00,   NULL,               1, '2026-03-14 11:15:00'),
(12, 3, 2, 'delivery',     2,    'delivery',   -2.00,    21.00,   NULL,               1, '2026-03-14 11:16:13');

-- --------------------------------------------------------
-- Table: transfers
-- --------------------------------------------------------

CREATE TABLE `transfers` (
  `id` int(11) NOT NULL,
  `reference` varchar(50) DEFAULT NULL,
  `from_warehouse_id` int(11) DEFAULT NULL,
  `to_warehouse_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `validated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `transfers` (`id`, `reference`, `from_warehouse_id`, `to_warehouse_id`, `notes`, `status`, `created_by`, `created_at`, `validated_at`) VALUES
(1, 'TR001', 1, 2, NULL, 'done', 1, '2026-03-14 07:22:56', NULL),
(2, 'TR002', 2, 3, NULL, 'done', 2, '2026-03-14 07:22:56', '2026-03-14 16:44:55'),
(3, 'TRF-20260314-8690A', 3, 1, '', 'done', 1, '2026-03-14 11:12:03', '2026-03-14 16:44:24');

-- --------------------------------------------------------
-- Table: transfer_items
-- --------------------------------------------------------

CREATE TABLE `transfer_items` (
  `id` int(11) NOT NULL,
  `transfer_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `transfer_items` (`id`, `transfer_id`, `product_id`, `quantity`) VALUES
(1, 1, 2, 20.00),
(2, 2, 3, 5.00),
(3, 3, 4, 10.00);

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','manager','staff') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `otp` varchar(10) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default password for all demo accounts: "password"
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`, `otp`, `otp_expires_at`, `is_active`) VALUES
(1, 'Admin User',   'admin@coreinventory.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',   '2026-03-14 07:22:56', NULL, NULL, 1),
(2, 'Manager One',  'manager@coreinventory.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', '2026-03-14 07:22:56', NULL, NULL, 1),
(3, 'Staff One',    'staff@coreinventory.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff',   '2026-03-14 07:22:56', NULL, NULL, 1);

-- --------------------------------------------------------
-- Table: warehouses
-- --------------------------------------------------------

CREATE TABLE `warehouses` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `warehouses` (`id`, `name`, `location`, `created_at`, `is_active`) VALUES
(1, 'Main Warehouse',      'Building A', '2026-03-14 07:22:56', 1),
(2, 'Secondary Warehouse', 'Building B', '2026-03-14 07:22:56', 1),
(3, 'Retail Storage',      'Building C', '2026-03-14 07:22:56', 1);

-- ============================================================
-- PRIMARY KEYS & INDEXES
-- ============================================================

ALTER TABLE `adjustments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `created_by` (`created_by`);

ALTER TABLE `adjustment_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `adjustment_id` (`adjustment_id`),
  ADD KEY `product_id` (`product_id`);

ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `deliveries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `created_by` (`created_by`);

ALTER TABLE `delivery_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `delivery_id` (`delivery_id`),
  ADD KEY `product_id` (`product_id`);

ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

ALTER TABLE `receipts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `created_by` (`created_by`);

ALTER TABLE `receipt_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `receipt_id` (`receipt_id`),
  ADD KEY `product_id` (`product_id`);

ALTER TABLE `stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `warehouse_id` (`warehouse_id`);
  -- UNIQUE KEY already defined inline above

ALTER TABLE `stock_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `created_by` (`created_by`);

ALTER TABLE `transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `from_warehouse_id` (`from_warehouse_id`),
  ADD KEY `to_warehouse_id` (`to_warehouse_id`);

ALTER TABLE `transfer_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transfer_id` (`transfer_id`),
  ADD KEY `product_id` (`product_id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

ALTER TABLE `warehouses`
  ADD PRIMARY KEY (`id`);

-- ============================================================
-- AUTO_INCREMENT
-- ============================================================

ALTER TABLE `adjustments`     MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `adjustment_items` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `categories`      MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
ALTER TABLE `deliveries`      MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `delivery_items`  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `password_resets` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
ALTER TABLE `products`        MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
ALTER TABLE `receipts`        MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `receipt_items`   MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `stock`           MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
ALTER TABLE `stock_ledger`    MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
ALTER TABLE `transfers`       MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `transfer_items`  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `users`           MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `warehouses`      MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

-- ============================================================
-- FOREIGN KEY CONSTRAINTS
-- ============================================================

ALTER TABLE `adjustments`
  ADD CONSTRAINT `adjustments_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `adjustments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `adjustment_items`
  ADD CONSTRAINT `adjustment_items_ibfk_1` FOREIGN KEY (`adjustment_id`) REFERENCES `adjustments` (`id`),
  ADD CONSTRAINT `adjustment_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

ALTER TABLE `deliveries`
  ADD CONSTRAINT `deliveries_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `deliveries_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

ALTER TABLE `delivery_items`
  ADD CONSTRAINT `delivery_items_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`id`),
  ADD CONSTRAINT `delivery_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

ALTER TABLE `receipts`
  ADD CONSTRAINT `receipts_ibfk_1` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `receipts_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

ALTER TABLE `receipt_items`
  ADD CONSTRAINT `receipt_items_ibfk_1` FOREIGN KEY (`receipt_id`) REFERENCES `receipts` (`id`),
  ADD CONSTRAINT `receipt_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

ALTER TABLE `stock`
  ADD CONSTRAINT `stock_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `stock_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`);

ALTER TABLE `stock_ledger`
  ADD CONSTRAINT `stock_ledger_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_ledger_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_ledger_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `transfers`
  ADD CONSTRAINT `transfers_ibfk_1` FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `transfers_ibfk_2` FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses` (`id`);

ALTER TABLE `transfer_items`
  ADD CONSTRAINT `transfer_items_ibfk_1` FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`),
  ADD CONSTRAINT `transfer_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
