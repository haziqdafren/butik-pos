<x-layouts.app title="Input Barang dan Pembelian">
    <section class="card">
            <h3>Tambah Barang Baru</h3>
            <form method="post" action="{{ route('products.store') }}" data-product-form>
                @csrf
                <div class="grid-3">
                    <div class="field"><label>Nama Barang</label><input class="input" name="name" required></div>
                    <div class="field">
                        <label>Kategori</label>
                        <select class="input" name="category" required>
                            @foreach($categories as $category)<option>{{ $category }}</option>@endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Toko</label>
                        <select class="input" name="store_id" required>
                            @foreach($stores as $store)<option value="{{ $store->id }}">{{ $store->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="field"><label>Warna</label><input class="input" name="color"></div>
                    <div class="field"><label>Ukuran</label><x-size-select name="size" value="" /></div>
                    <div class="field"><label>Supplier</label><input class="input" name="supplier"></div>
                    <div class="field">
                        <label>Ongkos Kirim/pcs</label>
                        <input class="input" type="number" data-shipping-cost min="0" placeholder="Otomatis dari kategori">
                        <small class="muted">Jeans: Rp 20.000 &middot; Lainnya: Rp 15.000 (bisa diubah)</small>
                    </div>
                    <div class="field"><label>Harga Modal</label><input class="input" type="number" name="cost_price" required></div>
                    <div class="field">
                        <label>Harga Jual <small class="muted">(otomatis, bisa diubah)</small></label>
                        <input class="input" type="number" name="selling_price" required>
                        <small data-price-preview class="muted" hidden></small>
                        <small data-disc-max class="muted" style="color:#059669" hidden></small>
                    </div>
                    <div class="field"><label>Stok Awal</label><input class="input" type="number" name="stock" value="1" required></div>
                    <div class="field"><label>Batas Stok Minimum</label><input class="input" type="number" name="min_stock" value="3" required></div>
                </div>
                <button class="button">Simpan Barang</button>
            </form>
    </section>

    <section class="card" style="margin-top:16px">
        <h3>Stok Barang</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>SKU</th><th>Nama</th><th>Kategori</th><th>Warna</th><th>Ukuran</th><th>Stok</th><th>Modal</th><th>Jual</th></tr></thead>
                <tbody>
                @foreach($products as $product)
                    <tr>
                        <td>{{ $product->sku }}</td>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->category }}</td>
                        <td>{{ $product->color }}</td>
                        <td>{{ $product->size }}</td>
                        <td><span class="badge {{ $product->stock <= $product->min_stock ? 'amber' : 'green' }}">{{ $product->stock }}</span></td>
                        <td>Rp {{ number_format($product->cost_price, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format($product->selling_price, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
