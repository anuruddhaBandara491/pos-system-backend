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
        // Create payments table
        Schema::create('payments', function (Blueprint $table) {
            $table->string('id')->primary()->comment('Unique payment ID (pay_*)');
            $table->string('order_id')->comment('Foreign key to orders table');
            $table->decimal('amount', 10, 2)->comment('Payment amount');
            $table->enum('method', ['cash', 'card', 'check'])->comment('Payment method');
            $table->enum('status', ['completed', 'pending', 'failed'])->default('completed')->comment('Payment status');
            $table->string('reference')->nullable()->comment('Payment reference number');
            $table->string('idempotency_key')->nullable()->unique()->comment('Idempotency key');
            $table->dateTime('timestamp')->useCurrent()->comment('When payment was processed');
            $table->json('metadata')->nullable()->comment('Additional payment metadata');
            $table->timestamps();

            // Indexes
            $table->index('order_id');
            $table->index('idempotency_key');
            $table->index('timestamp');
            $table->index('status');

            // Foreign key
            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->onDelete('cascade');
        });

        // Create payment_idempotency_keys table
        Schema::create('payment_idempotency_keys', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('idempotency_key')->unique()->comment('Idempotency key from request header');
            $table->string('payment_id')->nullable()->comment('Associated payment ID');
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->json('response')->nullable()->comment('Cached response for duplicates');
            $table->dateTime('expires_at')->comment('When idempotency entry expires (24 hours)');
            $table->timestamps();

            // Indexes
            $table->index('idempotency_key');
            $table->index('expires_at');

            // Foreign key
            $table->foreign('payment_id')
                ->references('id')
                ->on('payments')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_idempotency_keys');
        Schema::dropIfExists('payments');
    }
};
