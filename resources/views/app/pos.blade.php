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
    <div class="pos-grid">
        <section>
            <div class="toolbar">
                <input class="input" style="max-width:420px" placeholder="Cari nama, SKU, kategori, warna" oninput="document.querySelectorAll('[data-product-card]').forEach(card => card.hidden = !card.dataset.search.includes(this.value.toLowerCase()))">
                <a class="button secondary" href="{{ route('products.index') }}">Input Barang</a>
            </div>
            <div class="product-grid">
                @foreach($products as $product)
                    <button type="button" class="product-card" data-product-card data-search="{{ strtolower($product->name.' '.$product->sku.' '.$product->category.' '.$product->color.' '.$product->size) }}" onclick='POS.add(@json($product))'>
                        <small>{{ $product->sku }}</small>
                        <strong>{{ $product->name }}</strong>
                        <small>{{ $product->category }} · {{ $product->color }} · {{ $product->size }}</small>
                        <p class="money">Rp {{ number_format($product->selling_price, 0, ',', '.') }}</p>
                        <span class="badge {{ $product->stockBadgeClass() }}">{{ $product->stockLabel() }}</span>
                    </button>
                @endforeach
            </div>
        </section>

        <aside class="card">
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
                        <input class="input" type="number" name="discount_value" min="0" value="{{ old('discount_value', 0) }}" data-rupiah>
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

    @if(session('print_sale_id'))
        <script>
            (function () {
                var saleId = {{ (int) session('print_sale_id') }};
                var url    = '/kasir/struk/' + saleId;
                var popup  = window.open(url, 'receipt_' + saleId, 'width=340,height=700,scrollbars=yes,resizable=yes');
                if (!popup) {
                    window.open(url, '_blank');
                }
            })();
        </script>
    @endif
</x-layouts.app>
