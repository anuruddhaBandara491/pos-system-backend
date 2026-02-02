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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('action', 100)->index(); // login, logout, order_created, payment_recorded, etc.
            $table->string('entity_type', 100)->nullable()->index(); // Order, Payment, User, Product, etc.
            $table->unsignedBigInteger('entity_id')->nullable()->index(); // ID of affected entity
            $table->string('method', 10)->nullable(); // GET, POST, PUT, DELETE, etc.
            $table->string('endpoint', 255)->nullable(); // API endpoint called
            $table->string('ip_address', 45)->index(); // IPv4 (15) or IPv6 (45)
            $table->string('user_agent', 255)->nullable();
            $table->integer('response_code')->nullable()->index(); // HTTP status code
            $table->text('changes')->nullable(); // JSON of before/after data
            $table->text('details')->nullable(); // Additional context
            $table->timestamp('created_at')->index();

            // Foreign key
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
