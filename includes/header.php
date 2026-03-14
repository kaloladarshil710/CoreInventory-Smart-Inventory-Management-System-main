<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> — <?= $pageTitle ?? 'Dashboard' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>
            </svg>
        </div>
        <span class="brand-name">Core<strong>Inv</strong></span>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Main</div>

        <a href="<?= BASE_URL ?>/pages/dashboard.php" class="nav-item <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Dashboard
        </a>

        <a href="<?= BASE_URL ?>/pages/products.php" class="nav-item <?= ($activePage ?? '') === 'products' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            Products
        </a>

        <div class="nav-section-label">Operations</div>

        <a href="<?= BASE_URL ?>/pages/receipts.php" class="nav-item <?= ($activePage ?? '') === 'receipts' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
            Receipts
            <?php
            try {
                $db = getDB();
                $cnt = $db->query("SELECT COUNT(*) FROM receipts WHERE status IN ('draft','waiting','ready')")->fetchColumn();
                if ($cnt > 0) echo "<span class=\"nav-badge\">$cnt</span>";
            } catch(Exception $e) {}
            ?>
        </a>

        <a href="<?= BASE_URL ?>/pages/deliveries.php" class="nav-item <?= ($activePage ?? '') === 'deliveries' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 5v4h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            Deliveries
            <?php
            try {
                $cnt2 = $db->query("SELECT COUNT(*) FROM deliveries WHERE status IN ('draft','waiting','ready')")->fetchColumn();
                if ($cnt2 > 0) echo "<span class=\"nav-badge\">$cnt2</span>";
            } catch(Exception $e) {}
            ?>
        </a>

        <a href="<?= BASE_URL ?>/pages/transfers.php" class="nav-item <?= ($activePage ?? '') === 'transfers' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
            Internal Transfers
        </a>

        <a href="<?= BASE_URL ?>/pages/adjustments.php" class="nav-item <?= ($activePage ?? '') === 'adjustments' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Stock Adjustments
        </a>

        <a href="<?= BASE_URL ?>/pages/ledger.php" class="nav-item <?= ($activePage ?? '') === 'ledger' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Move History
        </a>

        <div class="nav-section-label">Configuration</div>

        <a href="<?= BASE_URL ?>/pages/warehouses.php" class="nav-item <?= ($activePage ?? '') === 'warehouses' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Warehouses
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar"><?= strtoupper(substr(currentUser()['name'] ?? 'U', 0, 1)) ?></div>
            <div class="user-meta">
                <span class="user-name"><?= htmlspecialchars(currentUser()['name'] ?? '') ?></span>
                <span class="user-role"><?= ucfirst(currentUser()['role'] ?? '') ?></span>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/pages/logout.php" class="logout-btn" title="Logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </a>
    </div>
</aside>

<!-- Main Content -->
<div class="main-wrapper" id="mainWrapper">
    <header class="topbar">
        <button class="sidebar-toggle" onclick="toggleSidebar()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <div class="topbar-title"><?= $pageTitle ?? 'Dashboard' ?></div>
        <div class="topbar-right">
            <span class="topbar-date"><?= date('D, d M Y') ?></span>
            <a href="<?= BASE_URL ?>/pages/products.php?show=low" title="Low stock alerts" style="position:relative; display:flex; color:var(--text2); padding:7px; border-radius:var(--radius-sm); border:1.5px solid var(--border); background:var(--bg3); transition:all 0.2s; text-decoration:none;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <?php
                try {
                    $lowCnt = $db->query("
                        SELECT COUNT(DISTINCT p.id) FROM products p
                        LEFT JOIN stock s ON s.product_id=p.id
                        WHERE p.is_active=1
                        GROUP BY p.id HAVING COALESCE(SUM(s.quantity),0) <= p.reorder_level
                    ")->fetchColumn();
                    if ($lowCnt > 0) echo "<span style='position:absolute;top:4px;right:4px;width:8px;height:8px;background:var(--red);border-radius:50%;border:2px solid var(--bg2);'></span>";
                } catch(Exception $e) {}
                ?>
            </a>
        </div>
    </header>

    <?php
    $flash = getFlash();
    if ($flash):
    ?>
    <div class="flash-message flash-<?= $flash['type'] ?>" id="flashMsg">
        <span><?= htmlspecialchars($flash['msg']) ?></span>
        <button onclick="this.parentElement.remove()">×</button>
    </div>
    <?php endif; ?>

    <main class="page-content">
