<x-layouts.app title="Laporan Stok">
    <div class="grid-4">
        <div class="card metric info"><small>Total Produk</small><strong>{{ $stockSummary['total'] }}</strong></div>
        <div class="card metric good"><small>Stok Aman</small><strong>{{ $stockSummary['aman'] }}</strong></div>
        <div class="card metric warn"><small>Stok Kritis (≤3)</small><strong>{{ $stockSummary['kritis'] }}</strong></div>
        <div class="card metric hot"><small>Stok Habis</small><strong>{{ $stockSummary['habis'] }}</strong></div>
    </div>

    <section class="card" style="margin-top:16px">
        <div class="toolbar" style="justify-content:space-between;align-items:center;margin-bottom:12px">
            <h3 style="margin:0">Laporan Stok Barang</h3>
            <a href="{{ route('owner.reports') }}" class="button secondary">Laporan Penjualan</a>
        </div>
        <form method="get" action="{{ route('owner.stock-report') }}" class="filter-bar">
            <input class="input" type="search" name="search_product" value="{{ $searchProduct ?? '' }}" placeholder="Cari nama, SKU, kategori...">
            <button class="button secondary" type="submit">Cari</button>
            @if($searchProduct)
                <a href="{{ route('owner.stock-report') }}" class="button secondary">Reset</a>
            @endif
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="col-hide-mobile">SKU</th>
                        <th>Barang</th>
                        <th class="col-hide-mobile">Kategori</th>
                        <th class="col-hide-mobile">Warna</th>
                        <th class="col-hide-mobile">Ukuran</th>
                        <th class="col-hide-mobile">Toko</th>
                        <th class="col-hide-mobile">Modal</th>
                        <th class="col-hide-mobile">Jual</th>
                        <th>Stok</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($products as $product)
                    <tr>
                        <td class="col-hide-mobile" style="font-size:11px;color:var(--muted)">{{ $product->sku }}</td>
                        <td><strong>{{ $product->name }}</strong></td>
                        <td class="col-hide-mobile">{{ $product->category }}</td>
                        <td class="col-hide-mobile">{{ $product->color ?: '-' }}</td>
                        <td class="col-hide-mobile">{{ $product->size ?: '-' }}</td>
                        <td class="col-hide-mobile">{{ $product->store->name }}</td>
                        <td class="col-hide-mobile">Rp {{ number_format($product->cost_price, 0, ',', '.') }}</td>
                        <td class="col-hide-mobile">Rp {{ number_format($product->selling_price, 0, ',', '.') }}</td>
                        <td><strong>{{ $product->stock }}</strong></td>
                        <td><span class="badge {{ $product->stockBadgeClass() }}">{{ $product->stockLabel() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="muted">Tidak ada produk ditemukan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-pager :paginator="$products" />
    </section>
</x-layouts.app>
