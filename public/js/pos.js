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
        const type = document.querySelector("[name='discount_type']")?.value || "amount";
        const value = Number(document.querySelector("[name='discount_value']")?.value || 0);
        const amount = type === "percent" ? Math.round(subtotal * Math.min(value, 100) / 100) : value;

        return Math.min(Math.max(amount, 0), subtotal);
    },
    payable() {
        return Math.max(0, this.total() - this.discountAmount());
    },
    change() {
        const paid = Number(document.querySelector("[name='amount_paid']")?.value || 0);

        return Math.max(0, paid - this.payable());
    },
    refreshSummary() {
        const subtotal = document.querySelector("[data-subtotal]");
        const discount = document.querySelector("[data-discount-preview]");
        const payable = document.querySelector("[data-payable-preview]");
        const change = document.querySelector("[data-change-preview]");
        const paidHint = document.querySelector("[data-paid-hint]");

        if (subtotal) subtotal.textContent = formatRupiah(this.total());
        if (discount) discount.textContent = `- ${formatRupiah(this.discountAmount())}`;
        if (payable) payable.textContent = formatRupiah(this.payable());
        if (change) change.textContent = formatRupiah(this.change());
        if (paidHint) paidHint.textContent = `Minimal pembayaran: ${formatRupiah(this.payable())}`;
    },
    showAlert(message) {
        const alert = document.querySelector("[data-pos-alert]");

        if (!alert) return;
        alert.textContent = message;
        alert.hidden = false;
    },
    clearAlert() {
        const alert = document.querySelector("[data-pos-alert]");

        if (!alert) return;
        alert.hidden = true;
        alert.textContent = "";
    },
    render() {
        const list = document.querySelector("[data-cart-list]");
        const checkoutItems = document.querySelectorAll("[data-items-target]");
        const discountItems = document.querySelector("[data-discount-items]");
        if (!list) return;

        list.innerHTML = this.cart.length ? this.cart.map((item) => `
            <div class="cart-line">
                <div><strong>${item.name}</strong><br><small class="muted">${item.sku}</small></div>
                <input class="input" type="number" min="1" max="${item.stock}" value="${item.qty}" onchange="POS.qty(${item.id}, this.value)">
                <span class="money">${formatRupiah(item.selling_price * item.qty)}</span>
                <button type="button" class="button secondary" onclick="POS.remove(${item.id})">X</button>
            </div>
        `).join("") : `<p class="muted">Keranjang masih kosong.</p>`;

        const inputs = this.cart.map((item, index) => `
            <input type="hidden" name="items[${index}][product_id]" value="${item.id}">
            <input type="hidden" name="items[${index}][qty]" value="${item.qty}">
        `).join("");
        checkoutItems.forEach((target) => target.innerHTML = inputs);
        if (discountItems) discountItems.innerHTML = inputs;
        this.refreshSummary();
    },
};

document.addEventListener("input", (event) => {
    if (event.target.matches("[name='discount_value'], [name='amount_paid'], [name='discount_reason']")) {
        if (event.target.matches("[name='discount_reason']")) {
            window.POS.clearAlert();
        }
        window.POS.refreshSummary();
    }
});

document.addEventListener("change", (event) => {
    if (event.target.matches("[name='discount_type']")) {
        window.POS.refreshSummary();
    }
});

document.addEventListener("submit", (event) => {
    if (!event.target.matches("[data-pos-checkout-form]")) {
        return;
    }

    const discountValue = Number(document.querySelector("[name='discount_value']")?.value || 0);
    const discountReason = document.querySelector("[name='discount_reason']")?.value.trim() || "";

    if (discountValue > 0 && !discountReason) {
        event.preventDefault();
        window.POS.showAlert("Alasan diskon wajib diisi. Keranjang tetap aman, isi catatan diskon lalu lanjutkan transaksi.");
        document.querySelector("[name='discount_reason']")?.focus();
    }
});

document.addEventListener("DOMContentLoaded", () => {
    initRupiahPreviews();

    if (Array.isArray(window.POS_INITIAL_CART) && window.POS_INITIAL_CART.length) {
        window.POS.cart = window.POS_INITIAL_CART;
        window.POS.render();
        return;
    }

    window.POS.refreshSummary();
});

function initRupiahPreviews() {
    document.querySelectorAll('[data-rupiah]').forEach(function (input) {
        var preview = input.parentNode.querySelector('[data-rp-preview]');
        if (!preview) return;

        function update() {
            var n = parseInt(input.value, 10);
            if (!isNaN(n) && n > 0) {
                preview.textContent = 'Rp ' + n.toLocaleString('id-ID');
                preview.hidden = false;
            } else {
                preview.hidden = true;
            }
        }

        input.addEventListener('input', update);
        input.addEventListener('blur', update);
        update();
    });
}

function sizeSelectChange(sel) {
    var wrapper = sel.closest('[data-size-wrapper]');
    var custom  = wrapper.querySelector('[data-size-custom]');
    var hidden  = wrapper.querySelector('input[type="hidden"]');
    if (sel.value === 'other') {
        custom.hidden = false;
        custom.oninput = function () { hidden.value = custom.value; };
        hidden.value = custom.value;
    } else {
        custom.hidden = true;
        hidden.value  = sel.value;
    }
}
