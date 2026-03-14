<?php
require_once __DIR__ . '/../includes/config.php';
requirePermission('view_receipts');

$db = getDB();
$pageTitle  = 'Receipts';
$activePage = 'receipts';

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
        foreach ($_POST['items'] ?? [] as $item) {
            $pid = (int)($item['product_id'] ?? 0);
            $qty = (float)($item['quantity'] ?? 0);
            if ($pid && $qty > 0) {
                $db->prepare("INSERT INTO receipt_items (receipt_id, product_id, quantity_expected, quantity_received) VALUES (?,?,?,0)")
                   ->execute([$rid, $pid, $qty]);
            }
        }
        setFlash('success', "Receipt <strong>{$ref}</strong> created.");
        header('Location: ' . BASE_URL . '/pages/receipts.php'); exit;
    }

    if ($action === 'validate') {
        denyAction('validate_receipts', '/pages/receipts.php');
        $rid  = (int)$_POST['receipt_id'];
        $stmt = $db->prepare("SELECT * FROM receipts WHERE id=? AND status NOT IN ('done','canceled')");
        $stmt->execute([$rid]);
        $receipt = $stmt->fetch();
        if ($receipt) {
            $iStmt = $db->prepare("SELECT * FROM receipt_items WHERE receipt_id=?");
            $iStmt->execute([$rid]);
            foreach ($iStmt->fetchAll() as $item) {
                $qty = (float)($_POST['received'][$item['id']] ?? $item['quantity_expected']);
                if ($qty > 0) {
                    $db->prepare("INSERT INTO stock (product_id, warehouse_id, quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity)")
                       ->execute([$item['product_id'], $receipt['warehouse_id'], $qty]);
                    $db->prepare("UPDATE receipt_items SET quantity_received=? WHERE id=?")->execute([$qty, $item['id']]);
                    $nq = $db->prepare("SELECT quantity FROM stock WHERE product_id=? AND warehouse_id=?");
                    $nq->execute([$item['product_id'], $receipt['warehouse_id']]);
                    $newQty = $nq->fetchColumn();
                    $db->prepare("INSERT INTO stock_ledger (product_id, warehouse_id, operation_type, reference_id, reference_type, quantity_change, quantity_after, notes, created_by) VALUES (?,?,'receipt',?,'receipt',?,?,'',?)")
                       ->execute([$item['product_id'], $receipt['warehouse_id'], $rid, $qty, $newQty, $_SESSION['user_id']]);
                }
            }
            $db->prepare("UPDATE receipts SET status='done', validated_at=NOW() WHERE id=?")->execute([$rid]);
            setFlash('success', 'Receipt validated. Stock updated.');
        }
        header('Location: ' . BASE_URL . '/pages/receipts.php'); exit;
    }

    if ($action === 'cancel') {
        denyAction('validate_receipts', '/pages/receipts.php');
        $db->prepare("UPDATE receipts SET status='canceled' WHERE id=? AND status='draft'")->execute([(int)$_POST['receipt_id']]);
        setFlash('success', 'Receipt canceled.');
        header('Location: ' . BASE_URL . '/pages/receipts.php'); exit;
    }
}

$statusFilter = $_GET['status'] ?? '';
$where = ''; $params = [];
if ($statusFilter) { $where = "WHERE r.status=?"; $params[] = $statusFilter; }

$receipts   = $db->prepare("SELECT r.*, w.name as warehouse_name, u.name as creator_name FROM receipts r JOIN warehouses w ON w.id=r.warehouse_id LEFT JOIN users u ON u.id=r.created_by $where ORDER BY r.created_at DESC");
$receipts->execute($params);
$receipts   = $receipts->fetchAll();
$warehouses = $db->query("SELECT * FROM warehouses WHERE is_active=1 ORDER BY name")->fetchAll();
$products   = $db->query("SELECT id,name,sku,unit_of_measure FROM products WHERE is_active=1 ORDER BY name")->fetchAll();
$pendingItems = $db->query("SELECT ri.*, p.name as pname, p.sku, p.unit_of_measure, r.id as rid FROM receipt_items ri JOIN products p ON p.id=ri.product_id JOIN receipts r ON r.id=ri.receipt_id WHERE r.status NOT IN ('done','canceled')")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<script>window.PRODUCT_DATA = <?= json_encode(array_values($products), JSON_HEX_TAG|JSON_HEX_QUOT) ?>;</script>

<div class="page-header">
    <div><h1>Receipts — Incoming Stock</h1><p>Track goods received from suppliers</p></div>
    <div style="display:flex;gap:10px;align-items:center;">
        <form method="GET" style="display:flex;gap:8px;">
            <select name="status" onchange="this.form.submit()" style="width:auto;min-width:130px;padding:9px 14px;font-size:13px;">
                <option value="">All Statuses</option>
                <?php foreach (['draft','waiting','ready','done','canceled'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($statusFilter): ?><a href="<?= BASE_URL ?>/pages/receipts.php" class="btn btn-ghost btn-sm">Clear</a><?php endif; ?>
        </form>
        <button class="btn btn-primary" onclick="openModal('receiptModal')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Receipt
        </button>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Reference</th><th>Supplier</th><th>Warehouse</th><th>Status</th><th>Created By</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($receipts as $r): ?>
            <tr>
                <td class="td-mono td-bold"><?= clean($r['reference']) ?></td>
                <td><?= clean($r['supplier_name'] ?? '—') ?></td>
                <td><?= clean($r['warehouse_name']) ?></td>
                <td><span class="badge <?= statusColor($r['status']) ?>"><?= $r['status'] ?></span></td>
                <td style="color:var(--text2);font-size:13px;"><?= clean($r['creator_name'] ?? '—') ?></td>
                <td class="td-mono"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                <td class="td-actions">
                    <?php if (in_array($r['status'], ['draft','waiting','ready'])): ?>
                        <?php if (can('validate_receipts')): ?>
                        <button class="btn btn-success btn-sm" onclick="openValidate(<?= $r['id'] ?>, '<?= clean($r['reference']) ?>')">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Validate
                        </button>
                        <form method="POST" style="display:inline-block;margin-left:4px;">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="receipt_id" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm" data-confirm="Cancel this receipt?">Cancel</button>
                        </form>
                        <?php else: ?>
                        <span style="color:var(--text3);font-size:12px;font-style:italic;">Awaiting validation</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:<?= $r['status']==='done'?'var(--green)':'var(--text3)' ?>;font-size:12px;">
                            <?= $r['status']==='done' ? '✓ Validated '.date('d M',strtotime($r['validated_at']?:$r['created_at'])) : 'Canceled' ?>
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$receipts): ?>
            <tr><td colspan="7"><div class="empty-state"><div class="empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></div><h3>No receipts found</h3><p>Create a receipt when goods arrive from a supplier</p></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Create Receipt Modal ── -->
<div class="modal-overlay" id="receiptModal">
    <div class="modal" style="max-width:660px;">
        <div class="modal-header">
            <h3>New Receipt</h3>
            <button class="modal-close" onclick="resetAndClose('receiptModal', 'receiptItems')">×</button>
        </div>
        <form method="POST" id="receiptForm" onsubmit="return validateItemForm('receiptItems','receiptErr')">
            <div class="modal-body">
                <input type="hidden" name="action" value="create">
                <div class="grid-2">
                    <div class="form-group">
                        <label>Supplier Name</label>
                        <input type="text" name="supplier_name" placeholder="e.g. Tech Supplier">
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
                    <textarea name="notes" rows="2" placeholder="Optional notes..."></textarea>
                </div>
                <?php echo buildItemsBlock('receiptItems', 'Products to Receive', 'Expected Qty'); ?>
                <div id="receiptErr" class="form-item-error" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="resetAndClose('receiptModal','receiptItems')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Create Receipt
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Validate Receipt Modal ── -->
<?php if (can('validate_receipts')): ?>
<div class="modal-overlay" id="validateModal">
    <div class="modal" style="max-width:580px;">
        <div class="modal-header">
            <h3>Validate Receipt</h3>
            <button class="modal-close" onclick="closeModal('validateModal')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="validate">
            <input type="hidden" name="receipt_id" id="validateReceiptId">
            <div class="modal-body" id="validateBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('validateModal')">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Confirm & Validate
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
const _pendingItems = <?= json_encode($pendingItems) ?>;

function openValidate(id, ref) {
    document.getElementById('validateReceiptId').value = id;
    const items = _pendingItems.filter(i => i.rid == id);
    const body  = document.getElementById('validateBody');
    if (!items.length) {
        body.innerHTML = '<div class="empty-state"><p>No items on this receipt.</p></div>';
    } else {
        let html = `<p style="margin-bottom:16px;color:var(--text2);font-size:13.5px;">Confirm actual quantities received for <strong style="color:var(--text)">${ref}</strong>:</p>`;
        html += `<div class="validate-table">
            <div class="validate-head">
                <span>Product</span><span>Expected</span><span>Received Qty</span>
            </div>`;
        items.forEach(i => {
            html += `<div class="validate-row">
                <div>
                    <div style="font-weight:600;color:var(--text);font-size:13.5px;">${i.pname}</div>
                    <div style="font-size:11px;color:var(--text3);font-family:var(--font-mono);">${i.sku}</div>
                </div>
                <div style="color:var(--text2);font-size:13px;">${parseFloat(i.quantity_expected).toFixed(2)} <span style="color:var(--text3)">${i.unit_of_measure}</span></div>
                <div>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <input type="number" name="received[${i.id}]" value="${i.quantity_expected}"
                            min="0" step="0.01" class="validate-qty-input">
                        <span class="item-uom-tag">${i.unit_of_measure}</span>
                    </div>
                </div>
            </div>`;
        });
        html += '</div>';
        body.innerHTML = html;
    }
    openModal('validateModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
