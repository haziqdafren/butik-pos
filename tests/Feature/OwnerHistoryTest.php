<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tests\Feature\Concerns\CreatesUsers;

class OwnerHistoryTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    public function test_guest_is_redirected_from_owner_history(): void
    {
        $this->get('/owner/history')->assertRedirect('/login');
    }

    public function test_cashier_gets_403_on_owner_history(): void
    {
        $this->actingAs($this->cashier())->get('/owner/history')->assertForbidden();
    }

    public function test_owner_can_view_owner_history(): void
    {
        $this->actingAs($this->owner())->get('/owner/history')->assertOk();
    }

    public function test_owner_history_shows_all_cashier_sales(): void
    {
        $owner   = $this->owner();
        $cashier = $this->cashier();
        $store   = Store::query()->create(['name' => 'Butik', 'code' => 'BTK', 'address' => 'Jl. Test']);

        // Sale by cashier
        Sale::query()->create([
            'invoice_number' => 'INV-CSR-001',
            'user_id'        => $cashier->id,
            'store_id'       => $store->id,
            'subtotal'       => 185000,
            'discount_amount'=> 0,
            'total'          => 185000,
            'amount_paid'    => 200000,
            'change'         => 15000,
            'payment_method' => 'Tunai',
            'cogs'           => 90000,
            'profit'         => 95000,
            'status'         => 'completed',
        ]);

        // Sale by owner
        Sale::query()->create([
            'invoice_number' => 'INV-OWN-002',
            'user_id'        => $owner->id,
            'store_id'       => $store->id,
            'subtotal'       => 200000,
            'discount_amount'=> 0,
            'total'          => 200000,
            'amount_paid'    => 200000,
            'change'         => 0,
            'payment_method' => 'QRIS',
            'cogs'           => 100000,
            'profit'         => 100000,
            'status'         => 'completed',
        ]);

        $response = $this->actingAs($owner)->get('/owner/history');
        $response->assertSee('INV-CSR-001');
        $response->assertSee('INV-OWN-002');
        $response->assertSee('Kasir Utama'); // cashier name visible
    }

    public function test_owner_history_has_no_void_button(): void
    {
        $owner = $this->owner();
        $store = Store::query()->create(['name' => 'Butik', 'code' => 'BTK', 'address' => 'Jl. Test']);

        Sale::query()->create([
            'invoice_number' => 'INV-TEST-001',
            'user_id'        => $owner->id,
            'store_id'       => $store->id,
            'subtotal'       => 100000, 'discount_amount' => 0,
            'total'          => 100000, 'amount_paid' => 100000, 'change' => 0,
            'payment_method' => 'Tunai', 'cogs' => 50000, 'profit' => 50000,
            'status'         => 'completed',
        ]);

        $response = $this->actingAs($owner)->get('/owner/history');
        $response->assertDontSee('Batalkan');
    }
}
