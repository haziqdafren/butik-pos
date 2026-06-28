<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rename existing store to "Butik 1" if still using default name
        DB::table('stores')
            ->where('code', 'BTK')
            ->where('name', 'Butik Utama')
            ->update(['name' => 'Butik 1']);

        // Add Butik 2 only if it doesn't exist yet
        if (!DB::table('stores')->where('code', 'BTK2')->exists()) {
            DB::table('stores')->insert([
                'code'       => 'BTK2',
                'name'       => 'Butik 2',
                'address'    => '',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('stores')->where('code', 'BTK2')->delete();
        DB::table('stores')->where('code', 'BTK')->update(['name' => 'Butik Utama']);
    }
};
