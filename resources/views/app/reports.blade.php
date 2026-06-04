<x-layouts.app title="Laporan Pendapatan">
    <div class="grid-4">
        <div class="card metric hot"><small>Pendapatan</small><strong>Rp {{ number_format($summary['revenue'], 0, ',', '.') }}</strong></div>
        <div class="card metric good"><small>Profit</small><strong>Rp {{ number_format($summary['profit'], 0, ',', '.') }}</strong></div>
        <div class="card metric warn"><small>HPP</small><strong>Rp {{ number_format($summary['cogs'], 0, ',', '.') }}</strong></div>
        <div class="card metric info"><small>Item Terjual</small><strong>{{ $summary['items'] }}</strong></div>
    </div>
    <section class="card" style="margin-top:16px">
        <h3>Riwayat Penjualan</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Invoice</th><th>Tanggal</th><th>Kasir</th><th>Item</th><th>Diskon</th><th>Total</th><th>Profit</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                @forelse($sales as $sale)
                    <tr>
                        <td>{{ $sale->invoice_number }}</td>
                        <td>{{ $sale->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $sale->cashier->name }}</td>
                        <td>{{ $sale->items->sum('qty') }}</td>
                        <td>Rp {{ number_format($sale->discount_amount, 0, ',', '.') }}</td>
                        <td class="money">Rp {{ number_format($sale->total, 0, ',', '.') }}</td>
                        <td class="money">Rp {{ number_format($sale->profit, 0, ',', '.') }}</td>
                        <td><span class="badge {{ $sale->status === 'voided' ? 'red' : 'green' }}">{{ $sale->status === 'voided' ? 'Void' : 'Selesai' }}</span></td>
                        <td><label class="button secondary mini" for="owner-sale-detail-{{ $sale->id }}">Detail</label></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">Belum ada transaksi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-pager :paginator="$sales" />
    </section>
    <section class="card" style="margin-top:16px">
        <h3>Laporan Stok</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>SKU</th><th>Barang</th><th>Kategori</th><th>Toko</th><th>Stok</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                @foreach($products as $product)
                    <tr>
                        <td>{{ $product->sku }}</td>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->category }}</td>
                        <td>{{ $product->store->name }}</td>
                        <td>{{ $product->stock }}</td>
                        <td><span class="badge {{ $product->stock <= $product->min_stock ? 'red' : 'green' }}">{{ $product->stock <= $product->min_stock ? 'Perlu restock' : 'Aman' }}</span></td>
                        <td><label class="button secondary mini" for="owner-product-detail-{{ $product->id }}">Detail</label></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <x-pager :paginator="$products" />
    </section>

    @foreach($sales as $sale)
        <input class="modal-toggle" type="checkbox" id="owner-sale-detail-{{ $sale->id }}">
        <div class="modal">
            <div class="modal-card">
                <div class="modal-head">
                    <h3>Detail {{ $sale->invoice_number }}</h3>
                    <label class="button secondary mini" for="owner-sale-detail-{{ $sale->id }}">Tutup</label>
                </div>
                <div class="modal-body">
                    <div class="detail-grid">
                        <div class="detail-box"><small>Kasir</small><strong>{{ $sale->cashier->name }}</strong></div>
                        <div class="detail-box"><small>Total</small><strong>Rp {{ number_format($sale->total, 0, ',', '.') }}</strong></div>
                        <div class="detail-box"><small>Profit</small><strong>Rp {{ number_format($sale->profit, 0, ',', '.') }}</strong></div>
                        <div class="detail-box"><small>Diskon</small><strong>Rp {{ number_format($sale->discount_amount, 0, ',', '.') }}</strong></div>
                        <div class="detail-box"><small>Metode</small><strong>{{ $sale->payment_method }}</strong></div>
                        <div class="detail-box"><small>Status</small><strong>{{ $sale->status }}</strong></div>
                    </div>
                    <table>
                        <thead><tr><th>SKU</th><th>Barang</th><th>Qty</th><th>Modal</th><th>Harga</th></tr></thead>
                        <tbody>
                        @foreach($sale->items as $item)
                            <tr>
                                <td>{{ $item->sku }}</td>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->qty }}</td>
                                <td>Rp {{ number_format($item->unit_cost * $item->qty, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format($item->line_total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <p><strong>Catatan diskon:</strong> {{ $sale->discount?->reason ?? '-' }}</p>
                    @forelse($sale->corrections as $correction)
                        <p><strong>Koreksi {{ $correction->status }}:</strong> {{ $correction->reason }} · diminta oleh {{ $correction->requester->name }}</p>
                    @empty
                        <p class="muted">Tidak ada koreksi.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endforeach

    @foreach($products as $product)
        <input class="modal-toggle" type="checkbox" id="owner-product-detail-{{ $product->id }}">
        <div class="modal">
            <div class="modal-card">
                <div class="modal-head">
                    <h3>{{ $product->name }}</h3>
                    <label class="button secondary mini" for="owner-product-detail-{{ $product->id }}">Tutup</label>
                </div>
                <div class="modal-body">
                    <div class="detail-grid">
                        <div class="detail-box"><small>SKU</small><strong>{{ $product->sku }}</strong></div>
                        <div class="detail-box"><small>Kategori</small><strong>{{ $product->category }}</strong></div>
                        <div class="detail-box"><small>Toko</small><strong>{{ $product->store->name }}</strong></div>
                        <div class="detail-box"><small>Warna</small><strong>{{ $product->color ?: '-' }}</strong></div>
                        <div class="detail-box"><small>Ukuran</small><strong>{{ $product->size ?: '-' }}</strong></div>
                        <div class="detail-box"><small>Supplier</small><strong>{{ $product->supplier ?: '-' }}</strong></div>
                        <div class="detail-box"><small>Harga Modal</small><strong>Rp {{ number_format($product->cost_price, 0, ',', '.') }}</strong></div>
                        <div class="detail-box"><small>Harga Jual</small><strong>Rp {{ number_format($product->selling_price, 0, ',', '.') }}</strong></div>
                        <div class="detail-box"><small>Stok</small><strong>{{ $product->stock }} pcs</strong></div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</x-layouts.app>
