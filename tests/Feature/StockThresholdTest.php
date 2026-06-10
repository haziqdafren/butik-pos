<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\PosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StockThresholdTest extends TestCase
{
    use RefreshDatabase;

    private function cashier(): User
    {
        return User::query()->create([
            'name'     => 'Kasir Utama',
            'email'    => 'kasir@butik.test',
            'password' => Hash::make('password'),
            'role'     => 'cashier',
        ]);
    }

    private function product(int $stock): Product
    {
        $store = Store::query()->create(['name' => 'Butik', 'code' => 'BTK', 'address' => 'Jl. Test']);
        return Product::query()->create([
            'store_id'       => $store->id,
            'sku'            => 'BTK-KEM-HIT-L-001',
            'name'           => 'Kemeja Linen Hitam',
            'category'       => 'kemeja',
            'color'          => 'Hitam',
            'size'           => 'L',
            'cost_price'     => 90000,
            'selling_price'  => 185000,
            'stock'          => $stock,
            'min_stock'      => 0,
        ]);
    }

    public function test_stock_status_ok_when_two_or_more(): void
    {
        $product = $this->product(2);
        $this->assertSame('ok', $product->stockStatus());
        $this->assertSame('green', $product->stockBadgeClass());
        $this->assertSame('Stok 2', $product->stockLabel());
    }

    public function test_stock_status_low_when_one(): void
    {
        $product = $this->product(1);
        $this->assertSame('low', $product->stockStatus());
        $this->assertSame('amber', $product->stockBadgeClass());
        $this->assertSame('Sedikit', $product->stockLabel());
    }

    public function test_stock_status_out_when_zero(): void
    {
        $product = $this->product(0);
        $this->assertSame('out', $product->stockStatus());
        $this->assertSame('red', $product->stockBadgeClass());
        $this->assertSame('Habis', $product->stockLabel());
    }

    public function test_checkout_triggers_low_stock_notif_when_stock_reaches_one(): void
    {
        $cashier = $this->cashier();
        $product = $this->product(2); // start at 2, buy 1 → stock = 1

        app(PosService::class)->checkout($cashier, [
            'payment_method' => 'Tunai',
            'amount_paid'    => 200000,
            'items'          => [['product_id' => $product->id, 'qty' => 1]],
        ]);

        $this->assertDatabaseHas('notifications', ['type' => 'low_stock']);
        $this->assertDatabaseMissing('notifications', ['type' => 'out_of_stock']);
    }

    public function test_checkout_triggers_out_of_stock_notif_when_stock_reaches_zero(): void
    {
        $cashier = $this->cashier();
        $product = $this->product(1); // start at 1, buy 1 → stock = 0

        app(PosService::class)->checkout($cashier, [
            'payment_method' => 'Tunai',
            'amount_paid'    => 200000,
            'items'          => [['product_id' => $product->id, 'qty' => 1]],
        ]);

        $this->assertDatabaseHas('notifications', ['type' => 'out_of_stock']);
    }

    public function test_out_of_stock_resolves_previous_low_stock_notif(): void
    {
        $cashier = $this->cashier();
        $product = $this->product(2);

        // First checkout: stock → 1, low_stock notif created
        app(PosService::class)->checkout($cashier, [
            'payment_method' => 'Tunai',
            'amount_paid'    => 200000,
            'items'          => [['product_id' => $product->id, 'qty' => 1]],
        ]);

        $this->assertDatabaseHas('notifications', ['type' => 'low_stock', 'read_at' => null]);

        // Second checkout: stock → 0, low_stock resolved, out_of_stock created
        app(PosService::class)->checkout($cashier, [
            'payment_method' => 'Tunai',
            'amount_paid'    => 200000,
            'items'          => [['product_id' => $product->fresh()->id, 'qty' => 1]],
        ]);

        // low_stock should be read (resolved)
        $lowNotif = Notification::where('type', 'low_stock')->first();
        $this->assertNotNull($lowNotif->read_at);

        // out_of_stock should be unread
        $this->assertDatabaseHas('notifications', ['type' => 'out_of_stock', 'read_at' => null]);
    }

    public function test_no_duplicate_low_stock_notif(): void
    {
        $cashier = $this->cashier();
        $product = $this->product(3);

        // stock 3 → 2: no notif
        app(PosService::class)->checkout($cashier, [
            'payment_method' => 'Tunai',
            'amount_paid'    => 200000,
            'items'          => [['product_id' => $product->id, 'qty' => 1]],
        ]);
        $this->assertDatabaseCount('notifications', 0);

        // stock 2 → 1: first notif
        app(PosService::class)->checkout($cashier, [
            'payment_method' => 'Tunai',
            'amount_paid'    => 200000,
            'items'          => [['product_id' => $product->id, 'qty' => 1]],
        ]);
        $this->assertDatabaseCount('notifications', 1);
    }
}
