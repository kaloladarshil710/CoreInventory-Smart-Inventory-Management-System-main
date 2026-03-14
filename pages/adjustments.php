<?php
require_once __DIR__ . '/../includes/config.php';
requirePermission('view_adjustments');

$db = getDB();
$pageTitle  = 'Stock Adjustments';
$activePage = 'adjustments';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CREATE — only admin (manage_adjustments)
    if ($action === 'create') {
        denyAction('manage_adjustments', '/pages/adjustments.php');
        $wid   = (int)$_POST['warehouse_id'];
        $notes = trim($_POST['notes'] ?? '');
        $ref   = generateRef('ADJ');

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
        setFlash('success', "Adjustment {$ref} created.");
        header('Location: ' . BASE_URL . '/pages/adjustments.php'); exit;
    }

    // VALIDATE — only admin (manage_adjustments)
    if ($action === 'validate') {
        denyAction('manage_adjustments', '/pages/adjustments.php');
        $aid = (int)$_POST['adjustment_id'];
        $stmt = $db->prepare("SELECT * FROM adjustments WHERE id=? AND status='draft'");
        $stmt->execute([$aid]);
        $adj = $stmt->fetch();

        if ($adj) {
            $items = $db->prepare("SELECT * FROM adjustment_items WHERE adjustment_id=?");
            $items->execute([$aid]);
            foreach ($items->fetchAll() as $item) {
                $db->prepare("INSERT INTO stock (product_id, warehouse_id, quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity)")
                   ->execute([$item['product_id'], $adj['warehouse_id'], $item['counted_quantity']]);
                $db->prepare("INSERT INTO stock_ledger (product_id, warehouse_id, operation_type, reference_id, reference_type, quantity_change, quantity_after, notes, created_by) VALUES (?,?,'adjustment',?,'adjustment',?,?,?,?)")
                   ->execute([$item['product_id'], $adj['warehouse_id'], $aid, $item['difference'], $item['counted_quantity'], 'Stock adjustment', $_SESSION['user_id']]);
            }
            $db->prepare("UPDATE adjustments SET status='done', validated_at=NOW() WHERE id=?")->execute([$aid]);
            setFlash('success', 'Adjustment applied. Stock corrected.');
        }
        header('Location: ' . BASE_URL . '/pages/adjustments.php'); exit;
    }
}

$adjustments = $db->query("SELECT a.*, w.name as warehouse_name, u.name as creator_name
    FROM adjustments a
    JOIN warehouses w ON w.id=a.warehouse_id
    LEFT JOIN users u ON u.id=a.created_by
    ORDER BY a.created_at DESC")->fetchAll();
$warehouses = $db->query("SELECT * FROM warehouses WHERE is_active=1")->fetchAll();
$products   = $db->query("SELECT * FROM products WHERE is_active=1 ORDER BY name")->fetchAll();
$productOpts = '';
foreach ($products as $p) $productOpts .= "<option value=\"{$p['id']}\">[{$p['sku']}] {$p['name']}</option>";

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Stock Adjustments</h1>
        <p>Fix mismatches between recorded and actual inventory counts</p>
    </div>
    <?php if (can('manage_adjustments')): ?>
    <button class="btn btn-primary" onclick="openModal('adjModal')">+ New Adjustment</button>
    <?php endif; ?>
</div>

<!-- Access notice for Manager/Staff -->
<?php if (!can('manage_adjustments')): ?>
<div class="flash-message flash-info" style="margin:0 0 20px;">
    <span>ℹ Stock Adjustments can only be created and validated by <strong>Admins</strong>. You have view-only access.</span>
</div>
<?php endif; ?>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Reference</th><th>Warehouse</th><th>Status</th><th>Created By</th><th>Created</th>
                <?php if (can('manage_adjustments')): ?><th>Actions</th><?php endif; ?></tr>
            </thead>
            <tbody>
            <?php foreach ($adjustments as $a): ?>
            <tr>
                <td class="td-mono td-bold"><?= clean($a['reference'] ?? '—') ?></td>
                <td><?= clean($a['warehouse_name']) ?></td>
                <td><span class="badge <?= statusColor($a['status'] ?? 'draft') ?>"><?= $a['status'] ?></span></td>
                <td><?= clean($a['creator_name'] ?? '—') ?></td>
                <td class="td-mono"><?= date('d M Y', strtotime($a['created_at'])) ?></td>
                <?php if (can('manage_adjustments')): ?>
                <td class="td-actions">
                    <?php if ($a['status'] === 'draft'): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="validate">
                        <input type="hidden" name="adjustment_id" value="<?= $a['id'] ?>">
                        <button type="submit" class="btn btn-success btn-sm" data-confirm="Apply this adjustment to stock?">✓ Apply</button>
                    </form>
                    <?php else: ?>
                    <span style="color:var(--text3);font-size:12px;">Applied <?= $a['validated_at'] ? date('d M', strtotime($a['validated_at'])) : '' ?></span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            <?php if (!$adjustments): ?>
            <tr><td colspan="<?= can('manage_adjustments') ? 6 : 5 ?>">
                <div class="empty-state">
                    <div class="empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></div>
                    <h3>No adjustments</h3><p>No stock adjustments recorded yet</p>
                </div>
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (can('manage_adjustments')): ?>
<div class="modal-overlay" id="adjModal">
    <div class="modal" style="max-width:700px">
        <div class="modal-header">
            <h3>New Stock Adjustment</h3>
            <button class="modal-close" onclick="closeModal('adjModal')">×</button>
        </div>
        <form method="POST">
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
                    <div class="form-group"><label>Notes / Reason</label><input type="text" name="notes" placeholder="Reason for adjustment..."></div>
                </div>
                <label style="margin-bottom:10px;display:block;">Products to Adjust <span style="color:var(--text3);font-size:11px;">(Enter physical count)</span></label>
                <div class="items-table-wrap" style="margin-bottom:12px;">
                    <table><thead><tr><th>Product</th><th>Counted Qty</th><th></th></tr></thead>
                    <tbody id="adjItems"></tbody></table>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" onclick="addItem('adjItems', `<?= addslashes($productOpts) ?>`)">+ Add Product</button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('adjModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Adjustment</button>
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
