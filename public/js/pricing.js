// Pricing calculator untuk form tambah barang dan restock

const SHIPPING_COST = {
    jeans: 20000,
    default: 15000,
};

function shippingCostForCategory(category) {
    return (category || '').toLowerCase().trim() === 'jeans'
        ? SHIPPING_COST.jeans
        : SHIPPING_COST.default;
}

function roundTo5k(value) {
    const remainder = value % 10000;
    if (remainder === 0) return value;
    if (remainder < 5000) return value - remainder + 5000;
    return value - remainder + 10000;
}

function calculateSellingPrice(costPrice, shippingCost) {
    const base = (Number(costPrice) || 0) + (Number(shippingCost) || 0);
    if (base <= 0) return 0;
    return roundTo5k(Math.round(base * 1.5));
}

function formatRupiahPlain(value) {
    return new Intl.NumberFormat('id-ID').format(value || 0);
}

// --- Form Tambah Barang (/barang) -------------------------------------------

function initProductForm() {
    const form = document.querySelector('[data-product-form]');
    if (!form) return;

    const categoryEl   = form.querySelector('[name="category"]');
    const costEl       = form.querySelector('[name="cost_price"]');
    const shippingEl   = form.querySelector('[data-shipping-cost]');
    const sellingEl    = form.querySelector('[name="selling_price"]');
    const previewEl    = form.querySelector('[data-price-preview]');
    const discMaxEl    = form.querySelector('[data-disc-max]');

    function updateShippingFromCategory() {
        if (!categoryEl || !shippingEl) return;
        const autoVal = shippingCostForCategory(categoryEl.value);
        shippingEl.value = autoVal;
        recalculate();
    }

    function recalculate() {
        const cost     = Number(costEl ? costEl.value : 0) || 0;
        const shipping = Number(shippingEl ? shippingEl.value : 0) || 0;
        const selling  = calculateSellingPrice(cost, shipping);
        const discMax  = Math.round(selling * 0.1);

        if (sellingEl) sellingEl.value = selling || '';

        if (previewEl) {
            const total = cost + shipping;
            if (total > 0) {
                previewEl.textContent =
                    'Modal Rp ' + formatRupiahPlain(cost) +
                    ' + Ongkir Rp ' + formatRupiahPlain(shipping) +
                    ' = Rp ' + formatRupiahPlain(total) +
                    ' \u2192 +50% = Rp ' + formatRupiahPlain(Math.round(total * 1.5)) +
                    ' \u2192 Dibulatkan: Rp ' + formatRupiahPlain(selling);
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

    if (categoryEl) categoryEl.addEventListener('change', updateShippingFromCategory);
    if (costEl)     costEl.addEventListener('input', recalculate);
    if (shippingEl) shippingEl.addEventListener('input', recalculate);

    updateShippingFromCategory();
}

// --- Form Restock Owner (owner-dashboard) -----------------------------------

function initRestockForm() {
    const form = document.querySelector('[data-restock-form]');
    if (!form) return;

    const productEl   = form.querySelector('[name="product_id"]');
    const costEl      = form.querySelector('[name="unit_cost"]');
    const shippingEl  = form.querySelector('[data-restock-shipping]');
    const previewEl   = form.querySelector('[data-restock-preview]');

    function getCategoryForProduct(productId) {
        const map = window.PRODUCT_CATEGORIES || {};
        return map[productId] || '';
    }

    function updateShippingFromProduct() {
        if (!productEl || !shippingEl) return;
        const category = getCategoryForProduct(productEl.value);
        shippingEl.value = shippingCostForCategory(category);
        recalculate();
    }

    function recalculate() {
        const cost     = Number(costEl ? costEl.value : 0) || 0;
        const shipping = Number(shippingEl ? shippingEl.value : 0) || 0;
        const selling  = calculateSellingPrice(cost, shipping);
        const discMax  = Math.round(selling * 0.1);

        if (previewEl) {
            const total = cost + shipping;
            if (total > 0) {
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
                    ' + Ongkir Rp ' + formatRupiahPlain(shipping) +
                    ' = Rp ' + formatRupiahPlain(total) +
                    ' \u2192 +50% \u2192 '
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

    if (productEl) productEl.addEventListener('change', updateShippingFromProduct);
    if (costEl)    costEl.addEventListener('input', recalculate);
    if (shippingEl) shippingEl.addEventListener('input', recalculate);
}

document.addEventListener('DOMContentLoaded', function () {
    initProductForm();
    initRestockForm();
});
