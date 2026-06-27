<x-layouts.app title="Dashboard Owner">
    <div class="grid-4">
        <div class="card metric hot"><small>Total Pendapatan</small><strong>Rp {{ number_format($summary['revenue'], 0, ',', '.') }}</strong></div>
        <div class="card metric good"><small>Profit Bersih</small><strong>Rp {{ number_format($summary['profit'], 0, ',', '.') }}</strong></div>
        <div class="card metric warn"><small>Total Transaksi</small><strong>{{ $summary['transactions'] }}</strong></div>
        <div class="card metric info"><small>Total Produk</small><strong>{{ $productsCount }}</strong></div>
    </div>
    <div class="grid-2" style="margin-top:12px">
        <div class="card metric"><small>Diskon Diberikan</small><strong>Rp {{ number_format($summary['discounts'], 0, ',', '.') }}</strong></div>
        <div class="card metric"><small>Jumlah Kasir</small><strong>{{ $cashiersCount }} orang</strong></div>
    </div>

    {{-- Panel Notifikasi --}}
    @if($unreadCount > 0)
    <div class="card" id="notifikasi" style="margin-top:16px;border-left:4px solid #ef4444">
        <div class="toolbar" style="justify-content:space-between;align-items:center">
            <h3 style="margin:0">Notifikasi <span style="background:#ef4444;color:white;border-radius:999px;padding:2px 8px;font-size:12px">{{ $unreadCount }}</span></h3>
            <form method="post" action="{{ route('owner.notifications.read-all') }}">
                @csrf
                <button class="button secondary mini">Tandai Semua Dibaca</button>
            </form>
        </div>
        <div style="margin-top:8px">
            @foreach($notifications as $notif)
                <div style="padding:10px 0;border-bottom:1px solid #f3f4f6">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start">
                        <div>
                            <span class="badge {{ $notif->type === 'void_transaction' ? 'red' : ($notif->type === 'out_of_stock' ? 'red' : 'amber') }}">
                                {{ $notif->type === 'void_transaction' ? 'Pembatalan' : ($notif->type === 'out_of_stock' ? 'Stok Habis' : 'Stok Sedikit') }}
                            </span>
                            <strong style="margin-left:6px">{{ $notif->title }}</strong>
                            <div class="muted" style="margin-top:2px">{{ $notif->body }}</div>
                        </div>
                        <small class="muted" style="white-space:nowrap;margin-left:12px">{{ $notif->created_at->diffForHumans() }}</small>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Chart: Tren Pendapatan 7 Hari --}}
    <div class="card" style="margin-top:16px">
        <h3 style="margin-bottom:16px">Tren Pendapatan — 7 Hari Terakhir</h3>
        <div class="chart">
            @foreach($chartData as $day)
                @php($barH = $chartMax > 0 ? max(8, round(($day['total'] / $chartMax) * 120)) : 8)
                <div class="bar {{ $day['is_today'] ? 'bar-today' : '' }}" style="height:{{ $barH }}px" title="Rp {{ number_format($day['total'], 0, ',', '.') }}">
                    <span class="bar-val">{{ $day['total'] > 0 ? number_format($day['total'] / 1000, 0, ',', '.').'k' : '' }}</span>
                    <span class="bar-label">{{ $day['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Catatan Diskon --}}
    <div class="card" style="margin-top:16px">
        <h3>Catatan Diskon</h3>
        @forelse($recentDiscounts as $discount)
            <div class="toolbar" style="justify-content:space-between">
                <div>
                    <strong>#{{ $discount->id }} · Rp {{ number_format($discount->amount, 0, ',', '.') }}</strong>
                    <div class="muted">{{ $discount->requester->name }} · {{ $discount->reason }}</div>
                </div>
                <span class="badge amber">{{ $discount->type === 'percent' ? $discount->value.'%' : 'nominal' }}</span>
            </div>
        @empty
            <p class="muted">Belum ada diskon tercatat.</p>
        @endforelse
    </div>

    <div class="grid-2" style="margin-top:16px">
        <div class="card">
            <h3>Transaksi Terbaru</h3>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Invoice</th><th>Kasir</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($recentSales as $sale)
                        <tr>
                            <td><span class="invoice-cell">{{ $sale->invoice_number }}</span></td>
                            <td>{{ $sale->cashier->name }}</td>
                            <td class="money">Rp {{ number_format($sale->total, 0, ',', '.') }}</td>
                            <td>
                                @if($sale->status === 'voided')
                                    <span class="badge red">Void</span>
                                @else
                                    <span class="badge green">Selesai</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">Belum ada transaksi.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <h3>Stok Perlu Perhatian</h3>
            @forelse($lowStock as $product)
                <div class="stock-row">
                    <div class="stock-row-info">
                        <strong>{{ $product->name }}</strong>
                        <small class="sku-line">{{ $product->sku }} · {{ $product->store->name }}</small>
                        <small>Supplier: {{ $product->supplier ?: '-' }}</small>
                    </div>
                    <span class="badge {{ $product->stockBadgeClass() }}" style="flex-shrink:0">{{ $product->stockLabel() }}</span>
                </div>
            @empty
                <p class="muted">Semua stok masih aman.</p>
            @endforelse
        </div>
    </div>

    {{-- Form Restock --}}
    <div class="card" style="margin-top:16px">
        <h3>Restock Barang</h3>
        <p class="muted">Catat pembelian barang dari supplier. Stok akan otomatis bertambah dan harga modal diperbarui.</p>
        <form method="post" action="{{ route('owner.restock') }}" data-restock-form>
            @csrf
            <div class="grid-2" style="gap:12px">
                <div class="field">
                    <label>Produk <span style="color:red">*</span></label>
                    <select class="input" name="product_id" required>
                        <option value="">-- Pilih Produk --</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }} (Stok: {{ $product->stock }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Supplier</label>
                    <input class="input" name="supplier" maxlength="120" placeholder="Nama supplier / toko">
                </div>
                <div class="field">
                    <label>Ongkos Kirim/pcs</label>
                    <input class="input" type="number" data-restock-shipping data-rupiah min="0" placeholder="Otomatis dari kategori produk">
                    <small class="muted">Dihitung otomatis saat produk dipilih. Bisa diubah.</small>
                </div>
                <div class="field">
                    <label>Jumlah <span style="color:red">*</span></label>
                    <input class="input" name="qty" type="number" min="1" required placeholder="Contoh: 12 (1 bal)">
                </div>
                <div class="field">
                    <label>Harga per Unit (Rp) <span style="color:red">*</span></label>
                    <input class="input" name="unit_cost" type="number" min="0" required placeholder="Harga beli per pcs" data-rupiah>
                    <small class="muted" data-rp-preview hidden></small>
                    <small data-restock-preview class="muted" style="margin-top:4px;display:block" hidden></small>
                </div>
                <div class="field" style="grid-column:1/-1">
                    <label>Catatan Barang</label>
                    <textarea class="input" name="notes" rows="2" maxlength="1000" placeholder="Contoh: 1 bal isi 12 pcs, beli di Tanah Abang blok A..."></textarea>
                </div>
            </div>
            <button class="button" style="margin-top:12px">Simpan Restock</button>
        </form>
    </div>
<script>
window.PRODUCT_CATEGORIES = @json($products->pluck('category', 'id'));
</script>
</x-layouts.app>
