<?php
require_once __DIR__ . '/../includes/config.php';
requirePermission('view_transfers');

$db = getDB();
$pageTitle  = 'Internal Transfers';
$activePage = 'transfers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $fromWH = (int)$_POST['from_warehouse_id'];
        $toWH   = (int)$_POST['to_warehouse_id'];
        $notes  = trim($_POST['notes'] ?? '');
        $ref    = generateRef('TRF');
        if ($fromWH === $toWH) {
            setFlash('error', 'Source and destination warehouse must be different.');
            header('Location: ' . BASE_URL . '/pages/transfers.php'); exit;
        }
        $db->prepare("INSERT INTO transfers (reference, from_warehouse_id, to_warehouse_id, notes, status, created_by) VALUES (?,?,?,?,'draft',?)")
           ->execute([$ref, $fromWH, $toWH, $notes, $_SESSION['user_id']]);
        $tid = $db->lastInsertId();
        foreach ($_POST['items'] ?? [] as $item) {
            $pid = (int)($item['product_id'] ?? 0);
            $qty = (float)($item['quantity'] ?? 0);
            if ($pid && $qty > 0)
                $db->prepare("INSERT INTO transfer_items (transfer_id, product_id, quantity) VALUES (?,?,?)")->execute([$tid, $pid, $qty]);
        }
        setFlash('success', "Transfer <strong>{$ref}</strong> created.");
        header('Location: ' . BASE_URL . '/pages/transfers.php'); exit;
    }

    if ($action === 'validate') {
        denyAction('validate_transfers', '/pages/transfers.php');
        $tid  = (int)$_POST['transfer_id'];
        $stmt = $db->prepare("SELECT * FROM transfers WHERE id=? AND status NOT IN ('done','canceled')");
        $stmt->execute([$tid]);
        $transfer = $stmt->fetch();
        if ($transfer) {
            $iStmt = $db->prepare("SELECT * FROM transfer_items WHERE transfer_id=?");
            $iStmt->execute([$tid]);
            foreach ($iStmt->fetchAll() as $item) {
                $db->prepare("UPDATE stock SET quantity=quantity-? WHERE product_id=? AND warehouse_id=?")
                   ->execute([$item['quantity'], $item['product_id'], $transfer['from_warehouse_id']]);
                $db->prepare("INSERT INTO stock (product_id, warehouse_id, quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity)")
                   ->execute([$item['product_id'], $transfer['to_warehouse_id'], $item['quantity']]);
                $fNew = $db->prepare("SELECT COALESCE(quantity,0) FROM stock WHERE product_id=? AND warehouse_id=?");
                $fNew->execute([$item['product_id'], $transfer['from_warehouse_id']]);
                $tNew = $db->prepare("SELECT COALESCE(quantity,0) FROM stock WHERE product_id=? AND warehouse_id=?");
                $tNew->execute([$item['product_id'], $transfer['to_warehouse_id']]);
                $db->prepare("INSERT INTO stock_ledger (product_id,warehouse_id,operation_type,reference_id,reference_type,quantity_change,quantity_after,created_by) VALUES (?,?,'transfer_out',?,'transfer',?,?,?)")
                   ->execute([$item['product_id'], $transfer['from_warehouse_id'], $tid, -$item['quantity'], $fNew->fetchColumn(), $_SESSION['user_id']]);
                $db->prepare("INSERT INTO stock_ledger (product_id,warehouse_id,operation_type,reference_id,reference_type,quantity_change,quantity_after,created_by) VALUES (?,?,'transfer_in',?,'transfer',?,?,?)")
                   ->execute([$item['product_id'], $transfer['to_warehouse_id'], $tid, $item['quantity'], $tNew->fetchColumn(), $_SESSION['user_id']]);
            }
            $db->prepare("UPDATE transfers SET status='done', validated_at=NOW() WHERE id=?")->execute([$tid]);
            setFlash('success', 'Transfer completed. Stock moved.');
        }
        header('Location: ' . BASE_URL . '/pages/transfers.php'); exit;
    }
}

$transfers  = $db->query("SELECT t.*, wf.name as from_name, wt.name as to_name, u.name as creator_name FROM transfers t JOIN warehouses wf ON wf.id=t.from_warehouse_id JOIN warehouses wt ON wt.id=t.to_warehouse_id LEFT JOIN users u ON u.id=t.created_by ORDER BY t.created_at DESC")->fetchAll();
$warehouses = $db->query("SELECT * FROM warehouses WHERE is_active=1 ORDER BY name")->fetchAll();
$products   = $db->query("SELECT id,name,sku,unit_of_measure FROM products WHERE is_active=1 ORDER BY name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<script>window.PRODUCT_DATA = <?= json_encode(array_values($products), JSON_HEX_TAG|JSON_HEX_QUOT) ?>;</script>

<div class="page-header">
    <div><h1>Internal Transfers</h1><p>Move stock between warehouses and locations</p></div>
    <button class="btn btn-primary" onclick="openModal('transferModal')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Transfer
    </button>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Reference</th><th>From</th><th>To</th><th>Status</th><th>Created By</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($transfers as $t): ?>
            <tr>
                <td class="td-mono td-bold"><?= clean($t['reference']) ?></td>
                <td><?= clean($t['from_name']) ?></td>
                <td><?= clean($t['to_name']) ?></td>
                <td><span class="badge <?= statusColor($t['status']) ?>"><?= $t['status'] ?></span></td>
                <td style="color:var(--text2);font-size:13px;"><?= clean($t['creator_name'] ?? '—') ?></td>
                <td class="td-mono"><?= date('d M Y', strtotime($t['created_at'])) ?></td>
                <td class="td-actions">
                    <?php if (in_array($t['status'], ['draft','waiting','ready'])): ?>
                        <?php if (can('validate_transfers')): ?>
                        <form method="POST" style="display:inline-block;">
                            <input type="hidden" name="action" value="validate">
                            <input type="hidden" name="transfer_id" value="<?= $t['id'] ?>">
                            <button type="submit" class="btn btn-success btn-sm" data-confirm="Execute this transfer and move stock?">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                Execute
                            </button>
                        </form>
                        <?php else: ?>
                        <span style="color:var(--text3);font-size:12px;font-style:italic;">Awaiting validation</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:var(--green);font-size:12px;">✓ Completed</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$transfers): ?>
            <tr><td colspan="7"><div class="empty-state"><div class="empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg></div><h3>No transfers yet</h3><p>Create a transfer to move stock between warehouses</p></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── New Transfer Modal ── -->
<div class="modal-overlay" id="transferModal">
    <div class="modal" style="max-width:660px;">
        <div class="modal-header">
            <h3>New Internal Transfer</h3>
            <button class="modal-close" onclick="resetAndClose('transferModal','transferItems')">×</button>
        </div>
        <form method="POST" id="transferForm" onsubmit="return validateItemForm('transferItems','transferErr')">
            <div class="modal-body">
                <input type="hidden" name="action" value="create">
                <div class="grid-2">
                    <div class="form-group">
                        <label>From Warehouse *</label>
                        <select name="from_warehouse_id" required id="fromWH">
                            <?php foreach ($warehouses as $w): ?>
                            <option value="<?= $w['id'] ?>"><?= clean($w['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>To Warehouse *</label>
                        <select name="to_warehouse_id" required id="toWH">
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
                <?php echo buildItemsBlock('transferItems', 'Products to Transfer', 'Quantity'); ?>
                <div id="transferErr" class="form-item-error" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="resetAndClose('transferModal','transferItems')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                    Create Transfer
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
