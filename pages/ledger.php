<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$db = getDB();
$pageTitle  = 'Move History';
$activePage = 'ledger';

$typeFilter = $_GET['type'] ?? '';
$prodFilter = (int)($_GET['product_id'] ?? 0);
$whFilter   = (int)($_GET['warehouse_id'] ?? 0);

$where  = "WHERE 1";
$params = [];

if ($typeFilter) { $where .= " AND sl.operation_type=?"; $params[] = $typeFilter; }
if ($prodFilter) { $where .= " AND sl.product_id=?";    $params[] = $prodFilter; }
if ($whFilter)   { $where .= " AND sl.warehouse_id=?";  $params[] = $whFilter; }

$ledger = $db->prepare("
    SELECT sl.*, p.name as product_name, p.sku, p.unit_of_measure,
           w.name as warehouse_name, u.name as user_name
    FROM stock_ledger sl
    JOIN products p ON p.id=sl.product_id
    JOIN warehouses w ON w.id=sl.warehouse_id
    LEFT JOIN users u ON u.id=sl.created_by
    $where
    ORDER BY sl.created_at DESC
    LIMIT 500
");
$ledger->execute($params);
$ledger = $ledger->fetchAll();

$products   = $db->query("SELECT id, name, sku FROM products WHERE is_active=1 ORDER BY name")->fetchAll();
$warehouses = $db->query("SELECT * FROM warehouses")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div><h1>Stock Ledger — Move History</h1><p>Complete log of all inventory movements</p></div>
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:14px 20px;">
        <form method="GET" class="filters-bar">
            <select name="type" onchange="this.form.submit()">
                <option value="">All Types</option>
                <option value="receipt" <?= $typeFilter==='receipt'?'selected':'' ?>>Receipt (In)</option>
                <option value="delivery" <?= $typeFilter==='delivery'?'selected':'' ?>>Delivery (Out)</option>
                <option value="transfer_in" <?= $typeFilter==='transfer_in'?'selected':'' ?>>Transfer In</option>
                <option value="transfer_out" <?= $typeFilter==='transfer_out'?'selected':'' ?>>Transfer Out</option>
                <option value="adjustment" <?= $typeFilter==='adjustment'?'selected':'' ?>>Adjustment</option>
            </select>
            <select name="product_id">
                <option value="">All Products</option>
                <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $prodFilter==$p['id']?'selected':'' ?>>[<?= clean($p['sku']) ?>] <?= clean($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="warehouse_id">
                <option value="">All Warehouses</option>
                <?php foreach ($warehouses as $w): ?>
                <option value="<?= $w['id'] ?>" <?= $whFilter==$w['id']?'selected':'' ?>><?= clean($w['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="<?= BASE_URL ?>/pages/ledger.php" class="btn btn-ghost">Clear</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Ledger (<?= count($ledger) ?> records)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Product</th>
                    <th>Warehouse</th>
                    <th>Change</th>
                    <th>Balance After</th>
                    <th>By</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ledger as $row): ?>
            <tr>
                <td class="td-mono"><?= date('d M Y H:i', strtotime($row['created_at'])) ?></td>
                <td>
                    <?php
                    $badges = [
                        'receipt'      => ['badge-in',  '↓ Receipt'],
                        'delivery'     => ['badge-out', '↑ Delivery'],
                        'transfer_in'  => ['badge-in',  '→ Transfer In'],
                        'transfer_out' => ['badge-out', '← Transfer Out'],
                        'adjustment'   => ['badge-adj', '± Adjustment'],
                    ];
                    [$cls, $label] = $badges[$row['operation_type']] ?? ['badge-draft', $row['operation_type']];
                    ?>
                    <span class="badge <?= $cls ?>"><?= $label ?></span>
                </td>
                <td>
                    <span class="td-bold"><?= clean($row['product_name']) ?></span>
                    <span class="td-mono" style="font-size:11px;color:var(--text3);display:block;"><?= clean($row['sku']) ?></span>
                </td>
                <td><?= clean($row['warehouse_name']) ?></td>
                <td style="font-weight:700;color:<?= $row['quantity_change'] >= 0 ? 'var(--green)' : 'var(--red)' ?>">
                    <?= $row['quantity_change'] >= 0 ? '+' : '' ?><?= fmtNum($row['quantity_change']) ?> <?= clean($row['unit_of_measure']) ?>
                </td>
                <td class="td-mono" style="font-weight:600;"><?= fmtNum($row['quantity_after']) ?></td>
                <td style="color:var(--text3);font-size:12px;"><?= clean($row['user_name'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$ledger): ?>
            <tr><td colspan="7"><div class="empty-state"><h3>No records found</h3></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
