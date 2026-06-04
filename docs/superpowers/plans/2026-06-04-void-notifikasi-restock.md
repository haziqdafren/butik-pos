# Void Kasir, Notifikasi, Laporan Email, Restock Owner — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Kasir dapat void transaksi sendiri dengan alasan; owner mendapat notifikasi in-app saat ada void dan stok menipis; laporan harian dikirim via email jam 20:00; restock dipindah ke owner-only dengan form detail pembukuan.

**Architecture:** Empat area perubahan — (1) PosService mendapat method `selfVoid` menggantikan flow request+approve, (2) tabel `notifications` baru untuk in-app alerts owner, (3) Mailable + Laravel Scheduler untuk laporan harian, (4) restock form dipindah ke halaman owner dengan route baru. Semua DB mutation dibungkus transaction. Security dipakai di layer controller via `abort_unless`.

**Tech Stack:** Laravel 13, PHP 8.3, Blade, MySQL, Laravel Scheduler (cron), Laravel Mail (SMTP/Gmail), Eloquent ORM, CSS native (tidak ada framework CSS baru).

---

## File Map

| File | Create/Modify | Tanggung Jawab |
|---|---|---|
| `database/migrations/2026_06_04_000002_add_notifications_table.php` | **Create** | Tabel notifications baru |
| `app/Models/Notification.php` | **Create** | Model Notification |
| `app/Mail/DailyReportMail.php` | **Create** | Mailable laporan harian |
| `resources/views/mail/daily-report.blade.php` | **Create** | Template email laporan harian |
| `app/Console/Commands/SendDailyReport.php` | **Create** | Artisan command kirim laporan |
| `app/Services/PosService.php` | **Modify** | Tambah `selfVoid`, `createLowStockNotification`, hapus approval flow |
| `app/Http/Controllers/AppController.php` | **Modify** | Tambah `selfVoid`, `markNotificationsRead`, `ownerRestock` routes; hapus `approveCorrection` |
| `routes/web.php` | **Modify** | Route baru: self-void, notif, restock owner; hapus approve-correction |
| `bootstrap/app.php` | **Modify** | Register schedule untuk command |
| `resources/views/app/cashier-history.blade.php` | **Modify** | Ganti "Ajukan Koreksi" → "Void Langsung" dengan modal alasan |
| `resources/views/app/owner-dashboard.blade.php` | **Modify** | Hapus panel pending corrections, tambah panel notifikasi, badge unread |
| `resources/views/app/reports.blade.php` | **Modify** | Hapus approval corrections section, tambah riwayat void |
| `resources/views/components/layouts/app.blade.php` | **Modify** | Badge notifikasi di topbar owner |
| `resources/views/app/owner-restock.blade.php` | **Create** | Halaman restock owner-only |
| `tests/Feature/PosTransactionTest.php` | **Modify** | Update test lama + tambah test self-void, notif, restock |
| `.env.example` | **Modify** | Tambah variabel MAIL_MAILER gmail, OWNER_EMAIL |

---

## Task 1: Migrasi tabel notifications

**Files:**
- Create: `database/migrations/2026_06_04_000002_add_notifications_table.php`

- [ ] **Step 1: Tulis migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->string('type'); // void_transaction | low_stock
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable(); // {"sale_id": X} atau {"product_id": X}
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
```

- [ ] **Step 2: Jalankan migrasi**

```bash
php artisan migrate
```

Expected output: `Migrasi berhasil` atau `DONE`.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_06_04_000002_add_notifications_table.php
git commit -m "feat: add notifications table migration"
```

---

## Task 2: Model Notification

**Files:**
- Create: `app/Models/Notification.php`

- [ ] **Step 1: Tulis model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'type',
        'title',
        'body',
        'data',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Models/Notification.php
git commit -m "feat: add Notification model"
```

---

## Task 3: Test self-void oleh kasir (TDD — tulis test dulu)

**Files:**
- Modify: `tests/Feature/PosTransactionTest.php`

- [ ] **Step 1: Tambah test `test_cashier_can_self_void_own_transaction`**

Tambahkan method berikut ke dalam class `PosTransactionTest` (sebelum method `private function product`):

```php
public function test_cashier_can_self_void_own_transaction(): void
{
    $cashier = User::query()->create([
        'name' => 'Kasir Utama',
        'email' => 'kasir@butik.test',
        'password' => Hash::make('password'),
        'role' => 'cashier',
    ]);

    $product = $this->product(stock: 3, price: 185000, cost: 90000);
    $service = app(PosService::class);

    $sale = $service->checkout($cashier, [
        'payment_method' => 'Tunai',
        'amount_paid' => 200000,
        'items' => [['product_id' => $product->id, 'qty' => 1]],
    ]);

    $this->assertSame(2, $product->fresh()->stock);
    $this->assertSame('completed', $sale->fresh()->status);

    $service->selfVoid($cashier, $sale, 'Salah pilih barang, customer minta batal.');

    $this->assertSame('voided', $sale->fresh()->status);
    $this->assertSame(3, $product->fresh()->stock); // stok kembali
    $this->assertSame(1, $sale->fresh()->corrections()->count());
    $this->assertSame('self_voided', $sale->fresh()->corrections()->first()->status);

    // Notifikasi owner dibuat
    $this->assertDatabaseHas('notifications', [
        'type' => 'void_transaction',
    ]);
}
```

- [ ] **Step 2: Tambah test `test_cashier_cannot_void_other_cashier_transaction`**

```php
public function test_cashier_cannot_void_other_cashier_transaction(): void
{
    $cashier1 = User::query()->create([
        'name' => 'Kasir Satu',
        'email' => 'kasir1@butik.test',
        'password' => Hash::make('password'),
        'role' => 'cashier',
    ]);

    $cashier2 = User::query()->create([
        'name' => 'Kasir Dua',
        'email' => 'kasir2@butik.test',
        'password' => Hash::make('password'),
        'role' => 'cashier',
    ]);

    $product = $this->product(stock: 3, price: 185000, cost: 90000);
    $service = app(PosService::class);

    $sale = $service->checkout($cashier1, [
        'payment_method' => 'Tunai',
        'amount_paid' => 200000,
        'items' => [['product_id' => $product->id, 'qty' => 1]],
    ]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Hanya kasir yang membuat transaksi ini yang dapat membatalkannya.');

    $service->selfVoid($cashier2, $sale, 'Alasan palsu lebih dari 8 huruf.');
}
```

- [ ] **Step 3: Tambah test `test_cashier_cannot_void_already_voided_transaction`**

```php
public function test_cashier_cannot_void_already_voided_transaction(): void
{
    $cashier = User::query()->create([
        'name' => 'Kasir Utama',
        'email' => 'kasir@butik.test',
        'password' => Hash::make('password'),
        'role' => 'cashier',
    ]);

    $product = $this->product(stock: 3, price: 185000, cost: 90000);
    $service = app(PosService::class);

    $sale = $service->checkout($cashier, [
        'payment_method' => 'Tunai',
        'amount_paid' => 200000,
        'items' => [['product_id' => $product->id, 'qty' => 1]],
    ]);

    $service->selfVoid($cashier, $sale, 'Void pertama karena salah input.');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Transaksi ini sudah dibatalkan.');

    $service->selfVoid($cashier, $sale->fresh(), 'Void kedua coba curang.');
}
```

- [ ] **Step 4: Tambah test `test_low_stock_notification_created_after_checkout`**

```php
public function test_low_stock_notification_created_after_checkout(): void
{
    $cashier = User::query()->create([
        'name' => 'Kasir Utama',
        'email' => 'kasir@butik.test',
        'password' => Hash::make('password'),
        'role' => 'cashier',
    ]);

    // stock=3, min_stock=3 → setelah beli 1 menjadi 2 ≤ 3, trigger notif
    $product = $this->product(stock: 3, price: 185000, cost: 90000);
    $service = app(PosService::class);

    $service->checkout($cashier, [
        'payment_method' => 'Tunai',
        'amount_paid' => 200000,
        'items' => [['product_id' => $product->id, 'qty' => 1]],
    ]);

    $this->assertDatabaseHas('notifications', [
        'type' => 'low_stock',
    ]);
}
```

- [ ] **Step 5: Tambah test `test_low_stock_notification_not_duplicated`**

```php
public function test_low_stock_notification_not_duplicated(): void
{
    $cashier = User::query()->create([
        'name' => 'Kasir Utama',
        'email' => 'kasir@butik.test',
        'password' => Hash::make('password'),
        'role' => 'cashier',
    ]);

    $product = $this->product(stock: 5, price: 185000, cost: 90000);
    $service = app(PosService::class);

    // Checkout pertama: stok jadi 4, masih > min_stock(3), belum notif
    $service->checkout($cashier, [
        'payment_method' => 'Tunai',
        'amount_paid' => 200000,
        'items' => [['product_id' => $product->id, 'qty' => 1]],
    ]);
    $this->assertDatabaseCount('notifications', 0);

    // Checkout kedua: stok jadi 3 = min_stock, notif pertama
    $service->checkout($cashier, [
        'payment_method' => 'Tunai',
        'amount_paid' => 200000,
        'items' => [['product_id' => $product->id, 'qty' => 1]],
    ]);
    $this->assertDatabaseCount('notifications', 1);

    // Checkout ketiga: stok jadi 2, sudah ada notif unread → skip duplikat
    $service->checkout($cashier, [
        'payment_method' => 'Tunai',
        'amount_paid' => 200000,
        'items' => [['product_id' => $product->id, 'qty' => 1]],
    ]);
    $this->assertDatabaseCount('notifications', 1);
}
```

- [ ] **Step 6: Update test lama `test_cashier_can_request_transaction_correction_and_owner_approval_voids_sale`**

Test ini menguji flow lama (pending → approve) yang akan **dihapus**. Ubah test ini menjadi test self-void via HTTP route:

```php
public function test_cashier_can_self_void_via_http(): void
{
    $cashier = User::query()->create([
        'name' => 'Kasir Utama',
        'email' => 'kasir@butik.test',
        'password' => Hash::make('password'),
        'role' => 'cashier',
    ]);

    $product = $this->product(stock: 3, price: 185000, cost: 90000);
    $service = app(PosService::class);

    $sale = $service->checkout($cashier, [
        'payment_method' => 'Tunai',
        'amount_paid' => 200000,
        'items' => [['product_id' => $product->id, 'qty' => 1]],
    ]);

    $response = $this->actingAs($cashier)->post(route('sales.self-void', $sale), [
        'reason' => 'Salah pilih ukuran, customer komplain.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status');
    $this->assertSame('voided', $sale->fresh()->status);
    $this->assertSame(3, $product->fresh()->stock);
}
```

- [ ] **Step 7: Update test lama `test_cashier_cannot_approve_transaction_correction`**

Ubah menjadi test bahwa kasir tidak bisa void transaksi kasir lain via HTTP:

```php
public function test_cashier_cannot_void_other_cashier_sale_via_http(): void
{
    $cashier1 = User::query()->create([
        'name' => 'Kasir Satu',
        'email' => 'kasir1@butik.test',
        'password' => Hash::make('password'),
        'role' => 'cashier',
    ]);

    $cashier2 = User::query()->create([
        'name' => 'Kasir Dua',
        'email' => 'kasir2@butik.test',
        'password' => Hash::make('password'),
        'role' => 'cashier',
    ]);

    $product = $this->product(stock: 3, price: 185000, cost: 90000);
    $service = app(PosService::class);

    $sale = $service->checkout($cashier1, [
        'payment_method' => 'Tunai',
        'amount_paid' => 200000,
        'items' => [['product_id' => $product->id, 'qty' => 1]],
    ]);

    $response = $this->actingAs($cashier2)->post(route('sales.self-void', $sale), [
        'reason' => 'Coba void transaksi orang lain.',
    ]);

    $response->assertForbidden();
    $this->assertSame('completed', $sale->fresh()->status);
}
```

- [ ] **Step 8: Jalankan test — harus FAIL karena selfVoid belum ada**

```bash
php artisan test --filter=PosTransactionTest
```

Expected: beberapa test FAIL dengan `Call to undefined method ... selfVoid()` atau route tidak ditemukan. Ini benar — kita TDD.

- [ ] **Step 9: Commit tests**

```bash
git add tests/Feature/PosTransactionTest.php
git commit -m "test: add self-void, low-stock notification, and security tests (failing)"
```

---

## Task 4: Implementasi `selfVoid` dan notifikasi di PosService

**Files:**
- Modify: `app/Services/PosService.php`

- [ ] **Step 1: Tambah use statement di PosService**

Di bagian `use` statements (baris 1–11), tambahkan:

```php
use App\Models\Notification;
```

- [ ] **Step 2: Tambah method `selfVoid` ke PosService**

Tambahkan setelah method `approveCorrection` (sebelum `private function calculateCart`):

```php
public function selfVoid(User $cashier, Sale $sale, string $reason): SaleCorrection
{
    $reason = trim($reason);

    if ($cashier->id !== $sale->user_id) {
        throw new RuntimeException('Hanya kasir yang membuat transaksi ini yang dapat membatalkannya.');
    }

    if ($sale->status !== 'completed') {
        throw new RuntimeException('Transaksi ini sudah dibatalkan.');
    }

    if (strlen($reason) < 8) {
        throw new RuntimeException('Alasan pembatalan minimal 8 karakter.');
    }

    return DB::transaction(function () use ($cashier, $sale, $reason): SaleCorrection {
        $sale = Sale::query()->with('items.product')->lockForUpdate()->findOrFail($sale->id);

        // Kembalikan stok semua item
        foreach ($sale->items as $item) {
            $item->product->increment('stock', $item->qty);
        }

        $sale->update(['status' => 'voided']);

        $correction = SaleCorrection::query()->create([
            'sale_id' => $sale->id,
            'requested_by' => $cashier->id,
            'approved_by' => null,
            'type' => 'void',
            'status' => 'self_voided',
            'reason' => $reason,
            'approved_at' => now(),
        ]);

        // Notifikasi in-app ke owner
        Notification::query()->create([
            'type' => 'void_transaction',
            'title' => 'Transaksi Dibatalkan',
            'body' => "Kasir {$cashier->name} membatalkan {$sale->invoice_number}. Alasan: {$reason}",
            'data' => ['sale_id' => $sale->id],
        ]);

        return $correction;
    });
}
```

- [ ] **Step 3: Tambah method `createLowStockNotification` (private) ke PosService**

Tambahkan setelah method `selfVoid`:

```php
private function createLowStockNotification(Product $product): void
{
    // Cek duplikat: sudah ada notif low_stock unread untuk produk ini?
    $exists = Notification::query()
        ->where('type', 'low_stock')
        ->whereNull('read_at')
        ->whereJsonContains('data->product_id', $product->id)
        ->exists();

    if ($exists) {
        return;
    }

    $supplier = $product->supplier ?: 'tidak diketahui';

    Notification::query()->create([
        'type' => 'low_stock',
        'title' => 'Stok Menipis',
        'body' => "Stok {$product->name} tinggal {$product->stock} pcs. Supplier: {$supplier}.",
        'data' => ['product_id' => $product->id],
    ]);
}
```

- [ ] **Step 4: Panggil `createLowStockNotification` di akhir method `checkout`, setelah loop item**

Temukan baris ini di method `checkout` (setelah foreach loop item):

```php
            return $sale->refresh();
        });
    }
```

Ubah menjadi:

```php
            // Cek stok menipis untuk setiap produk yang baru dibeli
            foreach ($totals['products'] as $line) {
                $freshProduct = $line['product']->fresh();
                if ($freshProduct->stock <= $freshProduct->min_stock) {
                    $this->createLowStockNotification($freshProduct);
                }
            }

            return $sale->refresh();
        });
    }
```

- [ ] **Step 5: Jalankan test**

```bash
php artisan test --filter=PosTransactionTest
```

Expected: test-test baru yang menyangkut service (selfVoid, low_stock) harus PASS. Test HTTP route masih FAIL karena route belum ada.

---

## Task 5: Route dan Controller untuk self-void, notifikasi, restock owner

**Files:**
- Modify: `app/Http/Controllers/AppController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Tambah `use App\Models\Notification;` di AppController**

Di bagian use statements AppController (baris 1–15), tambahkan:

```php
use App\Models\Notification;
```

- [ ] **Step 2: Ganti method `requestCorrection` dan `approveCorrection` di AppController**

Hapus kedua method tersebut dan gantikan dengan tiga method baru:

```php
public function selfVoid(Request $request, Sale $sale, PosService $service): RedirectResponse
{
    abort_unless($sale->user_id === auth()->id(), 403);

    $payload = $request->validate([
        'reason' => ['required', 'string', 'min:8', 'max:500'],
    ]);

    try {
        $service->selfVoid(auth()->user(), $sale, $payload['reason']);
    } catch (\RuntimeException $exception) {
        return back()->withErrors(['void' => $exception->getMessage()]);
    }

    return back()->with('status', "Transaksi {$sale->invoice_number} berhasil dibatalkan dan stok dikembalikan.");
}

public function markNotificationsRead(): RedirectResponse
{
    abort_unless(auth()->user()->isOwner(), 403);

    Notification::query()->whereNull('read_at')->update(['read_at' => now()]);

    return back()->with('status', 'Semua notifikasi telah ditandai dibaca.');
}

public function ownerRestock(Request $request): RedirectResponse
{
    abort_unless(auth()->user()->isOwner(), 403);

    $data = $request->validate([
        'product_id' => ['required', 'exists:products,id'],
        'qty' => ['required', 'integer', 'min:1'],
        'unit_cost' => ['required', 'integer', 'min:0'],
        'supplier' => ['nullable', 'string', 'max:120'],
        'notes' => ['nullable', 'string', 'max:1000'],
    ]);

    $product = Product::query()->findOrFail($data['product_id']);
    $product->increment('stock', $data['qty']);
    $product->update([
        'cost_price' => $data['unit_cost'],
        'supplier' => $data['supplier'] ?? $product->supplier,
    ]);
    $product->purchases()->create($data + ['user_id' => auth()->id()]);

    // Resolve notifikasi low_stock untuk produk ini
    Notification::query()
        ->where('type', 'low_stock')
        ->whereNull('read_at')
        ->whereJsonContains('data->product_id', $product->id)
        ->update(['read_at' => now()]);

    return back()->with('status', "Restock {$product->name} (+{$data['qty']} pcs) berhasil dicatat.");
}
```

- [ ] **Step 3: Update method `ownerDashboard` di AppController**

Ganti seluruh method `ownerDashboard` dengan versi yang menyertakan notifikasi dan menghapus `pendingCorrections`:

```php
public function ownerDashboard(): View
{
    abort_unless(auth()->user()->isOwner(), 403);

    $sales = Sale::query()->with('cashier', 'store')->latest()->get();
    $lowStock = Product::query()->with('store')->whereColumn('stock', '<=', 'min_stock')->orderBy('stock')->get();

    return view('app.owner-dashboard', [
        'sales' => $sales,
        'lowStock' => $lowStock,
        'recentDiscounts' => DiscountApproval::query()->with('requester')->latest()->take(8)->get(),
        'notifications' => Notification::query()->whereNull('read_at')->latest()->take(20)->get(),
        'unreadCount' => Notification::query()->whereNull('read_at')->count(),
        'products' => Product::query()->with('store')->orderBy('name')->get(),
        'productsCount' => Product::query()->count(),
        'cashiersCount' => User::query()->where('role', 'cashier')->count(),
        'summary' => [
            'revenue' => $sales->where('status', 'completed')->sum('total'),
            'profit' => $sales->where('status', 'completed')->sum('profit'),
            'discounts' => $sales->where('status', 'completed')->sum('discount_amount'),
            'transactions' => $sales->where('status', 'completed')->count(),
        ],
    ]);
}
```

- [ ] **Step 4: Update method `reports` di AppController**

Hapus `pendingCorrections` dari view data, karena approval flow sudah tidak ada:

```php
public function reports(): View
{
    abort_unless(auth()->user()->isOwner(), 403);

    return view('app.reports', [
        'sales' => Sale::query()->with('items', 'cashier', 'store', 'discount', 'corrections.requester')->latest()->paginate(10, ['*'], 'sales_page'),
        'products' => Product::query()->with('store')->orderBy('stock')->paginate(10, ['*'], 'products_page'),
        'summary' => [
            'revenue' => Sale::query()->where('status', 'completed')->sum('total'),
            'profit' => Sale::query()->where('status', 'completed')->sum('profit'),
            'cogs' => Sale::query()->where('status', 'completed')->sum('cogs'),
            'items' => (int) \App\Models\SaleItem::query()
                ->whereHas('sale', fn ($query) => $query->where('status', 'completed'))
                ->sum('qty'),
        ],
    ]);
}
```

- [ ] **Step 5: Update `routes/web.php`**

Ganti seluruh isi file `routes/web.php` dengan:

```php
<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function (): void {
    // Kasir
    Route::get('/kasir', [AppController::class, 'pos'])->name('cashier.pos');
    Route::get('/kasir/history', [AppController::class, 'cashierHistory'])->name('cashier.history');
    Route::post('/kasir/checkout', [AppController::class, 'checkout'])->name('sales.checkout');
    Route::post('/transaksi/{sale}/void', [AppController::class, 'selfVoid'])->name('sales.self-void');

    // Barang
    Route::get('/barang', [AppController::class, 'products'])->name('products.index');
    Route::post('/barang', [AppController::class, 'storeProduct'])->name('products.store');

    // Owner only
    Route::get('/owner/dashboard', [AppController::class, 'ownerDashboard'])->name('owner.dashboard');
    Route::get('/owner/laporan', [AppController::class, 'reports'])->name('owner.reports');
    Route::post('/owner/restock', [AppController::class, 'ownerRestock'])->name('owner.restock');
    Route::post('/owner/notifikasi/baca-semua', [AppController::class, 'markNotificationsRead'])->name('owner.notifications.read-all');
});
```

- [ ] **Step 6: Jalankan semua test**

```bash
php artisan test
```

Expected: semua test PASS termasuk test HTTP route self-void.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/AppController.php routes/web.php app/Services/PosService.php app/Models/Notification.php
git commit -m "feat: self-void by cashier, low-stock notifications, owner restock route"
```

---

## Task 6: Update view cashier-history.blade.php

**Files:**
- Modify: `resources/views/app/cashier-history.blade.php`

- [ ] **Step 1: Ganti seluruh isi file dengan versi baru**

```blade
<x-layouts.app title="History Transaksi Kasir">
    <section class="card">
        <div class="toolbar" style="justify-content:space-between; align-items:center">
            <h3 style="margin:0">Transaksi Saya</h3>
        </div>
        <p class="muted">Jika ada kesalahan input, klik <strong>Batalkan</strong> dan isi alasan dengan jelas. Transaksi akan langsung dibatalkan dan stok dikembalikan.</p>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Invoice</th><th>Waktu</th><th>Item</th><th>Diskon</th><th>Total</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                @forelse($sales as $sale)
                    <tr>
                        <td>{{ $sale->invoice_number }}</td>
                        <td>{{ $sale->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $sale->items->sum('qty') }}</td>
                        <td>Rp {{ number_format($sale->discount_amount, 0, ',', '.') }}</td>
                        <td class="money">Rp {{ number_format($sale->total, 0, ',', '.') }}</td>
                        <td>
                            @if($sale->status === 'voided')
                                <span class="badge red">Dibatalkan</span>
                            @else
                                <span class="badge green">Selesai</span>
                            @endif
                        </td>
                        <td>
                            <div class="row-actions">
                                <label class="button secondary mini" for="sale-detail-{{ $sale->id }}">Detail</label>
                                @if($sale->status === 'completed')
                                    <label class="button danger mini" for="sale-void-{{ $sale->id }}">Batalkan</label>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Belum ada transaksi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-pager :paginator="$sales" />
    </section>

    @foreach($sales as $sale)
        {{-- Modal Detail --}}
        <input class="modal-toggle" type="checkbox" id="sale-detail-{{ $sale->id }}">
        <div class="modal">
            <div class="modal-card">
                <div class="modal-head">
                    <h3>Detail {{ $sale->invoice_number }}</h3>
                    <label class="button secondary mini" for="sale-detail-{{ $sale->id }}">Tutup</label>
                </div>
                <div class="modal-body">
                    @if($sale->status === 'voided')
                        <div class="notice" style="background:#fde8e8;color:#b91c1c;border-color:#f87171">
                            Transaksi ini sudah dibatalkan.
                            @php($voidRecord = $sale->corrections->firstWhere('status', 'self_voided'))
                            @if($voidRecord)
                                Alasan: <strong>{{ $voidRecord->reason }}</strong>
                            @endif
                        </div>
                    @endif
                    <div class="detail-grid">
                        <div class="detail-box"><small>Subtotal</small><strong>Rp {{ number_format($sale->subtotal, 0, ',', '.') }}</strong></div>
                        <div class="detail-box"><small>Diskon</small><strong>Rp {{ number_format($sale->discount_amount, 0, ',', '.') }}</strong></div>
                        <div class="detail-box"><small>Total</small><strong>Rp {{ number_format($sale->total, 0, ',', '.') }}</strong></div>
                        <div class="detail-box"><small>Dibayar</small><strong>Rp {{ number_format($sale->amount_paid, 0, ',', '.') }}</strong></div>
                        <div class="detail-box"><small>Kembalian</small><strong>Rp {{ number_format($sale->change, 0, ',', '.') }}</strong></div>
                        <div class="detail-box"><small>Metode</small><strong>{{ $sale->payment_method }}</strong></div>
                    </div>
                    <table>
                        <thead><tr><th>SKU</th><th>Barang</th><th>Qty</th><th>Harga Satuan</th><th>Total</th></tr></thead>
                        <tbody>
                        @foreach($sale->items as $item)
                            <tr>
                                <td>{{ $item->sku }}</td>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->qty }}</td>
                                <td>Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format($item->line_total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @if($sale->discount)
                        <p style="margin-top:8px"><strong>Catatan diskon:</strong> {{ $sale->discount->reason }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Modal Void --}}
        @if($sale->status === 'completed')
            <input class="modal-toggle" type="checkbox" id="sale-void-{{ $sale->id }}">
            <div class="modal">
                <div class="modal-card">
                    <div class="modal-head">
                        <h3>Batalkan Transaksi</h3>
                        <label class="button secondary mini" for="sale-void-{{ $sale->id }}">Tutup</label>
                    </div>
                    <div class="modal-body">
                        <div class="notice" style="background:#fef3c7;color:#92400e;border-color:#fbbf24">
                            <strong>Perhatian:</strong> Setelah dibatalkan, transaksi <strong>{{ $sale->invoice_number }}</strong> tidak bisa diaktifkan kembali. Stok barang akan otomatis dikembalikan.
                        </div>
                        <form method="post" action="{{ route('sales.self-void', $sale) }}">
                            @csrf
                            <div class="field" style="margin-top:12px">
                                <label>Alasan Pembatalan <span style="color:red">*</span></label>
                                <textarea class="input" name="reason" required minlength="8" maxlength="500" rows="3" placeholder="Contoh: Salah pilih ukuran, customer minta batal, salah input barang..."></textarea>
                                <small class="muted">Minimal 8 karakter. Alasan ini akan tercatat dan dilihat owner.</small>
                            </div>
                            <div class="row-actions" style="margin-top:12px">
                                <label class="button secondary" for="sale-void-{{ $sale->id }}">Batal</label>
                                <button class="button danger" type="submit">Ya, Batalkan Transaksi</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
</x-layouts.app>
```

- [ ] **Step 2: Verifikasi halaman tampil di browser**

Buka `http://localhost/kasir/history` (login sebagai kasir), pastikan:
- Tabel transaksi tampil
- Tombol "Batalkan" muncul hanya untuk transaksi `completed`
- Modal void terbuka dengan textarea alasan
- Modal detail menampilkan subtotal, diskon, total, items

- [ ] **Step 3: Commit**

```bash
git add resources/views/app/cashier-history.blade.php
git commit -m "feat: cashier history void modal with reason input"
```

---

## Task 7: Update layout app.blade.php — badge notifikasi owner

**Files:**
- Modify: `resources/views/components/layouts/app.blade.php`

- [ ] **Step 1: Update topbar di layout untuk tampilkan badge notifikasi owner**

Ganti baris:

```blade
            <span>{{ auth()->user()->isOwner() ? 'Akses penuh' : 'Akses operasional' }}</span>
```

Menjadi:

```blade
            <div style="display:flex;align-items:center;gap:12px">
                @if(auth()->user()->isOwner())
                    @php($unreadNotifCount = \App\Models\Notification::query()->whereNull('read_at')->count())
                    @if($unreadNotifCount > 0)
                        <a href="{{ route('owner.dashboard') }}#notifikasi" style="position:relative;text-decoration:none">
                            <span style="background:#ef4444;color:white;border-radius:999px;padding:2px 8px;font-size:12px;font-weight:700">{{ $unreadNotifCount }} notifikasi baru</span>
                        </a>
                    @endif
                @endif
                <span>{{ auth()->user()->isOwner() ? 'Akses penuh' : 'Akses operasional' }}</span>
            </div>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/components/layouts/app.blade.php
git commit -m "feat: notification badge in topbar for owner"
```

---

## Task 8: Update owner-dashboard.blade.php — panel notifikasi + restock

**Files:**
- Modify: `resources/views/app/owner-dashboard.blade.php`
- Create: `resources/views/app/owner-restock.blade.php`

- [ ] **Step 1: Ganti seluruh isi owner-dashboard.blade.php**

```blade
<x-layouts.app title="Dashboard Owner">
    <div class="grid-4">
        <div class="card metric hot"><small>Total Pendapatan</small><strong>Rp {{ number_format($summary['revenue'], 0, ',', '.') }}</strong></div>
        <div class="card metric good"><small>Profit Bersih</small><strong>Rp {{ number_format($summary['profit'], 0, ',', '.') }}</strong></div>
        <div class="card metric warn"><small>Diskon Diberikan</small><strong>Rp {{ number_format($summary['discounts'], 0, ',', '.') }}</strong></div>
        <div class="card metric info"><small>Transaksi</small><strong>{{ $summary['transactions'] }}</strong></div>
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
                            <span class="badge {{ $notif->type === 'void_transaction' ? 'red' : 'amber' }}">
                                {{ $notif->type === 'void_transaction' ? 'Pembatalan' : 'Stok Menipis' }}
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

    <div class="grid-2" style="margin-top:16px">
        <div class="card">
            <h3>Tren Pendapatan</h3>
            @php($max = max(1, $sales->where('status','completed')->groupBy(fn($sale) => $sale->created_at->format('d M'))->map->sum('total')->max() ?? 1))
            <div class="chart">
                @foreach($sales->where('status','completed')->groupBy(fn($sale) => $sale->created_at->format('d M'))->take(7) as $label => $items)
                    <div class="bar" style="height: {{ max(18, ($items->sum('total') / $max) * 140) }}px"><span>{{ $label }}</span></div>
                @endforeach
            </div>
        </div>
        <div class="card">
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
    </div>

    <div class="grid-2" style="margin-top:16px">
        <div class="card">
            <h3>Transaksi Terbaru</h3>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Invoice</th><th>Kasir</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($sales->take(8) as $sale)
                        <tr>
                            <td>{{ $sale->invoice_number }}</td>
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
                <div class="toolbar" style="justify-content:space-between">
                    <div>
                        <strong>{{ $product->name }}</strong>
                        <div class="muted">{{ $product->sku }} · {{ $product->store->name }} · Supplier: {{ $product->supplier ?: '-' }}</div>
                    </div>
                    <span class="badge {{ $product->stock <= 2 ? 'red' : 'amber' }}">{{ $product->stock }} pcs</span>
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
        <form method="post" action="{{ route('owner.restock') }}">
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
                    <label>Jumlah <span style="color:red">*</span></label>
                    <input class="input" name="qty" type="number" min="1" required placeholder="Contoh: 12 (1 bal)">
                </div>
                <div class="field">
                    <label>Harga per Unit (Rp) <span style="color:red">*</span></label>
                    <input class="input" name="unit_cost" type="number" min="0" required placeholder="Harga beli per pcs">
                </div>
                <div class="field" style="grid-column:1/-1">
                    <label>Catatan Barang</label>
                    <textarea class="input" name="notes" rows="2" maxlength="1000" placeholder="Contoh: 1 bal isi 12 pcs, beli di Tanah Abang blok A..."></textarea>
                </div>
            </div>
            <button class="button" style="margin-top:12px">Simpan Restock</button>
        </form>
    </div>
</x-layouts.app>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/app/owner-dashboard.blade.php
git commit -m "feat: owner dashboard with notifications panel and restock form"
```

---

## Task 9: Update reports.blade.php — hapus approval section, tampilkan riwayat void

**Files:**
- Modify: `resources/views/app/reports.blade.php`

- [ ] **Step 1: Ganti seluruh isi reports.blade.php**

```blade
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
                        <td class="money {{ $sale->status === 'voided' ? 'muted' : '' }}">
                            {{ $sale->status === 'voided' ? '-' : 'Rp '.number_format($sale->profit, 0, ',', '.') }}
                        </td>
                        <td>
                            <span class="badge {{ $sale->status === 'voided' ? 'red' : 'green' }}">
                                {{ $sale->status === 'voided' ? 'Void' : 'Selesai' }}
                            </span>
                        </td>
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
                    @if($sale->status === 'voided')
                        @php($voidRecord = $sale->corrections->firstWhere('status', 'self_voided'))
                        <div class="notice" style="background:#fde8e8;color:#b91c1c;border-color:#f87171;margin-bottom:12px">
                            <strong>Transaksi ini dibatalkan.</strong>
                            @if($voidRecord)
                                Oleh: {{ $voidRecord->requester->name }} pada {{ $voidRecord->created_at->format('d/m/Y H:i') }}.
                                Alasan: <em>{{ $voidRecord->reason }}</em>
                            @endif
                        </div>
                    @endif
                    <div class="detail-grid">
                        <div class="detail-box"><small>Kasir</small><strong>{{ $sale->cashier->name }}</strong></div>
                        <div class="detail-box"><small>Subtotal</small><strong>Rp {{ number_format($sale->subtotal, 0, ',', '.') }}</strong></div>
                        <div class="detail-box"><small>Diskon</small><strong>Rp {{ number_format($sale->discount_amount, 0, ',', '.') }}</strong></div>
                        <div class="detail-box"><small>Total</small><strong>Rp {{ number_format($sale->total, 0, ',', '.') }}</strong></div>
                        <div class="detail-box"><small>Profit</small><strong>{{ $sale->status === 'voided' ? '-' : 'Rp '.number_format($sale->profit, 0, ',', '.') }}</strong></div>
                        <div class="detail-box"><small>Metode</small><strong>{{ $sale->payment_method }}</strong></div>
                    </div>
                    <table>
                        <thead><tr><th>SKU</th><th>Barang</th><th>Qty</th><th>Modal/pcs</th><th>Jual/pcs</th><th>Total</th></tr></thead>
                        <tbody>
                        @foreach($sale->items as $item)
                            <tr>
                                <td>{{ $item->sku }}</td>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->qty }}</td>
                                <td>Rp {{ number_format($item->unit_cost, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format($item->line_total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @if($sale->discount)
                        <p style="margin-top:8px"><strong>Catatan diskon:</strong> {{ $sale->discount->reason }}</p>
                    @endif
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
                        <div class="detail-box"><small>Stok Sekarang</small><strong>{{ $product->stock }} pcs</strong></div>
                        <div class="detail-box"><small>Min Stok</small><strong>{{ $product->min_stock }} pcs</strong></div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</x-layouts.app>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/app/reports.blade.php
git commit -m "feat: reports page removes approval section, shows void detail in modal"
```

---

## Task 10: Mailable laporan harian email

**Files:**
- Create: `app/Mail/DailyReportMail.php`
- Create: `resources/views/mail/daily-report.blade.php`

- [ ] **Step 1: Buat Mailable**

```php
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $ownerName,
        public readonly string $date,
        public readonly int $totalTransactions,
        public readonly int $revenue,
        public readonly int $profit,
        public readonly array $voids,       // [['invoice' => ..., 'cashier' => ..., 'reason' => ...]]
        public readonly array $lowStocks,   // [['name' => ..., 'stock' => ..., 'supplier' => ...]]
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Laporan Harian Toko — {$this->date}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.daily-report',
        );
    }
}
```

- [ ] **Step 2: Buat template email**

Buat file `resources/views/mail/daily-report.blade.php`:

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: 0 auto; padding: 20px; }
        h1 { color: #1e3a5f; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; }
        h2 { color: #374151; font-size: 16px; margin-top: 24px; margin-bottom: 8px; }
        .box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px; margin-bottom: 16px; }
        .row { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #f3f4f6; }
        .row:last-child { border-bottom: none; }
        .label { color: #6b7280; }
        .value { font-weight: bold; }
        .void-item { background: #fef2f2; border-left: 4px solid #ef4444; padding: 8px 12px; margin: 6px 0; border-radius: 0 4px 4px 0; }
        .stock-item { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 8px 12px; margin: 6px 0; border-radius: 0 4px 4px 0; }
        .ok { color: #059669; font-weight: bold; }
        .footer { color: #9ca3af; font-size: 12px; margin-top: 32px; border-top: 1px solid #e5e7eb; padding-top: 12px; }
    </style>
</head>
<body>
    <h1>Laporan Harian Butik</h1>
    <p>Halo <strong>{{ $ownerName }}</strong>,</p>
    <p>Berikut ringkasan kegiatan toko hari ini, <strong>{{ $date }}</strong>:</p>

    <h2>Penjualan Hari Ini</h2>
    <div class="box">
        @if($totalTransactions === 0)
            <p style="color:#6b7280;margin:0">Tidak ada transaksi hari ini.</p>
        @else
            <div class="row">
                <span class="label">Total Transaksi</span>
                <span class="value">{{ $totalTransactions }} kali</span>
            </div>
            <div class="row">
                <span class="label">Pemasukan</span>
                <span class="value">Rp {{ number_format($revenue, 0, ',', '.') }}</span>
            </div>
            <div class="row">
                <span class="label">Keuntungan Bersih</span>
                <span class="value">Rp {{ number_format($profit, 0, ',', '.') }}</span>
            </div>
        @endif
    </div>

    <h2>Pembatalan Transaksi Hari Ini</h2>
    @if(count($voids) === 0)
        <p class="ok">Tidak ada transaksi yang dibatalkan hari ini.</p>
    @else
        @foreach($voids as $void)
            <div class="void-item">
                <strong>{{ $void['invoice'] }}</strong> dibatalkan oleh {{ $void['cashier'] }}<br>
                <span style="color:#6b7280">Alasan: {{ $void['reason'] }}</span>
            </div>
        @endforeach
    @endif

    <h2>Barang Stok Menipis</h2>
    @if(count($lowStocks) === 0)
        <p class="ok">Semua stok masih aman. Tidak perlu restock hari ini.</p>
    @else
        @foreach($lowStocks as $item)
            <div class="stock-item">
                <strong>{{ $item['name'] }}</strong> — sisa <strong>{{ $item['stock'] }} pcs</strong><br>
                <span style="color:#6b7280">Supplier: {{ $item['supplier'] ?: 'belum diisi' }}</span>
            </div>
        @endforeach
        <p style="color:#92400e">Segera lakukan restock melalui menu <em>Dashboard Owner → Restock Barang</em>.</p>
    @endif

    <div class="footer">
        <p>Email ini dikirim otomatis setiap hari pukul 20:00 oleh Sistem Butik POS.<br>
        Jika ada pertanyaan, hubungi admin toko Anda.</p>
    </div>
</body>
</html>
```

- [ ] **Step 3: Commit**

```bash
git add app/Mail/DailyReportMail.php resources/views/mail/daily-report.blade.php
git commit -m "feat: DailyReportMail mailable and email template"
```

---

## Task 11: Artisan Command + Laravel Scheduler

**Files:**
- Create: `app/Console/Commands/SendDailyReport.php`
- Modify: `bootstrap/app.php`

- [ ] **Step 1: Buat command**

```php
<?php

namespace App\Console\Commands;

use App\Mail\DailyReportMail;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleCorrection;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendDailyReport extends Command
{
    protected $signature = 'report:daily';
    protected $description = 'Kirim laporan harian ke email owner';

    public function handle(): int
    {
        $owners = User::query()->where('role', 'owner')->whereNotNull('email')->get();

        if ($owners->isEmpty()) {
            $this->warn('Tidak ada owner dengan email terdaftar.');
            return self::SUCCESS;
        }

        $today = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $dateLabel = now()->translatedFormat('d F Y');

        $completedSales = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$today, $todayEnd])
            ->get();

        $voidedSales = Sale::query()
            ->with('corrections.requester', 'cashier')
            ->where('status', 'voided')
            ->whereBetween('updated_at', [$today, $todayEnd])
            ->get();

        $voids = $voidedSales->map(function (Sale $sale): array {
            $correction = $sale->corrections->firstWhere('status', 'self_voided');
            return [
                'invoice' => $sale->invoice_number,
                'cashier' => $sale->cashier->name,
                'reason' => $correction?->reason ?? '-',
            ];
        })->values()->toArray();

        $lowStocks = Product::query()
            ->whereColumn('stock', '<=', 'min_stock')
            ->orderBy('stock')
            ->get()
            ->map(fn (Product $p): array => [
                'name' => $p->name,
                'stock' => $p->stock,
                'supplier' => $p->supplier,
            ])
            ->toArray();

        foreach ($owners as $owner) {
            try {
                Mail::to($owner->email)->send(new DailyReportMail(
                    ownerName: $owner->name,
                    date: $dateLabel,
                    totalTransactions: $completedSales->count(),
                    revenue: (int) $completedSales->sum('total'),
                    profit: (int) $completedSales->sum('profit'),
                    voids: $voids,
                    lowStocks: $lowStocks,
                ));
                $this->info("Laporan dikirim ke {$owner->email}");
            } catch (\Throwable $e) {
                Log::error("Gagal kirim laporan harian ke {$owner->email}: {$e->getMessage()}");
                $this->error("Gagal kirim ke {$owner->email}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 2: Daftarkan schedule di `bootstrap/app.php`**

Buka `bootstrap/app.php`. Tambahkan schedule di dalam method `withSchedule` atau tambahkan blok baru jika belum ada. Cari baris `return Application::configure(...)` dan sebelum `->create()`, tambahkan:

```php
->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
    $schedule->command('report:daily')->dailyAt('20:00');
})
```

Contoh akhir `bootstrap/app.php` setelah edit:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('report:daily')->dailyAt('20:00');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

- [ ] **Step 3: Test command berjalan (dengan mail driver = log)**

Pastikan `.env` punya `MAIL_MAILER=log` agar email tidak benar-benar terkirim saat development:

```bash
php artisan report:daily
```

Expected output:
```
Laporan dikirim ke owner@butik.test
```

Cek `storage/logs/laravel.log` untuk melihat email log output.

- [ ] **Step 4: Commit**

```bash
git add app/Console/Commands/SendDailyReport.php bootstrap/app.php
git commit -m "feat: daily report command and scheduler at 20:00"
```

---

## Task 12: Konfigurasi email (.env.example)

**Files:**
- Modify: `.env.example`

- [ ] **Step 1: Tambah variabel mail ke .env.example**

Ganti blok MAIL di `.env.example` dengan:

```env
# Email — gunakan Gmail SMTP untuk production
# MAIL_MAILER=smtp
# MAIL_HOST=smtp.gmail.com
# MAIL_PORT=587
# MAIL_USERNAME=emailtoko@gmail.com
# MAIL_PASSWORD=app_password_gmail
# MAIL_ENCRYPTION=tls
# MAIL_FROM_ADDRESS=emailtoko@gmail.com
# MAIL_FROM_NAME="Butik POS"

# Development: gunakan log (email tidak benar-benar terkirim)
MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="noreply@butik.test"
MAIL_FROM_NAME="${APP_NAME}"
```

- [ ] **Step 2: Commit**

```bash
git add .env.example
git commit -m "docs: add Gmail SMTP instructions to .env.example"
```

---

## Task 13: Jalankan semua test — pastikan semua PASS

- [ ] **Step 1: Jalankan full test suite**

```bash
php artisan test
```

Expected: semua test PASS, tidak ada failure.

- [ ] **Step 2: Jika ada test lama yang masih referensi route `sales.corrections.approve` atau `requestCorrection`**

Cari dan hapus/update:

```bash
grep -n "corrections.approve\|requestCorrection\|approveCorrection" tests/Feature/PosTransactionTest.php
```

Jika masih ada, update test tersebut agar menggunakan flow baru (`selfVoid`).

- [ ] **Step 3: Verifikasi route list bersih**

```bash
php artisan route:list
```

Pastikan:
- Route `sales.self-void` ada
- Route `owner.restock` ada
- Route `owner.notifications.read-all` ada
- Route `sales.corrections.approve` **tidak ada**
- Route `sales.corrections.request` **tidak ada**

- [ ] **Step 4: Commit final jika ada perbaikan**

```bash
git add -A
git commit -m "fix: clean up old correction routes and tests"
```

---

## Task 14: Smoke test manual browser

- [ ] **Step 1: Jalankan server**

```bash
php artisan serve
```

- [ ] **Step 2: Login sebagai kasir → buat transaksi → coba void**

1. Buka `http://localhost:8000/kasir`
2. Tambah produk ke keranjang, checkout
3. Buka `http://localhost:8000/kasir/history`
4. Klik "Batalkan" pada transaksi terbaru
5. Isi alasan kurang dari 8 karakter → harus ditolak browser (minlength)
6. Isi alasan valid → submit → flash "Transaksi berhasil dibatalkan"
7. Status transaksi berubah menjadi "Dibatalkan"

- [ ] **Step 3: Verifikasi notifikasi di dashboard owner**

1. Login sebagai owner
2. Buka `http://localhost:8000/owner/dashboard`
3. Panel notifikasi harus tampil dengan info void tadi
4. Badge "X notifikasi baru" muncul di topbar
5. Klik "Tandai Semua Dibaca" → badge hilang

- [ ] **Step 4: Test restock**

1. Di dashboard owner, scroll ke form Restock Barang
2. Pilih produk, isi supplier, jumlah, harga
3. Submit → flash sukses, stok produk bertambah
4. Notifikasi low_stock produk tersebut (jika ada) hilang dari panel

- [ ] **Step 5: Test laporan email (log mode)**

```bash
php artisan report:daily
```

Cek `storage/logs/laravel.log` — pastikan email body berisi ringkasan transaksi hari ini.

- [ ] **Step 6: Verifikasi laporan halaman owner**

1. Buka `http://localhost:8000/owner/laporan`
2. Transaksi void tampil dengan badge "Void" merah
3. Klik "Detail" pada transaksi void → modal menampilkan alasan void, kasir, waktu
4. Profit kolom untuk void tampil sebagai "-"

---

## Self-Review Checklist

**Spec coverage:**
- [x] Void kasir tanpa approval owner → Task 3, 4, 5, 6
- [x] Wajib alasan min 8 karakter → Task 3, 4 (selfVoid validation), Task 6 (minlength di form)
- [x] Stok dikembalikan atomik → Task 4 (DB transaction di selfVoid)
- [x] Kasir hanya void milik sendiri → Task 3 (test 403), Task 5 (abort_unless)
- [x] Tidak bisa void dua kali → Task 3 (test), Task 4 (cek status completed)
- [x] Notifikasi in-app void_transaction → Task 4 (selfVoid creates notification), Task 7, 8
- [x] Notifikasi in-app low_stock setelah checkout → Task 4 (createLowStockNotification)
- [x] Deduplikasi low_stock → Task 3 (test), Task 4 (whereNull read_at check)
- [x] Restock resolve notifikasi low_stock → Task 5 (ownerRestock)
- [x] Laporan harian email jam 20:00 → Task 10, 11
- [x] Email awam-friendly → Task 10 (template plain language)
- [x] Edge case: email owner kosong → Task 11 (whereNotNull email)
- [x] Edge case: tidak ada transaksi hari ini → Task 10 (template handle 0)
- [x] Restock owner-only → Task 5 (abort_unless isOwner), Task 8 (form di dashboard)
- [x] Restock simpan ke purchases untuk pembukuan → Task 5
- [x] Hapus flow approval lama → Task 5 (route dihapus), Task 9 (view updated)
- [x] Security: semua abort_unless → Task 5
- [x] Audit trail void tidak bisa dihapus → tidak ada DELETE route

**Placeholder scan:** Tidak ada TBD atau TODO dalam plan ini.

**Type consistency:**
- `selfVoid(User $cashier, Sale $sale, string $reason): SaleCorrection` — konsisten di Task 3, 4, 5
- `createLowStockNotification(Product $product): void` — konsisten di Task 4
- `DailyReportMail` constructor params — konsisten di Task 10, 11
- Route `sales.self-void` — konsisten di Task 5, 6
- `status = 'self_voided'` — konsisten di Task 3, 4, 9, 11
