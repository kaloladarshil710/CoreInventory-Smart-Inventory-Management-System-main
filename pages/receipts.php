<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$db = getDB();
$pageTitle  = 'Receipts';
$activePage = 'receipts';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $supplier    = trim($_POST['supplier_name'] ?? '');
        $warehouseId = (int)$_POST['warehouse_id'];
        $notes       = trim($_POST['notes'] ?? '');
        $ref         = generateRef('RCT');

        $db->prepare("INSERT INTO receipts (reference, supplier_name, warehouse_id, notes, status, created_by) VALUES (?,?,?,?,'draft',?)")
           ->execute([$ref, $supplier, $warehouseId, $notes, $_SESSION['user_id']]);
        $rid = $db->lastInsertId();

        $items = $_POST['items'] ?? [];
        foreach ($items as $item) {
            $pid = (int)($item['product_id'] ?? 0);
            $qty = (float)($item['quantity'] ?? 0);
            if ($pid && $qty > 0) {
                $db->prepare("INSERT INTO receipt_items (receipt_id, product_id, quantity_expected, quantity_received) VALUES (?,?,?,0)")
                   ->execute([$rid, $pid, $qty]);
            }
        }
        setFlash('success', "Receipt {$ref} created.");
        header('Location: ' . BASE_URL . '/pages/receipts.php');
        exit;
    }

    if ($action === 'validate') {
        $rid = (int)$_POST['receipt_id'];
        $receipt = $db->prepare("SELECT * FROM receipts WHERE id=? AND status != 'done'")->execute([$rid]) && ($r = $db->prepare("SELECT * FROM receipts WHERE id=?")->execute([$rid]));
        $stmt = $db->prepare("SELECT * FROM receipts WHERE id=? AND status NOT IN ('done','canceled')");
        $stmt->execute([$rid]);
        $receipt = $stmt->fetch();

        if ($receipt) {
            $items = $db->prepare("SELECT * FROM receipt_items WHERE receipt_id=?");
            $items->execute([$rid]);
            $items = $items->fetchAll();

            $receivedData = $_POST['received'] ?? [];
            foreach ($items as $item) {
                $qty = (float)($receivedData[$item['id']] ?? $item['quantity_expected']);
                if ($qty > 0) {
                    // Update stock
                    $db->prepare("INSERT INTO stock (product_id, warehouse_id, quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity)")
                       ->execute([$item['product_id'], $receipt['warehouse_id'], $qty]);

                    // Update received qty
                    $db->prepare("UPDATE receipt_items SET quantity_received=? WHERE id=?")->execute([$qty, $item['id']]);

                    // Get new total for ledger
                    $newQty = $db->prepare("SELECT quantity FROM stock WHERE product_id=? AND warehouse_id=?");
                    $newQty->execute([$item['product_id'], $receipt['warehouse_id']]);
                    $newQty = $newQty->fetchColumn();

                    // Log
                    $db->prepare("INSERT INTO stock_ledger (product_id, warehouse_id, operation_type, reference_id, reference_type, quantity_change, quantity_after, notes, created_by) VALUES (?,'receipt',?,?,?,?,?,'',?)")
                       ->execute([$item['product_id'], $receipt['warehouse_id'], 'receipt', $rid, 'receipt', $qty, $newQty, $_SESSION['user_id']]);
                }
            }
            $db->prepare("UPDATE receipts SET status='done', validated_at=NOW() WHERE id=?")->execute([$rid]);
            setFlash('success', "Receipt validated. Stock updated.");
        }
        header('Location: ' . BASE_URL . '/pages/receipts.php');
        exit;
    }

    if ($action === 'cancel') {
        $db->prepare("UPDATE receipts SET status='canceled' WHERE id=? AND status='draft'")->execute([(int)$_POST['receipt_id']]);
        setFlash('success', 'Receipt canceled.');
        header('Location: ' . BASE_URL . '/pages/receipts.php');
        exit;
    }
}

$statusFilter = $_GET['status'] ?? '';
$where = '';
$params = [];
if ($statusFilter) { $where = "WHERE status=?"; $params[] = $statusFilter; }

$receipts = $db->prepare("SELECT r.*, w.name as warehouse_name FROM receipts r JOIN warehouses w ON w.id=r.warehouse_id $where ORDER BY r.created_at DESC");
$receipts->execute($params);
$receipts = $receipts->fetchAll();

$warehouses = $db->query("SELECT * FROM warehouses WHERE is_active=1")->fetchAll();
$products   = $db->query("SELECT * FROM products WHERE is_active=1 ORDER BY name")->fetchAll();

$productOpts = '';
foreach ($products as $p) $productOpts .= "<option value=\"{$p['id']}\">[{$p['sku']}] {$p['name']}</option>";

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Receipts — Incoming Stock</h1>
        <p>Track goods received from suppliers</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('receiptModal')">+ New Receipt</button>
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:14px 20px;">
        <form method="GET" class="filters-bar">
            <select name="status" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach (['draft','waiting','ready','done','canceled'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <a href="<?= BASE_URL ?>/pages/receipts.php" class="btn btn-ghost">Clear</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Reference</th><th>Supplier</th><th>Warehouse</th><th>Status</th><th>Created</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($receipts as $r): ?>
            <tr>
                <td class="td-mono td-bold"><?= clean($r['reference']) ?></td>
                <td><?= clean($r['supplier_name'] ?? '—') ?></td>
                <td><?= clean($r['warehouse_name']) ?></td>
                <td><span class="badge <?= statusColor($r['status']) ?>"><?= $r['status'] ?></span></td>
                <td class="td-mono"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                <td>
                    <?php if (in_array($r['status'], ['draft','waiting','ready'])): ?>
                    <button class="btn btn-success btn-sm" onclick="openValidate(<?= $r['id'] ?>, '<?= clean($r['reference']) ?>')">✓ Validate</button>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="receipt_id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" data-confirm="Cancel this receipt?">Cancel</button>
                    </form>
                    <?php else: ?>
                    <span style="color:var(--text3);font-size:12px;"><?= $r['validated_at'] ? date('d M Y', strtotime($r['validated_at'])) : '—' ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$receipts): ?>
            <tr><td colspan="6"><div class="empty-state"><h3>No receipts found</h3><p>Create a receipt when goods arrive from a supplier</p></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Receipt Modal -->
<div class="modal-overlay" id="receiptModal">
    <div class="modal" style="max-width:680px">
        <div class="modal-header">
            <h3>New Receipt</h3>
            <button class="modal-close" onclick="closeModal('receiptModal')">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="create">
                <div class="grid-2">
                    <div class="form-group">
                        <label>Supplier Name</label>
                        <input type="text" name="supplier_name" placeholder="Vendor / Supplier">
                    </div>
                    <div class="form-group">
                        <label>Receiving Warehouse *</label>
                        <select name="warehouse_id" required>
                            <?php foreach ($warehouses as $w): ?>
                            <option value="<?= $w['id'] ?>"><?= clean($w['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="2"></textarea>
                </div>
                <label style="margin-bottom:10px;display:block;">Products to Receive</label>
                <div class="items-table-wrap" style="margin-bottom:12px;">
                    <table>
                        <thead><tr><th>Product</th><th>Expected Qty</th><th></th></tr></thead>
                        <tbody id="receiptItems"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" onclick="addItem('receiptItems', `<?= addslashes($productOpts) ?>`)">+ Add Product</button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('receiptModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Receipt</button>
            </div>
        </form>
    </div>
</div>

<!-- Validate Receipt Modal -->
<div class="modal-overlay" id="validateModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Validate Receipt</h3>
            <button class="modal-close" onclick="closeModal('validateModal')">×</button>
        </div>
        <form method="POST" id="validateForm">
            <input type="hidden" name="action" value="validate">
            <input type="hidden" name="receipt_id" id="validateReceiptId">
            <div class="modal-body" id="validateBody">
                <p style="color:var(--text2)">Loading items...</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('validateModal')">Cancel</button>
                <button type="submit" class="btn btn-success">✓ Confirm & Validate</button>
            </div>
        </form>
    </div>
</div>

<script>
const receiptItems = <?= json_encode($db->query("
    SELECT ri.*, p.name as pname, p.sku, p.unit_of_measure,
           r.id as rid
    FROM receipt_items ri
    JOIN products p ON p.id=ri.product_id
    JOIN receipts r ON r.id=ri.receipt_id
    WHERE r.status NOT IN ('done','canceled')
")->fetchAll()) ?>;

function openValidate(id, ref) {
    document.getElementById('validateReceiptId').value = id;
    const items = receiptItems.filter(i => i.rid == id);
    if (!items.length) {
        document.getElementById('validateBody').innerHTML = '<p style="color:var(--text3)">No items on this receipt.</p>';
    } else {
        let html = `<p style="margin-bottom:14px;color:var(--text2)">Confirm quantities received for <strong>${ref}</strong>:</p>
        <table><thead><tr><th>Product</th><th>Expected</th><th>Received Qty</th></tr></thead><tbody>`;
        items.forEach(i => {
            html += `<tr>
                <td>${i.pname} <span style="color:var(--text3);font-size:11px;">${i.sku}</span></td>
                <td>${i.quantity_expected} ${i.unit_of_measure}</td>
                <td><input type="number" name="received[${i.id}]" value="${i.quantity_expected}" min="0" step="0.01"
                    style="width:100px;background:var(--bg3);border:1px solid var(--border);border-radius:4px;color:var(--text);padding:6px 10px;"></td>
            </tr>`;
        });
        html += '</tbody></table>';
        document.getElementById('validateBody').innerHTML = html;
    }
    openModal('validateModal');
}

document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => { if (!confirm(el.dataset.confirm)) e.preventDefault(); });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
