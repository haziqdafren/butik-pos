<?php

namespace Tests\Feature;

use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesUsers;
use Tests\TestCase;

class OwnerSettingsTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    public function test_guest_is_redirected_from_settings(): void
    {
        $this->get('/owner/settings')->assertRedirect('/login');
    }

    public function test_cashier_cannot_access_settings(): void
    {
        $this->actingAs($this->cashier())->get('/owner/settings')->assertForbidden();
    }

    public function test_owner_can_view_settings_page(): void
    {
        $this->actingAs($this->owner())->get('/owner/settings')->assertOk();
    }

    public function test_owner_can_save_settings(): void
    {
        $this->actingAs($this->owner())->post('/owner/settings', [
            'store_name'         => 'Butik Cantik',
            'store_address'      => 'Jl. Merdeka No. 1',
            'store_phone'        => '08123456789',
            'receipt_footer'     => 'Selamat berbelanja!',
            'owner_email'        => 'pemilik@butik.test',
            'store_open_time'    => '09:00',
            'store_close_time'   => '21:00',
            'auto_print_receipt' => '1',
        ])->assertRedirect('/owner/settings')->assertSessionHas('status');

        $this->assertSame('Butik Cantik', app(SettingsService::class)->get('store_name'));
        $this->assertSame('08123456789', app(SettingsService::class)->get('store_phone'));
    }

    public function test_cashier_cannot_save_settings(): void
    {
        $this->actingAs($this->cashier())->post('/owner/settings', [
            'store_name' => 'Hack Attempt',
        ])->assertForbidden();
    }

    public function test_store_name_is_required(): void
    {
        $this->actingAs($this->owner())->post('/owner/settings', [
            'store_name' => '',
        ])->assertSessionHasErrors('store_name');
    }

    public function test_settings_page_shows_existing_values(): void
    {
        app(SettingsService::class)->set('store_name', 'Butik Lama');

        $response = $this->actingAs($this->owner())->get('/owner/settings');
        $response->assertOk()->assertSee('Butik Lama');
    }
}
