<?php
require_once __DIR__ . '/../includes/config.php';
requirePermission('view_deliveries');

$db = getDB();
$pageTitle  = 'Deliveries';
$activePage = 'deliveries';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        denyAction('manage_deliveries', '/pages/deliveries.php');
        $customer    = trim($_POST['customer_name'] ?? '');
        $warehouseId = (int)$_POST['warehouse_id'];
        // BUG FIX: deliveries table has no 'notes' column — removed from INSERT
        $ref         = generateRef('DLV');

        $db->prepare("INSERT INTO deliveries (reference, customer_name, warehouse_id, status, created_by) VALUES (?,?,?,'draft',?)")
           ->execute([$ref, $customer, $warehouseId, $_SESSION['user_id']]);
        $did = $db->lastInsertId();

        foreach ($_POST['items'] ?? [] as $item) {
            $pid = (int)($item['product_id'] ?? 0);
            $qty = (float)($item['quantity'] ?? 0);
            if ($pid && $qty > 0) {
                $db->prepare("INSERT INTO delivery_items (delivery_id, product_id, quantity) VALUES (?,?,?)")
                   ->execute([$did, $pid, $qty]);
            }
        }
        setFlash('success', "Delivery order <strong>{$ref}</strong> created successfully.");
        header('Location: ' . BASE_URL . '/pages/deliveries.php'); exit;
    }

    if ($action === 'validate') {
        denyAction('validate_deliveries', '/pages/deliveries.php');
        $did = (int)$_POST['delivery_id'];
        $stmt = $db->prepare("SELECT * FROM deliveries WHERE id=? AND status NOT IN ('done','canceled')");
        $stmt->execute([$did]);
        $delivery = $stmt->fetch();

        if ($delivery) {
            $items = $db->prepare("SELECT * FROM delivery_items WHERE delivery_id=?");
            $items->execute([$did]);
            $allItems = $items->fetchAll();

            // First pass — check all stock
            foreach ($allItems as $item) {
                $avail = $db->prepare("SELECT COALESCE(SUM(quantity),0) FROM stock WHERE product_id=? AND warehouse_id=?");
                $avail->execute([$item['product_id'], $delivery['warehouse_id']]);
                $availQty = (float)$avail->fetchColumn();
                if ($availQty < $item['quantity']) {
                    $pName = $db->prepare("SELECT name FROM products WHERE id=?");
                    $pName->execute([$item['product_id']]);
                    $pNameStr = $pName->fetchColumn();
                    setFlash('error', "Insufficient stock for <strong>{$pNameStr}</strong>. Available: {$availQty}, Required: {$item['quantity']}");
                    header('Location: ' . BASE_URL . '/pages/deliveries.php'); exit;
                }
            }

            // Second pass — deduct stock
            // BUG FIX: Use transaction to prevent partial updates
            $db->beginTransaction();
            try {
                foreach ($allItems as $item) {
                    $db->prepare("UPDATE stock SET quantity=quantity-? WHERE product_id=? AND warehouse_id=?")
                       ->execute([$item['quantity'], $item['product_id'], $delivery['warehouse_id']]);
                    // BUG FIX: removed dead/incorrect $newQty assignment; fetch correctly
                    $nq = $db->prepare("SELECT COALESCE(SUM(quantity),0) FROM stock WHERE product_id=? AND warehouse_id=?");
                    $nq->execute([$item['product_id'], $delivery['warehouse_id']]);
                    $newQty = (float)$nq->fetchColumn();

                    $db->prepare("INSERT INTO stock_ledger (product_id, warehouse_id, operation_type, reference_id, reference_type, quantity_change, quantity_after, created_by) VALUES (?,?,'delivery',?,'delivery',?,?,?)")
                       ->execute([$item['product_id'], $delivery['warehouse_id'], $did, -$item['quantity'], $newQty, $_SESSION['user_id']]);
                }
                $db->prepare("UPDATE deliveries SET status='done', validated_at=NOW() WHERE id=?")->execute([$did]);
                $db->commit();
                setFlash('success', 'Delivery validated. Stock deducted successfully.');
            } catch (Exception $e) {
                $db->rollBack();
                setFlash('error', 'An error occurred while validating the delivery. Please try again.');
            }
        }
        header('Location: ' . BASE_URL . '/pages/deliveries.php'); exit;
    }

    if ($action === 'cancel') {
        denyAction('validate_deliveries', '/pages/deliveries.php');
        $db->prepare("UPDATE deliveries SET status='canceled' WHERE id=? AND status='draft'")->execute([(int)$_POST['delivery_id']]);
        setFlash('success', 'Delivery order canceled.');
        header('Location: ' . BASE_URL . '/pages/deliveries.php'); exit;
    }
}

$statusFilter = $_GET['status'] ?? '';
$where  = '';
$params = [];
if ($statusFilter) { $where = "WHERE d.status=?"; $params[] = $statusFilter; }

$deliveries = $db->prepare("
    SELECT d.*, w.name as warehouse_name, u.name as creator_name
    FROM deliveries d
    JOIN warehouses w ON w.id=d.warehouse_id
    LEFT JOIN users u ON u.id=d.created_by
    $where
    ORDER BY d.created_at DESC
");
$deliveries->execute($params);
$deliveries = $deliveries->fetchAll();

$warehouses = $db->query("SELECT * FROM warehouses WHERE is_active=1 ORDER BY name")->fetchAll();
$products   = $db->query("SELECT id, name, sku, unit_of_measure FROM products WHERE is_active=1 ORDER BY name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<script>
window.PRODUCT_DATA = <?= json_encode(array_values($products), JSON_HEX_TAG | JSON_HEX_QUOT) ?>;
</script>

<div class="page-header">
    <div>
        <h1>Delivery Orders</h1>
        <p>Manage outgoing stock to customers</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <form method="GET" style="display:flex;gap:8px;">
            <select name="status" onchange="this.form.submit()" style="width:auto;min-width:130px;padding:9px 14px;font-size:13px;">
                <option value="">All Statuses</option>
                <?php foreach (['draft','waiting','ready','done','canceled'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($statusFilter): ?>
            <a href="<?= BASE_URL ?>/pages/deliveries.php" class="btn btn-ghost btn-sm">Clear</a>
            <?php endif; ?>
        </form>
        <?php if (can('manage_deliveries')): ?>
        <button class="btn btn-primary" onclick="openModal('deliveryModal')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Delivery
        </button>
        <?php endif; ?>
    </div>
</div>

<?php
$stats = $db->query("SELECT
    SUM(status='draft') as draft,
    SUM(status='waiting') as waiting,
    SUM(status='ready') as ready,
    SUM(status='done') as done,
    SUM(status='canceled') as canceled
    FROM deliveries")->fetch();
?>
<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <?php
    $statItems = [
        ['Draft',    $stats['draft']    ?? 0, 'var(--text3)'],
        ['Waiting',  $stats['waiting']  ?? 0, 'var(--orange)'],
        ['Ready',    $stats['ready']    ?? 0, 'var(--accent)'],
        ['Done',     $stats['done']     ?? 0, 'var(--green)'],
        ['Canceled', $stats['canceled'] ?? 0, 'var(--red)'],
    ];
    foreach ($statItems as [$lbl, $val, $clr]): ?>
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:10px 18px;display:flex;align-items:center;gap:10px;">
        <span style="font-family:var(--font-head);font-size:22px;font-weight:800;color:<?= $clr ?>"><?= $val ?></span>
        <span style="font-size:12px;color:var(--text3);"><?= $lbl ?></span>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 5v4h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            Delivery Orders (<?= count($deliveries) ?>)
        </span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Customer</th>
                    <th>Warehouse</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($deliveries as $d): ?>
            <tr>
                <td class="td-mono td-bold"><?= clean($d['reference']) ?></td>
                <td><?= clean($d['customer_name'] ?? '—') ?></td>
                <td>
                    <span style="display:flex;align-items:center;gap:6px;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                        <?= clean($d['warehouse_name']) ?>
                    </span>
                </td>
                <td><span class="badge <?= statusColor($d['status'] ?? 'draft') ?>"><?= $d['status'] ?></span></td>
                <td style="color:var(--text2);font-size:13px;"><?= clean($d['creator_name'] ?? '—') ?></td>
                <td class="td-mono"><?= date('d M Y', strtotime($d['created_at'])) ?></td>
                <td class="td-actions">
                    <?php if (in_array($d['status'], ['draft','waiting','ready'])): ?>
                        <?php if (can('validate_deliveries')): ?>
                        <form method="POST" style="display:inline-block;margin-right:4px;">
                            <input type="hidden" name="action" value="validate">
                            <input type="hidden" name="delivery_id" value="<?= $d['id'] ?>">
                            <button type="submit" class="btn btn-success btn-sm"
                                data-confirm="Validate delivery and deduct stock?">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                Validate
                            </button>
                        </form>
                        <form method="POST" style="display:inline-block;">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="delivery_id" value="<?= $d['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm"
                                data-confirm="Cancel this delivery?">Cancel</button>
                        </form>
                        <?php else: ?>
                        <span style="color:var(--text3);font-size:12px;font-style:italic;">Awaiting validation</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:<?= $d['status'] === 'done' ? 'var(--green)' : 'var(--text3)' ?>;font-size:12px;">
                            <?= $d['status'] === 'done' ? '✓ Dispatched' : 'Canceled' ?>
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$deliveries): ?>
            <tr><td colspan="7">
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 5v4h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    </div>
                    <h3>No delivery orders</h3>
                    <p>Create a delivery when dispatching goods to a customer</p>
                </div>
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (can('manage_deliveries')): ?>
<div class="modal-overlay" id="deliveryModal">
    <div class="modal" style="max-width:680px;">
        <div class="modal-header">
            <h3>New Delivery Order</h3>
            <button class="modal-close" onclick="closeModal('deliveryModal')">×</button>
        </div>
        <form method="POST" id="deliveryForm" onsubmit="return validateDeliveryForm()">
            <div class="modal-body">
                <input type="hidden" name="action" value="create">
                <div class="grid-2" style="margin-bottom:16px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Customer Name</label>
                        <input type="text" name="customer_name" placeholder="e.g. ABC Company">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>From Warehouse *</label>
                        <select name="warehouse_id" required>
                            <?php foreach ($warehouses as $w): ?>
                            <option value="<?= $w['id'] ?>"><?= clean($w['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <?php echo buildItemsBlock('deliveryItems', 'Products to Deliver', 'Quantity'); ?>
                <div id="deliveryFormError" style="display:none;margin-top:12px;background:var(--red-soft);border:1px solid rgba(241,86,106,0.3);border-radius:var(--radius-sm);padding:10px 14px;font-size:13px;color:var(--red);"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('deliveryModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="deliverySubmitBtn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Create Delivery
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function validateDeliveryForm() {
    const rows = document.querySelectorAll('#deliveryItems .item-row');
    const errEl = document.getElementById('deliveryFormError');
    if (rows.length === 0) {
        errEl.style.display = 'block';
        errEl.textContent = 'Please add at least one product before creating a delivery.';
        errEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return false;
    }
    let valid = true;
    rows.forEach(row => {
        const sel = row.querySelector('.item-select');
        const qty = row.querySelector('.item-qty');
        if (!sel || !sel.value) { if (sel) sel.style.borderColor = 'var(--red)'; valid = false; }
        else { if (sel) sel.style.borderColor = ''; }
        if (!qty || !qty.value || parseFloat(qty.value) <= 0) { if (qty) qty.style.borderColor = 'var(--red)'; valid = false; }
        else { if (qty) qty.style.borderColor = ''; }
    });
    if (!valid) {
        errEl.style.display = 'block';
        errEl.textContent = 'Please select a product and enter a valid quantity for all rows.';
        errEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return false;
    }
    errEl.style.display = 'none';
    const btn = document.getElementById('deliverySubmitBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin 0.7s linear infinite;margin-right:6px;"></span>Creating...';
    }
    return true;
}

document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => { if (!confirm(el.dataset.confirm)) e.preventDefault(); });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
