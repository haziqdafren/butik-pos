<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Disable FK checks so we can truncate in any order
        DB::statement('PRAGMA foreign_keys = OFF');

        DB::table('sale_corrections')->truncate();
        DB::table('sale_items')->truncate();
        DB::table('sales')->truncate();
        DB::table('purchases')->truncate();
        DB::table('discount_approvals')->truncate();
        DB::table('notifications')->truncate();
        DB::table('products')->truncate();

        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function down(): void
    {
        // Irreversible — data wipe cannot be undone
    }
};
