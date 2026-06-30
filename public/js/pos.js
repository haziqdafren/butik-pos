const formatRupiah = (value) => new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(value || 0);

window.POS = {
    cart: [],
    add(product) {
        const found = this.cart.find((item) => item.id === product.id);
        if (found) {
            if (found.qty >= product.stock) return;
            found.qty += 1;
        } else {
            this.cart.push({ ...product, qty: 1 });
        }
        this.render();
    },
    remove(id) {
        this.cart = this.cart.filter((item) => item.id !== id);
        this.render();
    },
    qty(id, value) {
        const item = this.cart.find((line) => line.id === id);
        if (!item) return;
        item.qty = Math.max(1, Math.min(Number(value || 1), item.stock));
        this.render();
    },
    total() {
        return this.cart.reduce((sum, item) => sum + item.selling_price * item.qty, 0);
    },
    discountAmount() {
        const subtotal = this.total();
        const type  = document.querySelector("[name='discount_type']")?.value || "amount";
        const value = getMoneyRaw(document.querySelector("[data-discount-value]") || document.querySelector("[name='discount_value']"));
        const amount = type === "percent"
            ? Math.round(subtotal * Math.min(value, 100) / 100)
            : value;
        return Math.min(Math.max(amount, 0), subtotal);
    },
    payable() {
        return Math.max(0, this.total() - this.discountAmount());
    },
    change() {
        const paid = getMoneyRaw(document.querySelector("[name='amount_paid']") || document.querySelector("[data-rupiah][data-for='amount_paid']"));
        return Math.max(0, paid - this.payable());
    },
    refreshSummary() {
        const subtotal = document.querySelector("[data-subtotal]");
        const discount = document.querySelector("[data-discount-preview]");
        const payable  = document.querySelector("[data-payable-preview]");
        const change   = document.querySelector("[data-change-preview]");
        const paidHint = document.querySelector("[data-paid-hint]");

        if (subtotal) subtotal.textContent = formatRupiah(this.total());
        if (discount) discount.textContent = "- " + formatRupiah(this.discountAmount());
        if (payable)  payable.textContent  = formatRupiah(this.payable());
        if (change)   change.textContent   = formatRupiah(this.change());
        if (paidHint) paidHint.textContent = "Minimal pembayaran: " + formatRupiah(this.payable());
    },
    showAlert(message) {
        const el = document.querySelector("[data-pos-alert]");
        if (!el) return;
        el.textContent = message;
        el.hidden = false;
    },
    clearAlert() {
        const el = document.querySelector("[data-pos-alert]");
        if (!el) return;
        el.hidden = true;
        el.textContent = "";
    },
    render() {
        const list          = document.querySelector("[data-cart-list]");
        const checkoutItems = document.querySelectorAll("[data-items-target]");
        if (!list) return;

        // Cart HTML uses trusted server data (product names/SKUs from DB — not user input)
        // eslint-disable-next-line no-unsanitized/property
        list.innerHTML = this.cart.length ? this.cart.map((item) =>
            '<div class="cart-line">' +
                '<div><strong>' + item.name + '</strong><br><small class="muted">' + item.sku + '</small></div>' +
                '<input class="input" type="number" min="1" max="' + item.stock + '" value="' + item.qty + '" onchange="POS.qty(' + item.id + ', this.value)">' +
                '<span class="money">' + formatRupiah(item.selling_price * item.qty) + '</span>' +
                '<button type="button" class="button secondary" onclick="POS.remove(' + item.id + ')">X</button>' +
            '</div>'
        ).join("") : '<p class="muted">Keranjang masih kosong.</p>';

        const inputs = this.cart.map((item, index) =>
            '<input type="hidden" name="items[' + index + '][product_id]" value="' + item.id + '">' +
            '<input type="hidden" name="items[' + index + '][qty]" value="' + item.qty + '">'
        ).join("");
        checkoutItems.forEach((target) => { target.innerHTML = inputs; });
        this.refreshSummary();
    },
};

// ── Money masking ─────────────────────────────────────────────────────────────

// Read the raw numeric value from a masked or plain input
function getMoneyRaw(el) {
    if (!el) return 0;
    // If masked, read from sibling hidden input
    var hiddenName = el.dataset.maskedName;
    if (hiddenName) {
        var h = el.parentNode && el.parentNode.querySelector('input[type="hidden"][data-mask-for="' + hiddenName + '"]');
        if (h) return parseFloat(h.value) || 0;
    }
    return parseFloat((el.value || '').replace(/\./g, '').replace(',', '.')) || 0;
}

// Format integer as "100.000" (id-ID thousands)
function fmtThousands(n) {
    return (parseInt(n, 10) || 0).toLocaleString('id-ID');
}

/**
 * attachRupiahMask — turns a number/text input into a live-formatted money field.
 * Inserts a hidden sibling with the original name to carry the raw value to the server.
 */
function attachRupiahMask(input) {
    if (input.dataset.maskAttached) return;
    input.dataset.maskAttached = 'rupiah';

    var originalName = input.name || '';
    input.dataset.maskedName = originalName;
    if (originalName) input.removeAttribute('name');

    // Hidden sibling carries the real value
    var hidden = document.createElement('input');
    hidden.type              = 'hidden';
    hidden.name              = originalName;
    hidden.dataset.maskFor   = originalName;
    input.parentNode.insertBefore(hidden, input.nextSibling);

    // Seed from existing value (old() repopulation or programmatic set)
    var seed = parseInt((input.value || '').replace(/\D/g, ''), 10) || 0;
    input.value  = seed > 0 ? fmtThousands(seed) : '';
    hidden.value = seed > 0 ? seed : '';
    input.type   = 'text';
    input.inputMode = 'numeric';
    input.autocomplete = 'off';

    function refresh() {
        var raw    = (input.value || '').replace(/\D/g, '');
        var num    = parseInt(raw, 10) || 0;
        var oldLen = input.value.length;
        var selEnd = input.selectionEnd || 0;

        hidden.value = num > 0 ? num : '';
        input.value  = num > 0 ? fmtThousands(num) : '';

        // Restore cursor adjusted for dot insertion/removal
        var diff = input.value.length - oldLen;
        try { input.setSelectionRange(selEnd + diff, selEnd + diff); } catch (e) {}
    }

    input.addEventListener('input', refresh);

    // Allow external code (pricing.js) to set raw value and trigger reformat
    input.setRaw = function(n) {
        var num  = parseInt(n, 10) || 0;
        hidden.value = num > 0 ? num : '';
        input.value  = num > 0 ? fmtThousands(num) : '';
    };
    input.getRaw = function() {
        return parseInt(hidden.value, 10) || 0;
    };
}

/**
 * attachPercentMask — for the discount_value field when type is "percent".
 * While typing: plain digits. On blur: appends "%". On focus: strips "%".
 */
function attachPercentMask(input) {
    if (input.dataset.maskAttached) return;
    input.dataset.maskAttached = 'percent';

    var originalName = input.name || '';
    input.dataset.maskedName = originalName;
    if (originalName) input.removeAttribute('name');

    var hidden = document.createElement('input');
    hidden.type            = 'hidden';
    hidden.name            = originalName;
    hidden.dataset.maskFor = originalName;
    input.parentNode.insertBefore(hidden, input.nextSibling);

    var seed = parseFloat((input.value || '').replace(/[^0-9.]/g, '')) || 0;
    input.value  = seed > 0 ? seed + '%' : '';
    hidden.value = seed > 0 ? seed : '';
    input.type   = 'text';
    input.inputMode = 'numeric';
    input.autocomplete = 'off';

    input.addEventListener('focus', function() {
        input.value = hidden.value ? String(hidden.value) : '';
    });
    input.addEventListener('input', function() {
        var raw  = (input.value || '').replace(/[^0-9.]/g, '');
        var num  = parseFloat(raw) || 0;
        hidden.value = num > 0 ? num : '';
        // Don't append % while typing — do it on blur
    });
    input.addEventListener('blur', function() {
        var num  = parseFloat((input.value || '').replace(/[^0-9.]/g, '')) || 0;
        hidden.value = num > 0 ? num : '';
        input.value  = num > 0 ? num + '%' : '';
    });

    input.setRaw = function(n) {
        var num  = parseFloat(n) || 0;
        hidden.value = num > 0 ? num : '';
        input.value  = num > 0 ? num + '%' : '';
    };
    input.getRaw = function() {
        return parseFloat(hidden.value) || 0;
    };
}

/**
 * Tear down masking on discount_value so it can be re-masked with a different type.
 */
function teardownMask(input) {
    if (!input) return;
    var maskFor = input.dataset.maskedName;
    if (maskFor) {
        var h = input.parentNode.querySelector('input[type="hidden"][data-mask-for="' + maskFor + '"]');
        if (h) h.remove();
        input.name = maskFor;
    }
    delete input.dataset.maskAttached;
    delete input.dataset.maskedName;
    input.value = '';       // clear before switching type (avoids browser rejecting non-numeric value)
    input.type  = 'number';
    input.value = '';
}

/**
 * initMoneyInputs — attach masking to all money inputs within a root element.
 * Safe to call multiple times (idempotent).
 */
function initMoneyInputs(root) {
    root = root || document;

    root.querySelectorAll('[data-rupiah]').forEach(function(el) {
        attachRupiahMask(el);
    });

    root.querySelectorAll('[data-bulk-cost]').forEach(function(el) {
        attachRupiahMask(el);
    });

    // Discount value: check current type
    root.querySelectorAll('[data-discount-value]').forEach(function(el) {
        var typeEl = document.querySelector("[name='discount_type']");
        var type   = typeEl ? typeEl.value : 'amount';
        if (type === 'percent') {
            attachPercentMask(el);
        } else {
            attachRupiahMask(el);
        }
    });
}

/**
 * When discount_type changes, re-mask discount_value with the correct format.
 */
function reinitDiscountMask() {
    var valueEl = document.querySelector('[data-discount-value]');
    if (!valueEl) return;
    teardownMask(valueEl);

    var typeEl = document.querySelector("[name='discount_type']");
    var type   = typeEl ? typeEl.value : 'amount';
    if (type === 'percent') {
        attachPercentMask(valueEl);
    } else {
        attachRupiahMask(valueEl);
    }
    window.POS.refreshSummary();
}

// ── Event delegation ──────────────────────────────────────────────────────────

document.addEventListener("input", function(event) {
    var t = event.target;
    if (t.matches("[data-discount-value], [data-rupiah], [data-bulk-cost]") ||
        t.matches("[name='discount_reason']")) {
        if (t.matches("[name='discount_reason']")) window.POS.clearAlert();
        window.POS.refreshSummary();
    }
});

document.addEventListener("change", function(event) {
    if (event.target.matches("[name='discount_type']")) {
        reinitDiscountMask();
    }
});

document.addEventListener("submit", function(event) {
    if (!event.target.matches("[data-pos-checkout-form]")) return;

    // Read from hidden sibling (set by money mask) or fallback to field value
    var discountHidden = document.querySelector("input[type='hidden'][data-mask-for='discount_value']");
    var discountValue  = discountHidden ? (parseFloat(discountHidden.value) || 0) : 0;
    var discountReason = (document.querySelector("[name='discount_reason']") || {}).value;
    discountReason = (discountReason || '').trim();

    if (discountValue > 0 && !discountReason) {
        event.preventDefault();
        window.POS.showAlert("Alasan diskon wajib diisi. Keranjang tetap aman, isi catatan diskon lalu lanjutkan transaksi.");
        var el = document.querySelector("[name='discount_reason']");
        if (el) el.focus();
    }
});

document.addEventListener("DOMContentLoaded", function() {
    initMoneyInputs();

    if (Array.isArray(window.POS_INITIAL_CART) && window.POS_INITIAL_CART.length) {
        window.POS.cart = window.POS_INITIAL_CART;
        window.POS.render();
        return;
    }
    window.POS.refreshSummary();
});

// ── Size select ───────────────────────────────────────────────────────────────

function sizeSelectChange(sel) {
    var wrapper = sel.closest('[data-size-wrapper]');
    var custom  = wrapper.querySelector('[data-size-custom]');
    var hidden  = wrapper.querySelector('input[type="hidden"]');
    if (sel.value === 'other') {
        custom.hidden = false;
        custom.oninput = function() { hidden.value = custom.value; };
        hidden.value = custom.value;
    } else {
        custom.hidden = true;
        hidden.value  = sel.value;
    }
}

// ── Bulk table row management ─────────────────────────────────────────────────

function addProductRow() {
    var tbody = document.getElementById('bulk-tbody');
    if (!tbody) return;
    var rows  = tbody.querySelectorAll('tr');
    var clone = rows[rows.length - 1].cloneNode(true);

    // Remove money-mask hidden inputs injected by attachRupiahMask
    clone.querySelectorAll('input[type="hidden"][data-mask-for]').forEach(function(h) {
        h.remove();
    });

    // Reset masking flags and restore original state on cloned inputs
    clone.querySelectorAll('input[data-mask-attached]').forEach(function(i) {
        var origName = i.dataset.maskedName || '';
        if (origName) i.name = origName;
        delete i.dataset.maskAttached;
        delete i.dataset.maskedName;
        i.type  = 'number';
        i.value = '';
    });

    // Reset remaining inputs
    clone.querySelectorAll('input[type="number"], input[type="text"]:not([data-size-custom])').forEach(function(i) {
        if (!i.dataset.maskAttached) {
            i.value = (i.name && i.name.includes('stock')) ? '1' : '';
        }
    });

    // Reset selects
    clone.querySelectorAll('select').forEach(function(s) { s.selectedIndex = 0; });

    // Reset size inputs
    clone.querySelectorAll('[data-size-custom]').forEach(function(c) {
        c.hidden = true; c.value = '';
    });
    clone.querySelectorAll('input[type="hidden"]').forEach(function(h) {
        if (h.name && h.name.includes('size')) h.value = 'S';
    });

    // Re-index names
    var newIndex = rows.length;
    clone.querySelectorAll('[name]').forEach(function(el) {
        el.name = el.name.replace(/rows\[\d+\]/, 'rows[' + newIndex + ']');
    });

    tbody.appendChild(clone);

    // Re-attach money masking for the new row
    initMoneyInputs(clone);

    // Re-attach auto-pricing (defined in pricing.js)
    if (typeof window.initBulkRow === 'function') {
        window.initBulkRow(clone);
    }
}

function removeProductRow(btn) {
    var tbody = document.getElementById('bulk-tbody');
    if (!tbody) return;
    if (tbody.querySelectorAll('tr').length > 1) {
        btn.closest('tr').remove();
        tbody.querySelectorAll('tr').forEach(function(row, idx) {
            row.querySelectorAll('[name]').forEach(function(el) {
                el.name = el.name.replace(/rows\[\d+\]/, 'rows[' + idx + ']');
            });
        });
    }
}

function copyProductRow(btn) {
    var tbody = document.getElementById('bulk-tbody');
    if (!tbody) return;
    var sourceRow = btn.closest('tr');
    if (!sourceRow) return;

    // Step 1: collect field values from source row by field key (strip row index)
    var vals = {};
    sourceRow.querySelectorAll('input[name], select[name]').forEach(function(el) {
        var key = el.name.replace(/^rows\[\d+\]\[/, '').replace(/\]$/, '');
        vals[key] = el.value;
    });

    // Step 2: use addProductRow() to create a clean, properly-masked new row
    addProductRow();

    // Step 3: grab the newly added row (last row in tbody)
    var rows = tbody.querySelectorAll('tr');
    var newRow = rows[rows.length - 1];

    // Step 4: fill in ALL values from source row
    newRow.querySelectorAll('input[name], select[name]').forEach(function(el) {
        var key = el.name.replace(/^rows\[\d+\]\[/, '').replace(/\]$/, '');
        if (vals[key] !== undefined) {
            el.value = vals[key];
            el.dispatchEvent(new Event('change', { bubbles: true }));
            el.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });
}
