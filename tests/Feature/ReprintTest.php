<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\PosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReprintTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_history_shows_cetak_button_for_completed_sale(): void
    {
        $cashier = $this->cashier();
        $sale    = $this->sale($cashier);

        $response = $this->actingAs($cashier)->get('/kasir/history');

        $response->assertOk();
        $response->assertSee("/kasir/struk/{$sale->id}", false);
        $response->assertSee("cetak-btn-{$sale->id}", false);
    }

    public function test_cashier_history_cetak_button_absent_for_voided_sale(): void
    {
        $cashier = $this->cashier();
        $sale    = $this->sale($cashier);

        app(PosService::class)->selfVoid($cashier, $sale, 'Salah input barang, void untuk tes.');

        $response = $this->actingAs($cashier)->get('/kasir/history');

        $response->assertOk();
        $response->assertDontSee("cetak-btn-{$sale->id}", false);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function cashier(string $email = 'kasir@butik.test'): User
    {
        return User::query()->create([
            'name'     => 'Kasir Utama',
            'email'    => $email,
            'password' => Hash::make('password'),
            'role'     => 'cashier',
        ]);
    }

    private function product(int $stock = 5, int $price = 185000, int $cost = 90000): Product
    {
        $store = Store::query()->firstOrCreate(
            ['code' => 'BTK'],
            [
                'name'    => 'Butik Utama',
                'address' => 'Jl. Sudirman No. 12',
            ]
        );

        return Product::query()->create([
            'store_id'      => $store->id,
            'sku'           => 'BTK-KEM-HIT-L-' . rand(100, 999),
            'name'          => 'Kemeja Linen Hitam',
            'category'      => 'kemeja',
            'color'         => 'Hitam',
            'size'          => 'L',
            'supplier'      => 'Supplier A',
            'cost_price'    => $cost,
            'selling_price' => $price,
            'stock'         => $stock,
            'min_stock'     => 3,
        ]);
    }

    private function sale(User $cashier): \App\Models\Sale
    {
        $product = $this->product();

        return app(PosService::class)->checkout($cashier, [
            'payment_method' => 'Tunai',
            'amount_paid'    => 200000,
            'items'          => [
                ['product_id' => $product->id, 'qty' => 1],
            ],
        ]);
    }
}
