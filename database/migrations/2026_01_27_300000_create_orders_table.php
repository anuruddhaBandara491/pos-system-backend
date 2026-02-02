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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('cashier_id')->constrained('users')->onDelete('restrict');
            $table->string('order_number')->unique()->index(); // unique order number like ORD-20260127-001
            $table->decimal('subtotal', 10, 2)->default(0); // sum of items before tax/discount
            $table->decimal('tax', 10, 2)->default(0); // calculated tax
            $table->decimal('discount', 10, 2)->default(0); // applied discount
            $table->decimal('total', 10, 2)->default(0); // final total
            $table->enum('status', ['pending', 'completed', 'cancelled', 'refunded'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('branch_id');
            $table->index('cashier_id');
            $table->index('order_number');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
