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
            <p class="muted" style="margin:0 0 12px;font-size:13px">
                Harga jual dihitung otomatis: <strong>Modal × 1,5</strong>, dibulatkan ke Rp 5.000 terdekat.
            </p>
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
                            <th>Harga Jual (otomatis)</th>
                            <th>Stok *</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="bulk-tbody">
                        <tr>
                            <td><input class="input" type="text" name="rows[0][name]" required maxlength="120" placeholder="Nama barang"></td>
                            <td>
                                <select class="input" name="rows[0][category]" required data-bulk-category>
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
                                <input class="input" type="number" name="rows[0][cost_price]" min="0" required placeholder="0" data-bulk-cost data-rupiah>
                                <small class="muted" data-rp-preview hidden></small>
                            </td>
                            <td>
                                <input class="input" type="number" name="rows[0][selling_price]" min="0" required placeholder="–" data-bulk-selling
                                    style="font-weight:700;color:var(--ink)" title="Harga jual otomatis — bisa diubah manual">
                                <small data-bulk-price-preview class="muted" style="font-size:10px;line-height:1.5;display:block;margin-top:3px"></small>
                            </td>
                            <td><input class="input" type="number" name="rows[0][stock]" min="0" required value="1" style="width:64px"></td>
                            <td style="white-space:nowrap">
                                <button type="button" class="button secondary mini" onclick="copyProductRow(this)" title="Salin baris">⧉</button>
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
        <div class="toolbar" style="justify-content:space-between;align-items:center;margin-bottom:12px">
            <h3 style="margin:0">Stok Barang</h3>
            <form method="get" action="{{ route('products.index') }}" class="filter-bar" style="margin-bottom:0;flex-wrap:wrap;gap:6px">
                <input class="input" type="search" name="search" value="{{ $search ?? '' }}" placeholder="Cari nama, SKU...">
                <select class="input" name="store_filter" style="max-width:130px">
                    <option value="">Semua Toko</option>
                    @foreach($stores as $st)
                        <option value="{{ $st->id }}" @selected(request('store_filter') == $st->id)>{{ $st->name }}</option>
                    @endforeach
                </select>
                <select class="input" name="category_filter" style="max-width:140px">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" @selected(request('category_filter') === $cat)>{{ ucfirst($cat) }}</option>
                    @endforeach
                </select>
                <button class="button secondary" type="submit">Filter</button>
                @if($search || request('store_filter') || request('category_filter'))
                    <a href="{{ route('products.index') }}" class="button secondary">Reset</a>
                @endif
            </form>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th class="col-hide-mobile">SKU</th><th>Nama</th><th>Toko</th><th>Kategori</th><th class="col-hide-mobile">Supplier</th><th class="col-hide-mobile">Warna</th><th class="col-hide-mobile">Ukuran</th><th>Stok</th><th class="col-hide-mobile">Modal</th><th>Jual</th><th></th></tr></thead>
                <tbody>
                @forelse($products as $product)
                    <tr>
                        <td class="col-hide-mobile" style="font-size:11px;color:var(--muted)">{{ $product->sku }}</td>
                        <td>{{ $product->name }}</td>
                        <td style="font-size:12px;color:var(--muted)">{{ $product->store?->name }}</td>
                        <td>{{ $product->category }}</td>
                        <td class="col-hide-mobile" style="font-size:12px;color:var(--muted)">{{ $product->supplier ?: '-' }}</td>
                        <td class="col-hide-mobile">{{ $product->color }}</td>
                        <td class="col-hide-mobile">{{ $product->size }}</td>
                        <td><strong style="color:{{ $product->stockColor() }};font-size:15px">{{ $product->stock }}</strong></td>
                        <td class="col-hide-mobile">Rp {{ number_format($product->cost_price, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format($product->selling_price, 0, ',', '.') }}</td>
                        <td class="product-action-cell">
                            <label class="button secondary mini" for="modal-edit-{{ $product->id }}">Edit</label>
                            <label class="button secondary mini" for="modal-restock-{{ $product->id }}">Restock</label>
                            <form method="post" action="{{ route('products.destroy', $product) }}" onsubmit="return confirmDelete(this, 'Hapus barang {{ addslashes($product->name) }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="button danger mini">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">Belum ada barang.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-pager :paginator="$products" />
    </section>


{{-- Restock modals --}}
@foreach($products as $product)
    <input class="modal-toggle" type="checkbox" id="modal-restock-{{ $product->id }}">
    <div class="modal">
        <div class="modal-card" style="max-width:480px">
            <div class="modal-head">
                <h3>Restock — {{ $product->name }}</h3>
                <label class="button secondary mini" for="modal-restock-{{ $product->id }}">Tutup</label>
            </div>
            <div class="modal-body">
                <p class="muted" style="margin:0 0 12px;font-size:13px">
                    Stok sekarang: <strong style="color:{{ $product->stockColor() }}">{{ $product->stock }}</strong>
                </p>
                <form method="post" action="{{ route('owner.restock') }}">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <div class="grid-2" style="gap:12px">
                        <div class="field">
                            <label>Tambah Qty <span style="color:red">*</span></label>
                            <input class="input" type="number" name="qty" min="1" required value="1">
                        </div>
                        <div class="field">
                            <label>Harga Modal/pcs (Rp) <span style="color:red">*</span></label>
                            <input class="input" type="number" name="unit_cost" min="0" required value="{{ $product->cost_price }}"
                                oninput="restockCalc(this.closest('form'))">
                        </div>
                        <div class="field">
                            <label>Harga Jual (Rp) <span style="color:red">*</span></label>
                            <input class="input" type="number" name="selling_price" min="0" required value="{{ $product->selling_price }}"
                                data-restock-selling style="font-weight:700">
                            <small class="muted" data-restock-preview style="font-size:11px;display:block;margin-top:2px"></small>
                        </div>
                        <div class="field" style="grid-column:span 2">
                            <label>Supplier</label>
                            <input class="input" type="text" name="supplier" value="{{ $product->supplier }}" maxlength="120" placeholder="Nama supplier">
                        </div>
                        <div class="field" style="grid-column:span 2">
                            <label>Catatan</label>
                            <input class="input" type="text" name="notes" maxlength="1000" placeholder="Opsional">
                        </div>
                    </div>
                    <div style="margin-top:16px;display:flex;gap:8px">
                        <button class="button" type="submit">Simpan Restock</button>
                        <label class="button secondary" for="modal-restock-{{ $product->id }}">Batal</label>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

<script>
function restockCalc(form) {
    var cost = parseFloat(form.querySelector('[name=unit_cost]').value) || 0;
    var raw = cost * 1.5;
    var rounded = Math.ceil(raw / 5000) * 5000;
    var sellingInput = form.querySelector('[data-restock-selling]');
    var preview = form.querySelector('[data-restock-preview]');
    sellingInput.value = rounded > 0 ? rounded : '';
    preview.textContent = rounded > 0 ? 'Modal ×1,5 = Rp ' + rounded.toLocaleString('id-ID') + ' — bisa diubah manual' : '';
}
</script>

{{-- Edit product modals --}}
@foreach($products as $product)
    <input class="modal-toggle" type="checkbox" id="modal-edit-{{ $product->id }}">
    <div class="modal">
        <div class="modal-card" style="max-width:560px">
            <div class="modal-head">
                <h3>Edit Barang — {{ $product->name }}</h3>
                <label class="button secondary mini" for="modal-edit-{{ $product->id }}">Tutup</label>
            </div>
            <div class="modal-body">
                <form method="post" action="{{ route('products.update', $product) }}">
                    @csrf
                    @method('PUT')
                    <div class="grid-2" style="gap:12px">
                        <div class="field" style="grid-column:span 2">
                            <label>Nama Barang <span style="color:red">*</span></label>
                            <input class="input" type="text" name="name" value="{{ $product->name }}" required maxlength="120">
                        </div>
                        <div class="field">
                            <label>Kategori <span style="color:red">*</span></label>
                            <select class="input" name="category" required>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat }}" @selected($product->category === $cat)>{{ ucfirst($cat) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Toko <span style="color:red">*</span></label>
                            <select class="input" name="store_id" required>
                                @foreach($stores as $store)
                                    <option value="{{ $store->id }}" @selected($product->store_id === $store->id)>{{ $store->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Warna</label>
                            <input class="input" type="text" name="color" value="{{ $product->color }}" maxlength="60">
                        </div>
                        <div class="field">
                            <label>Ukuran</label>
                            <input class="input" type="text" name="size" value="{{ $product->size }}" maxlength="40">
                        </div>
                        <div class="field">
                            <label>Supplier</label>
                            <input class="input" type="text" name="supplier" value="{{ $product->supplier }}" maxlength="120">
                        </div>
                        <div class="field">
                            <label>Stok <span style="color:red">*</span></label>
                            <input class="input" type="number" name="stock" value="{{ $product->stock }}" min="0" required>
                        </div>
                        <div class="field">
                            <label>Harga Modal (Rp) <span style="color:red">*</span></label>
                            <input class="input" type="number" name="cost_price" value="{{ $product->cost_price }}" min="0" required>
                        </div>
                        <div class="field">
                            <label>Harga Jual (Rp) <span style="color:red">*</span></label>
                            <input class="input" type="number" name="selling_price" value="{{ $product->selling_price }}" min="0" required>
                            <small class="muted">Edit langsung sesuai kebutuhan.</small>
                        </div>
                    </div>
                    <div style="margin-top:16px;display:flex;gap:8px">
                        <button class="button" type="submit">Simpan Perubahan</button>
                        <label class="button secondary" for="modal-edit-{{ $product->id }}">Batal</label>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

</x-layouts.app>
