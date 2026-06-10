<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\PosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PosTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_cannot_access_owner_dashboard(): void
    {
        $cashier = User::query()->create([
            'name' => 'Kasir Utama',
            'email' => 'kasir@butik.test',
            'password' => Hash::make('password'),
            'role' => 'cashier',
        ]);

        $this->actingAs($cashier)->get('/owner/dashboard')->assertForbidden();
    }

    public function test_owner_can_access_income_report(): void
    {
        $owner = User::query()->create([
            'name' => 'Owner Butik',
            'email' => 'owner@butik.test',
            'password' => Hash::make('password'),
            'role' => 'owner',
        ]);

        $this->actingAs($owner)->get('/owner/dashboard')->assertOk();
    }

    public function test_cashier_discount_is_recorded_without_owner_approval(): void
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
            'discount_type' => 'amount',
            'discount_value' => 25000,
            'discount_reason' => 'Permintaan customer loyal',
            'items' => [
                ['product_id' => $product->id, 'qty' => 1],
            ],
        ]);

        $this->assertSame(160000, $sale->total);
        $this->assertSame(40000, $sale->change);
        $this->assertSame(70000, $sale->profit);
        $this->assertSame(2, $product->fresh()->stock);
        $this->assertSame(25000, $sale->discount_amount);
        $this->assertSame('recorded', $sale->discount->status);
        $this->assertSame('Permintaan customer loyal', $sale->discount->reason);
        $this->assertSame($cashier->id, $sale->discount->requested_by);
    }

    public function test_checkout_rejects_quantity_above_stock(): void
    {
        $cashier = User::query()->create([
            'name' => 'Kasir Utama',
            'email' => 'kasir@butik.test',
            'password' => Hash::make('password'),
            'role' => 'cashier',
        ]);

        $product = $this->product(stock: 1);

        $this->expectExceptionMessage('Stok tidak cukup untuk Kemeja Linen Hitam.');

        app(PosService::class)->checkout($cashier, [
            'payment_method' => 'QRIS',
            'amount_paid' => 500000,
            'items' => [
                ['product_id' => $product->id, 'qty' => 2],
            ],
        ]);
    }

    public function test_checkout_error_keeps_cart_input_when_discount_reason_is_missing(): void
    {
        $cashier = User::query()->create([
            'name' => 'Kasir Utama',
            'email' => 'kasir@butik.test',
            'password' => Hash::make('password'),
            'role' => 'cashier',
        ]);

        $product = $this->product(stock: 3, price: 185000, cost: 90000);

        $response = $this->actingAs($cashier)->post('/kasir/checkout', [
            'payment_method' => 'Tunai',
            'amount_paid' => 200000,
            'discount_type' => 'amount',
            'discount_value' => 10000,
            'discount_reason' => '',
            'items' => [
                ['product_id' => $product->id, 'qty' => 1],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('checkout');
        $this->assertSame(
            [['product_id' => $product->id, 'qty' => 1]],
            session()->getOldInput('items')
        );
    }

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
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame(1, $sale->fresh()->corrections()->count());
        $this->assertSame('self_voided', $sale->fresh()->corrections()->first()->status);

        $this->assertDatabaseHas('notifications', [
            'type' => 'void_transaction',
        ]);
    }

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

    public function test_low_stock_notification_created_after_checkout(): void
    {
        $cashier = User::query()->create([
            'name' => 'Kasir Utama', 'email' => 'kasir@butik.test',
            'password' => Hash::make('password'), 'role' => 'cashier',
        ]);

        // stock=2 → buy 1 → stock=1, triggers low_stock notif
        $product = $this->product(stock: 2, price: 185000, cost: 90000);
        $service = app(PosService::class);

        $service->checkout($cashier, [
            'payment_method' => 'Tunai',
            'amount_paid'    => 200000,
            'items'          => [['product_id' => $product->id, 'qty' => 1]],
        ]);

        $this->assertDatabaseHas('notifications', ['type' => 'low_stock']);
    }

    public function test_low_stock_notification_not_duplicated(): void
    {
        $cashier = User::query()->create([
            'name' => 'Kasir Utama', 'email' => 'kasir@butik.test',
            'password' => Hash::make('password'), 'role' => 'cashier',
        ]);

        $product = $this->product(stock: 4, price: 185000, cost: 90000);
        $service = app(PosService::class);

        // stock 4→3: no notif
        $service->checkout($cashier, [
            'payment_method' => 'Tunai', 'amount_paid' => 200000,
            'items' => [['product_id' => $product->id, 'qty' => 1]],
        ]);
        $this->assertDatabaseCount('notifications', 0);

        // stock 3→2: no notif
        $service->checkout($cashier, [
            'payment_method' => 'Tunai', 'amount_paid' => 200000,
            'items' => [['product_id' => $product->id, 'qty' => 1]],
        ]);
        $this->assertDatabaseCount('notifications', 0);

        // stock 2→1: first low_stock notif
        $service->checkout($cashier, [
            'payment_method' => 'Tunai', 'amount_paid' => 200000,
            'items' => [['product_id' => $product->id, 'qty' => 1]],
        ]);
        $this->assertDatabaseCount('notifications', 1);

        // stock 1→0: low_stock resolved, out_of_stock created → total 2 notifications
        $service->checkout($cashier, [
            'payment_method' => 'Tunai', 'amount_paid' => 200000,
            'items' => [['product_id' => $product->id, 'qty' => 1]],
        ]);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', ['type' => 'out_of_stock', 'read_at' => null]);
    }

    private function product(int $stock = 5, int $price = 185000, int $cost = 90000): Product
    {
        $store = Store::query()->create([
            'name' => 'Butik Utama',
            'code' => 'BTK',
            'address' => 'Jl. Sudirman No. 12',
        ]);

        return Product::query()->create([
            'store_id' => $store->id,
            'sku' => 'BTK-KEM-HIT-L-001',
            'name' => 'Kemeja Linen Hitam',
            'category' => 'kemeja',
            'color' => 'Hitam',
            'size' => 'L',
            'supplier' => 'Supplier A',
            'cost_price' => $cost,
            'selling_price' => $price,
            'stock' => $stock,
            'min_stock' => 0,
        ]);
    }
}
