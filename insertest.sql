USE coreinventory;

-- Extra Warehouses
INSERT INTO warehouses (name, location) VALUES
('East Storage', 'Building D, Floor 2'),
('Cold Storage', 'Building E');

-- Extra Categories
INSERT INTO categories (name, description) VALUES
('Electronics', 'Electronic components and devices'),
('Furniture', 'Office and warehouse furniture'),
('Chemicals', 'Industrial chemicals and solvents');

-- -- Extra Users
-- INSERT INTO users (name, email, password, role) VALUES
-- ('John Manager', 'manager@coreinventory.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager'),
-- ('Sara Staff', 'sara@coreinventory.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff'),
-- ('Ali Staff', 'ali@coreinventory.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff');
-- -- All passwords: password

-- Extra Products
INSERT INTO products (name, sku, category_id, unit_of_measure, reorder_level, description) VALUES
('Copper Wire 2mm',   'CPR-001', 1, 'kg',  30,  'Electrical copper wire, 2mm diameter'),
('LED Bulb 9W',       'LED-001', 1, 'pcs', 50,  '9W LED bulbs, warm white'),
('Office Desk',       'DSK-001', 2, 'pcs', 5,   'Standard 4-leg office desk'),
('Filing Cabinet',    'CAB-001', 2, 'pcs', 3,   '3-drawer metal filing cabinet'),
('Acetone 5L',        'ACT-001', 3, 'ltr', 20,  'Industrial grade acetone'),
('Bubble Wrap Roll',  'BWR-001', 3, 'pcs', 40,  '50m bubble wrap roll'),
('Steel Nuts M8',     'NUT-001', 4, 'pcs', 500, 'M8 hex nuts, zinc plated'),
('Hydraulic Oil 1L',  'HYD-001', 3, 'ltr', 15,  'ISO 46 hydraulic oil');

-- Stock for new products across warehouses
INSERT INTO stock (product_id, warehouse_id, quantity) VALUES
(5, 1, 120), (5, 2, 40),
(6, 1, 200), (6, 3, 80),
(7, 1, 15),
(8, 1, 60),  (8, 4, 20),
(9, 1, 8),
(10, 1, 25), (10, 2, 10),
(11, 1, 180),(11, 3, 90),
(12, 1, 600),(12, 2, 300);

-- Receipts
INSERT INTO receipts (reference, supplier_name, warehouse_id, status, notes, created_by, validated_at) VALUES
('REC-2024-001', 'Alpha Supplies Co.',     1, 'done',    'First bulk order',          1, '2024-11-01 10:00:00'),
('REC-2024-002', 'Beta Electronics Ltd.',  1, 'done',    'Monthly electronics stock', 1, '2024-11-15 14:30:00'),
('REC-2024-003', 'Gamma Packaging',        3, 'waiting', 'Packaging restock',         2, NULL),
('REC-2024-004', 'Delta Raw Materials',    2, 'draft',   'Pending supplier confirm',  3, NULL),
('REC-2024-005', 'Alpha Supplies Co.',     1, 'ready',   'Urgent reorder',            2, NULL);

-- Receipt Items
INSERT INTO receipt_items (receipt_id, product_id, quantity_expected, quantity_received) VALUES
(1, 1, 300, 300),
(1, 4, 500, 480),
(2, 5, 150, 150),
(2, 6, 300, 290),
(3, 7, 100, 0),
(4, 1, 200, 0),
(5, 8, 30,  0);

-- Deliveries
INSERT INTO deliveries (reference, customer_name, warehouse_id, status, notes, created_by, validated_at) VALUES
('DEL-2024-001', 'Omega Manufacturing',  1, 'done',    'Regular monthly delivery',  1, '2024-11-05 09:00:00'),
('DEL-2024-002', 'Sunrise Retail',       1, 'done',    'Bulk furniture order',      2, '2024-11-18 11:00:00'),
('DEL-2024-003', 'TechStart Inc.',       1, 'waiting', 'Awaiting stock check',      3, NULL),
('DEL-2024-004', 'Green Builders Ltd.',  2, 'draft',   'Partial delivery planned',  2, NULL),
('DEL-2024-005', 'Metro Supplies',       1, 'ready',   'Packed and ready',          1, NULL);

-- Delivery Items
INSERT INTO delivery_items (delivery_id, product_id, quantity) VALUES
(1, 1,  100),
(1, 4,  200),
(2, 7,  5),
(2, 8,  10),
(3, 5,  50),
(3, 6,  100),
(4, 1,  80),
(5, 9,  5);

-- Transfers
INSERT INTO transfers (reference, from_warehouse_id, to_warehouse_id, status, notes, created_by, validated_at) VALUES
('TRF-2024-001', 1, 2, 'done',    'Move steel rods to production', 1, '2024-11-10 08:00:00'),
('TRF-2024-002', 1, 3, 'done',    'Packaging stock redistribution',2, '2024-11-20 13:00:00'),
('TRF-2024-003', 2, 4, 'waiting', 'Cold storage transfer',         3, NULL),
('TRF-2024-004', 1, 2, 'draft',   'Scheduled next week',           2, NULL);

-- Transfer Items
INSERT INTO transfer_items (transfer_id, product_id, quantity) VALUES
(1, 1,  50),
(1, 4,  100),
(2, 7,  60),
(2, 11, 40),
(3, 9,  3),
(4, 5,  20);

-- Adjustments
INSERT INTO adjustments (reference, warehouse_id, status, notes, created_by, validated_at) VALUES
('ADJ-2024-001', 1, 'done',     'Monthly stock count Nov',    1, '2024-11-30 17:00:00'),
('ADJ-2024-002', 2, 'done',     'Discrepancy found in audit', 2, '2024-11-25 15:00:00'),
('ADJ-2024-003', 1, 'draft',    'Ongoing count Dec',          3, NULL);

-- Adjustment Items
INSERT INTO adjustment_items (adjustment_id, product_id, system_quantity, counted_quantity, difference) VALUES
(1, 1,  250, 245, -5),
(1, 4,  150, 150,  0),
(1, 6,  200, 195, -5),
(2, 1,  80,  78,  -2),
(2, 5,  40,  42,   2),
(3, 7,  15,  0,    0);

-- Stock Ledger (sample movement history)
INSERT INTO stock_ledger (product_id, warehouse_id, operation_type, reference_id, reference_type, quantity_change, quantity_after, notes, created_by) VALUES
(1, 1, 'receipt',      1, 'receipts',   300,  300,  'Initial stock receipt',        1),
(4, 1, 'receipt',      1, 'receipts',   480,  480,  'Initial bolts receipt',        1),
(1, 1, 'delivery',     1, 'deliveries', -100, 200,  'Delivered to Omega Mfg',       1),
(1, 1, 'transfer_out', 1, 'transfers',  -50,  150,  'Transferred to production',    1),
(1, 2, 'transfer_in',  1, 'transfers',   50,  130,  'Received from main warehouse', 1),
(1, 1, 'adjustment',   1, 'adjustments',-5,   145,  'Stock count correction',       1);