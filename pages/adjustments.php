<?php
require_once __DIR__ . '/../includes/config.php';
requirePermission('view_adjustments');

$db = getDB();
$pageTitle  = 'Stock Adjustments';
$activePage = 'adjustments';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        denyAction('manage_adjustments', '/pages/adjustments.php');
        $wid  = (int)$_POST['warehouse_id'];
        $notes = trim($_POST['notes'] ?? '');
        $ref  = generateRef('ADJ');
        $db->prepare("INSERT INTO adjustments (reference, warehouse_id, notes, status, created_by) VALUES (?,?,?,'draft',?)")
           ->execute([$ref, $wid, $notes, $_SESSION['user_id']]);
        $aid = $db->lastInsertId();
        foreach ($_POST['items'] ?? [] as $item) {
            $pid     = (int)($item['product_id'] ?? 0);
            $counted = (float)($item['quantity'] ?? 0);
            if ($pid) {
                $sysStmt = $db->prepare("SELECT COALESCE(quantity,0) FROM stock WHERE product_id=? AND warehouse_id=?");
                $sysStmt->execute([$pid, $wid]);
                $sys  = (float)$sysStmt->fetchColumn();
                $diff = $counted - $sys;
                $db->prepare("INSERT INTO adjustment_items (adjustment_id, product_id, system_quantity, counted_quantity, difference) VALUES (?,?,?,?,?)")
                   ->execute([$aid, $pid, $sys, $counted, $diff]);
            }
        }
        setFlash('success', "Adjustment <strong>{$ref}</strong> created.");
        header('Location: ' . BASE_URL . '/pages/adjustments.php'); exit;
    }

    if ($action === 'validate') {
        denyAction('manage_adjustments', '/pages/adjustments.php');
        $aid  = (int)$_POST['adjustment_id'];
        $stmt = $db->prepare("SELECT * FROM adjustments WHERE id=? AND status='draft'");
        $stmt->execute([$aid]);
        $adj = $stmt->fetch();
        if ($adj) {
            $iStmt = $db->prepare("SELECT * FROM adjustment_items WHERE adjustment_id=?");
            $iStmt->execute([$aid]);
            foreach ($iStmt->fetchAll() as $item) {
                $db->prepare("INSERT INTO stock (product_id, warehouse_id, quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity)")
                   ->execute([$item['product_id'], $adj['warehouse_id'], $item['counted_quantity']]);
                $db->prepare("INSERT INTO stock_ledger (product_id,warehouse_id,operation_type,reference_id,reference_type,quantity_change,quantity_after,notes,created_by) VALUES (?,?,'adjustment',?,'adjustment',?,?,'Stock adjustment',?)")
                   ->execute([$item['product_id'], $adj['warehouse_id'], $aid, $item['difference'], $item['counted_quantity'], $_SESSION['user_id']]);
            }
            $db->prepare("UPDATE adjustments SET status='done', validated_at=NOW() WHERE id=?")->execute([$aid]);
            setFlash('success', 'Adjustment applied. Stock corrected.');
        }
        header('Location: ' . BASE_URL . '/pages/adjustments.php'); exit;
    }
}

$adjustments = $db->query("SELECT a.*, w.name as warehouse_name, u.name as creator_name FROM adjustments a JOIN warehouses w ON w.id=a.warehouse_id LEFT JOIN users u ON u.id=a.created_by ORDER BY a.created_at DESC")->fetchAll();
$warehouses  = $db->query("SELECT * FROM warehouses WHERE is_active=1 ORDER BY name")->fetchAll();
$products    = $db->query("SELECT id,name,sku,unit_of_measure FROM products WHERE is_active=1 ORDER BY name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<script>window.PRODUCT_DATA = <?= json_encode(array_values($products), JSON_HEX_TAG|JSON_HEX_QUOT) ?>;</script>

<div class="page-header">
    <div><h1>Stock Adjustments</h1><p>Fix mismatches between recorded and physical inventory counts</p></div>
    <?php if (can('manage_adjustments')): ?>
    <button class="btn btn-primary" onclick="openModal('adjModal')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Adjustment
    </button>
    <?php endif; ?>
</div>

<?php if (!can('manage_adjustments')): ?>
<div class="access-notice" style="margin-bottom:20px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span>Stock Adjustments can only be created and applied by <strong>Admins</strong>. You have view-only access.</span>
</div>
<?php endif; ?>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Reference</th><th>Warehouse</th><th>Status</th><th>Notes</th><th>Created By</th><th>Date</th><?php if (can('manage_adjustments')): ?><th>Actions</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($adjustments as $a): ?>
            <tr>
                <td class="td-mono td-bold"><?= clean($a['reference'] ?? '—') ?></td>
                <td><?= clean($a['warehouse_name']) ?></td>
                <td><span class="badge <?= statusColor($a['status'] ?? 'draft') ?>"><?= $a['status'] ?></span></td>
                <td style="color:var(--text2);font-size:13px;"><?= clean($a['notes'] ?? '—') ?></td>
                <td style="color:var(--text2);font-size:13px;"><?= clean($a['creator_name'] ?? '—') ?></td>
                <td class="td-mono"><?= date('d M Y', strtotime($a['created_at'])) ?></td>
                <?php if (can('manage_adjustments')): ?>
                <td class="td-actions">
                    <?php if ($a['status'] === 'draft'): ?>
                    <form method="POST" style="display:inline-block;">
                        <input type="hidden" name="action" value="validate">
                        <input type="hidden" name="adjustment_id" value="<?= $a['id'] ?>">
                        <button type="submit" class="btn btn-success btn-sm" data-confirm="Apply this adjustment to stock?">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Apply
                        </button>
                    </form>
                    <?php else: ?>
                    <span style="color:var(--green);font-size:12px;">✓ Applied</span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            <?php if (!$adjustments): ?>
            <tr><td colspan="<?= can('manage_adjustments') ? 7 : 6 ?>"><div class="empty-state"><div class="empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></div><h3>No adjustments yet</h3></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (can('manage_adjustments')): ?>
<div class="modal-overlay" id="adjModal">
    <div class="modal" style="max-width:660px;">
        <div class="modal-header">
            <h3>New Stock Adjustment</h3>
            <button class="modal-close" onclick="resetAndClose('adjModal','adjItems')">×</button>
        </div>
        <form method="POST" id="adjForm" onsubmit="return validateItemForm('adjItems','adjErr')">
            <div class="modal-body">
                <input type="hidden" name="action" value="create">
                <div class="grid-2">
                    <div class="form-group">
                        <label>Warehouse *</label>
                        <select name="warehouse_id" required>
                            <?php foreach ($warehouses as $w): ?>
                            <option value="<?= $w['id'] ?>"><?= clean($w['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Reason / Notes</label>
                        <input type="text" name="notes" placeholder="e.g. Monthly count, damaged goods...">
                    </div>
                </div>
                <?php echo buildItemsBlock('adjItems', 'Products to Adjust', 'Physical Count'); ?>
                <div class="form-helper" style="margin-top:6px;">Enter the <strong>actual physical count</strong> you found. The system will calculate the difference automatically.</div>
                <div id="adjErr" class="form-item-error" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="resetAndClose('adjModal','adjItems')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Adjustment</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
