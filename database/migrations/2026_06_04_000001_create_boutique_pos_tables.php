<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('cashier')->after('password');
            $table->foreignId('store_id')->nullable()->after('role');
        });

        Schema::create('stores', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('category');
            $table->string('color')->nullable();
            $table->string('size')->nullable();
            $table->string('supplier')->nullable();
            $table->unsignedInteger('cost_price');
            $table->unsignedInteger('selling_price');
            $table->integer('stock')->default(0);
            $table->integer('min_stock')->default(3);
            $table->timestamps();
        });

        Schema::create('discount_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->unsignedInteger('value');
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('amount');
            $table->string('reason');
            $table->string('status')->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('discount_approval_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('discount_amount')->default(0);
            $table->unsignedInteger('total');
            $table->unsignedInteger('amount_paid');
            $table->unsignedInteger('change')->default(0);
            $table->string('payment_method');
            $table->unsignedInteger('cogs');
            $table->integer('profit');
            $table->string('status')->default('completed');
            $table->timestamps();
        });

        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('sku');
            $table->string('name');
            $table->unsignedInteger('qty');
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('unit_cost');
            $table->unsignedInteger('line_total');
        });

        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->unsignedInteger('unit_cost');
            $table->string('supplier')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sale_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('void');
            $table->string('status')->default('pending');
            $table->text('reason');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_corrections');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('discount_approvals');
        Schema::dropIfExists('products');
        Schema::dropIfExists('stores');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['role', 'store_id']);
        });
    }
};
