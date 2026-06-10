# Design: UX Improvements — Format Rupiah, Grafik, Ukuran, Stok, History, Input Massal

**Tanggal:** 2026-06-10
**Status:** Approved

---

## Ringkasan

Enam perbaikan UX pada sistem Butik POS:

1. **Format Rupiah** — semua input harga tampilkan titik pemisah ribuan (15.000, 200.000)
2. **Grafik Tren Pendapatan** — 7 hari terakhir, hari kosong = 0, layout lebih rapi
3. **Ukuran Dropdown + Override** — dropdown XS–XXL/All Size dengan opsi manual
4. **Hapus min_stock, logika stok baru** — hijau ≥ 2, kuning = 1, merah = 0; notifikasi per threshold
5. **History Transaksi Owner** — semua transaksi + kolom kasir, route/view baru
6. **Input Barang Massal** — tabel dinamis tambah-baris, ganti form tunggal

---

## 1. Format Rupiah

### Behaviour
- Input harga tetap `type="number"` (value dikirim ke server tanpa titik, validasi integer tetap jalan)
- JS `formatRupiah(input)` dipanggil pada event `input` dan `blur`:
  - Tampilkan preview `Rp 185.000` di `<small data-rp-preview>` di bawah field
  - Tidak mengubah value input itu sendiri (server tetap terima integer)
- Berlaku di semua field: `cost_price`, `selling_price`, `amount_paid`, `discount_value`, restock `unit_cost`, bulk form per baris

### Implementasi
- Satu fungsi global `formatRp(n)` → `'Rp ' + n.toLocaleString('id-ID')` di `pos.js` atau inline script di layout
- Setiap field harga diberi atribut `data-rupiah` → JS query `[data-rupiah]` dan attach listener
- `<small data-rp-preview class="muted" hidden></small>` disisipkan setelah setiap field `data-rupiah`

### Files
- `public/js/pos.js` — tambah `rupiah formatter`
- `resources/views/app/pos.blade.php` — `data-rupiah` pada `amount_paid`, `discount_value`
- `resources/views/app/products.blade.php` — `data-rupiah` pada `cost_price`, `selling_price`, bulk rows
- `resources/views/app/owner-dashboard.blade.php` — `data-rupiah` pada restock `unit_cost`

---

## 2. Grafik Tren Pendapatan — 7 Hari Terakhir

### Data
- Controller `ownerDashboard()` query baru: `Sale::completed()->last7Days()` — group by date, fill hari kosong dengan 0
- Return `$chartData` = array 7 elemen `[['label' => 'Sen 09', 'total' => 370000], ...]` sorted ascending
- `$chartMax` = max total dari 7 hari (min 1 untuk avoid division by zero)

### View
- Bar chart dengan label hari singkat (format `D d`, e.g. "Sen 09")
- Tinggi bar: `max(8px, (total / chartMax) * 120px)` — minimum 8px agar hari kosong tetap terlihat sebagai nol visual
- Tooltip hover menampilkan nilai rupiah formatted (CSS `title` attribute atau pure CSS tooltip)
- Bar hari ini (`today`) diberi class `bar-today` dengan warna sedikit lebih terang/accent
- Layout: chart berada di card sendiri full-width di bawah summary cards (bukan grid-2 dengan diskon)

### CSS
```css
.chart { display: flex; align-items: flex-end; gap: 6px; height: 140px; padding-bottom: 24px; }
.bar { flex: 1; background: var(--accent); border-radius: 4px 4px 0 0; opacity: 0.75; transition: opacity .15s; position: relative; min-height: 8px; }
.bar.bar-today { opacity: 1; }
.bar:hover { opacity: 1; }
.bar-label { position: absolute; bottom: -22px; left: 0; right: 0; text-align: center; font-size: 10px; color: var(--muted); white-space: nowrap; }
.bar-val { position: absolute; top: -18px; left: 0; right: 0; text-align: center; font-size: 10px; color: var(--muted); }
```

### Files
- `app/Http/Controllers/AppController.php` — `ownerDashboard()` tambah `$chartData`, `$chartMax`
- `resources/views/app/owner-dashboard.blade.php` — section chart baru
- `public/css/pos.css` — update `.chart`, `.bar`, tambah `.bar-today`, `.bar-label`, `.bar-val`

---

## 3. Ukuran — Dropdown + Override Manual

### Opsi Dropdown
`XS`, `S`, `M`, `L`, `XL`, `XXL`, `All Size`, `--- Lainnya ---`

### Behaviour
- Komponen Blade `<x-size-select name="size" :value="$value" />` — reusable di form tunggal, bulk, dan filter POS
- Jika value dari DB tidak ada di daftar opsi (e.g. "32", "38") → tampilkan sebagai "Lainnya" dengan input manual terisi value tersebut
- Saat "Lainnya" dipilih: input teks muncul (`data-size-custom` hidden → visible via JS)
- Value dikirim: dropdown value jika bukan "other", atau input manual jika "other"
- Hidden input `name="{{ $name }}"` yang valuenya di-sync JS dari kedua sumber

### Komponen Blade
File: `resources/views/components/size-select.blade.php`
```blade
@props(['name', 'value' => ''])
@php
    $sizes = ['XS','S','M','L','XL','XXL','All Size'];
    $isCustom = $value && !in_array($value, $sizes);
@endphp
<div data-size-wrapper>
    <select class="input" data-size-select onchange="sizeSelectChange(this)">
        @foreach($sizes as $s)
            <option value="{{ $s }}" @selected(!$isCustom && $value === $s)>{{ $s }}</option>
        @endforeach
        <option value="other" @selected($isCustom)>--- Lainnya ---</option>
    </select>
    <input class="input" style="margin-top:6px" data-size-custom
           @if(!$isCustom) hidden @endif
           value="{{ $isCustom ? $value : '' }}"
           placeholder="Isi ukuran manual (contoh: 32, 38, XL Jumbo)">
    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
</div>
```

### JS
```javascript
function sizeSelectChange(sel) {
    var wrapper = sel.closest('[data-size-wrapper]');
    var custom  = wrapper.querySelector('[data-size-custom]');
    var hidden  = wrapper.querySelector('input[type=hidden]');
    if (sel.value === 'other') {
        custom.hidden = false;
        custom.oninput = function() { hidden.value = custom.value; };
        hidden.value = custom.value;
    } else {
        custom.hidden = true;
        hidden.value  = sel.value;
    }
}
```

### Files
- `resources/views/components/size-select.blade.php` — komponen baru
- `resources/views/app/products.blade.php` — ganti `<input name="size">` dengan `<x-size-select>`
- `resources/views/app/pos.blade.php` — filter ukuran di search (opsional, low priority)
- `public/js/pos.js` — tambah `sizeSelectChange`

---

## 4. Hapus `min_stock` — Logika Stok Baru

### Kolom DB
`min_stock` **tidak dihapus** dari DB (migration risiko, data lama aman). Kolom diabaikan di semua logic baru.

### Threshold
| Stok | Status | Badge |
|------|--------|-------|
| ≥ 2  | Aman   | green |
| = 1  | Sedikit | amber |
| = 0  | Habis  | red   |

### Helper di Product Model
```php
public function stockStatus(): string  // 'ok' | 'low' | 'out'
{
    if ($this->stock === 0) return 'out';
    if ($this->stock === 1) return 'low';
    return 'ok';
}

public function stockBadgeClass(): string
{
    return match($this->stockStatus()) {
        'out' => 'red',
        'low' => 'amber',
        default => 'green',
    };
}

public function stockLabel(): string
{
    return match($this->stockStatus()) {
        'out' => 'Habis',
        'low' => 'Sedikit',
        default => 'Stok ' . $this->stock,
    };
}
```

### Notifikasi
`PosService::updateStockNotification(Product $product)` dipanggil setelah decrement:

```php
private function updateStockNotification(Product $product): void
{
    if ($product->stock === 0) {
        // Buat notif out_of_stock, resolve low_stock lama
        Notification::query()
            ->where('type', 'low_stock')
            ->whereNull('read_at')
            ->whereJsonContains('data->product_id', $product->id)
            ->update(['read_at' => now()]);

        $this->createNotifIfAbsent($product, 'out_of_stock',
            'Stok Habis',
            "Stok {$product->name} sudah habis. Segera lakukan restock.");
    } elseif ($product->stock === 1) {
        $this->createNotifIfAbsent($product, 'low_stock',
            'Stok Sedikit',
            "Stok {$product->name} tinggal 1 pcs. Supplier: " . ($product->supplier ?: '-') . '.');
    }
}

private function createNotifIfAbsent(Product $product, string $type, string $title, string $body): void
{
    $exists = Notification::query()
        ->where('type', $type)
        ->whereNull('read_at')
        ->whereJsonContains('data->product_id', $product->id)
        ->exists();

    if (!$exists) {
        Notification::query()->create([
            'type'  => $type,
            'title' => $title,
            'body'  => $body,
            'data'  => ['product_id' => $product->id],
        ]);
    }
}
```

### Dashboard "Stok Perlu Perhatian"
```php
$lowStock = Product::query()->where('stock', '<=', 1)->orderBy('stock')->get();
```

### Form — Hapus min_stock
- `products.blade.php` — hapus field "Batas Stok Minimum"
- `bulk-products.blade.php` — tidak ada kolom min_stock
- `AppController::storeProduct()` — hapus dari validasi, set `min_stock = 0` default agar kolom DB tidak null
- `AppController::storeBulkProducts()` — sama

### Files
- `app/Models/Product.php` — tambah `stockStatus()`, `stockBadgeClass()`, `stockLabel()`
- `app/Services/PosService.php` — ganti `createLowStockNotification` → `updateStockNotification`
- `app/Http/Controllers/AppController.php` — hapus `min_stock` dari validasi
- `resources/views/app/products.blade.php` — hapus field min_stock, gunakan `$product->stockBadgeClass()`
- `resources/views/app/pos.blade.php` — gunakan `$product->stockBadgeClass()` dan `$product->stockLabel()`
- `resources/views/app/owner-dashboard.blade.php` — update query lowStock
- `public/css/pos.css` — tambah badge `.badge.red` (sudah ada), pastikan warna konsisten

---

## 5. History Transaksi Owner

### Route Baru
```php
Route::get('/owner/history', [AppController::class, 'ownerHistory'])->name('owner.history');
```

### Controller
```php
public function ownerHistory(): View
{
    abort_unless(auth()->user()->isOwner(), 403);

    return view('app.owner-history', [
        'sales' => Sale::query()
            ->with('items', 'cashier', 'discount', 'corrections.requester')
            ->latest()
            ->paginate(20),
    ]);
}
```

### View `owner-history.blade.php`
Sama dengan `cashier-history.blade.php` dengan perbedaan:
- Judul: "History Transaksi" (bukan "Transaksi Saya")
- Kolom tambahan: **Kasir** (setelah Invoice)
- Tidak ada tombol Batalkan (owner tidak bisa void via history)
- Modal Detail tetap ada, tambahkan baris "Kasir: [nama]" di detail grid
- Pagination 20 per halaman (lebih banyak dari cashier yang 10)

### Tabel Kolom
`Invoice | Kasir | Waktu | Item | Diskon | Total | Status | Aksi`

Aksi: hanya Detail + Cetak (tanpa Batalkan).

### Sidebar
`resources/views/components/layouts/app.blade.php` — tambah link "History Transaksi" di section OWNER:
```blade
<a class="nav-link {{ request()->routeIs('owner.history') ? 'active' : '' }}"
   href="{{ route('owner.history') }}">History Transaksi</a>
```

### Files
- `app/Http/Controllers/AppController.php` — tambah `ownerHistory()`
- `routes/web.php` — tambah route
- `resources/views/app/owner-history.blade.php` — view baru
- `resources/views/components/layouts/app.blade.php` — tambah nav link

---

## 6. Input Barang Massal — Tabel Dinamis

### Route Baru
```php
Route::post('/barang/bulk', [AppController::class, 'storeBulkProducts'])->name('products.bulk-store');
```

### View — Ganti Form Tunggal
`products.blade.php` — form tunggal diganti dengan tabel dinamis:

```
| Nama | Kategori | Toko | Warna | Ukuran | Supplier | Modal | Jual | Stok | Hapus |
|------|----------|------|-------|--------|----------|-------|------|------|-------|
| [input] | [select] | [select] | [input] | [x-size-select] | [input] | [input data-rupiah] | [input data-rupiah] | [input] | [×] |
```

- Tombol "+ Tambah Baris" menambah row baru (kloning row pertama, reset values)
- Minimal 1 baris selalu ada
- Submit ke `POST /barang/bulk`
- Harga Jual per baris auto-kalkulasi (sama seperti form tunggal sebelumnya)

### Controller `storeBulkProducts`
```php
public function storeBulkProducts(Request $request): RedirectResponse
{
    $rows = $request->validate([
        'rows'                   => ['required', 'array', 'min:1'],
        'rows.*.store_id'        => ['required', 'exists:stores,id'],
        'rows.*.name'            => ['required', 'string', 'max:120'],
        'rows.*.category'        => ['required', 'string'],
        'rows.*.color'           => ['nullable', 'string', 'max:60'],
        'rows.*.size'            => ['nullable', 'string', 'max:40'],
        'rows.*.supplier'        => ['nullable', 'string', 'max:120'],
        'rows.*.cost_price'      => ['required', 'integer', 'min:0'],
        'rows.*.selling_price'   => ['required', 'integer', 'min:0'],
        'rows.*.stock'           => ['required', 'integer', 'min:0'],
    ])['rows'];

    DB::transaction(function () use ($rows): void {
        foreach ($rows as $row) {
            $store = Store::query()->findOrFail($row['store_id']);
            $row['sku']       = $this->sku($store->code, $row['category'], $row['color'] ?? null, $row['size'] ?? null);
            $row['min_stock'] = 0;
            Product::query()->create($row);
        }
    });

    $count = count($rows);
    return back()->with('status', "{$count} barang berhasil ditambahkan.");
}
```

### JS untuk Tambah/Hapus Baris
```javascript
function addProductRow() {
    var tbody = document.getElementById('bulk-tbody');
    var first = tbody.querySelector('tr');
    var clone = first.cloneNode(true);
    // reset semua input di clone
    clone.querySelectorAll('input').forEach(function(i) { i.value = ''; });
    clone.querySelectorAll('select').forEach(function(s) { s.selectedIndex = 0; });
    clone.querySelectorAll('[data-size-custom]').forEach(function(c) { c.hidden = true; });
    tbody.appendChild(clone);
}

function removeProductRow(btn) {
    var tbody = document.getElementById('bulk-tbody');
    if (tbody.querySelectorAll('tr').length > 1) {
        btn.closest('tr').remove();
    }
}
```

### Files
- `app/Http/Controllers/AppController.php` — tambah `storeBulkProducts()`
- `routes/web.php` — tambah route
- `resources/views/app/products.blade.php` — ganti form tunggal dengan tabel bulk
- `public/js/pos.js` — tambah `addProductRow`, `removeProductRow`

---

## 7. Non-Functional

### Testing
Setiap task punya test baru atau update test yang existing:
- `ProductsTest` — update untuk bulk submit, hapus min_stock assertion
- `PosTransactionTest` / `StockNotificationTest` baru — test threshold 0 dan 1
- `OwnerHistoryTest` baru — akses guard + tampil kolom kasir
- Existing tests yang touch `min_stock` di-update

### Performance
- Chart data: satu query `Sale::whereBetween('created_at', [7 days ago, now])` — tidak ada N+1
- Bulk insert: `DB::transaction` dengan loop `create()` — untuk jumlah kecil (max ~20 baris) ini cukup

### Security
- `storeBulkProducts`: `abort_unless(auth()->check(), 401)` — route sudah dalam auth middleware
- Validasi per-baris server-side, tidak hanya client-side
- `min_stock` di-set ke 0 server-side, tidak dari user input

---

## 8. File Changes Summary

### New Files
| File | Tujuan |
|------|--------|
| `resources/views/components/size-select.blade.php` | Komponen ukuran reusable |
| `resources/views/app/owner-history.blade.php` | History transaksi owner |
| `tests/Feature/OwnerHistoryTest.php` | Test owner history |
| `tests/Feature/BulkProductTest.php` | Test bulk input |
| `tests/Feature/StockThresholdTest.php` | Test notifikasi stok baru |

### Modified Files
| File | Perubahan |
|------|-----------|
| `app/Models/Product.php` | Tambah `stockStatus()`, `stockBadgeClass()`, `stockLabel()` |
| `app/Services/PosService.php` | Ganti `createLowStockNotification` → `updateStockNotification` |
| `app/Http/Controllers/AppController.php` | Tambah `ownerHistory()`, `storeBulkProducts()`; hapus `min_stock` dari validasi; update `ownerDashboard()` chart |
| `routes/web.php` | Tambah `/owner/history`, `/barang/bulk` |
| `resources/views/app/products.blade.php` | Ganti form tunggal → bulk table; hapus min_stock; `x-size-select`; `data-rupiah` |
| `resources/views/app/pos.blade.php` | `data-rupiah` pada amount_paid, discount_value; `stockBadgeClass()`/`stockLabel()` |
| `resources/views/app/owner-dashboard.blade.php` | Chart 7 hari; lowStock query baru; `data-rupiah` restock |
| `resources/views/app/cashier-history.blade.php` | `stockBadgeClass()` (minor) |
| `resources/views/components/layouts/app.blade.php` | Tambah nav link History Transaksi owner |
| `public/css/pos.css` | Chart CSS update; badge konsistensi |
| `public/js/pos.js` | `formatRp`, `sizeSelectChange`, `addProductRow`, `removeProductRow` |
