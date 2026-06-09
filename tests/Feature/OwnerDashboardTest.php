<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OwnerDashboardTest extends TestCase
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

    public function test_guest_is_redirected_from_owner_dashboard(): void
    {
        $this->get('/owner/dashboard')->assertRedirect('/login');
    }

    public function test_cashier_gets_403_on_owner_dashboard(): void
    {
        $this->actingAs($this->cashier())->get('/owner/dashboard')->assertForbidden();
    }

    public function test_owner_gets_200_on_owner_dashboard(): void
    {
        $this->actingAs($this->owner())->get('/owner/dashboard')->assertOk();
    }

    public function test_owner_gets_200_on_laporan(): void
    {
        $this->actingAs($this->owner())->get('/owner/laporan')->assertOk();
    }

    public function test_cashier_gets_403_on_laporan(): void
    {
        $this->actingAs($this->cashier())->get('/owner/laporan')->assertForbidden();
    }

    public function test_owner_can_mark_all_notifications_read(): void
    {
        $owner = $this->owner();

        // Create some unread notifications
        Notification::query()->create([
            'type'  => 'low_stock',
            'title' => 'Stok Rendah',
            'body'  => 'Produk hampir habis.',
            'data'  => ['product_id' => 1],
        ]);
        Notification::query()->create([
            'type'  => 'void_transaction',
            'title' => 'Transaksi Dibatalkan',
            'body'  => 'Transaksi dibatalkan oleh kasir.',
            'data'  => [],
        ]);

        $this->assertDatabaseCount('notifications', 2);
        $this->assertNull(Notification::query()->first()->read_at);

        $response = $this->actingAs($owner)
            ->post('/owner/notifikasi/baca-semua');

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Semua notifikasi telah ditandai dibaca.');

        $unreadCount = Notification::query()->whereNull('read_at')->count();
        $this->assertSame(0, $unreadCount);

        Notification::query()->each(function (Notification $notification): void {
            $this->assertNotNull($notification->read_at);
        });
    }

    public function test_cashier_gets_403_on_mark_notifications_read(): void
    {
        $this->actingAs($this->cashier())
            ->post('/owner/notifikasi/baca-semua')
            ->assertForbidden();
    }

    public function test_owner_dashboard_shows_sales_revenue_in_response(): void
    {
        $owner = $this->owner();
        $store = Store::query()->create([
            'name'    => 'Butik Utama',
            'code'    => 'BTK',
            'address' => 'Jl. Test',
        ]);

        // Create a completed sale with a known total
        Sale::query()->create([
            'user_id'        => $owner->id,
            'store_id'       => $store->id,
            'invoice_number' => 'INV-TEST-001',
            'status'         => 'completed',
            'payment_method' => 'Tunai',
            'subtotal'       => 185000,
            'discount_amount'=> 0,
            'total'          => 185000,
            'amount_paid'    => 200000,
            'change'         => 15000,
            'profit'         => 95000,
            'cogs'           => 90000,
        ]);

        $response = $this->actingAs($owner)->get('/owner/dashboard');

        $response->assertOk();
        // The view receives summary data; verify the page renders with the revenue amount visible
        $response->assertSee('185');
    }
}
