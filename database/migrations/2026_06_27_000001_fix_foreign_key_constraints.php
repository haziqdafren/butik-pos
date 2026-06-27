<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // sale_items.product_id: restrict → nullOnDelete
        // Allows deleting products that have sale history (item row stays, product_id becomes null)
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropForeign(['product_id']);
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });

        // purchases.user_id: restrict → nullOnDelete
        // Allows deleting cashier accounts that have purchase records
        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        // sale_corrections.requested_by: restrict → nullOnDelete
        // Allows deleting cashier accounts that have correction requests
        Schema::table('sale_corrections', function (Blueprint $table): void {
            $table->dropForeign(['requested_by']);
            $table->unsignedBigInteger('requested_by')->nullable()->change();
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropForeign(['product_id']);
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('sale_corrections', function (Blueprint $table): void {
            $table->dropForeign(['requested_by']);
            $table->unsignedBigInteger('requested_by')->nullable(false)->change();
            $table->foreign('requested_by')->references('id')->on('users')->restrictOnDelete();
        });
    }
};
