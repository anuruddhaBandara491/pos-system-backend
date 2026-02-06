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
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'total_paid')) {
                $table->decimal('total_paid', 10, 2)->default(0)->after('total');
            }
            if (! Schema::hasColumn('orders', 'balance')) {
                $table->decimal('balance', 10, 2)->default(0)->after('paid_amount');
            }
        });
    }

    /**`
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'balance')) {
                $table->dropColumn('balance');
            }
            if (Schema::hasColumn('orders', 'total_paid')) {
                $table->dropColumn('total_paid');
            }
        });
    }
};
