<x-layouts.app title="Input Barang dan Pembelian">

    @if(session('status'))
        <div class="notice" style="background:#ecfdf5;color:#065f46;border:1px solid #6ee7b7;padding:12px 16px;border-radius:8px;margin-bottom:16px">
            {{ session('status') }}
        </div>
    @endif

    <section class="card">
        <div class="toolbar" style="justify-content:space-between;align-items:center;margin-bottom:16px">
            <h3 style="margin:0">Input Barang Baru</h3>
            <button type="button" class="button secondary" onclick="addProductRow()">+ Tambah Baris</button>
        </div>

        <form method="post" action="{{ route('products.bulk-store') }}" id="bulk-form">
            @csrf
            @if($errors->any())
                <div class="error" style="margin-bottom:12px">
                    <strong>Ada kesalahan input:</strong>
                    <ul style="margin:4px 0 0 16px">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="bulk-table-wrap">
                <table class="bulk-table">
                    <thead>
                        <tr>
                            <th>Nama Barang *</th>
                            <th>Kategori *</th>
                            <th>Toko *</th>
                            <th>Warna</th>
                            <th>Ukuran</th>
                            <th>Supplier</th>
                            <th>Modal (Rp) *</th>
                            <th>Jual (Rp) *</th>
                            <th>Stok *</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="bulk-tbody">
                        <tr>
                            <td><input class="input" type="text" name="rows[0][name]" required maxlength="120" placeholder="Nama barang"></td>
                            <td>
                                <select class="input" name="rows[0][category]" required>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat }}">{{ ucfirst($cat) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="input" name="rows[0][store_id]" required>
                                    @foreach($stores as $store)
                                        <option value="{{ $store->id }}">{{ $store->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input class="input" type="text" name="rows[0][color]" placeholder="Warna" maxlength="60"></td>
                            <td>
                                @php $sizes = ['XS','S','M','L','XL','XXL','All Size']; @endphp
                                <div data-size-wrapper>
                                    <select class="input" data-size-select onchange="sizeSelectChange(this)">
                                        @foreach($sizes as $s)
                                            <option value="{{ $s }}" @selected($s === 'S')>{{ $s }}</option>
                                        @endforeach
                                        <option value="other">--- Lainnya ---</option>
                                    </select>
                                    <input class="input" type="text" data-size-custom hidden placeholder="Manual">
                                    <input type="hidden" name="rows[0][size]" value="S">
                                </div>
                            </td>
                            <td><input class="input" type="text" name="rows[0][supplier]" placeholder="Supplier" maxlength="120"></td>
                            <td>
                                <input class="input" type="number" name="rows[0][cost_price]" min="0" required placeholder="0" data-rupiah>
                                <small class="muted" data-rp-preview hidden></small>
                            </td>
                            <td>
                                <input class="input" type="number" name="rows[0][selling_price]" min="0" required placeholder="0" data-rupiah>
                                <small class="muted" data-rp-preview hidden></small>
                            </td>
                            <td><input class="input" type="number" name="rows[0][stock]" min="0" required value="1" style="width:64px"></td>
                            <td>
                                <button type="button" class="button danger mini" onclick="removeProductRow(this)" title="Hapus baris">×</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:14px;display:flex;gap:10px;align-items:center">
                <button class="button" type="submit">Simpan Semua Barang</button>
                <button type="button" class="button secondary" onclick="addProductRow()">+ Tambah Baris</button>
            </div>
        </form>
    </section>

    <section class="card" style="margin-top:16px">
        <h3>Stok Barang</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>SKU</th><th>Nama</th><th>Kategori</th><th>Warna</th><th>Ukuran</th><th>Stok</th><th>Modal</th><th>Jual</th></tr></thead>
                <tbody>
                @forelse($products as $product)
                    <tr>
                        <td>{{ $product->sku }}</td>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->category }}</td>
                        <td>{{ $product->color }}</td>
                        <td>{{ $product->size }}</td>
                        <td><span class="badge {{ $product->stockBadgeClass() }}">{{ $product->stockLabel() }}</span></td>
                        <td>Rp {{ number_format($product->cost_price, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format($product->selling_price, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Belum ada barang.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

</x-layouts.app>
