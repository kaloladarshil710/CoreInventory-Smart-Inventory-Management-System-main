// CoreInventory - App JS

// Modal helpers
function openModal(id) {
    document.getElementById(id)?.classList.add('open');
}

function closeModal(id) {
    document.getElementById(id)?.classList.remove('open');
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('open');
    });
});

// Dynamic line items for receipts/deliveries/adjustments
let itemCount = 0;

function addItem(tableId, productOptions) {
    itemCount++;
    const tbody = document.getElementById(tableId);
    if (!tbody) return;

    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select name="items[${itemCount}][product_id]" required style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:4px;color:var(--text);padding:6px 10px;font-size:13px;">
                <option value="">Select product...</option>
                ${productOptions}
            </select>
        </td>
        <td>
            <input type="number" name="items[${itemCount}][quantity]" min="0.01" step="0.01" placeholder="0.00"
                style="width:100px;background:var(--bg3);border:1px solid var(--border);border-radius:4px;color:var(--text);padding:6px 10px;font-size:13px;" required>
        </td>
        <td>
            <button type="button" onclick="this.closest('tr').remove()" style="background:none;border:none;color:var(--red);cursor:pointer;font-size:18px;padding:2px 8px;">×</button>
        </td>
    `;
    tbody.appendChild(tr);
}

// Search/filter tables
function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    input.addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
        });
    });
}

// Auto-dismiss flash messages
setTimeout(() => {
    const flash = document.getElementById('flash');
    if (flash) flash.style.opacity = '0';
    setTimeout(() => flash?.remove(), 300);
}, 4000);

// Confirm dangerous actions
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function(e) {
        if (!confirm(this.dataset.confirm)) e.preventDefault();
    });
});
