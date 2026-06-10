<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Feature\Concerns\CreatesUsers;

class BulkProductTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    private function store(): Store
    {
        return Store::query()->create(['name' => 'Butik Utama', 'code' => 'BTK', 'address' => 'Jl. Test']);
    }

    public function test_guest_is_redirected_from_bulk_store(): void
    {
        $this->post('/barang/bulk', [])->assertRedirect('/login');
    }

    public function test_owner_can_bulk_create_products(): void
    {
        $store = $this->store();

        $response = $this->actingAs($this->owner())->post('/barang/bulk', [
            'rows' => [
                [
                    'store_id'      => $store->id,
                    'name'          => 'Kemeja Putih',
                    'category'      => 'kemeja',
                    'color'         => 'Putih',
                    'size'          => 'M',
                    'supplier'      => 'Supplier A',
                    'cost_price'    => 90000,
                    'selling_price' => 185000,
                    'stock'         => 5,
                ],
                [
                    'store_id'      => $store->id,
                    'name'          => 'Celana Navy',
                    'category'      => 'celana',
                    'color'         => 'Navy',
                    'size'          => 'L',
                    'supplier'      => 'Supplier B',
                    'cost_price'    => 110000,
                    'selling_price' => 235000,
                    'stock'         => 4,
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', '2 barang berhasil ditambahkan.');
        $this->assertDatabaseHas('products', ['name' => 'Kemeja Putih', 'category' => 'kemeja']);
        $this->assertDatabaseHas('products', ['name' => 'Celana Navy', 'category' => 'celana']);
        $this->assertSame(2, Product::query()->count());
    }

    public function test_bulk_products_get_auto_generated_sku(): void
    {
        $store = $this->store();

        $this->actingAs($this->owner())->post('/barang/bulk', [
            'rows' => [[
                'store_id'      => $store->id,
                'name'          => 'Kaos Hitam',
                'category'      => 'kaos',
                'color'         => 'Hitam',
                'size'          => 'S',
                'cost_price'    => 50000,
                'selling_price' => 99000,
                'stock'         => 3,
            ]],
        ]);

        $product = Product::query()->first();
        $this->assertNotNull($product->sku);
        $this->assertStringStartsWith('BTK-', $product->sku);
    }

    public function test_bulk_create_requires_name(): void
    {
        $store = $this->store();

        $response = $this->actingAs($this->owner())->post('/barang/bulk', [
            'rows' => [[
                'store_id'      => $store->id,
                'name'          => '',  // empty
                'category'      => 'kaos',
                'cost_price'    => 50000,
                'selling_price' => 99000,
                'stock'         => 3,
            ]],
        ]);

        $response->assertSessionHasErrors('rows.0.name');
        $this->assertSame(0, Product::query()->count());
    }

    public function test_bulk_create_sets_min_stock_to_zero(): void
    {
        $store = $this->store();

        $this->actingAs($this->owner())->post('/barang/bulk', [
            'rows' => [[
                'store_id'      => $store->id,
                'name'          => 'Gaun Merah',
                'category'      => 'gaun',
                'cost_price'    => 120000,
                'selling_price' => 259000,
                'stock'         => 2,
            ]],
        ]);

        $this->assertSame(0, Product::query()->first()->min_stock);
    }

    public function test_cashier_can_also_bulk_create_products(): void
    {
        $store = $this->store();

        $response = $this->actingAs($this->cashier())->post('/barang/bulk', [
            'rows' => [[
                'store_id'      => $store->id,
                'name'          => 'Rok Merah',
                'category'      => 'rok',
                'cost_price'    => 70000,
                'selling_price' => 149000,
                'stock'         => 4,
            ]],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', '1 barang berhasil ditambahkan.');
    }
}
