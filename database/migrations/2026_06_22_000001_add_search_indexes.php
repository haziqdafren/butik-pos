<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->index(['name'], 'products_name_idx');
            $table->index(['sku'], 'products_sku_idx');
            $table->index(['category'], 'products_category_idx');
            $table->index(['stock'], 'products_stock_idx');
            $table->index(['store_id', 'stock'], 'products_store_stock_idx');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->index(['status', 'created_at'], 'sales_status_created_idx');
            $table->index(['user_id', 'created_at'], 'sales_user_created_idx');
            $table->index(['store_id', 'status'], 'sales_store_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_name_idx');
            $table->dropIndex('products_sku_idx');
            $table->dropIndex('products_category_idx');
            $table->dropIndex('products_stock_idx');
            $table->dropIndex('products_store_stock_idx');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex('sales_status_created_idx');
            $table->dropIndex('sales_user_created_idx');
            $table->dropIndex('sales_store_status_idx');
        });
    }
};
