// Pricing calculator untuk form tambah barang dan restock

function roundTo5k(value) {
    return Math.ceil(value / 5000) * 5000;
}

function calculateSellingPrice(costPrice) {
    const cost = Number(costPrice) || 0;
    if (cost <= 0) return 0;
    return roundTo5k(cost * 1.5);
}

function formatRupiahPlain(value) {
    return new Intl.NumberFormat('id-ID').format(value || 0);
}

// --- Form Tambah Barang (/barang) -------------------------------------------

function initProductForm() {
    const form = document.querySelector('[data-product-form]');
    if (!form) return;

    const costEl       = form.querySelector('[name="cost_price"]');
    const sellingEl    = form.querySelector('[name="selling_price"]');
    const previewEl    = form.querySelector('[data-price-preview]');
    const discMaxEl    = form.querySelector('[data-disc-max]');

    function recalculate() {
        const cost    = Number(costEl ? costEl.value : 0) || 0;
        const selling = calculateSellingPrice(cost);
        const discMax = Math.round(selling * 0.1);

        if (sellingEl) sellingEl.value = selling || '';

        if (previewEl) {
            if (cost > 0) {
                previewEl.textContent =
                    'Modal Rp ' + formatRupiahPlain(cost) +
                    ' \u2192 \u00d71,5 \u2192 Rp ' + formatRupiahPlain(selling);
                previewEl.hidden = false;
            } else {
                previewEl.hidden = true;
            }
        }

        if (discMaxEl) {
            if (selling > 0) {
                discMaxEl.textContent = 'Diskon maks 10%: Rp ' + formatRupiahPlain(discMax);
                discMaxEl.hidden = false;
            } else {
                discMaxEl.hidden = true;
            }
        }
    }

    if (costEl) costEl.addEventListener('input', recalculate);

    recalculate();
}

// --- Form Restock Owner (owner-dashboard) -----------------------------------

function initRestockForm() {
    const form = document.querySelector('[data-restock-form]');
    if (!form) return;

    const costEl    = form.querySelector('[name="unit_cost"]');
    const previewEl = form.querySelector('[data-restock-preview]');

    function recalculate() {
        const cost    = costEl ? (costEl.getRaw ? costEl.getRaw() : Number(costEl.value) || 0) : 0;
        const selling = calculateSellingPrice(cost);
        const discMax = Math.round(selling * 0.1);

        if (previewEl) {
            if (cost > 0) {
                const strong1 = document.createElement('strong');
                strong1.textContent = 'Estimasi harga jual baru: ';
                const strong2 = document.createElement('strong');
                strong2.textContent = 'Rp ' + formatRupiahPlain(selling);
                const span = document.createElement('span');
                span.style.color = '#6b7280';
                span.textContent = 'Diskon maks 10%: Rp ' + formatRupiahPlain(discMax);
                const br = document.createElement('br');

                previewEl.textContent = '';
                previewEl.appendChild(strong1);
                const text1 = document.createTextNode(
                    'Modal Rp ' + formatRupiahPlain(cost) +
                    ' \u2192 \u00d71,5 \u2192 '
                );
                previewEl.appendChild(text1);
                previewEl.appendChild(strong2);
                previewEl.appendChild(br);
                previewEl.appendChild(span);
                previewEl.hidden = false;
            } else {
                previewEl.hidden = true;
            }
        }
    }

    if (costEl) costEl.addEventListener('input', recalculate);
}

// --- Bulk Product Form (/barang — bulk table) --------------------------------

/**
 * Attach auto-pricing listeners to a single bulk table row.
 * Called on page load for each existing row, and from addProductRow()
 * whenever a new row is cloned into the table.
 */
function initBulkRow(row) {
    var costEl     = row.querySelector('[data-bulk-cost]');
    var sellingEl  = row.querySelector('[data-bulk-selling]');
    var previewEl  = row.querySelector('[data-bulk-price-preview]');

    if (!costEl || !sellingEl) return;

    function recalculate() {
        var cost    = costEl ? (costEl.getRaw ? costEl.getRaw() : Number(costEl.value) || 0) : 0;
        var selling = calculateSellingPrice(cost);

        sellingEl.value = selling > 0 ? selling : '';

        if (previewEl) {
            if (cost > 0) {
                previewEl.textContent =
                    'Rp\u00a0' + formatRupiahPlain(cost) +
                    ' \u00d71,5 \u2192 Rp\u00a0' + formatRupiahPlain(selling);
            } else {
                previewEl.textContent = '';
            }
        }
    }

    if (costEl) costEl.addEventListener('input', recalculate);

    // Initialise
    recalculate();
}

function initBulkProductForm() {
    var tbody = document.getElementById('bulk-tbody');
    if (!tbody) return;
    tbody.querySelectorAll('tr').forEach(function (row) {
        initBulkRow(row);
    });
}

// Export so addProductRow() in pos.js can call initBulkRow on cloned rows
window.initBulkRow = initBulkRow;

document.addEventListener('DOMContentLoaded', function () {
    initProductForm();
    initRestockForm();
    initBulkProductForm();
});
