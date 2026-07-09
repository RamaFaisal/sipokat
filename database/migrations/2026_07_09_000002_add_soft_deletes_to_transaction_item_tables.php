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
        Schema::table('order_items', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('receive_order_items', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('medicine_stock_opname_items', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('medicine_stocks', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('receive_order_items', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('medicine_stock_opname_items', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('medicine_stocks', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
