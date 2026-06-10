<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesUsers;
use Tests\TestCase;

class ProductsTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    private function store(): Store
    {
        return Store::query()->create([
            'name'    => 'Butik Utama',
            'code'    => 'BTK',
            'address' => 'Jl. Test No. 1',
        ]);
    }

    private function validProductPayload(int $storeId): array
    {
        return [
            'store_id'      => $storeId,
            'name'          => 'Kemeja Linen Putih',
            'category'      => 'kemeja',
            'color'         => 'Putih',
            'size'          => 'M',
            'supplier'      => 'Supplier B',
            'cost_price'    => 80000,
            'selling_price' => 160000,
            'stock'         => 5,
        ];
    }

    public function test_guest_is_redirected_from_products_page(): void
    {
        $this->get('/barang')->assertRedirect('/login');
    }

    public function test_cashier_can_view_products_page(): void
    {
        $this->actingAs($this->cashier())->get('/barang')->assertOk();
    }

    public function test_owner_can_view_products_page(): void
    {
        $this->actingAs($this->owner())->get('/barang')->assertOk();
    }

    public function test_owner_can_create_a_product(): void
    {
        $store = $this->store();

        $response = $this->actingAs($this->owner())
            ->post('/barang', $this->validProductPayload($store->id));

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Barang berhasil ditambahkan.');
    }

    public function test_creation_requires_store_id(): void
    {
        $payload = $this->validProductPayload(999);
        $payload['store_id'] = '';

        $this->actingAs($this->owner())
            ->post('/barang', $payload)
            ->assertSessionHasErrors('store_id');
    }

    public function test_creation_requires_selling_price(): void
    {
        $store = $this->store();
        $payload = $this->validProductPayload($store->id);
        unset($payload['selling_price']);

        $this->actingAs($this->owner())
            ->post('/barang', $payload)
            ->assertSessionHasErrors('selling_price');
    }

    public function test_created_product_appears_in_db_with_correct_fields(): void
    {
        $store = $this->store();

        $this->actingAs($this->owner())
            ->post('/barang', $this->validProductPayload($store->id));

        $this->assertDatabaseHas('products', [
            'store_id'      => $store->id,
            'name'          => 'Kemeja Linen Putih',
            'category'      => 'kemeja',
            'color'         => 'Putih',
            'size'          => 'M',
            'cost_price'    => 80000,
            'selling_price' => 160000,
            'stock'         => 5,
            'min_stock'     => 0,
        ]);
    }

    public function test_cashier_can_also_create_product(): void
    {
        $store = Store::query()->create(['name' => 'Butik Utama', 'code' => 'BTK', 'address' => 'Jl. Test']);

        $response = $this->actingAs($this->cashier())->post('/barang', [
            'store_id'      => $store->id,
            'name'          => 'Kaos Putih',
            'category'      => 'kaos',
            'color'         => 'Putih',
            'size'          => 'M',
            'cost_price'    => 50000,
            'selling_price' => 95000,
            'stock'         => 10,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Barang berhasil ditambahkan.');
        $this->assertDatabaseHas('products', ['name' => 'Kaos Putih', 'category' => 'kaos']);
    }

    public function test_sku_is_auto_generated_after_create(): void
    {
        $store = $this->store();

        $this->actingAs($this->owner())
            ->post('/barang', $this->validProductPayload($store->id));

        $product = Product::query()->where('name', 'Kemeja Linen Putih')->first();

        $this->assertNotNull($product);
        $this->assertNotNull($product->sku);
        $this->assertNotEmpty($product->sku);
    }
}
