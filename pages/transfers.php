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
            header('Location: ' . BASE_URL . '/pages/transfers.php');
            exit;
        }

        $db->prepare("INSERT INTO transfers (reference, from_warehouse_id, to_warehouse_id, notes, status, created_by) VALUES (?,?,?,?,'draft',?)")
           ->execute([$ref, $fromWH, $toWH, $notes, $_SESSION['user_id']]);
        $tid = $db->lastInsertId();

        foreach ($_POST['items'] ?? [] as $item) {
            $pid = (int)($item['product_id'] ?? 0);
            $qty = (float)($item['quantity'] ?? 0);
            if ($pid && $qty > 0) {
                $db->prepare("INSERT INTO transfer_items (transfer_id, product_id, quantity) VALUES (?,?,?)")
                   ->execute([$tid, $pid, $qty]);
            }
        }
        setFlash('success', "Transfer {$ref} created.");
        header('Location: ' . BASE_URL . '/pages/transfers.php');
        exit;
    }

    if ($action === 'validate') {
        if (!can('validate_transfers')) { setFlash('error','Access denied.'); header('Location: '.BASE_URL.'/pages/transfers.php'); exit; }
        $tid = (int)$_POST['transfer_id'];
        $stmt = $db->prepare("SELECT * FROM transfers WHERE id=? AND status NOT IN ('done','canceled')");
        $stmt->execute([$tid]);
        $transfer = $stmt->fetch();

        if ($transfer) {
            $items = $db->prepare("SELECT * FROM transfer_items WHERE transfer_id=?");
            $items->execute([$tid]);
            foreach ($items->fetchAll() as $item) {
                // Deduct from source
                $avail = (float)$db->prepare("SELECT COALESCE(quantity,0) FROM stock WHERE product_id=? AND warehouse_id=?")->execute([$item['product_id'], $transfer['from_warehouse_id']]) ?: 0;
                $checkStmt = $db->prepare("SELECT COALESCE(quantity,0) FROM stock WHERE product_id=? AND warehouse_id=?");
                $checkStmt->execute([$item['product_id'], $transfer['from_warehouse_id']]);
                $avail = (float)$checkStmt->fetchColumn();

                $db->prepare("UPDATE stock SET quantity=quantity-? WHERE product_id=? AND warehouse_id=?")
                   ->execute([$item['quantity'], $item['product_id'], $transfer['from_warehouse_id']]);

                // Add to destination
                $db->prepare("INSERT INTO stock (product_id, warehouse_id, quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity)")
                   ->execute([$item['product_id'], $transfer['to_warehouse_id'], $item['quantity']]);

                // Log both sides
                $fromNew = $db->prepare("SELECT quantity FROM stock WHERE product_id=? AND warehouse_id=?");
                $fromNew->execute([$item['product_id'], $transfer['from_warehouse_id']]);
                $fromNew = $fromNew->fetchColumn();

                $toNew = $db->prepare("SELECT quantity FROM stock WHERE product_id=? AND warehouse_id=?");
                $toNew->execute([$item['product_id'], $transfer['to_warehouse_id']]);
                $toNew = $toNew->fetchColumn();

                $db->prepare("INSERT INTO stock_ledger (product_id, warehouse_id, operation_type, reference_id, reference_type, quantity_change, quantity_after, created_by) VALUES (?,?,'transfer_out',?,'transfer',?,?,?)")
                   ->execute([$item['product_id'], $transfer['from_warehouse_id'], $tid, -$item['quantity'], $fromNew, $_SESSION['user_id']]);
                $db->prepare("INSERT INTO stock_ledger (product_id, warehouse_id, operation_type, reference_id, reference_type, quantity_change, quantity_after, created_by) VALUES (?,?,'transfer_in',?,'transfer',?,?,?)")
                   ->execute([$item['product_id'], $transfer['to_warehouse_id'], $tid, $item['quantity'], $toNew, $_SESSION['user_id']]);
            }
            $db->prepare("UPDATE transfers SET status='done', validated_at=NOW() WHERE id=?")->execute([$tid]);
            setFlash('success', 'Transfer completed. Stock moved.');
        }
        header('Location: ' . BASE_URL . '/pages/transfers.php');
        exit;
    }
}

$transfers  = $db->query("SELECT t.*, wf.name as from_name, wt.name as to_name FROM transfers t JOIN warehouses wf ON wf.id=t.from_warehouse_id JOIN warehouses wt ON wt.id=t.to_warehouse_id ORDER BY t.created_at DESC")->fetchAll();
$warehouses = $db->query("SELECT * FROM warehouses WHERE is_active=1")->fetchAll();
$products   = $db->query("SELECT * FROM products WHERE is_active=1 ORDER BY name")->fetchAll();
$productOpts = '';
foreach ($products as $p) $productOpts .= "<option value=\"{$p['id']}\">[{$p['sku']}] {$p['name']}</option>";

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div><h1>Internal Transfers</h1><p>Move stock between warehouses and locations</p></div>
    <button class="btn btn-primary" onclick="openModal('transferModal')">+ New Transfer</button>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Reference</th><th>From</th><th>To</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($transfers as $t): ?>
            <tr>
                <td class="td-mono td-bold"><?= clean($t['reference']) ?></td>
                <td><?= clean($t['from_name']) ?></td>
                <td><?= clean($t['to_name']) ?></td>
                <td><span class="badge <?= statusColor($t['status']) ?>"><?= $t['status'] ?></span></td>
                <td class="td-mono"><?= date('d M Y', strtotime($t['created_at'])) ?></td>
                <td>
                    <?php if (in_array($t['status'], ['draft','waiting','ready'])): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="validate">
                        <input type="hidden" name="transfer_id" value="<?= $t['id'] ?>">
                        <button type="submit" class="btn btn-success btn-sm" data-confirm="Execute this transfer?">✓ Execute</button>
                    </form>
                    <?php else: ?>
                    <span style="color:var(--text3);font-size:12px;">Completed</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$transfers): ?>
            <tr><td colspan="6"><div class="empty-state"><h3>No transfers yet</h3></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="transferModal">
    <div class="modal" style="max-width:680px">
        <div class="modal-header">
            <h3>New Internal Transfer</h3>
            <button class="modal-close" onclick="closeModal('transferModal')">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="create">
                <div class="grid-2">
                    <div class="form-group">
                        <label>From Warehouse *</label>
                        <select name="from_warehouse_id" required>
                            <?php foreach ($warehouses as $w): ?>
                            <option value="<?= $w['id'] ?>"><?= clean($w['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>To Warehouse *</label>
                        <select name="to_warehouse_id" required>
                            <?php foreach ($warehouses as $w): ?>
                            <option value="<?= $w['id'] ?>"><?= clean($w['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
                <label style="margin-bottom:10px;display:block;">Products to Transfer</label>
                <div class="items-table-wrap" style="margin-bottom:12px;">
                    <table><thead><tr><th>Product</th><th>Quantity</th><th></th></tr></thead>
                    <tbody id="transferItems"></tbody></table>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" onclick="addItem('transferItems', `<?= addslashes($productOpts) ?>`)">+ Add Product</button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('transferModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Transfer</button>
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
