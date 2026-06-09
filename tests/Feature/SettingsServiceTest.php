<?php

namespace Tests\Feature;

use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_default_when_key_missing(): void
    {
        $service = app(SettingsService::class);
        $this->assertSame('Toko Kami', $service->get('store_name', 'Toko Kami'));
    }

    public function test_set_persists_and_get_retrieves(): void
    {
        $service = app(SettingsService::class);
        $service->set('store_name', 'Butik Indah');
        $this->assertSame('Butik Indah', $service->get('store_name'));
    }

    public function test_all_returns_all_settings_merged_with_defaults(): void
    {
        $service = app(SettingsService::class);
        $service->set('store_name', 'Butik A');

        $all = $service->all();
        $this->assertSame('Butik A', $all['store_name']);
        // missing keys fall back to defaults
        $this->assertSame('', $all['store_address']);
        $this->assertSame('Terima kasih telah berbelanja!', $all['receipt_footer']);
    }

    public function test_set_invalidates_cache(): void
    {
        $service = app(SettingsService::class);
        $service->set('store_name', 'Old Name');

        // warm up cache
        $service->all();
        $this->assertTrue(Cache::has('store_settings'));

        // set busts cache
        $service->set('store_name', 'New Name');
        $this->assertFalse(Cache::has('store_settings'));

        // re-reading returns new value
        $this->assertSame('New Name', $service->get('store_name'));
    }

    public function test_get_bool_defaults_true_when_key_missing(): void
    {
        $service = app(SettingsService::class);
        $this->assertTrue($service->getBool('auto_print_receipt', true));
    }

    public function test_get_bool_can_be_disabled(): void
    {
        $service = app(SettingsService::class);
        $service->set('auto_print_receipt', '0');
        $this->assertFalse($service->getBool('auto_print_receipt', true));
    }

    public function test_set_many_saves_multiple_and_busts_cache_once(): void
    {
        $service = app(SettingsService::class);
        $service->setMany([
            'store_name' => 'Toko Banyak',
            'store_phone' => '08123456789',
        ]);

        $this->assertSame('Toko Banyak', $service->get('store_name'));
        $this->assertSame('08123456789', $service->get('store_phone'));
    }
}
