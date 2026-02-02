<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('sku'); // barcode/SKU
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2); // selling price
            $table->decimal('cost', 10, 2); // cost price
            $table->integer('stock_qty')->default(0); // current stock quantity
            $table->integer('reorder_level')->default(10); // low stock alert threshold
            $table->string('category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Unique constraint on sku per branch
            $table->unique(['branch_id', 'sku']);

            // Indexes for performance
            $table->index('branch_id');
            $table->index('sku');
            $table->index('is_active');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
