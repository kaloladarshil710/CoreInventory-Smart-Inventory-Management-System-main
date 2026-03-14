<?php
require_once __DIR__ . '/../includes/config.php';
requirePermission('view_products');

$db = getDB();
$pageTitle  = 'Products';
$activePage = 'products';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CREATE / EDIT — requires manage_products
    if ($action === 'create' || $action === 'edit') {
        denyAction('manage_products', '/pages/products.php');
        $name    = trim($_POST['name'] ?? '');
        $sku     = trim($_POST['sku'] ?? '');
        $cat     = (int)($_POST['category_id'] ?? 0);
        $uom     = trim($_POST['unit_of_measure'] ?? 'pcs');
        $reorder = (int)($_POST['reorder_level'] ?? 0);
        $desc    = trim($_POST['description'] ?? '');

        if ($name && $sku) {
            if ($action === 'create') {
                $initQty     = (float)($_POST['initial_stock'] ?? 0);
                $warehouseId = (int)($_POST['warehouse_id'] ?? 1);
                $stmt = $db->prepare("INSERT INTO products (name, sku, category_id, unit_of_measure, reorder_level, description) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$name, $sku, $cat ?: null, $uom, $reorder, $desc]);
                $pid = $db->lastInsertId();
                if ($initQty > 0) {
                    $db->prepare("INSERT INTO stock (product_id, warehouse_id, quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity)")
                       ->execute([$pid, $warehouseId, $initQty]);
                    $db->prepare("INSERT INTO stock_ledger (product_id, warehouse_id, operation_type, quantity_change, quantity_after, notes, created_by) VALUES (?,?,'adjustment',?,?,?,?)")
                       ->execute([$pid, $warehouseId, $initQty, $initQty, 'Initial stock', $_SESSION['user_id']]);
                }
                setFlash('success', "Product '{$name}' created successfully.");
            } else {
                $id = (int)$_POST['product_id'];
                $db->prepare("UPDATE products SET name=?, sku=?, category_id=?, unit_of_measure=?, reorder_level=?, description=? WHERE id=?")
                   ->execute([$name, $sku, $cat ?: null, $uom, $reorder, $desc, $id]);
                setFlash('success', "Product updated.");
            }
        } else {
            setFlash('error', 'Name and SKU are required.');
        }
        header('Location: ' . BASE_URL . '/pages/products.php'); exit;
    }

    // DELETE — requires manage_products
    if ($action === 'delete') {
        denyAction('manage_products', '/pages/products.php');
        $id = (int)$_POST['product_id'];
        $db->prepare("UPDATE products SET is_active=0 WHERE id=?")->execute([$id]);
        setFlash('success', 'Product deactivated.');
        header('Location: ' . BASE_URL . '/pages/products.php'); exit;
    }

    // CREATE CATEGORY — requires manage_categories
    if ($action === 'create_category') {
        denyAction('manage_categories', '/pages/products.php');
        $cname = trim($_POST['cat_name'] ?? '');
        if ($cname) {
            $db->prepare("INSERT INTO categories (name) VALUES (?)")->execute([$cname]);
            setFlash('success', "Category '{$cname}' created.");
        }
        header('Location: ' . BASE_URL . '/pages/products.php'); exit;
    }
}

$search    = trim($_GET['q'] ?? '');
$catFilter = (int)($_GET['cat'] ?? 0);
$where     = "WHERE p.is_active=1";
$params    = [];
if ($search)    { $where .= " AND (p.name LIKE ? OR p.sku LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($catFilter) { $where .= " AND p.category_id=?"; $params[] = $catFilter; }

$products = $db->prepare("
    SELECT p.*, c.name as category_name,
           COALESCE((SELECT SUM(quantity) FROM stock WHERE product_id=p.id), 0) as total_stock
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    $where ORDER BY p.name
");
$products->execute($params);
$products   = $products->fetchAll();
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$warehouses = $db->query("SELECT * FROM warehouses WHERE is_active=1 ORDER BY name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Products</h1>
        <p>Product catalog and stock availability</p>
    </div>
    <div style="display:flex;gap:10px;">
        <?php if (can('manage_categories')): ?>
        <button class="btn btn-ghost" onclick="openModal('categoryModal')">+ Category</button>
        <?php endif; ?>
        <?php if (can('manage_products')): ?>
        <button class="btn btn-primary" onclick="openModal('productModal')">+ New Product</button>
        <?php endif; ?>
    </div>
</div>

<!-- Staff notice -->
<?php if (!can('manage_products')): ?>
<div class="flash-message flash-info" style="margin:0 0 20px;">
    <span>ℹ You have <strong>view-only</strong> access to products. Contact a Manager or Admin to make changes.</span>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:14px 20px;">
        <form method="GET" class="filters-bar">
            <input type="text" name="q" placeholder="Search by name or SKU..." value="<?= clean($search) ?>">
            <select name="cat">
                <option value="">All Categories</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $catFilter == $c['id'] ? 'selected' : '' ?>><?= clean($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="<?= BASE_URL ?>/pages/products.php" class="btn btn-ghost">Clear</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Products (<?= count($products) ?>)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Product</th><th>SKU</th><th>Category</th><th>UoM</th>
                    <th>Total Stock</th><th>Reorder Level</th><th>Status</th>
                    <?php if (can('manage_products')): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $p):
                $ratio = $p['reorder_level'] > 0 ? min(100, ($p['total_stock'] / ($p['reorder_level'] * 2)) * 100) : 100;
                $stockClass = $p['total_stock'] == 0 ? 'stock-out' : ($p['total_stock'] <= $p['reorder_level'] ? 'stock-low' : 'stock-ok');
            ?>
            <tr>
                <td class="td-bold"><?= clean($p['name']) ?></td>
                <td class="td-mono"><?= clean($p['sku']) ?></td>
                <td><?= clean($p['category_name'] ?? '—') ?></td>
                <td><?= clean($p['unit_of_measure']) ?></td>
                <td>
                    <div class="stock-bar-wrap <?= $stockClass ?>">
                        <span style="font-weight:600;min-width:50px;"><?= fmtNum($p['total_stock'], 0) ?></span>
                        <div class="stock-bar"><div class="stock-bar-fill" style="width:<?= $ratio ?>%"></div></div>
                    </div>
                </td>
                <td><?= $p['reorder_level'] ?></td>
                <td>
                    <?php if ($p['total_stock'] == 0): ?>
                        <span class="badge badge-canceled">Out of Stock</span>
                    <?php elseif ($p['total_stock'] <= $p['reorder_level']): ?>
                        <span class="badge badge-waiting">Low Stock</span>
                    <?php else: ?>
                        <span class="badge badge-done">In Stock</span>
                    <?php endif; ?>
                </td>
                <?php if (can('manage_products')): ?>
                <td class="td-actions">
                    <button class="btn btn-ghost btn-sm" onclick="editProduct(<?= htmlspecialchars(json_encode($p)) ?>)">Edit</button>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" data-confirm="Deactivate this product?">Remove</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            <?php if (!$products): ?>
            <tr><td colspan="<?= can('manage_products') ? 8 : 7 ?>">
                <div class="empty-state">
                    <div class="empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></div>
                    <h3>No products found</h3>
                    <p>Create your first product to get started</p>
                </div>
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (can('manage_products')): ?>
<!-- Create/Edit Product Modal -->
<div class="modal-overlay" id="productModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="productModalTitle">New Product</h3>
            <button class="modal-close" onclick="closeModal('productModal')">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="productAction" value="create">
                <input type="hidden" name="product_id" id="productId" value="">
                <div class="grid-2">
                    <div class="form-group"><label>Product Name *</label><input type="text" name="name" id="productName" required></div>
                    <div class="form-group"><label>SKU / Code *</label><input type="text" name="sku" id="productSku" required></div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id" id="productCategory">
                            <option value="">No Category</option>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Unit of Measure</label>
                        <select name="unit_of_measure" id="productUom">
                            <?php foreach (['pcs','kg','g','litre','ml','box','carton','pair','set','metre'] as $u): ?>
                            <option value="<?= $u ?>"><?= $u ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label>Reorder Level</label><input type="number" name="reorder_level" id="productReorder" min="0" value="0"></div>
                <div class="form-group"><label>Description</label><textarea name="description" id="productDesc" rows="2"></textarea></div>
                <div id="initialStockSection">
                    <hr style="border-color:var(--border);margin:12px 0;">
                    <div class="grid-2">
                        <div class="form-group"><label>Initial Stock (optional)</label><input type="number" name="initial_stock" min="0" step="0.01" value="0"></div>
                        <div class="form-group">
                            <label>Warehouse</label>
                            <select name="warehouse_id">
                                <?php foreach ($warehouses as $w): ?>
                                <option value="<?= $w['id'] ?>"><?= clean($w['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('productModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Product</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (can('manage_categories')): ?>
<!-- Category Modal -->
<div class="modal-overlay" id="categoryModal">
    <div class="modal">
        <div class="modal-header">
            <h3>New Category</h3>
            <button class="modal-close" onclick="closeModal('categoryModal')">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="create_category">
                <div class="form-group"><label>Category Name *</label><input type="text" name="cat_name" required></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('categoryModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Category</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function editProduct(p) {
    document.getElementById('productModalTitle').textContent = 'Edit Product';
    document.getElementById('productAction').value = 'edit';
    document.getElementById('productId').value = p.id;
    document.getElementById('productName').value = p.name;
    document.getElementById('productSku').value = p.sku;
    document.getElementById('productCategory').value = p.category_id || '';
    document.getElementById('productUom').value = p.unit_of_measure;
    document.getElementById('productReorder').value = p.reorder_level;
    document.getElementById('productDesc').value = p.description || '';
    document.getElementById('initialStockSection').style.display = 'none';
    openModal('productModal');
}
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => { if (!confirm(el.dataset.confirm)) e.preventDefault(); });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
