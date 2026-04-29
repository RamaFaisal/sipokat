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
        Schema::table('medicines', function (Blueprint $table) {
            $table->enum('stock_status', [
                'available',
                'almost_empty',
                'empty',
            ])->default('empty')->after('min_stock');
            $table->enum('status', [
                'active',
                'inactive',
            ])->default('active')->after('stock_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn('stock_status');
            $table->dropColumn('status');
        });
    }
};
