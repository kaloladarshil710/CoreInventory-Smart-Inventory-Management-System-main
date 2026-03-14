<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$db = getDB();
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

// KPI Queries
$totalProducts = $db->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn();

$lowStock = $db->query("
    SELECT COUNT(DISTINCT p.id) FROM products p
    JOIN stock s ON s.product_id = p.id
    WHERE p.is_active=1
    GROUP BY p.id
    HAVING SUM(s.quantity) <= p.reorder_level AND SUM(s.quantity) > 0
")->fetchColumn();

$outOfStock = $db->query("
    SELECT COUNT(*) FROM products p
    WHERE is_active=1
    AND (SELECT COALESCE(SUM(quantity),0) FROM stock WHERE product_id=p.id) = 0
")->fetchColumn();

$pendingReceipts   = $db->query("SELECT COUNT(*) FROM receipts WHERE status IN ('draft','waiting','ready')")->fetchColumn();
$pendingDeliveries = $db->query("SELECT COUNT(*) FROM deliveries WHERE status IN ('draft','waiting','ready')")->fetchColumn();
$pendingTransfers  = $db->query("SELECT COUNT(*) FROM transfers WHERE status IN ('draft','waiting','ready')")->fetchColumn();

// Recent Operations
$recentOps = $db->query("
    SELECT 'receipt' as type, reference, status, created_at, supplier_name as party FROM receipts
    UNION ALL
    SELECT 'delivery', reference, status, created_at, customer_name FROM deliveries
    UNION ALL
    SELECT 'transfer', reference, status, created_at, CONCAT('WH Transfer') FROM transfers
    ORDER BY created_at DESC LIMIT 10
")->fetchAll();

// Low stock products
$lowStockProds = $db->query("
    SELECT p.name, p.sku, p.unit_of_measure, p.reorder_level, COALESCE(SUM(s.quantity),0) as qty
    FROM products p
    LEFT JOIN stock s ON s.product_id = p.id
    WHERE p.is_active=1
    GROUP BY p.id
    HAVING qty <= p.reorder_level
    ORDER BY qty ASC
    LIMIT 8
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="kpi-grid">
    <div class="kpi-card" style="--kpi-color:var(--accent)">
        <div class="kpi-icon">
            <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
        </div>
        <div class="kpi-label">Total Products</div>
        <div class="kpi-value"><?= $totalProducts ?></div>
        <div class="kpi-sub">Active SKUs in system</div>
    </div>
    <div class="kpi-card" style="--kpi-color:var(--orange)">
        <div class="kpi-label">Low Stock</div>
        <div class="kpi-value"><?= $lowStock ?></div>
        <div class="kpi-sub">Below reorder level</div>
    </div>
    <div class="kpi-card" style="--kpi-color:var(--red)">
        <div class="kpi-label">Out of Stock</div>
        <div class="kpi-value"><?= $outOfStock ?></div>
        <div class="kpi-sub">Zero quantity</div>
    </div>
    <div class="kpi-card" style="--kpi-color:var(--green)">
        <div class="kpi-label">Pending Receipts</div>
        <div class="kpi-value"><?= $pendingReceipts ?></div>
        <div class="kpi-sub">Awaiting validation</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#a78bfa">
        <div class="kpi-label">Pending Deliveries</div>
        <div class="kpi-value"><?= $pendingDeliveries ?></div>
        <div class="kpi-sub">Awaiting dispatch</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#38bdf8">
        <div class="kpi-label">Internal Transfers</div>
        <div class="kpi-value"><?= $pendingTransfers ?></div>
        <div class="kpi-sub">Scheduled moves</div>
    </div>
</div>

<div class="grid-2" style="gap:20px;">
    <!-- Recent Operations -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Recent Operations</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Type</th>
                        <th>Party</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentOps as $op): ?>
                <tr>
                    <td class="td-mono"><?= clean($op['reference']) ?></td>
                    <td>
                        <?php if ($op['type'] === 'receipt'): ?>
                            <span class="badge badge-in">↓ Receipt</span>
                        <?php elseif ($op['type'] === 'delivery'): ?>
                            <span class="badge badge-out">↑ Delivery</span>
                        <?php else: ?>
                            <span class="badge badge-adj">⇄ Transfer</span>
                        <?php endif; ?>
                    </td>
                    <td><?= clean($op['party'] ?? '—') ?></td>
                    <td><span class="badge <?= statusColor($op['status']) ?>"><?= $op['status'] ?></span></td>
                    <td class="td-mono"><?= date('d M', strtotime($op['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$recentOps): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--text3);padding:30px;">No operations yet</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Low Stock Alerts -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">⚠ Low Stock Alerts</span>
            <a href="<?= BASE_URL ?>/pages/products.php" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Qty</th>
                        <th>Reorder</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($lowStockProds as $p): ?>
                <tr>
                    <td class="td-bold"><?= clean($p['name']) ?></td>
                    <td class="td-mono"><?= clean($p['sku']) ?></td>
                    <td style="color:<?= $p['qty'] == 0 ? 'var(--red)' : 'var(--orange)' ?>;font-weight:600;">
                        <?= fmtNum($p['qty'], 0) ?> <?= clean($p['unit_of_measure']) ?>
                    </td>
                    <td style="color:var(--text3)"><?= $p['reorder_level'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$lowStockProds): ?>
                <tr><td colspan="4" style="text-align:center;color:var(--green);padding:30px;">✓ All stock levels healthy</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
