<?php
require_once __DIR__ . '/../includes/config.php';
requirePermission('view_warehouses');

$db = getDB();
$pageTitle  = 'Warehouses';
$activePage = 'warehouses';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CREATE — only admin
    if ($action === 'create') {
        denyAction('manage_warehouses', '/pages/warehouses.php');
        $name = trim($_POST['name'] ?? '');
        $loc  = trim($_POST['location'] ?? '');
        if ($name) {
            $db->prepare("INSERT INTO warehouses (name, location) VALUES (?,?)")->execute([$name, $loc]);
            setFlash('success', "Warehouse '{$name}' created.");
        }
        header('Location: ' . BASE_URL . '/pages/warehouses.php'); exit;
    }

    // TOGGLE — only admin
    if ($action === 'toggle') {
        denyAction('manage_warehouses', '/pages/warehouses.php');
        $id = (int)$_POST['warehouse_id'];
        $db->prepare("UPDATE warehouses SET is_active = NOT is_active WHERE id=?")->execute([$id]);
        setFlash('success', 'Warehouse status updated.');
        header('Location: ' . BASE_URL . '/pages/warehouses.php'); exit;
    }
}

$warehouses = $db->query("SELECT w.*,
    (SELECT COUNT(DISTINCT s.product_id) FROM stock s WHERE s.warehouse_id=w.id AND s.quantity>0) as active_products
    FROM warehouses w ORDER BY w.name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div><h1>Warehouses</h1><p>Manage storage locations and facilities</p></div>
    <?php if (can('manage_warehouses')): ?>
    <button class="btn btn-primary" onclick="openModal('whModal')">+ New Warehouse</button>
    <?php endif; ?>
</div>

<!-- View-only notice for Manager -->
<?php if (!can('manage_warehouses')): ?>
<div class="flash-message flash-info" style="margin:0 0 20px;">
    <span>ℹ You have <strong>view-only</strong> access to warehouses. Only Admins can create or deactivate warehouses.</span>
</div>
<?php endif; ?>

<div class="grid-3">
<?php foreach ($warehouses as $w): ?>
<div class="card" style="<?= !$w['is_active'] ? 'opacity:0.55' : '' ?>">
    <div class="card-body">
        <div style="display:flex;align-items:start;justify-content:space-between;margin-bottom:14px;">
            <div>
                <div style="font-family:var(--font-head);font-size:17px;font-weight:700;margin-bottom:4px;"><?= clean($w['name']) ?></div>
                <div style="font-size:12px;color:var(--text3);"><?= clean($w['location'] ?? 'No location set') ?></div>
            </div>
            <?php if ($w['is_active']): ?>
            <span class="badge badge-done">Active</span>
            <?php else: ?>
            <span class="badge badge-canceled">Inactive</span>
            <?php endif; ?>
        </div>
        <div style="background:var(--bg3);border-radius:6px;padding:12px;margin-bottom:14px;">
            <div style="font-size:11px;color:var(--text3);margin-bottom:4px;">PRODUCTS IN STOCK</div>
            <div style="font-family:var(--font-head);font-size:26px;font-weight:800;color:var(--accent);"><?= $w['active_products'] ?></div>
        </div>
        <?php if (can('manage_warehouses')): ?>
        <form method="POST">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="warehouse_id" value="<?= $w['id'] ?>">
            <button type="submit" class="btn btn-ghost btn-sm" style="width:100%"
                data-confirm="<?= $w['is_active'] ? 'Deactivate' : 'Activate' ?> this warehouse?">
                <?= $w['is_active'] ? 'Deactivate' : 'Activate' ?>
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php if (can('manage_warehouses')): ?>
<div class="modal-overlay" id="whModal">
    <div class="modal">
        <div class="modal-header">
            <h3>New Warehouse</h3>
            <button class="modal-close" onclick="closeModal('whModal')">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="create">
                <div class="form-group"><label>Warehouse Name *</label><input type="text" name="name" required placeholder="e.g. Main Warehouse"></div>
                <div class="form-group"><label>Location / Address</label><input type="text" name="location" placeholder="Building A, Floor 2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('whModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Warehouse</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => { if (!confirm(el.dataset.confirm)) e.preventDefault(); });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
