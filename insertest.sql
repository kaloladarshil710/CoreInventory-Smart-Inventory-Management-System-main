-- ============================================================
-- CoreInventory — Dummy Data for Testing
-- Run this AFTER importing database.sql
-- ============================================================

USE coreinventory;

-- ============================================================
-- CLEAR EXISTING SEED DATA (optional, safe to run)
-- ============================================================
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE stock_ledger;
TRUNCATE TABLE adjustment_items;
TRUNCATE TABLE adjustments;
TRUNCATE TABLE transfer_items;
TRUNCATE TABLE transfers;
TRUNCATE TABLE delivery_items;
TRUNCATE TABLE deliveries;
TRUNCATE TABLE receipt_items;
TRUNCATE TABLE receipts;
TRUNCATE TABLE stock;
TRUNCATE TABLE products;
TRUNCATE TABLE categories;
TRUNCATE TABLE warehouses;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- USERS (password = "password123" for all)
-- ============================================================
-- INSERT INTO users (id, name, email, password, role) VALUES
-- (1, 'Arjun Mehta',    'admin@coreinventory.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
-- (2, 'Priya Sharma',   'manager@coreinventory.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager'),
-- (3, 'Ravi Patel',     'staff1@coreinventory.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff'),
-- (4, 'Sneha Joshi',    'staff2@coreinventory.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff');

-- ============================================================
-- WAREHOUSES
-- ============================================================
INSERT INTO warehouses (id, name, location, is_active) VALUES
(1, 'Main Warehouse',    'Plot 12, GIDC Industrial Area, Rajkot',   1),
(2, 'Production Floor',  'Building B, Shed 3, Rajkot',              1),
(3, 'Secondary Storage', 'Plot 45, Aji GIDC, Rajkot',               1),
(4, 'Dispatch Bay',      'Gate 2, Lodhika Industrial Area, Rajkot', 1);

-- ============================================================
-- CATEGORIES
-- ============================================================
INSERT INTO categories (id, name, description) VALUES
(1, 'Raw Materials',    'Basic input materials used in production'),
(2, 'Finished Goods',   'Ready-to-ship completed products'),
(3, 'Packaging',        'Boxes, tapes, wrapping materials'),
(4, 'Spare Parts',      'Machine spare parts and tools'),
(5, 'Electronics',      'Electronic components and devices'),
(6, 'Chemicals',        'Chemicals and lubricants');

-- ============================================================
-- PRODUCTS (30 products)
-- ============================================================
INSERT INTO products (id, name, sku, category_id, unit_of_measure, reorder_level, description, is_active) VALUES
-- Raw Materials
(1,  'Steel Rods 12mm',        'STL-001', 1, 'kg',  100, 'High tensile steel rods, 12mm diameter', 1),
(2,  'Aluminium Sheets',        'ALU-001', 1, 'kg',  50,  'Aluminium sheets 2mm thickness',          1),
(3,  'Copper Wire 2mm',         'COP-001', 1, 'kg',  30,  'Copper wire for electrical use',           1),
(4,  'Iron Pipes 1 inch',       'IRN-001', 1, 'pcs', 40,  'Iron pipes, 1 inch diameter, 6ft long',   1),
(5,  'Rubber Gaskets',          'RBR-001', 1, 'pcs', 200, 'Standard rubber gaskets for joints',       1),
(6,  'PVC Granules',            'PVC-001', 1, 'kg',  80,  'PVC granules for moulding',                1),

-- Finished Goods
(7,  'Industrial Fan 18 inch',  'FAN-001', 2, 'pcs', 10,  '18-inch industrial ceiling fan',           1),
(8,  'Water Pump 1HP',          'PMP-001', 2, 'pcs', 8,   '1HP electric water pump',                  1),
(9,  'Steel Chair',             'CHR-001', 2, 'pcs', 15,  'Heavy duty steel office chair',             1),
(10, 'Metal Cabinet 4-drawer',  'CAB-001', 2, 'pcs', 5,   '4-drawer metal filing cabinet',            1),
(11, 'Aluminium Ladder 8ft',    'LAD-001', 2, 'pcs', 6,   '8-foot aluminium step ladder',             1),
(12, 'Steel Workbench',         'WRK-001', 2, 'pcs', 4,   'Heavy duty steel workbench 6ft',           1),

-- Packaging
(13, 'Cardboard Box Large',     'PKG-001', 3, 'pcs', 150, 'Large corrugated cardboard box',           1),
(14, 'Cardboard Box Medium',    'PKG-002', 3, 'pcs', 200, 'Medium corrugated cardboard box',          1),
(15, 'Bubble Wrap Roll',        'PKG-003', 3, 'roll',20,  'Bubble wrap 1m x 50m roll',                1),
(16, 'Packing Tape 2 inch',     'PKG-004', 3, 'pcs', 50,  'Brown packing tape 2 inch x 50m',          1),
(17, 'Stretch Film Roll',       'PKG-005', 3, 'roll',15,  'Stretch film wrap 500mm x 300m',           1),

-- Spare Parts
(18, 'Ball Bearing 6205',       'SBR-001', 4, 'pcs', 50,  'Deep groove ball bearing 6205',            1),
(19, 'V-Belt A-40',             'VBL-001', 4, 'pcs', 30,  'V-belt A section 40 inch',                 1),
(20, 'Oil Seal 30x50',          'OSL-001', 4, 'pcs', 40,  'Rubber oil seal 30x50mm',                  1),
(21, 'Hex Bolts M10',           'BLT-001', 4, 'pcs', 300, 'Hex bolt M10 x 50mm zinc plated',          1),
(22, 'Lock Nuts M10',           'NUT-001', 4, 'pcs', 300, 'Nylon lock nut M10',                        1),

-- Electronics
(23, 'MCB 32A Single Pole',     'MCB-001', 5, 'pcs', 20,  'Miniature circuit breaker 32A 1P',         1),
(24, 'Electric Motor 2HP',      'MTR-001', 5, 'pcs', 5,   '2HP single phase electric motor',          1),
(25, 'Control Panel Box',       'CPB-001', 5, 'pcs', 8,   'IP65 metal control panel enclosure',       1),
(26, 'Contactor 25A',           'CTR-001', 5, 'pcs', 15,  '25A 3-pole magnetic contactor',             1),
(27, 'Cable 4mm 3-Core',        'CBL-001', 5, 'metre',100,'3-core flexible cable 4mm',                 1),

-- Chemicals
(28, 'Machine Oil 5L',          'OIL-001', 6, 'litre',20, 'ISO 46 hydraulic machine oil 5L',          1),
(29, 'Grease Cartridge 400g',   'GRS-001', 6, 'pcs', 25,  'Lithium grease cartridge 400g',             1),
(30, 'Rust Remover 1L',         'RST-001', 6, 'litre',10, 'Industrial rust remover spray 1L',          1);

-- ============================================================
-- STOCK (initial quantities per warehouse)
-- ============================================================
INSERT INTO stock (product_id, warehouse_id, quantity) VALUES
-- Main Warehouse (WH1)
(1,  1, 850),  -- Steel Rods
(2,  1, 320),  -- Aluminium Sheets
(3,  1, 95),   -- Copper Wire
(4,  1, 160),  -- Iron Pipes
(5,  1, 480),  -- Rubber Gaskets
(6,  1, 210),  -- PVC Granules
(7,  1, 42),   -- Industrial Fan
(8,  1, 18),   -- Water Pump
(9,  1, 67),   -- Steel Chair
(10, 1, 12),   -- Metal Cabinet
(11, 1, 14),   -- Aluminium Ladder
(12, 1, 7),    -- Steel Workbench
(13, 1, 540),  -- Cardboard Box Large
(14, 1, 820),  -- Cardboard Box Medium
(15, 1, 38),   -- Bubble Wrap
(16, 1, 95),   -- Packing Tape
(17, 1, 22),   -- Stretch Film
(18, 1, 185),  -- Ball Bearing
(19, 1, 76),   -- V-Belt
(20, 1, 130),  -- Oil Seal
(21, 1, 1200), -- Hex Bolts
(22, 1, 980),  -- Lock Nuts
(23, 1, 55),   -- MCB
(24, 1, 11),   -- Electric Motor
(25, 1, 19),   -- Control Panel Box
(26, 1, 44),   -- Contactor
(27, 1, 380),  -- Cable
(28, 1, 46),   -- Machine Oil
(29, 1, 60),   -- Grease
(30, 1, 8),    -- Rust Remover  ← below reorder level

-- Production Floor (WH2)
(1,  2, 200),
(3,  2, 45),
(5,  2, 120),
(6,  2, 80),
(18, 2, 60),
(19, 2, 25),
(21, 2, 400),
(28, 2, 18),

-- Secondary Storage (WH3)
(2,  3, 150),
(4,  3, 80),
(13, 3, 300),
(14, 3, 450),
(15, 3, 18),
(16, 3, 40),
(22, 3, 500),
(27, 3, 120),

-- Dispatch Bay (WH4)
(7,  4, 15),
(8,  4, 6),
(9,  4, 20),
(10, 4, 4),
(11, 4, 5),
(12, 4, 2);

-- ============================================================
-- RECEIPTS (10 — mix of done / draft / ready)
-- ============================================================
INSERT INTO receipts (id, reference, supplier_name, warehouse_id, status, notes, created_by, validated_at, created_at) VALUES
(1,  'RCT-20240101-AA001', 'Tata Steel Ltd',          1, 'done',    'Monthly steel order',           1, '2024-11-02 10:00:00', '2024-11-01 09:00:00'),
(2,  'RCT-20240110-BB002', 'Hindalco Industries',      1, 'done',    'Aluminium sheet bulk order',    1, '2024-11-11 14:00:00', '2024-11-10 08:30:00'),
(3,  'RCT-20240120-CC003', 'Finolex Cables Pvt Ltd',   1, 'done',    'Copper wire restock',           2, '2024-11-21 11:30:00', '2024-11-20 10:00:00'),
(4,  'RCT-20240201-DD004', 'SKF Bearings India',       1, 'done',    'Bearing quarterly order',       1, '2024-12-02 09:45:00', '2024-12-01 08:00:00'),
(5,  'RCT-20240210-EE005', 'Supreme Industries',       3, 'done',    'Packaging material restock',    2, '2024-12-11 15:00:00', '2024-12-10 09:00:00'),
(6,  'RCT-20240215-FF006', 'Havells India Ltd',        1, 'done',    'Electrical components',         1, '2024-12-16 12:00:00', '2024-12-15 10:30:00'),
(7,  'RCT-20240301-GG007', 'Castrol India Ltd',        1, 'done',    'Lubricants monthly supply',     2, '2024-12-20 10:00:00', '2024-12-19 08:00:00'),
(8,  'RCT-20240310-HH008', 'Pidilite Industries',      1, 'ready',   'Chemicals pending arrival',     3, NULL,                  '2025-01-05 09:00:00'),
(9,  'RCT-20240315-II009', 'Tata Steel Ltd',           1, 'waiting', 'Steel rods Q1 order',           1, NULL,                  '2025-01-10 11:00:00'),
(10, 'RCT-20240320-JJ010', 'Bosch India',              1, 'draft',   'Spare parts PO raised',         2, NULL,                  '2025-01-12 14:00:00');

INSERT INTO receipt_items (receipt_id, product_id, quantity_expected, quantity_received) VALUES
(1, 1, 500, 500), (1, 4, 100, 100),
(2, 2, 200, 200),
(3, 3, 80,  80),
(4, 18, 100, 100), (4, 19, 50, 50), (4, 20, 80, 80),
(5, 13, 300, 300), (5, 14, 400, 400), (5, 15, 20, 20),
(6, 23, 30, 30), (6, 26, 25, 25), (6, 27, 200, 200),
(7, 28, 30, 30), (7, 29, 40, 40),
(8, 30, 20, 0),  (8, 6, 100, 0),
(9, 1, 300, 0),  (9, 4, 60, 0),
(10, 18, 50, 0), (10, 19, 30, 0), (10, 21, 500, 0);

-- ============================================================
-- DELIVERIES (10 — mix of done / draft)
-- ============================================================
INSERT INTO deliveries (id, reference, customer_name, warehouse_id, status, notes, created_by, validated_at, created_at) VALUES
(1,  'DLV-20240105-AA001', 'Rajkot Engineering Works',   1, 'done', 'Steel supply order',          1, '2024-11-06 14:00:00', '2024-11-05 10:00:00'),
(2,  'DLV-20240112-BB002', 'Gujarat Pumps Pvt Ltd',      1, 'done', 'Pump dispatch',               2, '2024-11-13 11:00:00', '2024-11-12 09:30:00'),
(3,  'DLV-20240122-CC003', 'Morbi Tiles Industries',     4, 'done', 'Cabinet and chair dispatch',  1, '2024-11-23 16:00:00', '2024-11-22 14:00:00'),
(4,  'DLV-20240205-DD004', 'Saurashtra Cables',          4, 'done', 'Cable supply',                2, '2024-12-06 10:30:00', '2024-12-05 09:00:00'),
(5,  'DLV-20240212-EE005', 'Jamnagar Auto Parts',        1, 'done', 'Spare parts supply',          1, '2024-12-13 15:00:00', '2024-12-12 11:00:00'),
(6,  'DLV-20240220-FF006', 'Bhavnagar Steel Co.',        1, 'done', 'Steel rods bulk',             3, '2024-12-21 09:00:00', '2024-12-20 08:00:00'),
(7,  'DLV-20240305-GG007', 'Amreli Hardware Store',      4, 'done', 'Fans and ladders',            2, '2024-12-28 14:00:00', '2024-12-27 10:00:00'),
(8,  'DLV-20240310-HH008', 'Junagadh Electricals',      1, 'draft', 'MCB and contactor order',    1, NULL,                  '2025-01-08 09:00:00'),
(9,  'DLV-20240312-II009', 'Surendranagar Industries',   1, 'draft', 'Packaging material sale',    2, NULL,                  '2025-01-11 10:30:00'),
(10, 'DLV-20240315-JJ010', 'Porbandar Machinery',        4, 'ready', 'Workbench and pump',         1, NULL,                  '2025-01-14 13:00:00');

INSERT INTO delivery_items (delivery_id, product_id, quantity) VALUES
(1, 1, 150), (1, 4, 40),
(2, 8, 5),
(3, 10, 3), (3, 9, 12),
(4, 27, 100),
(5, 18, 30), (5, 19, 15), (5, 21, 200),
(6, 1, 200),
(7, 7, 10), (7, 11, 5),
(8, 23, 15), (8, 26, 10),
(9, 13, 100), (9, 14, 200),
(10, 12, 2), (10, 8, 3);

-- ============================================================
-- INTERNAL TRANSFERS (8 — mix of done / draft)
-- ============================================================
INSERT INTO transfers (id, reference, from_warehouse_id, to_warehouse_id, status, notes, created_by, validated_at, created_at) VALUES
(1, 'TRF-20240108-AA001', 1, 2, 'done', 'Steel rods to production',           1, '2024-11-09 09:00:00', '2024-11-08 08:00:00'),
(2, 'TRF-20240115-BB002', 1, 2, 'done', 'Copper wire to production line',     2, '2024-11-16 10:00:00', '2024-11-15 09:00:00'),
(3, 'TRF-20240208-CC003', 1, 4, 'done', 'Finished goods to dispatch bay',     1, '2024-12-09 14:00:00', '2024-12-08 11:00:00'),
(4, 'TRF-20240215-DD004', 3, 1, 'done', 'Packaging from secondary to main',   3, '2024-12-16 10:00:00', '2024-12-15 08:30:00'),
(5, 'TRF-20240222-EE005', 1, 2, 'done', 'Spare parts to production floor',    2, '2024-12-23 11:00:00', '2024-12-22 09:00:00'),
(6, 'TRF-20240308-FF006', 1, 3, 'done', 'Stock redistribution',               1, '2025-01-02 15:00:00', '2025-01-01 10:00:00'),
(7, 'TRF-20240312-GG007', 1, 2, 'draft','Raw materials for next batch',       2, NULL,                  '2025-01-13 09:00:00'),
(8, 'TRF-20240314-HH008', 4, 1, 'draft','Return from dispatch - cancelled po',1, NULL,                  '2025-01-14 11:00:00');

INSERT INTO transfer_items (transfer_id, product_id, quantity) VALUES
(1, 1, 100), (1, 5, 50),
(2, 3, 30),
(3, 7, 8), (3, 9, 15), (3, 11, 4),
(4, 13, 150), (4, 14, 200),
(5, 18, 40), (5, 19, 20), (5, 20, 30),
(6, 2, 80), (6, 16, 20),
(7, 1, 150), (7, 6, 50),
(8, 7, 3), (8, 12, 1);

-- ============================================================
-- STOCK ADJUSTMENTS (6)
-- ============================================================
INSERT INTO adjustments (id, reference, warehouse_id, status, notes, created_by, validated_at, created_at) VALUES
(1, 'ADJ-20240115-AA001', 1, 'done', 'Monthly cycle count — Nov',     1, '2024-11-15 17:00:00', '2024-11-15 14:00:00'),
(2, 'ADJ-20240130-BB002', 2, 'done', 'Production floor audit',        2, '2024-11-30 16:00:00', '2024-11-30 13:00:00'),
(3, 'ADJ-20240215-CC003', 1, 'done', '3 pcs damaged in transit',      1, '2024-12-15 11:00:00', '2024-12-15 09:00:00'),
(4, 'ADJ-20240228-DD004', 3, 'done', 'Secondary storage recount',     3, '2024-12-28 15:00:00', '2024-12-28 12:00:00'),
(5, 'ADJ-20240310-EE005', 1, 'done', 'Annual physical stock count',   1, '2025-01-05 18:00:00', '2025-01-05 10:00:00'),
(6, 'ADJ-20240315-FF006', 1, 'draft','Pending verification count',    2, NULL,                  '2025-01-14 09:00:00');

INSERT INTO adjustment_items (adjustment_id, product_id, system_quantity, counted_quantity, difference) VALUES
(1, 5,  500, 480, -20),
(1, 16, 100, 95,   -5),
(2, 18, 100, 98,   -2),
(2, 21, 500, 490, -10),
(3, 10, 15,  12,   -3),
(3, 11, 17,  14,   -3),
(4, 13, 450, 460,  10),
(4, 14, 620, 615,  -5),
(5, 1,  900, 850,  -50),
(5, 9,  80,  67,   -13),
(5, 28, 50,  46,    -4),
(6, 30, 8,   0,     -8),
(6, 24, 11,  11,    0);

-- ============================================================
-- STOCK LEDGER (history of all movements)
-- ============================================================
INSERT INTO stock_ledger (product_id, warehouse_id, operation_type, reference_id, reference_type, quantity_change, quantity_after, notes, created_by, created_at) VALUES
-- Receipt logs
(1,  1, 'receipt',      1, 'receipt',  500,  500,  'RCT-20240101-AA001', 1, '2024-11-02 10:00:00'),
(4,  1, 'receipt',      1, 'receipt',  100,  100,  'RCT-20240101-AA001', 1, '2024-11-02 10:00:00'),
(2,  1, 'receipt',      2, 'receipt',  200,  200,  'RCT-20240110-BB002', 1, '2024-11-11 14:00:00'),
(3,  1, 'receipt',      3, 'receipt',   80,   80,  'RCT-20240120-CC003', 2, '2024-11-21 11:30:00'),
(18, 1, 'receipt',      4, 'receipt',  100,  100,  'RCT-20240201-DD004', 1, '2024-12-02 09:45:00'),
(19, 1, 'receipt',      4, 'receipt',   50,   50,  'RCT-20240201-DD004', 1, '2024-12-02 09:45:00'),
(20, 1, 'receipt',      4, 'receipt',   80,   80,  'RCT-20240201-DD004', 1, '2024-12-02 09:45:00'),
(13, 3, 'receipt',      5, 'receipt',  300,  300,  'RCT-20240210-EE005', 2, '2024-12-11 15:00:00'),
(14, 3, 'receipt',      5, 'receipt',  400,  400,  'RCT-20240210-EE005', 2, '2024-12-11 15:00:00'),
(23, 1, 'receipt',      6, 'receipt',   30,   30,  'RCT-20240215-FF006', 1, '2024-12-16 12:00:00'),
(26, 1, 'receipt',      6, 'receipt',   25,   25,  'RCT-20240215-FF006', 1, '2024-12-16 12:00:00'),
(27, 1, 'receipt',      6, 'receipt',  200,  200,  'RCT-20240215-FF006', 1, '2024-12-16 12:00:00'),
(28, 1, 'receipt',      7, 'receipt',   30,   30,  'RCT-20240301-GG007', 2, '2024-12-20 10:00:00'),
(29, 1, 'receipt',      7, 'receipt',   40,   40,  'RCT-20240301-GG007', 2, '2024-12-20 10:00:00'),
-- Delivery logs
(1,  1, 'delivery',     1, 'delivery', -150, 350,  'DLV-20240105-AA001', 1, '2024-11-06 14:00:00'),
(4,  1, 'delivery',     1, 'delivery',  -40,  60,  'DLV-20240105-AA001', 1, '2024-11-06 14:00:00'),
(8,  1, 'delivery',     2, 'delivery',   -5,  13,  'DLV-20240112-BB002', 2, '2024-11-13 11:00:00'),
(10, 4, 'delivery',     3, 'delivery',   -3,   9,  'DLV-20240122-CC003', 1, '2024-11-23 16:00:00'),
(9,  4, 'delivery',     3, 'delivery',  -12,   8,  'DLV-20240122-CC003', 1, '2024-11-23 16:00:00'),
(27, 1, 'delivery',     4, 'delivery', -100, 100,  'DLV-20240205-DD004', 2, '2024-12-06 10:30:00'),
(18, 1, 'delivery',     5, 'delivery',  -30,  70,  'DLV-20240212-EE005', 1, '2024-12-13 15:00:00'),
(19, 1, 'delivery',     5, 'delivery',  -15,  35,  'DLV-20240212-EE005', 1, '2024-12-13 15:00:00'),
(1,  1, 'delivery',     6, 'delivery', -200, 150,  'DLV-20240220-FF006', 3, '2024-12-21 09:00:00'),
(7,  4, 'delivery',     7, 'delivery',  -10,   5,  'DLV-20240305-GG007', 2, '2024-12-28 14:00:00'),
(11, 4, 'delivery',     7, 'delivery',   -5,   0,  'DLV-20240305-GG007', 2, '2024-12-28 14:00:00'),
-- Transfer logs
(1,  1, 'transfer_out', 1, 'transfer', -100, 400,  'TRF-20240108-AA001', 1, '2024-11-09 09:00:00'),
(1,  2, 'transfer_in',  1, 'transfer',  100, 100,  'TRF-20240108-AA001', 1, '2024-11-09 09:00:00'),
(3,  1, 'transfer_out', 2, 'transfer',  -30,  50,  'TRF-20240115-BB002', 2, '2024-11-16 10:00:00'),
(3,  2, 'transfer_in',  2, 'transfer',   30,  30,  'TRF-20240115-BB002', 2, '2024-11-16 10:00:00'),
(7,  1, 'transfer_out', 3, 'transfer',   -8,  34,  'TRF-20240208-CC003', 1, '2024-12-09 14:00:00'),
(7,  4, 'transfer_in',  3, 'transfer',    8,  23,  'TRF-20240208-CC003', 1, '2024-12-09 14:00:00'),
-- Adjustment logs
(5,  1, 'adjustment',   1, 'adjustment', -20, 480, 'Monthly cycle count', 1, '2024-11-15 17:00:00'),
(16, 1, 'adjustment',   1, 'adjustment',  -5,  95, 'Monthly cycle count', 1, '2024-11-15 17:00:00'),
(10, 1, 'adjustment',   3, 'adjustment',  -3,  12, '3 pcs damaged',       1, '2024-12-15 11:00:00'),
(1,  1, 'adjustment',   5, 'adjustment', -50, 850, 'Annual count',        1, '2025-01-05 18:00:00'),
(9,  1, 'adjustment',   5, 'adjustment', -13,  67, 'Annual count',        1, '2025-01-05 18:00:00'),
(28, 1, 'adjustment',   5, 'adjustment',  -4,  46, 'Annual count',        1, '2025-01-05 18:00:00');

-- ============================================================
-- DONE! Login credentials:
-- admin@coreinventory.com   / password
-- manager@coreinventory.com / password
-- staff1@coreinventory.com  / password
-- staff2@coreinventory.com  / password
-- ============================================================