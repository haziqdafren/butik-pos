<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $store = Store::query()->firstOrCreate(
            ['code' => 'BTK'],
            ['name' => 'Kasablanka Butik 1', 'address' => 'Jl. Sudirman No. 12']
        );

        Store::query()->firstOrCreate(
            ['code' => 'BTK2'],
            ['name' => 'Kasablanka Butik 2', 'address' => '']
        );

        User::query()->firstOrCreate([
            'email' => 'owner@butik.test',
        ], [
            'name' => 'Owner Butik',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'store_id' => $store->id,
        ]);

        User::query()->firstOrCreate([
            'email' => 'kasir@butik.test',
        ], [
            'name' => 'Kasir Butik',
            'password' => Hash::make('password'),
            'role' => 'cashier',
            'store_id' => $store->id,
        ]);

        // No dummy products — system starts clean for real data
    }
}
