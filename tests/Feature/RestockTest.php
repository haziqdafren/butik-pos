<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RestockTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::query()->create([
            'name'     => 'Owner Butik',
            'email'    => 'owner@butik.test',
            'password' => Hash::make('password'),
            'role'     => 'owner',
        ]);
    }

    private function cashier(): User
    {
        return User::query()->create([
            'name'     => 'Kasir Utama',
            'email'    => 'kasir@butik.test',
            'password' => Hash::make('password'),
            'role'     => 'cashier',
        ]);
    }

    private function product(int $stock = 10): Product
    {
        $store = Store::query()->create([
            'name'    => 'Butik Utama',
            'code'    => 'BTK',
            'address' => 'Jl. Test No. 1',
        ]);

        return Product::query()->create([
            'store_id'      => $store->id,
            'sku'           => 'BTK-KEM-HIT-L-001',
            'name'          => 'Kemeja Linen Hitam',
            'category'      => 'kemeja',
            'color'         => 'Hitam',
            'size'          => 'L',
            'supplier'      => 'Supplier A',
            'cost_price'    => 90000,
            'selling_price' => 185000,
            'stock'         => $stock,
            'min_stock'     => 3,
        ]);
    }

    public function test_guest_is_redirected_from_owner_restock(): void
    {
        $product = $this->product();

        $this->post('/owner/restock', [
            'product_id' => $product->id,
            'qty'        => 5,
            'unit_cost'  => 90000,
        ])->assertRedirect('/login');
    }

    public function test_cashier_gets_403_on_owner_restock(): void
    {
        $product = $this->product();

        $this->actingAs($this->cashier())
            ->post('/owner/restock', [
                'product_id' => $product->id,
                'qty'        => 5,
                'unit_cost'  => 90000,
            ])->assertForbidden();
    }

    public function test_owner_can_restock_a_product_and_stock_increases(): void
    {
        $owner   = $this->owner();
        $product = $this->product(stock: 10);

        $response = $this->actingAs($owner)->post('/owner/restock', [
            'product_id' => $product->id,
            'qty'        => 5,
            'unit_cost'  => 95000,
            'supplier'   => 'Supplier Baru',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $fresh = $product->fresh();
        $this->assertSame(15, $fresh->stock);
        $this->assertSame(95000, $fresh->cost_price);
    }

    public function test_restock_resolves_low_stock_notification_for_that_product(): void
    {
        $owner   = $this->owner();
        $product = $this->product(stock: 2);

        // Create a second product in a separate store so we can verify scoped resolution
        $otherStore = Store::query()->create([
            'name'    => 'Butik Cabang',
            'code'    => 'CAB',
            'address' => 'Jl. Cabang No. 2',
        ]);
        $otherProduct = Product::query()->create([
            'store_id'      => $otherStore->id,
            'sku'           => 'CAB-KAO-PUT-S-001',
            'name'          => 'Kaos Polos Putih',
            'category'      => 'kaos',
            'color'         => 'Putih',
            'size'          => 'S',
            'supplier'      => 'Supplier B',
            'cost_price'    => 40000,
            'selling_price' => 80000,
            'stock'         => 1,
            'min_stock'     => 3,
        ]);

        // Create an unread low_stock notification for the primary product
        Notification::query()->create([
            'type'  => 'low_stock',
            'title' => 'Stok Rendah',
            'body'  => 'Kemeja Linen Hitam stok menipis.',
            'data'  => ['product_id' => $product->id],
        ]);

        // Create an unread low_stock notification for the OTHER product
        $otherNotification = Notification::query()->create([
            'type'  => 'low_stock',
            'title' => 'Stok Rendah',
            'body'  => 'Kaos Polos Putih stok menipis.',
            'data'  => ['product_id' => $otherProduct->id],
        ]);

        $this->assertDatabaseHas('notifications', [
            'type'    => 'low_stock',
            'read_at' => null,
        ]);

        $this->actingAs($owner)->post('/owner/restock', [
            'product_id' => $product->id,
            'qty'        => 10,
            'unit_cost'  => 90000,
        ]);

        // The notification for the restocked product should now be marked read
        $notification = Notification::query()
            ->whereJsonContains('data->product_id', $product->id)
            ->first();
        $this->assertNotNull($notification->read_at);

        // The notification for the OTHER product must remain unread (scoped query check)
        $otherNotification->refresh();
        $this->assertNull($otherNotification->read_at);
    }

    public function test_restock_requires_qty_at_least_1(): void
    {
        $owner   = $this->owner();
        $product = $this->product();

        $this->actingAs($owner)->post('/owner/restock', [
            'product_id' => $product->id,
            'qty'        => 0,
            'unit_cost'  => 90000,
        ])->assertSessionHasErrors('qty');
    }

    public function test_restock_requires_existing_product_id(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post('/owner/restock', [
            'product_id' => 99999,
            'qty'        => 5,
            'unit_cost'  => 90000,
        ])->assertSessionHasErrors('product_id');
    }
}
