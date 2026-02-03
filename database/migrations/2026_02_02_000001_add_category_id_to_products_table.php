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
        Schema::table('products', function (Blueprint $table) {
            // Add nullable category_id foreign key
            $table->foreignId('category_id')
                ->nullable()
                ->after('category')
                ->constrained('categories')
                ->onDelete('set null');

            // Add index for performance
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop foreign key and index
            $table->dropForeignKeyIfExists(['category_id']);
            $table->dropIndex(['category_id']);

            // Drop column
            $table->dropColumn('category_id');
        });
    }
};
