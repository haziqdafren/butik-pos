<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\PosService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_receipt(): void
    {
        $cashier = $this->cashier();
        $sale    = $this->sale($cashier);

        $this->get("/kasir/struk/{$sale->id}")->assertRedirect('/login');
    }

    public function test_cashier_can_view_own_receipt(): void
    {
        $cashier = $this->cashier();
        $sale    = $this->sale($cashier);

        $response = $this->actingAs($cashier)->get("/kasir/struk/{$sale->id}");

        $response->assertOk();
        $response->assertSee($sale->invoice_number);
        $response->assertSee($cashier->name);
        $response->assertSee('Kemeja Linen Hitam');
    }

    public function test_other_cashier_cannot_view_receipt(): void
    {
        $cashier1 = $this->cashier('kasir1@butik.test');
        $cashier2 = $this->cashier('kasir2@butik.test');
        $sale     = $this->sale($cashier1);

        $this->actingAs($cashier2)->get("/kasir/struk/{$sale->id}")->assertForbidden();
    }

    public function test_owner_can_view_any_receipt(): void
    {
        $cashier = $this->cashier();
        $owner   = User::query()->create([
            'name'     => 'Owner Butik',
            'email'    => 'owner@butik.test',
            'password' => Hash::make('password'),
            'role'     => 'owner',
        ]);
        $sale = $this->sale($cashier);

        $this->actingAs($owner)->get("/kasir/struk/{$sale->id}")->assertOk();
    }

    public function test_receipt_shows_store_settings(): void
    {
        $cashier  = $this->cashier();
        $sale     = $this->sale($cashier);
        $settings = app(SettingsService::class);

        $settings->setMany([
            'store_name'     => 'Butik Cantik',
            'receipt_footer' => 'Sampai jumpa lagi!',
        ]);

        $response = $this->actingAs($cashier)->get("/kasir/struk/{$sale->id}");

        $response->assertOk();
        $response->assertSee('Butik Cantik');
        $response->assertSee('Sampai jumpa lagi!');
    }

    public function test_checkout_flashes_print_sale_id(): void
    {
        $cashier = $this->cashier();
        $product = $this->product();

        $response = $this->actingAs($cashier)->post('/kasir/checkout', [
            'payment_method' => 'Tunai',
            'amount_paid'    => 200000,
            'items'          => [
                ['product_id' => $product->id, 'qty' => 1],
            ],
        ]);

        $response->assertSessionHas('print_sale_id');
    }

    public function test_receipt_page_has_auto_print_script(): void
    {
        $cashier = $this->cashier();
        $sale    = $this->sale($cashier);

        $response = $this->actingAs($cashier)->get("/kasir/struk/{$sale->id}");

        $response->assertOk();
        $response->assertSee('autoPrint');
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
        $store = Store::query()->create([
            'name'    => 'Butik Utama',
            'code'    => 'BTK',
            'address' => 'Jl. Sudirman No. 12',
        ]);

        return Product::query()->create([
            'store_id'      => $store->id,
            'sku'           => 'BTK-KEM-HIT-L-001',
            'name'          => 'Kemeja Linen Hitam',
            'category'      => 'kemeja',
            'color'         => 'Hitam',
            'size'          => 'L',
            'supplier'      => 'Supplier A',
            'cost_price'    => $cost,
            'selling_price' => $price,
            'stock'         => $stock,
            'min_stock'     => 0,
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
