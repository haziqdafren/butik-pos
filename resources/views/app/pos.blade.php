<x-layouts.app title="Transaksi Kasir">
    @php
        $oldCart = collect(old('items', []))
            ->map(function ($item) use ($products) {
                $product = $products->firstWhere('id', (int) ($item['product_id'] ?? 0));

                if (! $product) {
                    return null;
                }

                return array_merge($product->toArray(), [
                    'qty' => max(1, (int) ($item['qty'] ?? 1)),
                ]);
            })
            ->filter()
            ->values();
    @endphp
    <script>
        window.POS_INITIAL_CART = @json($oldCart);
    </script>
    {{-- Mobile tab bar (hidden on desktop via CSS) --}}
    <div class="pos-tab-bar">
        <button class="pos-tab active" id="posTabProducts" onclick="switchPosTab('products')">Produk</button>
        <button class="pos-tab" id="posTabCart" onclick="switchPosTab('cart')">
            Keranjang <span class="pos-tab-count" id="posCartCount">0</span>
        </button>
    </div>

    <div class="pos-grid">
        <section id="posPanelProducts" class="pos-panel-active">
            {{-- Store filter tabs --}}
            <style>
            #posStoreTabs { display:flex; gap:4px; flex-wrap:wrap; margin-bottom:12px; background:#f4f7f9; border:1px solid #dfe5eb; border-radius:10px; padding:4px; }
            #posStoreTabs .pos-store-tab { padding:7px 18px; border:none !important; border-radius:7px; background:transparent; font-size:13px; font-weight:600; color:#67727f; cursor:pointer; transition:background .15s,color .15s,box-shadow .15s; white-space:nowrap; }
            #posStoreTabs .pos-store-tab:hover { color:#1d242c; background:rgba(255,255,255,.7); }
            #posStoreTabs .pos-store-tab.active { background:white !important; color:#b2472f !important; box-shadow:0 1px 4px rgba(16,33,41,.12); }
            </style>
            <div class="pos-store-tabs" id="posStoreTabs">
                <button class="pos-store-tab active" data-store="all" onclick="filterPosStore('all',this)">🏪 Semua Toko</button>
                @foreach($stores as $store)
                    <button class="pos-store-tab" data-store="{{ $store->id }}" onclick="filterPosStore('{{ $store->id }}',this)">{{ $store->name }}</button>
                @endforeach
            </div>

            {{-- Search bar --}}
            <div class="toolbar" style="margin-bottom:12px">
                <input class="input" id="posSearchInput" style="max-width:420px" placeholder="Cari nama, SKU, kategori, warna..." oninput="filterPosProducts()">
                <a class="button secondary" href="{{ route('products.index') }}">Input Barang</a>
            </div>

            <div class="product-grid" id="posProductGrid">
                @foreach($products as $product)
                    <button type="button" class="product-card" data-product-card
                        data-store="{{ $product->store_id }}"
                        data-search="{{ strtolower($product->name.' '.$product->sku.' '.$product->category.' '.($product->color ?? '').' '.($product->size ?? '')) }}"
                        onclick='POS.add(@json($product));if(window.innerWidth<=820)switchPosTab("cart")'>
                        <div class="product-card-store">{{ $product->store?->name }}</div>
                        <strong>{{ $product->name }}</strong>
                        <small>{{ $product->category }}{{ $product->color ? ' · '.$product->color : '' }}{{ $product->size ? ' · '.$product->size : '' }}</small>
                        <p class="money">Rp {{ number_format($product->selling_price, 0, ',', '.') }}</p>
                        <span style="color:{{ $product->stockColor() }};font-weight:700">{{ $product->stock }}</span>
                    </button>
                @endforeach
            </div>
            <p id="posNoResults" hidden style="padding:32px;text-align:center;color:var(--muted);font-size:14px">Tidak ada produk ditemukan.</p>

            <script>
            var _posActiveStore = 'all';
            function filterPosStore(storeId, btn) {
                _posActiveStore = String(storeId);
                document.querySelectorAll('.pos-store-tab').forEach(function(t){ t.classList.remove('active'); });
                if (btn) btn.classList.add('active');
                // Update the hidden store_id so the sale is recorded against the correct store
                var storeInput = document.getElementById('checkoutStoreId');
                if (storeInput && storeId !== 'all') storeInput.value = storeId;
                filterPosProducts();
            }
            function filterPosProducts() {
                var q = (document.getElementById('posSearchInput').value || '').toLowerCase();
                var cards = document.querySelectorAll('[data-product-card]');
                var visible = 0;
                cards.forEach(function(card) {
                    var storeMatch = _posActiveStore === 'all' || card.dataset.store === _posActiveStore;
                    var searchMatch = !q || card.dataset.search.includes(q);
                    card.hidden = !(storeMatch && searchMatch);
                    if (!card.hidden) visible++;
                });
                document.getElementById('posNoResults').hidden = visible > 0;
            }
            // Auto-select cashier's own store on load
            document.addEventListener('DOMContentLoaded', function() {
                var myStore = '{{ auth()->user()->store_id }}';
                if (myStore) {
                    var tab = document.querySelector('.pos-store-tab[data-store="' + myStore + '"]');
                    if (tab) filterPosStore(myStore, tab);
                }
            });
            </script>
        </section>

        <aside class="card" id="posPanelCart">
            <h3>Keranjang Transaksi</h3>
            <div data-cart-list><p class="muted">Keranjang masih kosong.</p></div>

            <form method="post" action="{{ route('sales.checkout') }}" style="margin-top:16px" id="checkoutForm" data-pos-checkout-form>
                @csrf
                <input type="hidden" name="store_id" id="checkoutStoreId" value="{{ auth()->user()->store_id }}">
                <div class="field" style="margin-bottom:12px">
                    <label>Sumber Stok</label>
                    <select class="input" name="stock_source">
                        <option value="">Toko Sendiri</option>
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}">Ambil dari {{ $store->name }}</option>
                        @endforeach
                    </select>
                    <small class="muted" style="font-size:12px">Isi jika barang diambil dari toko lain.</small>
                </div>
                <div class="error" data-pos-alert hidden></div>
                <div data-items-target></div>
                <div class="grid-2" style="grid-template-columns:1fr 1fr">
                    <div class="field">
                        <label>Tipe Diskon</label>
                        <select class="input" name="discount_type">
                            <option value="amount" @selected(old('discount_type', 'amount') === 'amount')>Nominal</option>
                            <option value="percent" @selected(old('discount_type') === 'percent')>Persen</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Nilai Diskon</label>
                        <input class="input" type="number" name="discount_value" min="0" value="{{ old('discount_value', 0) }}" data-discount-value>
                        <small class="muted" data-rp-preview hidden></small>
                    </div>
                </div>
                <div class="field">
                    <label>Catatan Diskon</label>
                    <input class="input" name="discount_reason" value="{{ old('discount_reason') }}" placeholder="Wajib diisi jika ada diskon">
                </div>
                <div class="payment-preview">
                    <div class="summary-row"><span>Subtotal</span><span data-subtotal>Rp 0</span></div>
                    <div class="summary-row discount"><span>Diskon</span><span data-discount-preview>- Rp 0</span></div>
                    <div class="summary-row total"><span>Total Bayar</span><span data-payable-preview>Rp 0</span></div>
                </div>
                <div class="field">
                    <label>Metode Pembayaran</label>
                    <select class="input" name="payment_method">
                        @foreach(['Tunai', 'QRIS', 'Transfer', 'Debit'] as $method)
                            <option @selected(old('payment_method', 'Tunai') === $method)>{{ $method }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Uang Diterima</label>
                    <input class="input" type="number" name="amount_paid" min="0" value="{{ old('amount_paid') }}" required data-rupiah>
                    <small class="muted" data-rp-preview hidden></small>
                    <small class="muted" data-paid-hint>Minimal pembayaran: Rp 0</small>
                </div>
                <div class="summary-row change-row"><span>Kembalian</span><span data-change-preview>Rp 0</span></div>
                <button class="button" style="width:100%">Selesaikan Transaksi</button>
            </form>
        </aside>
    </div>

    <script>
    // ── POS Mobile Tab Switcher ──────────────────────────────
    function switchPosTab(panel) {
        var products = document.getElementById('posPanelProducts');
        var cart     = document.getElementById('posPanelCart');
        var tabP     = document.getElementById('posTabProducts');
        var tabC     = document.getElementById('posTabCart');
        if (panel === 'products') {
            products.classList.add('pos-panel-active');
            cart.classList.remove('pos-panel-active');
            tabP.classList.add('active');
            tabC.classList.remove('active');
        } else {
            cart.classList.add('pos-panel-active');
            products.classList.remove('pos-panel-active');
            tabC.classList.add('active');
            tabP.classList.remove('active');
        }
    }
    // Update cart count badge on tab
    function updatePosCartCount(n) {
        var el = document.getElementById('posCartCount');
        if (el) el.textContent = n;
    }
    // Hook into POS cart renders (patch after POS.js loads)
    document.addEventListener('DOMContentLoaded', function() {
        // On tablet/desktop: always show both panels
        function ensureDesktopLayout() {
            if (window.innerWidth > 820) {
                var p = document.getElementById('posPanelProducts');
                var c = document.getElementById('posPanelCart');
                if (p) p.classList.add('pos-panel-active');
                if (c) c.classList.add('pos-panel-active');
            }
        }
        ensureDesktopLayout();
        window.addEventListener('resize', ensureDesktopLayout);

        // Observe cart list changes to update badge count
        var cartList = document.querySelector('[data-cart-list]');
        if (cartList) {
            new MutationObserver(function() {
                var lines = cartList.querySelectorAll('.cart-line');
                var total = 0;
                lines.forEach(function(l) {
                    var q = l.querySelector('input[name$="[qty]"]');
                    if (q) total += Math.max(1, parseInt(q.value) || 1);
                });
                updatePosCartCount(total);
            }).observe(cartList, { childList: true, subtree: true });
        }
    });
    </script>

    @if(session('status'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof showToast === 'function') showToast({{ Js::from(session('status')) }});
            });
        </script>
    @endif
    @if($errors->has('checkout'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof showToast === 'function') showToast({{ Js::from($errors->first('checkout')) }}, 'error');
            });
        </script>
    @endif
    @if(session('print_sale_id'))
        <script>
            (function () {
                var saleId = {{ (int) session('print_sale_id') }};
                var url    = '{{ url('/kasir/struk') }}/' + saleId;
                var popup  = window.open(url, 'receipt_' + saleId, 'width=340,height=700,scrollbars=yes,resizable=yes');
                if (!popup) {
                    window.open(url, '_blank');
                }
            })();
        </script>
    @endif

    <script>
    // ── Shared Bluetooth printer helpers ────────────────────────────────────────
    async function btFindPrintChar(server) {
        const services = await server.getPrimaryServices();
        for (const service of services) {
            const chars = await service.getCharacteristics();
            for (const char of chars) {
                if (char.properties.write || char.properties.writeWithoutResponse) {
                    return char;
                }
            }
        }
        return null;
    }

    // Cache connected printer for the page session — avoids picker on repeat prints
    let _btDevice = null;
    let _btChar   = null;

    async function btGetPrinter() {
        // 1. Reuse already-connected device from this page session
        if (_btDevice && _btDevice.gatt.connected && _btChar) {
            return { device: _btDevice, char: _btChar };
        }
        // 2. Try previously-paired device via getDevices() — no picker
        //    This works across page reloads if Permissions-Policy: bluetooth=* is set
        //    and the user previously paired through the picker on this browser.
        const wasPaired = localStorage.getItem('bt_printer_paired') === '1';
        if (navigator.bluetooth.getDevices && wasPaired) {
            try {
                const paired  = await navigator.bluetooth.getDevices();
                const printer = paired.find(d => d.name === 'RPP02N');
                if (printer) {
                    const server = await printer.gatt.connect();
                    const char   = await btFindPrintChar(server);
                    if (char) {
                        _btDevice = printer;
                        _btChar   = char;
                        return { device: printer, char };
                    }
                }
            } catch (_) {}
        }
        // 3. Full picker — only on very first use ever (or if getDevices not supported)
        const device = await navigator.bluetooth.requestDevice({
            filters: [{ name: 'RPP02N' }],
            optionalServices: [
                '000018f0-0000-1000-8000-00805f9b34fb',
                'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
                '49535343-fe7d-4ae5-8fa9-9fafd205e455'
            ]
        });
        const server = await device.gatt.connect();
        const char   = await btFindPrintChar(server);
        if (!char) throw new Error('Printer tidak valid — karakteristik write tidak ditemukan.');
        _btDevice = device;
        _btChar   = char;
        // Remember that user has paired once — future page loads will try getDevices() first
        try { localStorage.setItem('bt_printer_paired', '1'); } catch(_) {}
        return { device, char };
    }

    // Build and send ESC/POS bytes for a receipt JSON payload.
    async function btPrintReceipt(printChar, res) {
        const encoder = new TextEncoder();
        const data    = [];
        const push     = arr  => data.push(...arr);
        const pushLine = str  => data.push(...encoder.encode(str + '\n'));
        const fmtRp    = num  => 'Rp ' + new Intl.NumberFormat('id-ID').format(num);
        const fmtNum   = num  => new Intl.NumberFormat('id-ID').format(num);
        const pad      = (l, r, w) => { const sp = Math.max(0, w - l.length - r.length); return l + ' '.repeat(sp) + r; };

        push([0x1B, 0x40]);         // init
        push([0x1B, 0x61, 0x01]);   // center
        pushLine(res.store_name);
        if (res.store_address) pushLine(res.store_address);
        if (res.store_phone)   pushLine(res.store_phone);
        pushLine('--------------------------------');

        push([0x1B, 0x61, 0x00]);   // left
        pushLine('Kasir   : ' + res.cashier_name);
        pushLine('Tanggal : ' + res.created_at);
        pushLine('Invoice : ' + res.invoice_number);
        pushLine('--------------------------------');

        res.items.forEach(i => {
            pushLine(i.name);
            if (i.attrs && i.attrs.length) pushLine(i.attrs.join(' \u00b7 '));
            pushLine(pad(i.qty + 'x', fmtRp(i.line_total), 32));
        });
        pushLine('--------------------------------');
        pushLine(pad('Subtotal', fmtNum(res.subtotal), 32));
        if (res.discount_amount > 0) {
            pushLine(pad('Diskon', '- ' + fmtNum(res.discount_amount), 32));
        }
        pushLine('--------------------------------');
        push([0x1B, 0x45, 0x01]);   // bold on
        pushLine(pad('TOTAL', fmtRp(res.total), 32));
        push([0x1B, 0x45, 0x00]);   // bold off
        pushLine('--------------------------------');
        pushLine(pad(res.payment_method, fmtNum(res.amount_paid), 32));
        pushLine(pad('Kembalian', fmtNum(res.change), 32));
        pushLine('================================');
        push([0x1B, 0x61, 0x01]);   // center
        pushLine(res.receipt_footer || 'Terima kasih telah berbelanja!');
        push([0x0A, 0x0A, 0x0A]);   // feed

        const bytes = new Uint8Array(data);
        for (let i = 0; i < bytes.length; i += 100) {
            await printChar.writeValue(bytes.slice(i, i + 100));
        }
    }

    function btnSet(btn, text, disabled) {
        if (!btn) return;
        btn.textContent = text;
        btn.disabled    = disabled;
    }

    // ── Checkout form: Bluetooth path ──────────────────────────────────────────
    document.getElementById('checkoutForm').addEventListener('submit', async function(e) {
        if (!navigator.bluetooth) return; // let normal form submit happen
        e.preventDefault();

        const btn          = this.querySelector('button[type=submit], button:not([type])') || this.querySelector('button');
        const originalText = btn ? btn.textContent : '';
        btnSet(btn, 'Memulai Printer...', true);

        let printer;
        try {
            printer = await btGetPrinter();
        } catch (err) {
            btnSet(btn, originalText, false);
            if (err.name !== 'NotFoundError') {
                if (typeof showToast === 'function') showToast('Koneksi printer gagal: ' + err.message, 'warn');
            }
            if (confirm('Lanjutkan transaksi tanpa cetak Bluetooth?')) {
                this.submit();
            }
            return;
        }

        btnSet(btn, 'Menyimpan...', true);

        try {
            const formData = new FormData(this);
            formData.append('ajax_checkout', '1');
            const response = await fetch(this.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                },
                body: formData
            });
            const res = await response.json();

            if (!response.ok) {
                const msg = res.errors ? Object.values(res.errors)[0][0] : (res.message || 'Gagal checkout');
                if (typeof showToast === 'function') showToast(msg, 'error');
                btnSet(btn, originalText, false);
                if (printer.device.gatt.connected) printer.device.gatt.disconnect();
                return;
            }

            btnSet(btn, 'Mencetak...', true);
            await btPrintReceipt(printer.char, res);

            if (typeof showToast === 'function') showToast('Transaksi & cetak berhasil!');
            setTimeout(() => {
                if (printer.device.gatt.connected) printer.device.gatt.disconnect();
                window.location.href = window.location.pathname;
            }, 1500);

        } catch (err) {
            console.error(err);
            if (typeof showToast === 'function') showToast('Kesalahan cetak: ' + err.message, 'error');
            btnSet(btn, originalText, false);
            setTimeout(() => window.location.href = window.location.pathname, 2000);
        }
    });
    </script>
</x-layouts.app>
