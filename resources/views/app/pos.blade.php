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
                        <small>{{ $product->category }}@if($product->color) · {{ $product->color }}@endif@if($product->size) · {{ $product->size }}@endif</small>
                        <p class="money">Rp {{ number_format($product->selling_price, 0, ',', '.') }}</p>
                        <span class="badge {{ $product->stockBadgeClass() }}">{{ $product->stockLabel() }}</span>
                    </button>
                @endforeach
            </div>
            <p id="posNoResults" hidden style="padding:32px;text-align:center;color:var(--muted);font-size:14px">Tidak ada produk ditemukan.</p>

            <script>
            var _posActiveStore = 'all';
            function filterPosStore(storeId, btn) {
                _posActiveStore = storeId;
                document.querySelectorAll('.pos-store-tab').forEach(function(t){ t.classList.remove('active'); });
                btn.classList.add('active');
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
            </script>
        </section>

        <aside class="card" id="posPanelCart">
            <h3>Keranjang Transaksi</h3>
            <div data-cart-list><p class="muted">Keranjang masih kosong.</p></div>

            <form method="post" action="{{ route('sales.checkout') }}" style="margin-top:16px" data-pos-checkout-form>
                @csrf
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
</x-layouts.app>
