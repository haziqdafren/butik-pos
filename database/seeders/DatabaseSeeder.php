<?php

namespace Database\Seeders;

use App\Models\Product;
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
            ['name' => 'Butik 1', 'address' => 'Jl. Sudirman No. 12']
        );

        Store::query()->firstOrCreate(
            ['code' => 'BTK2'],
            ['name' => 'Butik 2', 'address' => '']
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

        $products = [
            ['sku' => 'BTK-KEM-HIT-L-001', 'name' => 'Kemeja Linen Hitam', 'category' => 'kemeja', 'color' => 'Hitam', 'size' => 'L', 'cost_price' => 90000, 'selling_price' => 185000, 'stock' => 8],
            ['sku' => 'BTK-CEL-KHA-32-002', 'name' => 'Celana Chino Khaki', 'category' => 'celana', 'color' => 'Khaki', 'size' => '32', 'cost_price' => 110000, 'selling_price' => 235000, 'stock' => 5],
            ['sku' => 'BTK-GAU-MOC-M-003', 'name' => 'Gaun Satin Mocca', 'category' => 'gaun', 'color' => 'Mocca', 'size' => 'M', 'cost_price' => 140000, 'selling_price' => 299000, 'stock' => 6],
            ['sku' => 'BTK-TUN-PUT-FR-004', 'name' => 'Tunik Bordir Putih', 'category' => 'tunik', 'color' => 'Putih', 'size' => 'Free', 'cost_price' => 80000, 'selling_price' => 169000, 'stock' => 12],
            ['sku' => 'BTK-TAS-CRS-ALL-005', 'name' => 'Tas Crossbody Kulit', 'category' => 'tas', 'color' => 'Coklat', 'size' => 'All', 'cost_price' => 95000, 'selling_price' => 219000, 'stock' => 3],
            ['sku' => 'BTK-KAO-RJT-M-006', 'name' => 'Kaos Rajut Sage', 'category' => 'kaos rajut', 'color' => 'Sage', 'size' => 'M', 'cost_price' => 65000, 'selling_price' => 149000, 'stock' => 2],
            ['sku' => 'BTK-ROK-PLI-FR-007', 'name' => 'Rok Plisket Navy', 'category' => 'rok', 'color' => 'Navy', 'size' => 'Free', 'cost_price' => 70000, 'selling_price' => 159000, 'stock' => 9],
            ['sku' => 'BTK-SEP-BLK-38-008', 'name' => 'Sepatu Loafer Hitam', 'category' => 'sepatu', 'color' => 'Hitam', 'size' => '38', 'cost_price' => 155000, 'selling_price' => 329000, 'stock' => 4],
        ];

        foreach ($products as $product) {
            Product::query()->firstOrCreate(
                ['sku' => $product['sku']],
                $product + ['store_id' => $store->id, 'supplier' => 'Supplier Utama', 'min_stock' => 3]
            );
        }
    }
}
