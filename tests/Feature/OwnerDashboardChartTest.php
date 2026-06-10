<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OwnerDashboardChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_chart_always_has_7_days(): void
    {
        $owner = User::query()->create([
            'name'     => 'Owner Butik',
            'email'    => 'owner@butik.test',
            'password' => Hash::make('password'),
            'role'     => 'owner',
        ]);

        $response = $this->actingAs($owner)->get('/owner/dashboard');

        $response->assertOk();
        $chartData = $response->viewData('chartData');
        $this->assertCount(7, $chartData);
    }

    public function test_dashboard_chart_fills_empty_days_with_zero(): void
    {
        $owner = User::query()->create([
            'name'     => 'Owner Butik',
            'email'    => 'owner@butik.test',
            'password' => Hash::make('password'),
            'role'     => 'owner',
        ]);

        $response = $this->actingAs($owner)->get('/owner/dashboard');
        $chartData = $response->viewData('chartData');

        foreach ($chartData as $day) {
            $this->assertSame(0, $day['total']);
            $this->assertArrayHasKey('label', $day);
            $this->assertArrayHasKey('is_today', $day);
        }
    }

    public function test_dashboard_chart_includes_todays_sale(): void
    {
        $owner = User::query()->create([
            'name'     => 'Owner Butik',
            'email'    => 'owner@butik.test',
            'password' => Hash::make('password'),
            'role'     => 'owner',
        ]);

        $store = Store::query()->create(['name' => 'Butik', 'code' => 'BTK', 'address' => 'Jl. Test']);

        Sale::query()->create([
            'invoice_number' => 'INV-TEST-001',
            'user_id'        => $owner->id,
            'store_id'       => $store->id,
            'subtotal'       => 200000,
            'discount_amount'=> 0,
            'total'          => 200000,
            'amount_paid'    => 200000,
            'change'         => 0,
            'payment_method' => 'Tunai',
            'cogs'           => 100000,
            'profit'         => 100000,
            'status'         => 'completed',
        ]);

        $response  = $this->actingAs($owner)->get('/owner/dashboard');
        $chartData = $response->viewData('chartData');
        $today     = collect($chartData)->firstWhere('is_today', true);

        $this->assertNotNull($today);
        $this->assertSame(200000, $today['total']);
    }
}
