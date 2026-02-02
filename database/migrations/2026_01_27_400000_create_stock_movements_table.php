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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['sale', 'adjustment', 'return', 'damage', 'inventory_count'])->index();
            $table->integer('quantity'); // positive or negative
            $table->string('reference_type')->nullable(); // 'order', 'adjustment', 'return'
            $table->unsignedBigInteger('reference_id')->nullable(); // order_id, user_id, etc
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->decimal('balance_qty', 8, 2)->comment('Stock balance after movement');
            $table->timestamps();

            // Indexes for quick lookups
            $table->index(['product_id', 'created_at']);
            $table->index(['branch_id', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index('reference_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
