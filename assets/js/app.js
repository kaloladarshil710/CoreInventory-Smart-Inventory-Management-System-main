// CoreInventory — App JS v5 (Final)

// ── Sidebar ─────────────────────────────────────────────────
let sidebarCollapsed = false;

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const wrapper = document.getElementById('mainWrapper');
    sidebarCollapsed = !sidebarCollapsed;
    sidebar.classList.toggle('collapsed', sidebarCollapsed);
    if (wrapper) wrapper.style.marginLeft = sidebarCollapsed ? '0' : '260px';
}

if (window.innerWidth <= 768) {
    document.getElementById('mainWrapper')?.addEventListener('click', () => {
        document.getElementById('sidebar')?.classList.remove('open');
    });
}

// ── Modal ────────────────────────────────────────────────────
function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('open');
    document.body.style.overflow = '';
}

// Close on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('open');
            document.body.style.overflow = '';
        }
    });
});

// Close on Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => {
            m.classList.remove('open');
            document.body.style.overflow = '';
        });
    }
});

// ── Item List — addItem ──────────────────────────────────────
let _itemCounter = 0;

// window.PRODUCT_DATA must be set per-page:
// <script>window.PRODUCT_DATA = <?= json_encode(...) ?>;</script>

function addItem(listId) {
    _itemCounter++;
    const list = document.getElementById(listId);
    if (!list) { console.error('Item list not found:', listId); return; }

    // Remove empty state
    const empty = list.querySelector('.items-empty-state');
    if (empty) empty.remove();

    // Build <option> list
    const products = window.PRODUCT_DATA || [];
    let opts = '<option value="">— Select a product —</option>';
    products.forEach(p => {
        const label = '[' + p.sku + '] ' + p.name;
        opts += '<option value="' + p.id + '" data-uom="' + (p.unit_of_measure || 'pcs') + '">'
              + label.replace(/</g,'&lt;').replace(/>/g,'&gt;')
              + '</option>';
    });

    // Build row
    const n   = _itemCounter;
    const row = document.createElement('div');
    row.className = 'item-row';
    row.dataset.item = n;
    row.innerHTML =
        '<div class="item-product-col">' +
            '<select name="items[' + n + '][product_id]" class="item-select" required onchange="updateRowUom(this)">' +
                opts +
            '</select>' +
        '</div>' +
        '<div class="item-qty-col">' +
            '<input type="number" name="items[' + n + '][quantity]"' +
                ' class="item-qty" min="0.01" step="0.01" placeholder="0.00" required>' +
            '<span class="item-uom-tag" data-uom-for="' + n + '">pcs</span>' +
        '</div>' +
        '<div class="item-del-col">' +
            '<button type="button" class="item-row-delete" onclick="removeItem(this)" title="Remove">' +
                '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">' +
                    '<line x1="18" y1="6" x2="6" y2="18"/>' +
                    '<line x1="6" y1="6" x2="18" y2="18"/>' +
                '</svg>' +
            '</button>' +
        '</div>';
    list.appendChild(row);

    // Focus the select
    row.querySelector('.item-select').focus();

    // Update badge
    refreshBadge(listId);
}

function updateRowUom(select) {
    const opt = select.options[select.selectedIndex];
    const uom = opt ? (opt.dataset.uom || 'pcs') : 'pcs';
    const row = select.closest('.item-row');
    const tag = row ? row.querySelector('.item-uom-tag') : null;
    if (tag) tag.textContent = uom;
    // Clear error state
    select.classList.remove('error');
}

function removeItem(btn) {
    const row  = btn.closest('.item-row');
    const list = row ? row.parentElement : null;
    if (!row) return;
    row.remove();

    if (list) {
        if (!list.querySelector('.item-row')) {
            list.innerHTML =
                '<div class="items-empty-state">' +
                    '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.25">' +
                        '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>' +
                    '</svg>' +
                    '<span>No products added yet</span>' +
                '</div>';
        }
        refreshBadge(list.id);
    }
}

function refreshBadge(listId) {
    const list  = document.getElementById(listId);
    const badge = document.getElementById(listId + 'Badge');
    if (!list || !badge) return;
    const count = list.querySelectorAll('.item-row').length;
    badge.textContent = count + (count === 1 ? ' item' : ' items');
    badge.classList.toggle('has-items', count > 0);
}

// ── Form validation for item forms ──────────────────────────
function validateItemForm(listId, errId) {
    const list   = document.getElementById(listId);
    const errEl  = errId ? document.getElementById(errId) : null;

    if (!list) return true;

    const rows = list.querySelectorAll('.item-row');

    if (rows.length === 0) {
        showItemError(errEl, 'Please add at least one product.');
        return false;
    }

    let valid = true;
    rows.forEach(row => {
        const sel = row.querySelector('.item-select');
        const qty = row.querySelector('.item-qty');
        if (sel && !sel.value) { sel.classList.add('error'); valid = false; }
        else if (sel) sel.classList.remove('error');
        if (qty && (!qty.value || parseFloat(qty.value) <= 0)) { qty.classList.add('error'); valid = false; }
        else if (qty) qty.classList.remove('error');
    });

    if (!valid) {
        showItemError(errEl, 'Please select a product and enter a valid quantity for every row.');
        return false;
    }

    hideItemError(errEl);
    return true;
}

function showItemError(el, msg) {
    if (!el) return;
    el.style.display = 'block';
    el.textContent = msg;
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function hideItemError(el) {
    if (el) el.style.display = 'none';
}

// ── Reset modal + items list ─────────────────────────────────
function resetAndClose(modalId, listId) {
    // Reset form
    const modal = document.getElementById(modalId);
    if (modal) {
        const form = modal.querySelector('form');
        if (form) form.reset();
    }
    // Clear item list
    const list = document.getElementById(listId);
    if (list) {
        list.innerHTML =
            '<div class="items-empty-state">' +
                '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.25">' +
                    '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>' +
                '</svg>' +
                '<span>No products added yet</span>' +
            '</div>';
        refreshBadge(listId);
    }
    // Hide any error
    const err = document.getElementById(listId.replace('Items','').replace('adj','adj') + 'Err');
    if (err) err.style.display = 'none';
    closeModal(modalId);
}

// ── Confirm dangerous actions ────────────────────────────────
document.addEventListener('click', function(e) {
    const el = e.target.closest('[data-confirm]');
    if (el && !confirm(el.dataset.confirm)) e.preventDefault();
});

// ── Auto-dismiss flash messages ──────────────────────────────
const flash = document.getElementById('flashMsg');
if (flash) {
    setTimeout(() => {
        flash.style.transition = 'opacity 0.4s';
        flash.style.opacity    = '0';
        setTimeout(() => flash.remove(), 400);
    }, 5000);
}

// ── Table search ─────────────────────────────────────────────
function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;
    input.addEventListener('keyup', function () {
        const q = this.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}
