<?php
require_once __DIR__ . '/../includes/config.php';
requirePermission('view_users');

$db         = getDB();
$pageTitle  = 'User Management';
$activePage = 'users';
$myRole     = userRole();
$myId       = (int)($_SESSION['user_id'] ?? 0);

// ── HANDLE POST ACTIONS ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── CREATE USER ──────────────────────────────────────────
    if ($action === 'create') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'staff';

        // Permission: only admin can create manager; admin/manager can create staff
        if ($role === 'manager' && !isAdmin()) {
            setFlash('error', 'Only Admins can create Manager accounts.');
            header('Location: ' . BASE_URL . '/pages/users.php'); exit;
        }
        if ($role === 'admin') {
            setFlash('error', 'Cannot create another Admin account.');
            header('Location: ' . BASE_URL . '/pages/users.php'); exit;
        }
        if (!can('create_staff')) {
            setFlash('error', 'Access denied.');
            header('Location: ' . BASE_URL . '/pages/users.php'); exit;
        }

        // Validate
        if (!$name || !$email || strlen($password) < 6) {
            setFlash('error', 'Name, email and password (min 6 chars) are required.');
            header('Location: ' . BASE_URL . '/pages/users.php'); exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Invalid email address.');
            header('Location: ' . BASE_URL . '/pages/users.php'); exit;
        }

        // Check duplicate
        $chk = $db->prepare("SELECT id FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            setFlash('error', 'Email already exists.');
            header('Location: ' . BASE_URL . '/pages/users.php'); exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)")
           ->execute([$name, $email, $hash, $role]);
        setFlash('success', "User '{$name}' created successfully as " . ucfirst($role) . ".");
        header('Location: ' . BASE_URL . '/pages/users.php'); exit;
    }

    // ── EDIT USER ────────────────────────────────────────────
    if ($action === 'edit') {
        if (!can('edit_user')) {
            setFlash('error', 'Access denied.'); header('Location: ' . BASE_URL . '/pages/users.php'); exit;
        }

        $uid      = (int)$_POST['user_id'];
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $newRole  = $_POST['role'] ?? '';
        $password = $_POST['password'] ?? '';

        // Get target user
        $target = $db->prepare("SELECT * FROM users WHERE id = ?");
        $target->execute([$uid]);
        $target = $target->fetch();

        if (!$target) {
            setFlash('error', 'User not found.'); header('Location: ' . BASE_URL . '/pages/users.php'); exit;
        }

        // Manager can only edit staff
        if ($myRole === 'manager' && $target['role'] !== 'staff') {
            setFlash('error', 'Managers can only edit Staff accounts.');
            header('Location: ' . BASE_URL . '/pages/users.php'); exit;
        }

        // Cannot change role to admin
        if ($newRole === 'admin' && !isAdmin()) {
            setFlash('error', 'Only Admin can assign Admin role.');
            header('Location: ' . BASE_URL . '/pages/users.php'); exit;
        }

        // Cannot change your own role
        if ($uid === $myId && $newRole !== $target['role']) {
            setFlash('error', 'You cannot change your own role.');
            header('Location: ' . BASE_URL . '/pages/users.php'); exit;
        }

        // Build update
        if ($password) {
            $db->prepare("UPDATE users SET name=?, email=?, role=?, password=? WHERE id=?")
               ->execute([$name, $email, $newRole, password_hash($password, PASSWORD_DEFAULT), $uid]);
        } else {
            $db->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?")
               ->execute([$name, $email, $newRole, $uid]);
        }
        setFlash('success', 'User updated successfully.');
        header('Location: ' . BASE_URL . '/pages/users.php'); exit;
    }

    // ── DELETE USER ──────────────────────────────────────────
    if ($action === 'delete') {
        if (!isAdmin()) {
            setFlash('error', 'Only Admin can delete users.');
            header('Location: ' . BASE_URL . '/pages/users.php'); exit;
        }
        $uid = (int)$_POST['user_id'];
        if ($uid === $myId) {
            setFlash('error', 'You cannot delete your own account.');
            header('Location: ' . BASE_URL . '/pages/users.php'); exit;
        }
        $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$uid]);
        setFlash('success', 'User deactivated.');
        header('Location: ' . BASE_URL . '/pages/users.php'); exit;
    }

    // ── TOGGLE ACTIVE ────────────────────────────────────────
    if ($action === 'toggle') {
        if (!isAdmin()) {
            setFlash('error', 'Only Admin can activate/deactivate users.');
            header('Location: ' . BASE_URL . '/pages/users.php'); exit;
        }
        $uid = (int)$_POST['user_id'];
        if ($uid === $myId) {
            setFlash('error', 'You cannot deactivate your own account.');
            header('Location: ' . BASE_URL . '/pages/users.php'); exit;
        }
        $db->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?")->execute([$uid]);
        setFlash('success', 'User status updated.');
        header('Location: ' . BASE_URL . '/pages/users.php'); exit;
    }
}

// ── FETCH USERS ───────────────────────────────────────────────
// Manager sees only staff; Admin sees everyone
$filter = $_GET['role'] ?? '';

if ($myRole === 'manager') {
    // Manager can only see staff
    $stmt = $db->prepare("SELECT * FROM users WHERE role = 'staff' ORDER BY name");
    $stmt->execute();
} elseif ($filter) {
    $stmt = $db->prepare("SELECT * FROM users WHERE role = ? ORDER BY name");
    $stmt->execute([$filter]);
} else {
    $stmt = $db->query("SELECT * FROM users ORDER BY FIELD(role,'admin','manager','staff'), name");
}
$users = $stmt->fetchAll();

// Roles this user can create
$creatableRoles = [];
if (isAdmin())           $creatableRoles[] = 'manager';
if (can('create_staff')) $creatableRoles[] = 'staff';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.user-card {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: border-color 0.2s, transform 0.2s;
    position: relative;
    overflow: hidden;
}
.user-card:hover { border-color: var(--border2); transform: translateY(-2px); }
.user-card::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 3px;
}
.user-card.role-admin::before   { background: var(--green); }
.user-card.role-manager::before { background: var(--accent); }
.user-card.role-staff::before   { background: var(--text3); }
.user-card.inactive { opacity: 0.5; }

.user-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
}

.user-av-lg {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-family: var(--font-head);
    font-weight: 800;
    font-size: 18px;
    color: #fff;
    flex-shrink: 0;
}
.role-admin   .user-av-lg { background: linear-gradient(135deg, var(--green), #0da97a); }
.role-manager .user-av-lg { background: linear-gradient(135deg, var(--accent), #2563eb); }
.role-staff   .user-av-lg { background: linear-gradient(135deg, #4a5568, #2d3748); }

.user-info-block { flex: 1; min-width: 0; }
.user-info-name { font-family: var(--font-head); font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 3px; }
.user-info-email { font-size: 12px; color: var(--text3); margin-bottom: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.perm-list { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 8px; }
.perm-chip {
    font-size: 10.5px;
    padding: 2px 8px;
    border-radius: 20px;
    font-weight: 600;
    letter-spacing: 0.3px;
}
.perm-yes { background: var(--green-soft); color: var(--green); }
.perm-no  { background: rgba(74,85,104,0.15); color: var(--text4); text-decoration: line-through; }

.role-section-head {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 24px 0 14px;
}
.role-section-line {
    flex: 1;
    height: 1px;
    background: var(--border);
}
.role-section-label {
    font-family: var(--font-head);
    font-size: 13px;
    font-weight: 700;
    color: var(--text3);
    letter-spacing: 0.5px;
    text-transform: uppercase;
    white-space: nowrap;
}
</style>

<div class="page-header">
    <div>
        <h1>User Management</h1>
        <p>
            <?php if ($myRole === 'admin'): ?>
                Manage all users — create, edit, activate/deactivate accounts
            <?php else: ?>
                Manage Staff accounts — you can create and edit staff users
            <?php endif; ?>
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <?php if (isAdmin()): ?>
        <a href="?role=" class="btn btn-ghost btn-sm <?= !$filter ? 'btn-active' : '' ?>">All</a>
        <a href="?role=admin"   class="btn btn-ghost btn-sm">Admins</a>
        <a href="?role=manager" class="btn btn-ghost btn-sm">Managers</a>
        <a href="?role=staff"   class="btn btn-ghost btn-sm">Staff</a>
        <?php endif; ?>
        <?php if (!empty($creatableRoles)): ?>
        <button class="btn btn-primary" onclick="openModal('createUserModal')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add User
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Permission Summary Card -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <span class="card-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Your Permissions — <span style="color:var(--accent)"><?= ucfirst($myRole) ?></span>
        </span>
    </div>
    <div class="card-body" style="padding:16px 22px;">
        <div class="perm-list">
            <?php
            $allPerms = [
                'view_dashboard'       => 'View Dashboard',
                'manage_products'      => 'Manage Products',
                'validate_receipts'    => 'Validate Receipts',
                'validate_deliveries'  => 'Validate Deliveries',
                'validate_transfers'   => 'Validate Transfers',
                'manage_adjustments'   => 'Manage Adjustments',
                'validate_adjustments' => 'Validate Adjustments',
                'manage_warehouses'    => 'Manage Warehouses',
                'view_users'           => 'View Users',
                'create_manager'       => 'Create Manager',
                'create_staff'         => 'Create Staff',
                'edit_user'            => 'Edit Users',
                'delete_user'          => 'Delete Users',
            ];
            foreach ($allPerms as $perm => $label):
                $has = can($perm);
            ?>
            <span class="perm-chip <?= $has ? 'perm-yes' : 'perm-no' ?>">
                <?= $has ? '✓' : '✗' ?> <?= $label ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Users List -->
<?php
$grouped = ['admin' => [], 'manager' => [], 'staff' => []];
foreach ($users as $u) $grouped[$u['role']][] = $u;

$roleLabels = [
    'admin'   => ['👑 Admin', 'var(--green)'],
    'manager' => ['📊 Manager', 'var(--accent)'],
    'staff'   => ['📦 Staff', 'var(--text3)'],
];

foreach ($grouped as $role => $roleUsers):
    if (empty($roleUsers)) continue;
    [$rLabel, $rColor] = $roleLabels[$role];
?>

<div class="role-section-head">
    <div class="role-section-line"></div>
    <div class="role-section-label" style="color:<?= $rColor ?>"><?= $rLabel ?> (<?= count($roleUsers) ?>)</div>
    <div class="role-section-line"></div>
</div>

<div class="user-grid" style="margin-bottom:8px;">
<?php foreach ($roleUsers as $u):
    $isMe = ((int)$u['id'] === $myId);
    $isActive = ($u['is_active'] ?? 1) == 1;
    // Can current user edit this person?
    $canEdit   = can('edit_user') && ($myRole === 'admin' || $u['role'] === 'staff') && !$isMe;
    $canToggle = isAdmin() && !$isMe;
?>
<div class="user-card role-<?= $u['role'] ?> <?= !$isActive ? 'inactive' : '' ?>">
    <div class="user-av-lg"><?= strtoupper(substr($u['name'], 0, 1)) ?></div>

    <div class="user-info-block">
        <div class="user-info-name">
            <?= clean($u['name']) ?>
            <?php if ($isMe): ?><span style="font-size:10px;color:var(--accent);font-weight:600;margin-left:6px;">YOU</span><?php endif; ?>
            <?php if (!$isActive): ?><span style="font-size:10px;color:var(--red);font-weight:600;margin-left:6px;">INACTIVE</span><?php endif; ?>
        </div>
        <div class="user-info-email"><?= clean($u['email']) ?></div>
        <div style="display:flex;align-items:center;gap:8px;">
            <span class="badge <?= roleBadge($u['role']) ?>"><?= ucfirst($u['role']) ?></span>
            <span style="font-size:11px;color:var(--text3);">Since <?= date('M Y', strtotime($u['created_at'])) ?></span>
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;">
        <?php if ($canEdit): ?>
        <button class="btn btn-ghost btn-sm" onclick='openEditModal(<?= json_encode([
            "id"    => $u["id"],
            "name"  => $u["name"],
            "email" => $u["email"],
            "role"  => $u["role"],
        ]) ?>)'>Edit</button>
        <?php endif; ?>

        <?php if ($canToggle): ?>
        <form method="POST">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
            <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-danger' : 'btn-success' ?>"
                style="width:100%"
                data-confirm="<?= $isActive ? 'Deactivate' : 'Activate' ?> this user?">
                <?= $isActive ? 'Deactivate' : 'Activate' ?>
            </button>
        </form>
        <?php endif; ?>

        <?php if (!$canEdit && !$canToggle && !$isMe): ?>
        <span style="font-size:11px;color:var(--text4);padding:4px 8px;">No actions</span>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php endforeach; ?>

<?php if (empty($users)): ?>
<div class="card"><div class="card-body">
    <div class="empty-state">
        <div class="empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
        <h3>No users found</h3>
        <p>Create the first user to get started</p>
    </div>
</div></div>
<?php endif; ?>


<!-- CREATE USER MODAL -->
<div class="modal-overlay" id="createUserModal">
    <div class="modal" style="max-width:480px;">
        <div class="modal-header">
            <h3>Add New User</h3>
            <button class="modal-close" onclick="closeModal('createUserModal')">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="create">

                <?php if ($myRole === 'manager'): ?>
                <!-- Manager info box -->
                <div style="background:var(--accent-soft);border:1px solid rgba(79,142,255,0.2);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:18px;font-size:13px;color:var(--text2);">
                    <strong style="color:var(--accent);">Manager Permission:</strong>
                    You can only create <strong>Staff</strong> accounts.
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" required placeholder="John Doe">
                </div>
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" required placeholder="user@company.com">
                </div>
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" id="createRoleSelect" onchange="updateRoleInfo(this.value)">
                        <?php foreach ($creatableRoles as $r): ?>
                        <option value="<?= $r ?>"><?= ucfirst($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Role info box -->
                <div id="roleInfoBox" style="border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:16px;font-size:12.5px;display:none;"></div>

                <div class="form-group">
                    <label>Password * <span style="font-weight:400;color:var(--text3);text-transform:none;">(min 6 characters)</span></label>
                    <div style="position:relative;">
                        <input type="password" name="password" id="createPw" required minlength="6" placeholder="••••••••">
                        <button type="button" class="pw-toggle" onclick="togglePw('createPw', this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Confirm Password *</label>
                    <input type="password" name="confirm_password" id="createPwConfirm" required placeholder="Repeat password">
                    <div id="pwMatchMsg" style="font-size:12px;margin-top:4px;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('createUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="createSubmitBtn">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT USER MODAL -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal" style="max-width:480px;">
        <div class="modal-header">
            <h3>Edit User</h3>
            <button class="modal-close" onclick="closeModal('editUserModal')">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="editUserId">

                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" id="editName" required>
                </div>
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" id="editEmail" required>
                </div>

                <?php if (isAdmin()): ?>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="editRole">
                        <option value="admin">Admin</option>
                        <option value="manager">Manager</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
                <?php else: ?>
                <!-- Manager can't change role -->
                <input type="hidden" name="role" value="staff">
                <div style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius-sm);padding:10px 14px;margin-bottom:16px;font-size:13px;color:var(--text2);">
                    Role: <strong>Staff</strong> (Managers cannot change roles)
                </div>
                <?php endif; ?>

                <hr class="divider">
                <div class="form-group">
                    <label>New Password <span style="font-weight:400;color:var(--text3);text-transform:none;">(leave blank to keep current)</span></label>
                    <div style="position:relative;">
                        <input type="password" name="password" id="editPw" minlength="6" placeholder="Leave blank to keep unchanged">
                        <button type="button" class="pw-toggle" onclick="togglePw('editPw', this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('editUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
// Role info descriptions
const roleInfo = {
    manager: {
        color: 'rgba(79,142,255,0.08)',
        border: 'rgba(79,142,255,0.2)',
        textColor: 'var(--text2)',
        icon: '📊',
        text: '<strong style="color:var(--accent)">Manager</strong> — Can manage products, create/validate receipts & deliveries, create staff accounts, view warehouses. <strong>Cannot</strong> manage warehouses or delete users.'
    },
    staff: {
        color: 'rgba(74,85,104,0.1)',
        border: 'rgba(74,85,104,0.2)',
        textColor: 'var(--text2)',
        icon: '📦',
        text: '<strong style="color:var(--text2)">Staff</strong> — Can view dashboard & products, create receipts, deliveries and transfers. <strong>Cannot</strong> validate operations, manage products, or access settings.'
    }
};

function updateRoleInfo(role) {
    const box = document.getElementById('roleInfoBox');
    if (roleInfo[role]) {
        const r = roleInfo[role];
        box.style.display = 'block';
        box.style.background = r.color;
        box.style.border = '1px solid ' + r.border;
        box.style.color = r.textColor;
        box.innerHTML = r.icon + ' ' + r.text;
    } else {
        box.style.display = 'none';
    }
}

// Run on load
const sel = document.getElementById('createRoleSelect');
if (sel) updateRoleInfo(sel.value);

function openEditModal(user) {
    document.getElementById('editUserId').value  = user.id;
    document.getElementById('editName').value    = user.name;
    document.getElementById('editEmail').value   = user.email;
    const roleEl = document.getElementById('editRole');
    if (roleEl) roleEl.value = user.role;
    document.getElementById('editPw').value = '';
    openModal('editUserModal');
}

function togglePw(id, btn) {
    const input = document.getElementById(id);
    input.type = input.type === 'text' ? 'password' : 'text';
    btn.style.color = input.type === 'text' ? 'var(--accent)' : '';
}

// Password match check
const pw1 = document.getElementById('createPw');
const pw2 = document.getElementById('createPwConfirm');
const msg = document.getElementById('pwMatchMsg');
const submitBtn = document.getElementById('createSubmitBtn');

function checkMatch() {
    if (!pw2.value) { msg.textContent = ''; return; }
    if (pw1.value === pw2.value) {
        msg.textContent = '✓ Passwords match';
        msg.style.color = 'var(--green)';
        if (submitBtn) submitBtn.disabled = false;
    } else {
        msg.textContent = '✗ Passwords do not match';
        msg.style.color = 'var(--red)';
        if (submitBtn) submitBtn.disabled = true;
    }
}
if (pw1) pw1.addEventListener('input', checkMatch);
if (pw2) pw2.addEventListener('input', checkMatch);

document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => { if (!confirm(el.dataset.confirm)) e.preventDefault(); });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
