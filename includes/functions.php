<?php
// ============================================================
// CoreInventory — Shared UI Helper Functions
// Include this after config.php on pages that use item blocks
// ============================================================

/**
 * Render the items block HTML (product selector list)
 * Used in: receipts, deliveries, transfers, adjustments
 */
function buildItemsBlock(string $listId, string $title, string $qtyLabel): string {
    $emptyIcon = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.25;"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>';
    return <<<HTML
<div class="items-block" data-items-wrap>
    <div class="items-block-header">
        <span class="items-block-title">{$title}</span>
        <span class="item-count-badge" id="{$listId}Badge">0 items</span>
    </div>
    <div class="items-block-cols">
        <span class="icol-product">Product</span>
        <span class="icol-qty">{$qtyLabel}</span>
        <span></span>
    </div>
    <div class="items-block-body" id="{$listId}">
        <div class="items-empty-state">
            {$emptyIcon}
            <span>No products added yet</span>
        </div>
    </div>
</div>
<button type="button" class="add-item-btn" onclick="addItem('{$listId}')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="15" height="15">
        <line x1="12" y1="5" x2="12" y2="19"/>
        <line x1="5" y1="12" x2="19" y2="12"/>
    </svg>
    Add Product
</button>
HTML;
}
