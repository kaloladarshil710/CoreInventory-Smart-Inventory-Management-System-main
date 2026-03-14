<?php
require_once __DIR__ . '/../includes/config.php';
requirePermission('view_deliveries');

$db = getDB();
$pageTitle  = 'Deliveries';
$activePage = 'deliveries';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $customer    = trim($_POST['customer_name'] ?? '');
        $warehouseId = (int)$_POST['warehouse_id'];
        $notes       = trim($_POST['notes'] ?? '');
        $ref         = generateRef('DLV');

        $db->prepare("INSERT INTO deliveries (reference, customer_name, warehouse_id, notes, status, created_by) VALUES (?,?,?,?,'draft',?)")
           ->execute([$ref, $customer, $warehouseId, $notes, $_SESSION['user_id']]);
        $did = $db->lastInsertId();

        foreach ($_POST['items'] ?? [] as $item) {
            $pid = (int)($item['product_id'] ?? 0);
            $qty = (float)($item['quantity'] ?? 0);
            if ($pid && $qty > 0) {
                $db->prepare("INSERT INTO delivery_items (delivery_id, product_id, quantity) VALUES (?,?,?)")
                   ->execute([$did, $pid, $qty]);
            }
        }
        setFlash('success', "Delivery order {$ref} created.");
        header('Location: ' . BASE_URL . '/pages/deliveries.php');
        exit;
    }

    if ($action === 'validate') {
        denyAction('validate_deliveries', '/pages/deliveries.php');
        if (!can('validate_deliveries')) { setFlash('error','Access denied.'); header('Location: '.BASE_URL.'/pages/deliveries.php'); exit; }
        $did = (int)$_POST['delivery_id'];
        $stmt = $db->prepare("SELECT * FROM deliveries WHERE id=? AND status NOT IN ('done','canceled')");
        $stmt->execute([$did]);
        $delivery = $stmt->fetch();

        if ($delivery) {
            $items = $db->prepare("SELECT * FROM delivery_items WHERE delivery_id=?");
            $items->execute([$did]);
            foreach ($items->fetchAll() as $item) {
                // Check available stock
                $avail = $db->prepare("SELECT quantity FROM stock WHERE product_id=? AND warehouse_id=?");
                $avail->execute([$item['product_id'], $delivery['warehouse_id']]);
                $avail = (float)($avail->fetchColumn() ?? 0);

                if ($avail < $item['quantity']) {
                    setFlash('error', "Insufficient stock for one or more products.");
                    header('Location: ' . BASE_URL . '/pages/deliveries.php');
                    exit;
                }

                // Deduct stock
                $db->prepare("UPDATE stock SET quantity=quantity-? WHERE product_id=? AND warehouse_id=?")
                   ->execute([$item['quantity'], $item['product_id'], $delivery['warehouse_id']]);

                $newQty = $db->prepare("SELECT quantity FROM stock WHERE product_id=? AND warehouse_id=?");
                $newQty->execute([$item['product_id'], $delivery['warehouse_id']]);
                $newQty = $newQty->fetchColumn();

                $db->prepare("INSERT INTO stock_ledger (product_id, warehouse_id, operation_type, reference_id, reference_type, quantity_change, quantity_after, created_by) VALUES (?,?,'delivery',?,'delivery',?,?,?)")
                   ->execute([$item['product_id'], $delivery['warehouse_id'], $did, -$item['quantity'], $newQty, $_SESSION['user_id']]);
            }
            $db->prepare("UPDATE deliveries SET status='done', validated_at=NOW() WHERE id=?")->execute([$did]);
            setFlash('success', 'Delivery validated. Stock deducted.');
        }
        header('Location: ' . BASE_URL . '/pages/deliveries.php');
        exit;
    }

    if ($action === 'cancel') {
        denyAction('validate_deliveries', '/pages/deliveries.php');
        $db->prepare("UPDATE deliveries SET status='canceled' WHERE id=? AND status='draft'")->execute([(int)$_POST['delivery_id']]);
        setFlash('success', 'Delivery canceled.');
        header('Location: ' . BASE_URL . '/pages/deliveries.php');
        exit;
    }
}

$deliveries = $db->query("SELECT d.*, w.name as warehouse_name FROM deliveries d JOIN warehouses w ON w.id=d.warehouse_id ORDER BY d.created_at DESC")->fetchAll();
$warehouses = $db->query("SELECT * FROM warehouses WHERE is_active=1")->fetchAll();
$products   = $db->query("SELECT * FROM products WHERE is_active=1 ORDER BY name")->fetchAll();
$productOpts = '';
foreach ($products as $p) $productOpts .= "<option value=\"{$p['id']}\">[{$p['sku']}] {$p['name']}</option>";

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div><h1>Delivery Orders</h1><p>Manage outgoing stock to customers</p></div>
    <button class="btn btn-primary" onclick="openModal('deliveryModal')">+ New Delivery</button>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Reference</th><th>Customer</th><th>Warehouse</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($deliveries as $d): ?>
            <tr>
                <td class="td-mono td-bold"><?= clean($d['reference']) ?></td>
                <td><?= clean($d['customer_name'] ?? '—') ?></td>
                <td><?= clean($d['warehouse_name']) ?></td>
                <td><span class="badge <?= statusColor($d['status']) ?>"><?= $d['status'] ?></span></td>
                <td class="td-mono"><?= date('d M Y', strtotime($d['created_at'])) ?></td>
                <td>
                    <?php if (in_array($d['status'], ['draft','waiting','ready'])): ?>
                    <?php if (can('validate_deliveries')): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="validate">
                        <input type="hidden" name="delivery_id" value="<?= $d['id'] ?>">
                        <button type="submit" class="btn btn-success btn-sm" data-confirm="Validate delivery and deduct stock?">✓ Validate</button>
                    </form>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="delivery_id" value="<?= $d['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" data-confirm="Cancel this delivery?">Cancel</button>
                    </form>
                    <?php endif; ?>
                    <?php else: ?>
                    <span style="color:var(--text3);font-size:12px;"><?= $d['status'] === 'done' ? '✓ Dispatched' : 'Canceled' ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$deliveries): ?>
            <tr><td colspan="6"><div class="empty-state"><h3>No delivery orders</h3><p>Create a delivery when dispatching goods to a customer</p></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Delivery Modal -->
<div class="modal-overlay" id="deliveryModal">
    <div class="modal" style="max-width:680px">
        <div class="modal-header">
            <h3>New Delivery Order</h3>
            <button class="modal-close" onclick="closeModal('deliveryModal')">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="create">
                <div class="grid-2">
                    <div class="form-group">
                        <label>Customer Name</label>
                        <input type="text" name="customer_name">
                    </div>
                    <div class="form-group">
                        <label>From Warehouse *</label>
                        <select name="warehouse_id" required>
                            <?php foreach ($warehouses as $w): ?>
                            <option value="<?= $w['id'] ?>"><?= clean($w['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
                <label style="margin-bottom:10px;display:block;">Products to Deliver</label>
                <div class="items-table-wrap" style="margin-bottom:12px;">
                    <table><thead><tr><th>Product</th><th>Quantity</th><th></th></tr></thead>
                    <tbody id="deliveryItems"></tbody></table>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" onclick="addItem('deliveryItems', `<?= addslashes($productOpts) ?>`)">+ Add Product</button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('deliveryModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Delivery</button>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => { if (!confirm(el.dataset.confirm)) e.preventDefault(); });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
