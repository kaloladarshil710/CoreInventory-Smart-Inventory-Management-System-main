// CoreInventory — App JS v2

// ---- Sidebar ----
let sidebarCollapsed = false;

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const wrapper = document.getElementById('mainWrapper');
    sidebarCollapsed = !sidebarCollapsed;
    sidebar.classList.toggle('collapsed', sidebarCollapsed);
    if (wrapper) wrapper.style.marginLeft = sidebarCollapsed ? '0' : '260px';
}

// Mobile: swipe to close sidebar
if (window.innerWidth <= 768) {
    document.getElementById('mainWrapper')?.addEventListener('click', () => {
        document.getElementById('sidebar')?.classList.remove('open');
    });
}

// ---- Modal helpers ----
function openModal(id) {
    document.getElementById(id)?.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id)?.classList.remove('open');
    document.body.style.overflow = '';
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('open');
            document.body.style.overflow = '';
        }
    });
});

// Close modal on Escape key
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => {
            m.classList.remove('open');
            document.body.style.overflow = '';
        });
    }
});

// ---- Dynamic line items ----
let itemCount = 0;

function addItem(tableId, productOptions) {
    itemCount++;
    const tbody = document.getElementById(tableId);
    if (!tbody) return;

    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select name="items[${itemCount}][product_id]" required class="item-input" style="width:100%;min-width:180px;">
                <option value="">— Select product —</option>
                ${productOptions}
            </select>
        </td>
        <td>
            <input type="number" name="items[${itemCount}][quantity]"
                min="0.01" step="0.01" placeholder="0.00"
                class="item-input" style="width:110px;" required>
        </td>
        <td>
            <button type="button" class="item-delete" onclick="this.closest('tr').remove()" title="Remove">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </td>
    `;
    tbody.appendChild(tr);

    // Focus the select
    tr.querySelector('select').focus();
}

// ---- Confirm dangerous actions ----
document.addEventListener('click', function(e) {
    const el = e.target.closest('[data-confirm]');
    if (el && !confirm(el.dataset.confirm)) e.preventDefault();
});

// ---- Auto-dismiss flash messages ----
const flash = document.getElementById('flashMsg');
if (flash) {
    setTimeout(() => {
        flash.style.transition = 'opacity 0.4s';
        flash.style.opacity = '0';
        setTimeout(() => flash.remove(), 400);
    }, 4500);
}

// ---- Table search filter ----
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

// ---- Number formatting ----
function formatNum(n, dec = 0) {
    return parseFloat(n).toLocaleString('en-IN', { minimumFractionDigits: dec });
}
